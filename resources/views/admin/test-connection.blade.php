{{--
    SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

    Admin "Test Connection" page. Single button + inline result span;
    the button POSTs back to the same route (CSRF-protected) and the
    JSON envelope is rendered in-place. No theme dependencies — works
    on any Bagisto admin theme because we don't extend a layout. The
    intended discovery flow is via the "Verify engine connectivity"
    section of the package README pointing the merchant at /admin/opensalestax/test-connection.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OpenSalesTax — Test Connection</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 720px;
            margin: 2em auto;
            padding: 1em;
            color: #1d2327;
        }
        h1 { font-size: 1.4em; margin-bottom: 0.5em; }
        p.lead { color: #555; margin-top: 0; }
        button {
            background: #2271b1;
            color: white;
            border: 0;
            padding: 0.6em 1.4em;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1em;
        }
        button:disabled { opacity: 0.5; cursor: wait; }
        button:hover:not(:disabled) { background: #135e96; }
        #ostax-result {
            margin-left: 1em;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.95em;
        }
        .help {
            margin-top: 2em;
            padding: 0.8em 1em;
            background: #fff8e5;
            border-left: 4px solid #ffb900;
            border-radius: 2px;
            font-size: 0.9em;
            color: #555;
        }
    </style>
</head>
<body>
    <h1>OpenSalesTax — Test Connection</h1>
    <p class="lead">
        Probe the configured engine's <code>/v1/health</code> endpoint and confirm Bagisto can reach it.
        Catches typo'd engine URLs before they bite at checkout.
    </p>

    <p>
        <button type="button" id="ostax-test-btn">Test connection</button>
        <span id="ostax-result"></span>
    </p>

    <div class="help">
        Engine URL is configured via the <code>OPENSALESTAX_BASE_URL</code> environment variable
        (see <code>config/opensalestax.php</code>). After changing the value, run
        <code>php artisan config:clear</code> and reload this page before re-testing.
    </div>

    <script>
        (function () {
            const btn    = document.getElementById('ostax-test-btn');
            const result = document.getElementById('ostax-result');
            const token  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            btn.addEventListener('click', async function () {
                btn.disabled = true;
                result.style.color = '';
                result.textContent = 'Testing…';
                try {
                    const resp = await fetch(window.location.pathname, {
                        method:  'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept':       'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: '{}',
                    });
                    const data = await resp.json();
                    if (data && data.ok) {
                        result.style.color = 'green';
                        result.textContent = '✓ ' + (data.message || 'OK');
                    } else {
                        result.style.color = '#d63638';
                        result.textContent = '✗ ' + ((data && data.error) || 'Unknown error');
                    }
                } catch (e) {
                    result.style.color = '#d63638';
                    result.textContent = '✗ ' + e.message;
                } finally {
                    btn.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
