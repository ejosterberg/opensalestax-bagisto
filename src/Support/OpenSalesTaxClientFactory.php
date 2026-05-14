<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use OpenSalesTax\Bagisto\Exceptions\OpenSalesTaxConfigurationException;
use OpenSalesTax\Client;
use Psr\Log\LoggerInterface;

/**
 * Builds the SDK `OpenSalesTax\Client` from the package config.
 *
 * Returns `null` when:
 *  - `base_url` is empty (package is inert; the listener yields to Bagisto)
 *  - URL validation fails AND `fail_hard` is false (we log + return null;
 *    fail-hard rethrows as OpenSalesTaxConfigurationException so the caller
 *    can surface it)
 *
 * Returns a built Client otherwise, with TLS verify driven by the
 * `tls_verify` config flag and the timeout pulled from `timeout`.
 */
final class OpenSalesTaxClientFactory implements OpenSalesTaxClientFactoryInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
        private readonly ?UrlValidator $validator = null,
    ) {
    }

    public function make(): ?Client
    {
        /** @var mixed $rawBase */
        $rawBase = $this->config->get('opensalestax.base_url', '');
        $baseUrl = is_string($rawBase) ? $rawBase : '';
        if ($baseUrl === '') {
            return null;
        }

        $validator = $this->validator ?? new UrlValidator(
            (bool) $this->config->get('opensalestax.allow_private_nets', false),
        );

        try {
            $validator->validate($baseUrl);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('opensalestax: base URL rejected by validator', [
                'reason' => $e->getMessage(),
            ]);
            if ((bool) $this->config->get('opensalestax.fail_hard', false)) {
                throw new OpenSalesTaxConfigurationException($e->getMessage(), 0, $e);
            }
            return null;
        }

        /** @var mixed $apiKey */
        $apiKey = $this->config->get('opensalestax.api_key');
        /** @var mixed $rawTimeout */
        $rawTimeout = $this->config->get('opensalestax.timeout', 10);
        $timeout = is_numeric($rawTimeout) ? (float) $rawTimeout : 10.0;
        $tlsVerify = (bool) $this->config->get('opensalestax.tls_verify', true);

        $guzzle = new GuzzleClient([
            'timeout' => $timeout,
            'verify'  => $tlsVerify,
        ]);

        return new Client(
            baseUrl: $baseUrl,
            apiKey: is_string($apiKey) && $apiKey !== '' ? $apiKey : null,
            timeoutSeconds: $timeout,
            httpClient: $guzzle,
        );
    }
}
