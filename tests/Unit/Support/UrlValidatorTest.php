<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Support;

use InvalidArgumentException;
use OpenSalesTax\Bagisto\Support\UrlValidator;
use PHPUnit\Framework\TestCase;

final class UrlValidatorTest extends TestCase
{
    public function testEmptyUrlRejected(): void
    {
        $v = new UrlValidator(allowPrivateNets: false);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');
        $v->validate('');
    }

    public function testMalformedUrlRejected(): void
    {
        $v = new UrlValidator(allowPrivateNets: false);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fully-qualified');
        $v->validate('not-a-url');
    }

    public function testNonHttpSchemeRejected(): void
    {
        $v = new UrlValidator(allowPrivateNets: false);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('http or https');
        $v->validate('ftp://ost.example.com/');
    }

    public function testLoopbackRejected(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => ['127.0.0.1'],
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private');
        $v->validate('https://localhost.example/');
    }

    /**
     * @dataProvider rfc1918Provider
     */
    public function testAllRfc1918RangesRejected(string $ip): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => [$ip],
        );
        $this->expectException(InvalidArgumentException::class);
        $v->validate('https://internal.example/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rfc1918Provider(): iterable
    {
        yield '10.0.0.0/8'    => ['10.1.2.3'];
        yield '172.16.0.0/12' => ['172.20.5.1'];
        yield '192.168.0.0/16' => ['192.168.1.1'];
    }

    public function testLinkLocalRejected(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => ['169.254.169.254'], // AWS metadata
        );
        $this->expectException(InvalidArgumentException::class);
        $v->validate('https://metadata.example/');
    }

    public function testCgnatRejected(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => ['100.64.1.1'],
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private');
        $v->validate('https://cgnat.example/');
    }

    public function testIpv4MulticastRejected(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => ['224.0.0.1'],
        );
        $this->expectException(InvalidArgumentException::class);
        $v->validate('https://mcast.example/');
    }

    public function testPublicIpAccepted(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => ['203.0.113.5'], // TEST-NET-3 documentation, treated as public by filter_var
        );
        // No exception → pass.
        $v->validate('https://public.example/');
        $this->assertTrue(true);
    }

    public function testUnresolvableHostRejected(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $h) => [],
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be resolved');
        $v->validate('https://does-not-exist.example/');
    }

    public function testOptInAllowsPrivateNet(): void
    {
        $v = new UrlValidator(
            allowPrivateNets: true,
            hostResolver: static fn (string $h) => ['10.32.161.126'],
        );
        // No exception → pass even though the IP is RFC1918.
        $v->validate('http://10.32.161.126:8080/');
        $this->assertTrue(true);
    }
}
