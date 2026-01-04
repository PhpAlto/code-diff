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
 * Represents the result of a diff operation.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class DiffResult
{
    /**
     * @param list<Hunk> $hunks
     */
    public function __construct(
        public array $hunks,
        public ?string $oldLabel = null,
        public ?string $newLabel = null,
        public bool $oldHasTrailingNewline = true,
        public bool $newHasTrailingNewline = true,
    ) {
    }

    /**
     * @return list<Hunk>
     */
    public function hunks(): array
    {
        return $this->hunks;
    }

    public function isEmpty(): bool
    {
        return [] === $this->hunks;
    }
}
