<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Exceptions;

use RuntimeException;

/**
 * Base exception for the opensalestax-bagisto package.
 *
 * Catching this class lets callers handle any package-originated error without
 * also catching generic Laravel / framework exceptions. The fail-hard branch
 * of CartTotalsListener rethrows OST SDK exceptions wrapped in subclasses of
 * this type.
 */
class OpenSalesTaxBagistoException extends RuntimeException
{
}
