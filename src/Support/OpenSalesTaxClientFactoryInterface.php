<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use OpenSalesTax\Client;

/**
 * Contract for "build an SDK Client (or null)" — exists so the listener
 * can be unit-tested against a fake factory without subclassing the
 * production one. Production binding is `OpenSalesTaxClientFactory`.
 */
interface OpenSalesTaxClientFactoryInterface
{
    /**
     * Build the SDK Client from current config, or return null when the
     * package is not configured / failed validation in fail-soft mode.
     */
    public function make(): ?Client;
}
