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

namespace Alto\Code\Diff\Tests\Engine;

use Alto\Code\Diff\Engine\MyersDiffEngine;
use Alto\Code\Diff\Engine\TokenizerInterface;
use Alto\Code\Diff\Exception\BinaryInputException;
use Alto\Code\Diff\Exception\SizeLimitException;
use Alto\Code\Diff\Options\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MyersDiffEngine::class)]
final class MyersDiffEngineTest extends TestCase
{
    public function testDiffSimpleChange(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();

        $result = $engine->diff("line1\nline2\n", "line1\nline2 modified\n", $options);

        $this->assertNotEmpty($result->hunks);
    }

    public function testSizeLimitExceeded(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(maxBytes: 10);

        $this->expectException(SizeLimitException::class);
        $engine->diff('12345678901', '12345678901', $options);
    }

    public function testBinaryInput(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();

        $this->expectException(BinaryInputException::class);
        $engine->diff("text\x00binary", 'text', $options);
    }

    public function testIgnoreWhitespace(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(ignoreWhitespace: true);

        $result = $engine->diff("line 1\n", "line    1\n", $options);
        $this->assertEmpty($result->hunks);
    }

    public function testIdenticalInputs(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();

        $result = $engine->diff("line1\n", "line1\n", $options);
        $this->assertEmpty($result->hunks);
    }

    public function testMultipleHunks(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(contextLines: 0);

        // Gap > 2*0+1 = 1
        $old = "A\nB\nC\nD\nE\n";
        $new = "A modified\nB\nC\nD\nE modified\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertCount(2, $result->hunks);
    }

    public function testWordDiffUnevenReplacement(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(wordDiff: true);

        // Replace 2 lines with 1 line
        $old = "line1\nline2\n";
        $new = "line1 modified\n";
        $result = $engine->diff($old, $new, $options);
        $this->assertNotEmpty($result->hunks);
        // Verify we have word spans
        $this->assertNotEmpty($result->hunks[0]->edits[0]->wordSpans);

        // Replace 1 line with 2 lines
        $old = "line1\n";
        $new = "line1\nline2\n";
        $result = $engine->diff($old, $new, $options);
        $this->assertNotEmpty($result->hunks);
    }

    public function testHunkStartWithDelAfterContext(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(contextLines: 1);

        $old = "ctx\ndel\n";
        $new = "ctx\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertCount(1, $result->hunks);
        $hunk = $result->hunks[0];
        // Hunk should start at line 1 (ctx)
        $this->assertSame(1, $hunk->oldStart);
        $this->assertSame(1, $hunk->newStart);
        // Edits: eq(ctx), del(del)
        $this->assertSame('eq', $hunk->edits[0]->op);
        $this->assertSame('del', $hunk->edits[1]->op);
    }

    public function testHunkStartWithAddAfterContext(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(contextLines: 1);

        $old = "ctx\n";
        $new = "ctx\nadd\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertCount(1, $result->hunks);
        $hunk = $result->hunks[0];
        // Hunk should start at line 1 (ctx)
        $this->assertSame(1, $hunk->oldStart);
        $this->assertSame(1, $hunk->newStart);
        // Edits: eq(ctx), add(add)
        $this->assertSame('eq', $hunk->edits[0]->op);
        $this->assertSame('add', $hunk->edits[1]->op);
    }

    public function testFindOldStartForAddWithContextZero(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(contextLines: 0);

        $old = "line1\n";
        $new = "line1\nline2\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertCount(1, $result->hunks);
        $hunk = $result->hunks[0];

        // Hunk starts at the addition (line 2 of new file)
        // Old start should be line 1 + 1 = 2 (insertion point after line 1)
        $this->assertSame(2, $hunk->oldStart);
        $this->assertSame(0, $hunk->oldLen);
        $this->assertSame(2, $hunk->newStart);
        $this->assertSame(1, $hunk->newLen);
        $this->assertSame('add', $hunk->edits[0]->op);
    }

    public function testWordDiffWithWordDeletion(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(wordDiff: true);

        $old = "word1 word2\n";
        $new = "word1\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertNotEmpty($result->hunks);
        $edits = $result->hunks[0]->edits;

        // Should be a replacement (del + add)
        $this->assertSame('del', $edits[0]->op);
        $this->assertSame('add', $edits[1]->op);

        // Check word spans for deletion
        $delSpans = $edits[0]->wordSpans;
        $this->assertNotEmpty($delSpans);

        // word1 (eq), space (del), word2 (del)
        // Or word1 (eq), space (eq/del?), word2 (del)
        // "word1 word2" -> ["word1", " ", "word2"]
        // "word1" -> ["word1"]
        // LCS: word1
        // Dels: " ", "word2"

        $hasDelSpan = false;
        foreach ($delSpans as $span) {
            if ('del' === $span->op) {
                $hasDelSpan = true;
                break;
            }
        }
        $this->assertTrue($hasDelSpan, 'Should have deleted word spans');
    }

    public function testWordDiffWithExtraAddLines(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(wordDiff: true);

        // Replace 1 line with 2 lines (must be a replacement, not just append)
        $old = "line1\n";
        $new = "line1mod\nline2\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertNotEmpty($result->hunks);
        $edits = $result->hunks[0]->edits;

        // Should have del(line1), add(line1mod), add(line2)
        // The first pair (line1 -> line1mod) is handled by word diff loop
        // The second add (line2) is handled by the extra add loop

        $this->assertCount(3, $edits);
        $this->assertSame('del', $edits[0]->op);
        $this->assertSame('add', $edits[1]->op);
        $this->assertSame('add', $edits[2]->op);

        $this->assertSame('line1', $edits[0]->text);
        $this->assertSame('line1mod', $edits[1]->text);
        $this->assertSame('line2', $edits[2]->text);
    }

    public function testDiffEmptyInputs(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();
        $result = $engine->diff('', '', $options);
        $this->assertEmpty($result->hunks);
    }

    public function testDiffEmptyOld(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();
        $result = $engine->diff('', "line1\n", $options);
        $this->assertCount(1, $result->hunks);
        $this->assertSame('add', $result->hunks[0]->edits[0]->op);
    }

    public function testDiffEmptyNew(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();
        $result = $engine->diff("line1\n", '', $options);
        $this->assertCount(1, $result->hunks);
        $this->assertSame('del', $result->hunks[0]->edits[0]->op);
    }

    public function testDiffAddAtStart(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();
        $result = $engine->diff("B\n", "A\nB\n", $options);
        $this->assertNotEmpty($result->hunks);
        // Should contain Add A
        $foundAdd = false;
        foreach ($result->hunks[0]->edits as $edit) {
            if ('add' === $edit->op && 'A' === trim($edit->text)) {
                $foundAdd = true;
            }
        }
        $this->assertTrue($foundAdd);
    }

    public function testDiffDelAtStart(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options();
        $result = $engine->diff("A\nB\n", "B\n", $options);
        $this->assertNotEmpty($result->hunks);
        // Should contain Del A
        $foundDel = false;
        foreach ($result->hunks[0]->edits as $edit) {
            if ('del' === $edit->op && 'A' === trim($edit->text)) {
                $foundDel = true;
            }
        }
        $this->assertTrue($foundDel);
    }

    public function testFindNewStartForDelWithContextZero(): void
    {
        $engine = new MyersDiffEngine();
        $options = new Options(contextLines: 0);

        $old = "line1\nline2\n";
        $new = "line1\n";

        $result = $engine->diff($old, $new, $options);
        $this->assertCount(1, $result->hunks);
        $hunk = $result->hunks[0];

        $this->assertSame(2, $hunk->oldStart);
        $this->assertSame(1, $hunk->oldLen);
        $this->assertSame(2, $hunk->newStart);
        $this->assertSame(0, $hunk->newLen);
        $this->assertSame('del', $hunk->edits[0]->op);
    }

    public function testFindNewStartForDelUnreachableBranch(): void
    {
        $engine = new MyersDiffEngine();
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('findNewStartForDel');

        // Construct an edit script where the preceding edit has no newIdx (e.g. 'del')
        $editScript = [
            ['op' => 'del', 'oldIdx' => 0], // No newIdx
            ['op' => 'del', 'oldIdx' => 1], // Target
        ];

        // Start at index 1.
        // Loop checks index 0. 'del' -> no newIdx.
        // Loop finishes.
        // Returns 0.

        $result = $method->invoke($engine, $editScript, 1);
        $this->assertSame(0, $result);
    }

    public function testFindOldStartForAddUnreachableBranch(): void
    {
        $engine = new MyersDiffEngine();
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('findOldStartForAdd');

        // Construct an edit script where the preceding edit has no oldIdx (e.g. 'add')
        $editScript = [
            ['op' => 'add', 'newIdx' => 0], // No oldIdx
            ['op' => 'add', 'newIdx' => 1], // Target
        ];

        // Start at index 1.
        // Loop checks index 0. 'add' -> no oldIdx.
        // Loop finishes.
        // Returns 0.

        $result = $method->invoke($engine, $editScript, 1);
        $this->assertSame(0, $result);
    }

    public function testComputeEditScriptWithEmptyTokens(): void
    {
        $tokenizer = $this->createStub(TokenizerInterface::class);
        $tokenizer->method('tokenize')->willReturn([]);

        $engine = new MyersDiffEngine($tokenizer);
        $options = new Options(wordDiff: true);

        $old = "line1\n";
        $new = "line2\n";

        $result = $engine->diff($old, $new, $options);

        $this->assertNotEmpty($result->hunks);
        $edits = $result->hunks[0]->edits;
        $this->assertCount(2, $edits); // del, add
        $this->assertSame('del', $edits[0]->op);
        $this->assertSame('add', $edits[1]->op);

        $this->assertEmpty($edits[0]->wordSpans);
        $this->assertEmpty($edits[1]->wordSpans);
    }

    public function testBuildHunksWithEmptyScript(): void
    {
        // This is unreachable via diff() because diff() handles empty inputs early
        // and computeEditScript never returns empty for non-empty inputs.

        $engine = new MyersDiffEngine();
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('buildHunks');

        $result = $method->invoke($engine, [], [], [], 3, null);
        $this->assertSame([], $result);
    }
}
