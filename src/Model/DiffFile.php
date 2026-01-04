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
 * Represents a diff for a single file.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class DiffFile
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $oldPath,
        public string $newPath,
        public DiffResult $result,
        public array $headers = [],
    ) {
    }
}
