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

namespace Alto\Code\Diff\Tests\Options;

use Alto\Code\Diff\Options\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Options::class)]
final class OptionsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $options = new Options();

        $this->assertSame(3, $options->contextLines);
        $this->assertFalse($options->wordDiff);
        $this->assertFalse($options->ignoreWhitespace);
        $this->assertSame(5_000_000, $options->maxBytes);
    }

    public function testWithers(): void
    {
        $options = new Options();

        $newOptions = $options->withContextLines(10);
        $this->assertNotSame($options, $newOptions);
        $this->assertSame(10, $newOptions->contextLines);
        $this->assertSame(3, $options->contextLines);

        $newOptions = $options->withWordDiff(true);
        $this->assertNotSame($options, $newOptions);
        $this->assertTrue($newOptions->wordDiff);
        $this->assertFalse($options->wordDiff);

        $newOptions = $options->withIgnoreWhitespace(true);
        $this->assertNotSame($options, $newOptions);
        $this->assertTrue($newOptions->ignoreWhitespace);
        $this->assertFalse($options->ignoreWhitespace);

        $newOptions = $options->withMaxBytes(100);
        $this->assertNotSame($options, $newOptions);
        $this->assertSame(100, $newOptions->maxBytes);
        $this->assertSame(5_000_000, $options->maxBytes);
    }

    public function testContextLinesCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Options(contextLines: -1);
    }

    public function testMaxBytesMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Options(maxBytes: 0);
    }
}
