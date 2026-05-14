<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Support;

use Illuminate\Config\Repository as ConfigRepository;
use OpenSalesTax\Bagisto\Exceptions\OpenSalesTaxConfigurationException;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactory;
use OpenSalesTax\Bagisto\Support\UrlValidator;
use OpenSalesTax\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OpenSalesTaxClientFactoryTest extends TestCase
{
    public function testEmptyBaseUrlReturnsNull(): void
    {
        $factory = new OpenSalesTaxClientFactory(
            new ConfigRepository(['opensalestax' => ['base_url' => '']]),
            new NullLogger(),
        );

        self::assertNull($factory->make());
    }

    public function testValidPublicUrlReturnsClient(): void
    {
        $factory = new OpenSalesTaxClientFactory(
            new ConfigRepository(['opensalestax' => [
                'base_url'   => 'https://ost.example.com',
                'api_key'    => 'secret-key',
                'timeout'    => 5,
                'tls_verify' => true,
                'allow_private_nets' => false,
            ]]),
            new NullLogger(),
            new UrlValidator(allowPrivateNets: false, hostResolver: static fn (string $h) => ['203.0.113.5']),
        );

        $client = $factory->make();
        self::assertInstanceOf(Client::class, $client);
    }

    public function testPrivateUrlRejectedReturnsNullByDefault(): void
    {
        $factory = new OpenSalesTaxClientFactory(
            new ConfigRepository(['opensalestax' => [
                'base_url'           => 'http://10.32.161.126:8080',
                'allow_private_nets' => false,
                'fail_hard'          => false,
            ]]),
            new NullLogger(),
            new UrlValidator(allowPrivateNets: false, hostResolver: static fn (string $h) => ['10.32.161.126']),
        );

        self::assertNull($factory->make());
    }

    public function testPrivateUrlAllowedWithOptIn(): void
    {
        $factory = new OpenSalesTaxClientFactory(
            new ConfigRepository(['opensalestax' => [
                'base_url'           => 'http://10.32.161.126:8080',
                'allow_private_nets' => true,
                'timeout'            => 10,
                'tls_verify'         => true,
            ]]),
            new NullLogger(),
            new UrlValidator(allowPrivateNets: true, hostResolver: static fn (string $h) => ['10.32.161.126']),
        );

        $client = $factory->make();
        self::assertInstanceOf(Client::class, $client);
    }

    public function testFailHardRethrowsConfigurationException(): void
    {
        $factory = new OpenSalesTaxClientFactory(
            new ConfigRepository(['opensalestax' => [
                'base_url'           => 'ftp://bad-scheme.example/',
                'allow_private_nets' => false,
                'fail_hard'          => true,
            ]]),
            new NullLogger(),
            new UrlValidator(allowPrivateNets: false, hostResolver: static fn (string $h) => ['203.0.113.5']),
        );

        $this->expectException(OpenSalesTaxConfigurationException::class);
        $factory->make();
    }
}
