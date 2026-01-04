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
use Alto\Code\Diff\Renderer\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlRenderer::class)]
final class HtmlRendererTest extends TestCase
{
    public function testRenderEmptyResult(): void
    {
        $renderer = new HtmlRenderer();
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertSame('', $output);
    }

    public function testRenderWithAddition(): void
    {
        $renderer = new HtmlRenderer();
        $edits = [new Edit('add', 'new line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('<table class="diff-table">', $output);
        $this->assertStringContainsString('diff-add', $output);
        $this->assertStringContainsString('new line', $output);
    }

    public function testRenderWithDeletion(): void
    {
        $renderer = new HtmlRenderer();
        $edits = [new Edit('del', 'old line')];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('diff-del', $output);
        $this->assertStringContainsString('old line', $output);
    }

    public function testRenderWithContext(): void
    {
        $renderer = new HtmlRenderer();
        $edits = [new Edit('eq', 'context line')];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('diff-ctx', $output);
        $this->assertStringContainsString('context line', $output);
    }

    public function testRenderWithoutLineNumbers(): void
    {
        $renderer = new HtmlRenderer(showLineNumbers: false);
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('colspan="2"', $output);
        $this->assertStringNotContainsString('diff-line-num', $output);
    }

    public function testRenderWithLineNumbers(): void
    {
        $renderer = new HtmlRenderer(showLineNumbers: true);
        $edits = [new Edit('eq', 'line')];
        $hunk = new Hunk(5, 1, 10, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('diff-line-num', $output);
        $this->assertStringContainsString('5</td>', $output);
        $this->assertStringContainsString('10</td>', $output);
    }

    public function testRenderWithWrapLines(): void
    {
        $renderer = new HtmlRenderer(wrapLines: true);
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('diff-wrap', $output);
    }

    public function testRenderWithCustomClassPrefix(): void
    {
        $renderer = new HtmlRenderer(classPrefix: 'custom-');
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('custom-table', $output);
        $this->assertStringContainsString('custom-add', $output);
    }

    public function testRenderBundle(): void
    {
        $renderer = new HtmlRenderer();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $file = new DiffFile('old.txt', 'new.txt', $result);
        $bundle = new DiffBundle([$file]);

        $output = $renderer->render($bundle);

        $this->assertStringContainsString('diff-file', $output);
        $this->assertStringContainsString('diff-file-header', $output);
        $this->assertStringContainsString('old.txt', $output);
        $this->assertStringContainsString('new.txt', $output);
    }

    public function testRenderWithWordSpans(): void
    {
        $renderer = new HtmlRenderer();
        $wordSpans = [
            new WordSpan('del', 'old'),
            new WordSpan('eq', ' '),
            new WordSpan('add', 'new'),
        ];
        $edits = [new Edit('add', 'old new', $wordSpans)];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('diff-word-del', $output);
        $this->assertStringContainsString('diff-word-add', $output);
    }

    public function testRenderEscapesHtml(): void
    {
        $renderer = new HtmlRenderer();
        $edits = [new Edit('add', '<script>alert("xss")</script>')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }
}
