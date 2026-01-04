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
 * Exception thrown when binary input is detected.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class BinaryInputException extends \RuntimeException implements CodeDiffExceptionInterface
{
}
