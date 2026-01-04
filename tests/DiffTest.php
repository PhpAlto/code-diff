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

namespace Alto\Code\Diff\Tests;

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Model\DiffResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Diff::class)]
final class DiffTest extends TestCase
{
    public function testBasicDiff(): void
    {
        $old = "line 1\nline 2\nline 3\n";
        $new = "line 1\nline 2 modified\nline 3\n";

        $result = Diff::build()->compare($old, $new);

        $this->assertInstanceOf(DiffResult::class, $result);
        $this->assertNotEmpty($result->hunks());
    }

    public function testNoDifference(): void
    {
        $text = "same content\n";

        $result = Diff::build()->compare($text, $text);

        $this->assertInstanceOf(DiffResult::class, $result);
        $this->assertEmpty($result->hunks());
        $this->assertTrue($result->isEmpty());
    }

    public function testEmptyFiles(): void
    {
        $result = Diff::build()->compare('', '');

        $this->assertInstanceOf(DiffResult::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testAddLines(): void
    {
        $old = "line 1\n";
        $new = "line 1\nline 2\nline 3\n";

        $result = Diff::build()->compare($old, $new);

        $this->assertFalse($result->isEmpty());
        $hunks = $result->hunks();
        $this->assertCount(1, $hunks);
    }

    public function testDeleteLines(): void
    {
        $old = "line 1\nline 2\nline 3\n";
        $new = "line 1\n";

        $result = Diff::build()->compare($old, $new);

        $this->assertFalse($result->isEmpty());
        $hunks = $result->hunks();
        $this->assertCount(1, $hunks);
    }

    public function testContextLines(): void
    {
        $old = "1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n";
        $new = "1\n2\n3\n4\nCHANGED\n6\n7\n8\n9\n10\n";

        $result = Diff::build()
            ->contextLines(2)
            ->compare($old, $new);

        $this->assertFalse($result->isEmpty());
        $hunks = $result->hunks();
        $this->assertCount(1, $hunks);

        $hunk = $hunks[0];
        $this->assertSame(3, $hunk->oldStart);
    }

    public function testWithWordDiff(): void
    {
        $old = "Hello world\n";
        $new = "Hello universe\n";

        $result = Diff::build()
            ->withWordDiff(true)
            ->compare($old, $new);

        $this->assertFalse($result->isEmpty());
        $hunks = $result->hunks();
        $this->assertCount(1, $hunks);

        $hasWordSpans = false;
        foreach ($hunks[0]->edits as $edit) {
            if ([] !== $edit->wordSpans) {
                $hasWordSpans = true;
                break;
            }
        }

        $this->assertTrue($hasWordSpans, 'Word diff should produce word spans');
    }

    public function testIgnoreWhitespace(): void
    {
        $old = "hello world\n";
        $new = "hello    world\n";

        $resultWithoutIgnore = Diff::build()->compare($old, $new);
        $resultWithIgnore = Diff::build()
            ->ignoreWhitespace(true)
            ->compare($old, $new);

        $this->assertFalse($resultWithoutIgnore->isEmpty());
        $this->assertTrue($resultWithIgnore->isEmpty());
    }

    public function testFluentApi(): void
    {
        $diff = Diff::build()
            ->withWordDiff(true)
            ->ignoreWhitespace(false)
            ->contextLines(5)
            ->maxBytes(1000000);

        $this->assertInstanceOf(Diff::class, $diff);
    }

    public function testWithEngine(): void
    {
        $engine = $this->createMock(\Alto\Code\Diff\Engine\DiffEngineInterface::class);
        $engine->expects($this->once())
            ->method('diff')
            ->willReturn(new DiffResult([]));

        $diff = Diff::build()->withEngine($engine);
        $result = $diff->compare('old', 'new');

        $this->assertInstanceOf(DiffResult::class, $result);
    }
}
