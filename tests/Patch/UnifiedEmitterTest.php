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

namespace Alto\Code\Diff\Tests\Patch;

use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;
use Alto\Code\Diff\Patch\UnifiedEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnifiedEmitter::class)]
final class UnifiedEmitterTest extends TestCase
{
    public function testEmitEmptyResult(): void
    {
        $emitter = new UnifiedEmitter();
        $result = new DiffResult([]);
        $output = $emitter->emit($result);

        $this->assertSame("--- a\n+++ b", $output);
    }

    public function testEmitResult(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('add', 'new line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk], oldLabel: 'old.txt', newLabel: 'new.txt');

        $output = $emitter->emit($result);

        $this->assertStringContainsString('--- old.txt', $output);
        $this->assertStringContainsString('+++ new.txt', $output);
        $this->assertStringContainsString('+new line', $output);
    }

    public function testEmitResultWithoutLabels(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('add', 'line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);

        $output = $emitter->emit($result);

        $this->assertStringContainsString('--- a', $output);
        $this->assertStringContainsString('+++ b', $output);
    }

    public function testEmitBundle(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $file = new DiffFile('file.txt', 'file.txt', $result);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('diff --git a/file.txt b/file.txt', $output);
        $this->assertStringContainsString('--- a/file.txt', $output);
        $this->assertStringContainsString('+++ b/file.txt', $output);
    }

    public function testEmitBundleWithHeaders(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $headers = [
            'diff' => 'diff --git a/custom.txt b/custom.txt',
            'index' => 'index abc..def 100644',
        ];
        $file = new DiffFile('file.txt', 'file.txt', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('diff --git a/custom.txt b/custom.txt', $output);
        $this->assertStringContainsString('index abc..def 100644', $output);
    }

    public function testEmitBundleWithModeHeaders(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('eq', 'content')];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $headers = [
            'old_mode' => 'old mode 100644',
            'new_mode' => 'new mode 100755',
        ];
        $file = new DiffFile('script.sh', 'script.sh', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('old mode 100644', $output);
        $this->assertStringContainsString('new mode 100755', $output);
    }

    public function testEmitBundleWithNewFileMode(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $headers = ['new_file_mode' => 'new file mode 100644'];
        $file = new DiffFile('new.txt', 'new.txt', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('new file mode 100644', $output);
    }

    public function testEmitBundleWithDeletedFileMode(): void
    {
        $emitter = new UnifiedEmitter();
        $edits = [new Edit('del', 'content')];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);
        $headers = ['deleted_file_mode' => 'deleted file mode 100644'];
        $file = new DiffFile('deleted.txt', 'deleted.txt', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('deleted file mode 100644', $output);
    }

    public function testEmitBundleWithRenameHeaders(): void
    {
        $emitter = new UnifiedEmitter();
        $result = new DiffResult([]);
        $headers = [
            'similarity_index' => 'similarity index 100%',
            'rename_from' => 'rename from old.txt',
            'rename_to' => 'rename to new.txt',
        ];
        $file = new DiffFile('old.txt', 'new.txt', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('similarity index 100%', $output);
        $this->assertStringContainsString('rename from old.txt', $output);
        $this->assertStringContainsString('rename to new.txt', $output);
    }

    public function testEmitBundleWithCopyHeaders(): void
    {
        $emitter = new UnifiedEmitter();
        $result = new DiffResult([]);
        $headers = [
            'copy_from' => 'copy from orig.txt',
            'copy_to' => 'copy to copy.txt',
        ];
        $file = new DiffFile('orig.txt', 'copy.txt', $result, $headers);
        $bundle = new DiffBundle([$file]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('copy from orig.txt', $output);
        $this->assertStringContainsString('copy to copy.txt', $output);
    }

    public function testEmitBundleWithMultipleFiles(): void
    {
        $emitter = new UnifiedEmitter();

        $edits1 = [new Edit('add', 'content1')];
        $hunk1 = new Hunk(1, 0, 1, 1, $edits1);
        $result1 = new DiffResult([$hunk1]);
        $file1 = new DiffFile('file1.txt', 'file1.txt', $result1);

        $edits2 = [new Edit('add', 'content2')];
        $hunk2 = new Hunk(1, 0, 1, 1, $edits2);
        $result2 = new DiffResult([$hunk2]);
        $file2 = new DiffFile('file2.txt', 'file2.txt', $result2);

        $bundle = new DiffBundle([$file1, $file2]);

        $output = $emitter->emit($bundle);

        $this->assertStringContainsString('diff --git a/file1.txt b/file1.txt', $output);
        $this->assertStringContainsString('diff --git a/file2.txt b/file2.txt', $output);
        $this->assertStringContainsString('+content1', $output);
        $this->assertStringContainsString('+content2', $output);
    }

    public function testEmitEmptyBundle(): void
    {
        $emitter = new UnifiedEmitter();
        $bundle = new DiffBundle([]);

        $output = $emitter->emit($bundle);

        $this->assertSame('', $output);
    }
}
