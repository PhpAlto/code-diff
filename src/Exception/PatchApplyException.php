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
 * Exception thrown when a patch cannot be applied.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class PatchApplyException extends \RuntimeException implements CodeDiffExceptionInterface
{
    public function __construct(
        public readonly int $hunkIndex,
        string $message,
    ) {
        parent::__construct($message);
    }
}
