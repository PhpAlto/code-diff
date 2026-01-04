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
use Alto\Code\Diff\Model\WordSpan;
use Alto\Code\Diff\Renderer\AnsiSideBySideRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnsiSideBySideRenderer::class)]
final class AnsiSideBySideRendererTest extends TestCase
{
    public function testRenderEmptyResult(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertSame('', $output);
    }

    public function testRenderWithAddition(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('add', 'new line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('new line', $output);
        $this->assertStringContainsString('>', $output);
        $this->assertStringContainsString("\033[", $output);
    }

    public function testRenderWithDeletion(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('del', 'old line')];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('old line', $output);
        $this->assertStringContainsString('<', $output);
    }

    public function testRenderWithContext(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('eq', 'context')];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('context', $output);
        $this->assertStringContainsString(' ', $output);
    }

    public function testRenderWithChange(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [
            new Edit('del', 'old'),
            new Edit('add', 'new'),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('old', $output);
        $this->assertStringContainsString('new', $output);
        $this->assertStringContainsString('|', $output);
    }

    public function testRenderWithoutLineNumbers(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false);
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('line', $output);
    }

    public function testRenderWithLineNumbers(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: true);
        $edits = [new Edit('eq', 'line')];
        $hunk = new Hunk(5, 1, 10, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('5', $output);
        $this->assertStringContainsString('10', $output);
    }

    public function testRenderBundle(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $file = new DiffFile('old.txt', 'new.txt', $result);
        $bundle = new DiffBundle([$file]);

        $output = $renderer->render($bundle);

        $this->assertStringContainsString('old.txt', $output);
        $this->assertStringContainsString('new.txt', $output);
        $this->assertStringContainsString('content', $output);
    }

    public function testRenderWithWordSpans(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $wordSpans = [
            new WordSpan('del', 'old'),
            new WordSpan('eq', ' '),
        ];
        $edits = [new Edit('del', 'old word', $wordSpans)];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('old', $output);
        $this->assertStringContainsString("\033[41m", $output);
    }

    public function testRenderHunkHeader(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 5, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('@@ -1,0 +5,1 @@', $output);
    }

    public function testRenderTruncatesLongLines(): void
    {
        $renderer = new AnsiSideBySideRenderer(width: 40);
        $longLine = str_repeat('a', 100);
        $edits = [new Edit('add', $longLine)];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('~', $output);
    }

    public function testRenderTruncatesLongLinesWithWordHighlights(): void
    {
        $renderer = new AnsiSideBySideRenderer(width: 40);
        $longLine = str_repeat('a', 100);
        $wordSpans = [new WordSpan('add', $longLine)];
        // Use a replacement (del + add) to trigger word span rendering
        $edits = [
            new Edit('del', 'old'),
            new Edit('add', $longLine, $wordSpans),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('~', $output);
        $this->assertStringContainsString("\033[42m", $output); // Green BG for add word
    }

    public function testRenderTruncatesLongLinesInLeftColumn(): void
    {
        $renderer = new AnsiSideBySideRenderer(width: 40);
        $longLine = str_repeat('a', 100);
        $edits = [new Edit('del', $longLine)];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('~', $output);
    }

    public function testRenderTruncatesExcessiveWordHighlights(): void
    {
        $renderer = new AnsiSideBySideRenderer(width: 40);
        // colWidth is roughly (40-7)/2 = 16
        $wordSpans = [
            new WordSpan('add', '1234567890'), // 10 chars
            new WordSpan('add', '1234567890'), // 10 chars (total 20 > 16)
            new WordSpan('add', 'extra'),      // Should be skipped (break triggered)
        ];
        $edits = [
            new Edit('del', 'old'),
            new Edit('add', '12345678901234567890extra', $wordSpans),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('~', $output);
        $this->assertStringNotContainsString('extra', $output);
    }

    public function testRenderContainsAnsiCodes(): void
    {
        $renderer = new AnsiSideBySideRenderer();
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString("\033[0m", $output);
        $this->assertStringContainsString("\033[", $output);
    }

    public function testColumnsRemainAlignedWithHighlights(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: 50);
        $wordSpans = [new WordSpan('del', 'left column text that is long')];
        $edits = [
            new Edit('del', 'left column text that is long', $wordSpans),
            new Edit('add', 'right column replacement'),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $lines = [];
        foreach (explode("\n", $output) as $line) {
            $visible = preg_replace('/\033\[[0-9;]*m/', '', $line) ?? $line;
            if (str_contains($visible, ' | ')) {
                $lines[] = $visible;
            }
        }

        $this->assertNotEmpty($lines);

        [$leftColumn] = explode(' | ', $lines[0], 2);
        $expectedWidth = (int) ((50 - 3) / 2);

        $this->assertSame($expectedWidth, mb_strlen($leftColumn));
    }

    public function testRenderEmptyBundleReturnsEmptyString(): void
    {
        $renderer = new AnsiSideBySideRenderer();

        $output = $renderer->render(new DiffBundle([]));

        $this->assertSame('', $output);
    }

    public function testRenderTruncatesRightColumnToColumnWidth(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: 20);
        $edits = [new Edit('add', 'abcdefghij')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $lines = explode("\n", $output);
        $visible = preg_replace('/\033\[[0-9;]*m/', '', $lines[1]) ?? $lines[1];
        [$leftColumn, $rightColumn] = explode(' > ', $visible, 2);

        $this->assertSame(str_repeat(' ', 8), $leftColumn);
        $this->assertSame('abcdefg~', $rightColumn);
    }

    public function testRenderTruncatesReplacementColumnsSymmetrically(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: 22);
        $edits = [
            new Edit('del', 'ABCDEFGHIJK'),
            new Edit('add', 'JKLMNOPQRST'),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $lines = explode("\n", $output);
        $visible = preg_replace('/\033\[[0-9;]*m/', '', $lines[1]) ?? $lines[1];
        [$leftColumn, $rightColumn] = explode(' | ', $visible, 2);

        $this->assertSame('ABCDEFGH~', $leftColumn);
        $this->assertSame('JKLMNOPQ~', $rightColumn);
    }

    public function testRenderTruncatesMultibyteTextWithoutBreakingAlignment(): void
    {
        $width = 30;
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: $width);
        $oldText = '変更前の行🚀🚀🚀🚀🚀';
        $newText = '変更後の行✨✨✨✨✨';
        $edits = [
            new Edit('del', $oldText),
            new Edit('add', $newText),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $lines = explode("\n", $output);
        $visible = preg_replace('/\033\[[0-9;]*m/', '', $lines[1]) ?? $lines[1];
        [$leftColumn, $rightColumn] = explode(' | ', $visible, 2);

        $this->assertSame('変更前の行🚀🚀🚀🚀🚀   ', $leftColumn);
        $this->assertSame('変更後の行✨✨✨✨✨', $rightColumn);
        $this->assertSame(13, mb_strlen($leftColumn));
        $this->assertSame(10, mb_strlen($rightColumn));
    }

    public function testWordHighlightsTruncateWithoutTildeWhenSingleCharRemaining(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: 20);
        $wordSpans = [
            new WordSpan('add', 'ABCDEFG'),
            new WordSpan('add', 'XYZ'),
        ];
        $edits = [
            new Edit('del', 'legacy'),
            new Edit('add', 'ABCDEFGXYZ', $wordSpans),
        ];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $rawLine = explode("\n", $output)[1];
        $visible = preg_replace('/\033\[[0-9;]*m/', '', $rawLine) ?? $rawLine;
        [, $rightColumn] = explode(' | ', $visible, 2);

        $this->assertStringContainsString("\033[42m", $rawLine);
        $this->assertSame('ABCDEFGX', $rightColumn);
        $this->assertStringNotContainsString('~', $rightColumn);
    }

    public function testRenderHandlesZeroWidthColumnsGracefully(): void
    {
        $renderer = new AnsiSideBySideRenderer(showLineNumbers: false, width: 3);
        $edits = [new Edit('add', 'data')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);
        $lines = explode("\n", $output);
        $visible = preg_replace('/\033\[[0-9;]*m/', '', $lines[1]) ?? $lines[1];

        $this->assertSame(' > ', $visible);
    }
}
