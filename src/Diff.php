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

namespace Alto\Code\Diff;

use Alto\Code\Diff\Engine\DiffEngineInterface;
use Alto\Code\Diff\Engine\MyersDiffEngine;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Options\Options;

/**
 * Main entry point for generating diffs.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Diff
{
    private Options $options;
    private DiffEngineInterface $engine;

    private function __construct()
    {
        $this->options = new Options();
        $this->engine = new MyersDiffEngine();
    }

    /**
     * Create a new builder instance.
     */
    public static function build(): self
    {
        return new self();
    }

    /**
     * Enable or disable word-level diff.
     */
    public function withWordDiff(bool $on = true): self
    {
        $clone = clone $this;
        $clone->options = $this->options->withWordDiff($on);

        return $clone;
    }

    /**
     * Enable or disable whitespace ignoring.
     */
    public function ignoreWhitespace(bool $on = true): self
    {
        $clone = clone $this;
        $clone->options = $this->options->withIgnoreWhitespace($on);

        return $clone;
    }

    /**
     * Set the number of context lines.
     */
    public function contextLines(int $n): self
    {
        $clone = clone $this;
        $clone->options = $this->options->withContextLines($n);

        return $clone;
    }

    /**
     * Set the maximum input size in bytes.
     */
    public function maxBytes(int $bytes): self
    {
        $clone = clone $this;
        $clone->options = $this->options->withMaxBytes($bytes);

        return $clone;
    }

    /**
     * Set the diff engine to use.
     */
    public function withEngine(DiffEngineInterface $engine): self
    {
        $clone = clone $this;
        $clone->engine = $engine;

        return $clone;
    }

    /**
     * Compare two strings and return the diff result.
     */
    public function compare(string $old, string $new): DiffResult
    {
        return $this->engine->diff($old, $new, $this->options);
    }
}
