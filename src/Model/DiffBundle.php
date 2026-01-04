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

namespace Alto\Code\Diff\Model;

/**
 * Represents a collection of diffs for multiple files.
 *
 * A bundle corresponds to a multi-file patch (like the output of `git diff`
 * across multiple files) and contains a list of DiffFile objects.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class DiffBundle
{
    /**
     * @param list<DiffFile> $files
     */
    public function __construct(
        public array $files,
    ) {
    }

    /**
     * @return list<DiffFile>
     */
    public function files(): array
    {
        return $this->files;
    }
}
