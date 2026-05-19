<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use OpenSalesTax\Bagisto\Support\EngineConnectionTester;

/**
 * Bagisto admin controller for the OpenSalesTax "Test Connection" page.
 *
 * Two routes (registered in src/Http/admin-routes.php):
 *   GET  /admin/opensalestax/test-connection -> showPage()  blade
 *   POST /admin/opensalestax/test-connection -> runTest()   JSON
 *
 * Both are gated by Bagisto's admin auth middleware. The page renders a
 * single button + result span; the button POSTs back to the same path
 * (with CSRF) and the JSON envelope is rendered inline. No navigation,
 * no toast-and-vanish.
 */
final class TestConnectionController
{
    public function __construct(
        private readonly EngineConnectionTester $tester,
    ) {
    }

    public function showPage(): View
    {
        return view('opensalestax::admin.test-connection');
    }

    public function runTest(): JsonResponse
    {
        return new JsonResponse($this->tester->test());
    }
}
