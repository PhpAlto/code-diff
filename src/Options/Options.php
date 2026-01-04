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

namespace Alto\Code\Diff\Options;

/**
 * Configuration options for the diff engine.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class Options
{
    public function __construct(
        public int $contextLines = 3,
        public bool $wordDiff = false,
        public bool $ignoreWhitespace = false,
        public int $maxBytes = 5_000_000,
    ) {
        if ($contextLines < 0) {
            throw new \InvalidArgumentException('Context lines must be greater than or equal to 0.');
        }

        if ($maxBytes <= 0) {
            throw new \InvalidArgumentException('Maximum bytes must be greater than 0.');
        }
    }

    public function withContextLines(int $n): self
    {
        return new self($n, $this->wordDiff, $this->ignoreWhitespace, $this->maxBytes);
    }

    public function withWordDiff(bool $on = true): self
    {
        return new self($this->contextLines, $on, $this->ignoreWhitespace, $this->maxBytes);
    }

    public function withIgnoreWhitespace(bool $on = true): self
    {
        return new self($this->contextLines, $this->wordDiff, $on, $this->maxBytes);
    }

    public function withMaxBytes(int $bytes): self
    {
        return new self($this->contextLines, $this->wordDiff, $this->ignoreWhitespace, $bytes);
    }
}
