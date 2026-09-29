<?php

declare(strict_types=1);

namespace EICC\StaticForge\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a name cannot be reduced to a usable slug (empty result or regex failure).
 */
class InvalidSlugException extends InvalidArgumentException
{
}
