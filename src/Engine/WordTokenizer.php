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

namespace Alto\Code\Diff\Engine;

/**
 * Tokenizes input into words.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class WordTokenizer implements TokenizerInterface
{
    /**
     * Split input into words.
     *
     * @return list<string>
     */
    public function tokenize(string $input): array
    {
        if ('' === $input) {
            return [];
        }

        $tokens = preg_split('/(\s+)/', $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return false !== $tokens ? $tokens : [$input];
    }
}
