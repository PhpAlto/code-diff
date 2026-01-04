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

namespace Alto\Code\Diff\Tests\Renderer;

use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;
use Alto\Code\Diff\Renderer\UnifiedRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnifiedRenderer::class)]
final class UnifiedRendererTest extends TestCase
{
    public function testRenderEmptyResult(): void
    {
        $renderer = new UnifiedRenderer();
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertSame('', $output);
    }

    public function testRenderEmptyResultWithLabels(): void
    {
        $renderer = new UnifiedRenderer('old.txt', 'new.txt');
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertSame("--- old.txt\n+++ new.txt", $output);
    }

    public function testRenderWithAddition(): void
    {
        $renderer = new UnifiedRenderer('old.txt', 'new.txt');
        $edits = [
            new Edit('eq', 'line1'),
            new Edit('add', 'line2'),
        ];
        $hunk = new Hunk(1, 1, 1, 2, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('--- old.txt', $output);
        $this->assertStringContainsString('+++ new.txt', $output);
        $this->assertStringContainsString('@@ -1 +1,2 @@', $output);
        $this->assertStringContainsString(' line1', $output);
        $this->assertStringContainsString('+line2', $output);
    }

    public function testRenderWithDeletion(): void
    {
        $renderer = new UnifiedRenderer();
        $edits = [
            new Edit('del', 'line1'),
            new Edit('eq', 'line2'),
        ];
        $hunk = new Hunk(1, 2, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('@@ -1,2 +1 @@', $output);
        $this->assertStringContainsString('-line1', $output);
        $this->assertStringContainsString(' line2', $output);
    }

    public function testRenderWithChange(): void
    {
        $renderer = new UnifiedRenderer();
        $edits = [
            new Edit('del', 'old line'),
            new Edit('add', 'new line'),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('-old line', $output);
        $this->assertStringContainsString('+new line', $output);
    }

    public function testRenderWithoutTrailingNewline(): void
    {
        $renderer = new UnifiedRenderer();
        $edits = [new Edit('add', 'line1')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk], newHasTrailingNewline: false);

        $output = $renderer->render($result);

        $this->assertStringContainsString('\\ No newline at end of file', $output);
    }

    public function testRenderWithoutTrailingNewlineOnDeletion(): void
    {
        $renderer = new UnifiedRenderer();
        $edits = [new Edit('del', 'line1')];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk], oldHasTrailingNewline: false);

        $output = $renderer->render($result);

        $this->assertStringContainsString('\\ No newline at end of file', $output);
    }

    public function testRenderWithoutTrailingNewlineOnContext(): void
    {
        $renderer = new UnifiedRenderer();
        $edits = [new Edit('eq', 'line1')];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk], oldHasTrailingNewline: false, newHasTrailingNewline: false);

        $output = $renderer->render($result);

        $this->assertStringContainsString('\\ No newline at end of file', $output);
    }

    public function testRenderBundle(): void
    {
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $file = new DiffFile('old.txt', 'new.txt', $result);
        $bundle = new DiffBundle([$file]);

        $renderer = new UnifiedRenderer();
        $output = $renderer->render($bundle);

        $this->assertStringContainsString('+content', $output);
    }

    public function testRenderEmptyBundle(): void
    {
        $bundle = new DiffBundle([]);
        $renderer = new UnifiedRenderer();
        $output = $renderer->render($bundle);

        $this->assertSame('', $output);
    }

    public function testRenderMultipleHunks(): void
    {
        $renderer = new UnifiedRenderer();
        $hunk1 = new Hunk(1, 1, 1, 1, [new Edit('del', 'line1')]);
        $hunk2 = new Hunk(10, 1, 10, 1, [new Edit('add', 'line10')]);
        $result = new DiffResult([$hunk1, $hunk2]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('@@ -1 +1 @@', $output);
        $this->assertStringContainsString('@@ -10 +10 @@', $output);
    }
}
