<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Support;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use OpenSalesTax\Bagisto\Support\EngineConnectionTester;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactoryInterface;
use OpenSalesTax\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * `OpenSalesTax\Client` is declared final, so we can't `createMock()` it.
 * Instead we wire it with a Guzzle MockHandler — the same approach the
 * SDK's own tests use — and let the real `health()` call run against the
 * mocked HTTP transport.
 */
final class EngineConnectionTesterTest extends TestCase
{
    public function testNullClientReportsConfigError(): void
    {
        $factory = new class () implements OpenSalesTaxClientFactoryInterface {
            public function make(): ?Client
            {
                return null;
            }
        };

        $tester = new EngineConnectionTester($factory, new NullLogger());
        $envelope = $tester->test();

        self::assertFalse($envelope['ok']);
        self::assertStringContainsString('base URL is not set', $envelope['error']);
    }

    public function testHappyPathShapesSuccessMessage(): void
    {
        $factory = $this->factoryReturningHealthBody('{"status":"ok","version":"0.59.0","database_connected":true}');

        $tester = new EngineConnectionTester($factory, new NullLogger());
        $envelope = $tester->test();

        self::assertTrue($envelope['ok']);
        self::assertStringContainsString('0.59.0', $envelope['message']);
        self::assertStringContainsString('ok', $envelope['message']);
        self::assertStringContainsString('connected', $envelope['message']);
    }

    public function testDbDisconnectedReportedDistinctly(): void
    {
        $factory = $this->factoryReturningHealthBody('{"status":"degraded","version":"0.59.0","database_connected":false}');

        $tester = new EngineConnectionTester($factory, new NullLogger());
        $envelope = $tester->test();

        self::assertTrue($envelope['ok']);
        self::assertStringContainsString('disconnected', $envelope['message']);
        self::assertStringContainsString('degraded', $envelope['message']);
    }

    public function testNon200ResponseBubblesAsError(): void
    {
        $factory = $this->factoryWithMockHandler(new MockHandler([
            new Psr7Response(503, [], '{"error":"unavailable"}'),
        ]));

        $tester = new EngineConnectionTester($factory, new NullLogger());
        $envelope = $tester->test();

        self::assertFalse($envelope['ok']);
        self::assertNotEmpty($envelope['error']);
    }

    public function testTransportErrorBubblesAsError(): void
    {
        $factory = $this->factoryWithMockHandler(new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'https://ost.example.com/v1/health')
            ),
        ]));

        $tester = new EngineConnectionTester($factory, new NullLogger());
        $envelope = $tester->test();

        self::assertFalse($envelope['ok']);
        self::assertNotEmpty($envelope['error']);
    }

    private function factoryReturningHealthBody(string $jsonBody): OpenSalesTaxClientFactoryInterface
    {
        return $this->factoryWithMockHandler(new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], $jsonBody),
        ]));
    }

    private function factoryWithMockHandler(MockHandler $handler): OpenSalesTaxClientFactoryInterface
    {
        $stack = HandlerStack::create($handler);
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $client = new Client(
            baseUrl: 'https://ost.example.com',
            apiKey: null,
            timeoutSeconds: 5.0,
            httpClient: $guzzle,
        );

        return new class ($client) implements OpenSalesTaxClientFactoryInterface {
            public function __construct(private Client $client)
            {
            }

            public function make(): ?Client
            {
                return $this->client;
            }
        };
    }
}
