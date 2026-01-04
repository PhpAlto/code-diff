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

/**
 * Utility for normalizing newlines.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class NewlineNormalizer
{
    /**
     * Normalize line endings and detect trailing newline.
     *
     * @return array{string, bool} [normalized string, had trailing newline]
     */
    public static function normalize(string $s): array
    {
        $normalized = str_replace("\r\n", "\n", $s);
        $normalized = str_replace("\r", "\n", $normalized);

        $hasTrailingNewline = '' !== $normalized && str_ends_with($normalized, "\n");

        return [$normalized, $hasTrailingNewline];
    }

    /**
     * Split string into lines, preserving empty trailing line info via return value.
     *
     * @return array{list<string>, bool} [lines without newlines, had trailing newline]
     */
    public static function splitLines(string $s): array
    {
        [$normalized, $hasTrailingNewline] = self::normalize($s);

        if ('' === $normalized) {
            return [[], false];
        }

        if ($hasTrailingNewline) {
            $normalized = substr($normalized, 0, -1);
        }

        $lines = explode("\n", $normalized);

        return [$lines, $hasTrailingNewline];
    }
}
