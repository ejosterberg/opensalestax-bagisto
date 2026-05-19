<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

/*
 * Admin routes for opensalestax-bagisto.
 *
 * Loaded by OpenSalesTaxServiceProvider::boot() inside Bagisto's
 * "admin" route group (so they automatically pick up the admin auth +
 * locale + URL prefix middleware Bagisto registers for /admin/*).
 *
 * Surface intentionally tiny — one GET (renders the Test Connection
 * page) + one POST (runs the test, returns JSON). No DB writes, no
 * state changes — read-only probe of the configured engine.
 */

use Illuminate\Support\Facades\Route;
use OpenSalesTax\Bagisto\Http\Controllers\Admin\TestConnectionController;

Route::group([
    'prefix'     => 'admin/opensalestax',
    'middleware' => ['web', 'admin'],
    'as'         => 'admin.opensalestax.',
], static function (): void {
    Route::get('test-connection', [TestConnectionController::class, 'showPage'])
        ->name('test-connection.show');
    Route::post('test-connection', [TestConnectionController::class, 'runTest'])
        ->name('test-connection.run');
});
