<?php

// SPDX-License-Identifier: Apache-2.0

/**
 * One-off CLI script: confirms the SDK round-trips against a real OST engine.
 *
 * Usage:  php tools/smoke-test.php
 *
 * NOT run in CI (no engine network access there); meant for the maintainer
 * to verify before tagging.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$engineUrl = getenv('OPENSALESTAX_BASE_URL') ?: 'http://10.32.161.126:8080';
$apiKey = getenv('OPENSALESTAX_API_KEY') ?: null;
$zip = getenv('OPENSALESTAX_TEST_ZIP') ?: '55401';

echo "Smoke-testing engine at {$engineUrl} (ZIP={$zip})\n";

$client = new OpenSalesTax\Client(
    baseUrl: $engineUrl,
    apiKey: $apiKey,
    timeoutSeconds: 10.0,
);

$health = $client->health();
echo "Health: status={$health->status} version={$health->version} db={$health->databaseConnected}\n";

$address = new OpenSalesTax\Address(zip5: $zip);
$lines = [
    new OpenSalesTax\LineItem(amount: '100.00', category: 'general'),
];
$response = $client->calculate($address, $lines);
echo "Calculate: subtotal={$response->subtotal} tax_total={$response->taxTotal}\n";

foreach ($response->lines as $i => $line) {
    echo "  line[{$i}]: amount={$line->amount} tax={$line->tax} rate={$line->ratePct}%\n";
    foreach ($line->jurisdictions as $j) {
        echo "    {$j->name} ({$j->type}) rate={$j->ratePct}%\n";
    }
}
