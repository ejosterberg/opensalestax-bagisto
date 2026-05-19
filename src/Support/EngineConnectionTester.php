<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use OpenSalesTax\Exceptions\OpenSalesTaxException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service object that powers the admin "Test Connection" button.
 *
 * Asks the existing client factory for a built `OpenSalesTax\Client`,
 * calls `health()`, and shapes the result into a small JSON envelope
 * the admin endpoint returns inline to the browser. Never throws —
 * matches the fail-soft contract of the rest of the package.
 *
 * Return shape (matches the WooCom + Magento + Saleor + Vendure pattern):
 *   - { ok: true,  message: 'Engine v0.59.0 reachable — database connected' }
 *   - { ok: false, error:   'Engine base URL is not set.' }
 *   - { ok: false, error:   'OST engine returned HTTP 500' }
 */
final class EngineConnectionTester
{
    public function __construct(
        private readonly OpenSalesTaxClientFactoryInterface $clientFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function test(): array
    {
        try {
            $client = $this->clientFactory->make();
        } catch (Throwable $e) {
            $this->logger->warning('opensalestax: test connection - factory rejected config', [
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($client === null) {
            return ['ok' => false, 'error' => 'Engine base URL is not set (or rejected by validator). Set OPENSALESTAX_BASE_URL and retry.'];
        }

        try {
            $health = $client->health();
        } catch (OpenSalesTaxException $e) {
            $this->logger->warning('opensalestax: test connection failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            $this->logger->warning('opensalestax: test connection failed (transport)', [
                'class' => $e::class,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e::class . ': ' . $e->getMessage()];
        }

        $message = sprintf(
            'Engine v%s is %s — database %s',
            $health->version !== '' ? $health->version : 'unknown',
            $health->status !== '' ? $health->status : 'unknown',
            $health->databaseConnected ? 'connected' : 'disconnected',
        );
        $this->logger->info('opensalestax: test connection ok', [
            'version' => $health->version,
            'status'  => $health->status,
        ]);

        return ['ok' => true, 'message' => $message];
    }
}
