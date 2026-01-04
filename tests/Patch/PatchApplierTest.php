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

use Alto\Code\Diff\Exception\PatchApplyException;
use Alto\Code\Diff\Exception\SizeLimitException;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;
use Alto\Code\Diff\Patch\PatchApplier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchApplier::class)]
final class PatchApplierTest extends TestCase
{
    public function testApplyEmptyPatch(): void
    {
        $applier = new PatchApplier();
        $result = $applier->apply("original content\n", '');

        $this->assertSame("original content\n", $result);
    }

    public function testApplySimpleAddition(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,2 @@
 line1
+line2
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply("line1\n", $patch);

        $this->assertSame("line1\nline2\n", $result);
    }

    public function testApplySimpleDeletion(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,2 +1,1 @@
 line1
-line2
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply("line1\nline2\n", $patch);

        $this->assertSame("line1\n", $result);
    }

    public function testApplyChange(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old line
+new line
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply("old line\n", $patch);

        $this->assertSame("new line\n", $result);
    }

    public function testApplyMultipleHunks(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old1
+new1
@@ -3,1 +3,1 @@
-old3
+new3
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply("old1\nline2\nold3\n", $patch);

        $this->assertSame("new1\nline2\nnew3\n", $result);
    }

    public function testApplyThrowsOnSizeLimit(): void
    {
        $applier = new PatchApplier(maxBytes: 10);
        $largeContent = str_repeat('a', 100);

        $this->expectException(SizeLimitException::class);
        $applier->apply($largeContent, '');
    }

    public function testApplyThrowsWhenContextDoesNotMatch(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-expected line
+new line
PATCH;

        $applier = new PatchApplier();

        $this->expectException(PatchApplyException::class);
        $this->expectExceptionMessage('Hunk #1 failed to apply');
        $applier->apply("different line\n", $patch);
    }

    public function testApplyWithFuzz(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -2,1 +2,1 @@
-old line
+new line
PATCH;

        $applier = new PatchApplier(fuzz: 2);
        // "old line" is at line 3 (index 2). Patch expects it at line 2 (index 1).
        // Difference is +1 line. Fuzz should handle it.
        $result = $applier->apply("line1\nline2\nold line\n", $patch);

        $this->assertSame("line1\nline2\nnew line\n", $result);
    }

    public function testApplyWithNegativeFuzz(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -3,1 +3,1 @@
-old line
+new line
PATCH;

        $applier = new PatchApplier(fuzz: 2);
        // "old line" is at line 2 (index 1). Patch expects it at line 3 (index 2).
        // Difference is -1 line. Fuzz should handle it.
        $result = $applier->apply("line1\nold line\nline3\n", $patch);

        $this->assertSame("line1\nnew line\nline3\n", $result);
    }

    public function testApplyWithOldStartZero(): void
    {
        // When oldStart is 0, targetLine becomes -1.
        $patch = <<<'PATCH'
--- /dev/null
+++ new.txt
@@ -0,0 +1,1 @@
+new line
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply('', $patch);

        $this->assertSame("new line\n", $result);
    }

    public function testApplyWithNegativeTargetLine(): void
    {
        // Hunk 1 deletes lines, making Hunk 2's target negative.
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,5 +1,0 @@
-1
-2
-3
-4
-5
@@ -3,1 +1,1 @@
-3
+3 modified
PATCH;

        $applier = new PatchApplier();

        $this->expectException(PatchApplyException::class);
        $applier->apply("1\n2\n3\n4\n5\n", $patch);
    }

    public function testApplyWithContextOutOfBounds(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,2 +1,2 @@
  line1
  line2
PATCH;

        $applier = new PatchApplier(fuzz: 1);
        // File only has 1 line. Context requires 2.
        // matchesAt should return false.
        // Since we have fuzz, it might try offsets, but all should fail or be out of bounds.

        $this->expectException(PatchApplyException::class);
        $applier->apply("line1\n", $patch);
    }

    public function testApplyToEmptyFile(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,0 +1,1 @@
+new line
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply('', $patch);

        $this->assertSame("new line\n", $result);
    }

    public function testApplyResultingInEmptyFile(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +0,0 @@
-only line
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply("only line\n", $patch);

        $this->assertSame('', $result);
    }

    public function testApplyBundle(): void
    {
        $files = [
            'file1.txt' => "line1\n",
            'file2.txt' => "line2\n",
        ];

        $edits1 = [new Edit('del', 'line1'), new Edit('add', 'new1')];
        $hunk1 = new Hunk(1, 1, 1, 1, $edits1);
        $result1 = new DiffResult([$hunk1]);
        $diffFile1 = new DiffFile('file1.txt', 'file1.txt', $result1);

        $edits2 = [new Edit('del', 'line2'), new Edit('add', 'new2')];
        $hunk2 = new Hunk(1, 1, 1, 1, $edits2);
        $result2 = new DiffResult([$hunk2]);
        $diffFile2 = new DiffFile('file2.txt', 'file2.txt', $result2);

        $bundle = new DiffBundle([$diffFile1, $diffFile2]);

        $applier = new PatchApplier();
        $patched = $applier->applyBundle($files, $bundle);

        $this->assertSame("new1\n", $patched['file1.txt']);
        $this->assertSame("new2\n", $patched['file2.txt']);
    }

    public function testApplyBundleThrowsOnMissingFile(): void
    {
        $files = [];

        $edits = [new Edit('add', 'content')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $diffFile = new DiffFile('missing.txt', 'missing.txt', $result);
        $bundle = new DiffBundle([$diffFile]);

        $applier = new PatchApplier();

        $this->expectException(PatchApplyException::class);
        $this->expectExceptionMessage('File not found: missing.txt');
        $applier->applyBundle($files, $bundle);
    }

    public function testApplyBundleHandlesRename(): void
    {
        $files = ['old.txt' => "content\n"];

        $edits = [new Edit('eq', 'content')];
        $hunk = new Hunk(1, 1, 1, 1, $edits);
        $result = new DiffResult([$hunk]);
        $diffFile = new DiffFile('old.txt', 'new.txt', $result);
        $bundle = new DiffBundle([$diffFile]);

        $applier = new PatchApplier();
        $patched = $applier->applyBundle($files, $bundle);

        $this->assertArrayHasKey('new.txt', $patched);
        $this->assertArrayNotHasKey('old.txt', $patched);
        $this->assertSame("content\n", $patched['new.txt']);
    }

    public function testApplyBundleSkipsEmptyDiffForMissingFile(): void
    {
        $files = ['existing.txt' => "content\n"];

        $emptyResult = new DiffResult([]);
        $diffFile = new DiffFile('missing.txt', 'missing.txt', $emptyResult);
        $bundle = new DiffBundle([$diffFile]);

        $applier = new PatchApplier();
        $patched = $applier->applyBundle($files, $bundle);

        $this->assertSame($files, $patched);
    }

    public function testApplyThrowsOnMultiFilePatch(): void
    {
        $patch = <<<'PATCH'
diff --git a/file1.txt b/file1.txt
--- file1.txt
+++ file1.txt
@@ -1,1 +1,1 @@
-line1
+line1 updated
diff --git a/file2.txt b/file2.txt
--- file2.txt
+++ file2.txt
@@ -1,1 +1,1 @@
-line2
+line2 updated
PATCH;

        $applier = new PatchApplier();

        $this->expectException(PatchApplyException::class);
        $applier->apply("line1\n", $patch);
    }

    public function testApplyRespectsNoNewlineMarker(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -0,0 +1,1 @@
+no newline here
\ No newline at end of file
PATCH;

        $applier = new PatchApplier();
        $result = $applier->apply('', $patch);

        $this->assertSame('no newline here', $result);
    }

    public function testApplyBundleCreatesFilesFromDevNull(): void
    {
        $edits = [new Edit('add', 'hello')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);
        $result = new DiffResult([$hunk], newHasTrailingNewline: false);
        $diffFile = new DiffFile('/dev/null', 'new.txt', $result);
        $bundle = new DiffBundle([$diffFile]);

        $applier = new PatchApplier();
        $patched = $applier->applyBundle([], $bundle);

        $this->assertArrayHasKey('new.txt', $patched);
        $this->assertSame('hello', $patched['new.txt']);
    }

    public function testApplyBundleRemovesFilesWhenNewPathIsDevNull(): void
    {
        $edits = [new Edit('del', 'line')];
        $hunk = new Hunk(1, 1, 1, 0, $edits);
        $result = new DiffResult([$hunk]);
        $diffFile = new DiffFile('old.txt', '/dev/null', $result);
        $bundle = new DiffBundle([$diffFile]);

        $applier = new PatchApplier();
        $patched = $applier->applyBundle(['old.txt' => "line\n"], $bundle);

        $this->assertArrayNotHasKey('old.txt', $patched);
    }
}
