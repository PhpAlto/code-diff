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
use Alto\Code\Diff\Renderer\JsonRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRenderer::class)]
final class JsonRendererTest extends TestCase
{
    public function testRenderEmptyResult(): void
    {
        $renderer = new JsonRenderer();
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame('result', $data['type']);
        $this->assertSame([], $data['hunks']);
    }

    public function testRenderWithHunks(): void
    {
        $renderer = new JsonRenderer();
        $edits = [new Edit('add', 'new line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $data = json_decode($output, true);
        $this->assertCount(1, $data['hunks']);
        $this->assertSame(1, $data['hunks'][0]['oldStart']);
        $this->assertSame(0, $data['hunks'][0]['oldLen']);
        $this->assertSame(1, $data['hunks'][0]['newStart']);
        $this->assertSame(1, $data['hunks'][0]['newLen']);
        $this->assertCount(1, $data['hunks'][0]['edits']);
        $this->assertSame('add', $data['hunks'][0]['edits'][0]['op']);
        $this->assertSame('new line', $data['hunks'][0]['edits'][0]['text']);
    }

    public function testRenderWithPrettyPrint(): void
    {
        $renderer = new JsonRenderer(prettyPrint: true);
        $result = new DiffResult([]);
        $output = $renderer->render($result);

        $this->assertStringContainsString("\n", $output);
        $this->assertStringContainsString('    ', $output);
    }

    public function testRenderBundle(): void
    {
        $renderer = new JsonRenderer();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $file = new DiffFile('old.txt', 'new.txt', $result);
        $bundle = new DiffBundle([$file]);

        $output = $renderer->render($bundle);

        $data = json_decode($output, true);
        $this->assertSame('bundle', $data['type']);
        $this->assertCount(1, $data['files']);
        $this->assertSame('old.txt', $data['files'][0]['oldPath']);
        $this->assertSame('new.txt', $data['files'][0]['newPath']);
    }

    public function testRenderWithWordSpans(): void
    {
        $renderer = new JsonRenderer();
        $wordSpans = [
            new WordSpan('del', 'old'),
            new WordSpan('add', 'new'),
        ];
        $edits = [new Edit('add', 'new word', $wordSpans)];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $data = json_decode($output, true);
        $this->assertIsArray($data['hunks'][0]['edits'][0]['wordSpans']);
        $this->assertCount(2, $data['hunks'][0]['edits'][0]['wordSpans']);
        $this->assertSame('del', $data['hunks'][0]['edits'][0]['wordSpans'][0]['op']);
        $this->assertSame('old', $data['hunks'][0]['edits'][0]['wordSpans'][0]['text']);
    }

    public function testRenderWithoutWordSpans(): void
    {
        $renderer = new JsonRenderer();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $renderer->render($result);

        $data = json_decode($output, true);
        $this->assertNull($data['hunks'][0]['edits'][0]['wordSpans']);
    }

    public function testRenderWithLabels(): void
    {
        $renderer = new JsonRenderer();
        $result = new DiffResult([], oldLabel: 'old.txt', newLabel: 'new.txt');

        $output = $renderer->render($result);

        $data = json_decode($output, true);
        $this->assertSame('old.txt', $data['oldLabel']);
        $this->assertSame('new.txt', $data['newLabel']);
    }

    public function testRenderWithTrailingNewlineFlags(): void
    {
        $renderer = new JsonRenderer();
        $result = new DiffResult([], oldHasTrailingNewline: false, newHasTrailingNewline: false);

        $output = $renderer->render($result);

        $data = json_decode($output, true);
        $this->assertFalse($data['oldHasTrailingNewline']);
        $this->assertFalse($data['newHasTrailingNewline']);
    }
}
