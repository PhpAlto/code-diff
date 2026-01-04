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

namespace Alto\Code\Diff\Util;

use Alto\Code\Diff\Exception\BinaryInputException;

/**
 * Utility for detecting binary content.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class BinaryGuard
{
    private const int CHECK_BYTES = 8192;

    /**
     * Check if input looks like binary content and throw if so.
     *
     * @throws BinaryInputException
     */
    public static function assertText(string $s): void
    {
        if ('' === $s) {
            return;
        }

        $checkLength = min(strlen($s), self::CHECK_BYTES);
        $sample = substr($s, 0, $checkLength);

        if (str_contains($sample, "\x00")) {
            throw new BinaryInputException('Input appears to be binary (contains null bytes)');
        }

        $nonPrintable = 0;
        for ($i = 0; $i < $checkLength; ++$i) {
            $byte = ord($sample[$i]);
            if ($byte < 32 && 9 !== $byte && 10 !== $byte && 13 !== $byte) {
                ++$nonPrintable;
            }
        }

        if ($nonPrintable > $checkLength * 0.3) {
            throw new BinaryInputException('Input appears to be binary (too many non-printable characters)');
        }
    }
}
