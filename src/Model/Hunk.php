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
 * Represents a contiguous block of changes.
 *
 * A hunk consists of a sequence of edits (insertions, deletions, context)
 * grouped together, typically surrounded by unchanged context lines.
 * In a unified diff, this corresponds to a section starting with `@@ ... @@`.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class Hunk
{
    /**
     * @param list<Edit> $edits
     */
    public function __construct(
        public int $oldStart,
        public int $oldLen,
        public int $newStart,
        public int $newLen,
        public array $edits,
    ) {
    }
}
