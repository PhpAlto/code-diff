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

namespace Alto\Code\Diff\Tests\Util;

use Alto\Code\Diff\Util\NewlineNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewlineNormalizer::class)]
final class NewlineNormalizerTest extends TestCase
{
    public function testNormalizeUnixLineEndings(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize("line1\nline2\n");

        $this->assertSame("line1\nline2\n", $normalized);
        $this->assertTrue($hasTrailing);
    }

    public function testNormalizeWindowsLineEndings(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize("line1\r\nline2\r\n");

        $this->assertSame("line1\nline2\n", $normalized);
        $this->assertTrue($hasTrailing);
    }

    public function testNormalizeMacLineEndings(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize("line1\rline2\r");

        $this->assertSame("line1\nline2\n", $normalized);
        $this->assertTrue($hasTrailing);
    }

    public function testNormalizeMixedLineEndings(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize("line1\r\nline2\nline3\r");

        $this->assertSame("line1\nline2\nline3\n", $normalized);
        $this->assertTrue($hasTrailing);
    }

    public function testNormalizeNoTrailingNewline(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize("line1\nline2");

        $this->assertSame("line1\nline2", $normalized);
        $this->assertFalse($hasTrailing);
    }

    public function testNormalizeEmptyString(): void
    {
        [$normalized, $hasTrailing] = NewlineNormalizer::normalize('');

        $this->assertSame('', $normalized);
        $this->assertFalse($hasTrailing);
    }

    public function testSplitLinesBasic(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines("line1\nline2\nline3\n");

        $this->assertSame(['line1', 'line2', 'line3'], $lines);
        $this->assertTrue($hasTrailing);
    }

    public function testSplitLinesNoTrailingNewline(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines("line1\nline2\nline3");

        $this->assertSame(['line1', 'line2', 'line3'], $lines);
        $this->assertFalse($hasTrailing);
    }

    public function testSplitLinesWindowsEndings(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines("line1\r\nline2\r\n");

        $this->assertSame(['line1', 'line2'], $lines);
        $this->assertTrue($hasTrailing);
    }

    public function testSplitLinesEmptyString(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines('');

        $this->assertSame([], $lines);
        $this->assertFalse($hasTrailing);
    }

    public function testSplitLinesSingleLine(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines("single line\n");

        $this->assertSame(['single line'], $lines);
        $this->assertTrue($hasTrailing);
    }

    public function testSplitLinesSingleLineNoNewline(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines('single line');

        $this->assertSame(['single line'], $lines);
        $this->assertFalse($hasTrailing);
    }

    public function testSplitLinesEmptyLines(): void
    {
        [$lines, $hasTrailing] = NewlineNormalizer::splitLines("\n\n\n");

        $this->assertSame(['', '', ''], $lines);
        $this->assertTrue($hasTrailing);
    }
}
