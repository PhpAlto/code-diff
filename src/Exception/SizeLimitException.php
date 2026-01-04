<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2025–present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\Code\Diff\Exception;

/**
 * Exception thrown when input size exceeds the limit.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class SizeLimitException extends \RuntimeException implements CodeDiffExceptionInterface
{
}
