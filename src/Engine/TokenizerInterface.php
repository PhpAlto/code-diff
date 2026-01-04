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
 * Interface for tokenizers.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface TokenizerInterface
{
    /**
     * Tokenize the input string.
     *
     * @return list<string>
     */
    public function tokenize(string $input): array;
}
