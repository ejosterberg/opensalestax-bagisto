<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Exceptions;

/**
 * Thrown when the package configuration is invalid in a way that fail-soft
 * cannot recover from (only when fail_hard is enabled — fail-soft callers
 * simply yield to Bagisto's built-in tax).
 *
 * Examples: base_url is set but fails URL validation, or the URL parser
 * cannot recognize the configured value at all.
 */
final class OpenSalesTaxConfigurationException extends OpenSalesTaxBagistoException
{
}
