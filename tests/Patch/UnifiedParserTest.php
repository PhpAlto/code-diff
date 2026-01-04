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

use Alto\Code\Diff\Exception\BinaryInputException;
use Alto\Code\Diff\Exception\ParseException;
use Alto\Code\Diff\Patch\UnifiedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnifiedParser::class)]
final class UnifiedParserTest extends TestCase
{
    public function testParseEmptyPatch(): void
    {
        $parser = new UnifiedParser();
        $bundle = $parser->parse('');

        $this->assertSame([], $bundle->files());
    }

    public function testParseSimpleAddition(): void
    {
        $patch = <<<'PATCH'
--- old.txt
+++ new.txt
@@ -1,0 +1,1 @@
+new line
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $files = $bundle->files();
        $this->assertCount(1, $files);
        $this->assertSame('old.txt', $files[0]->oldPath);
        $this->assertSame('new.txt', $files[0]->newPath);

        $hunks = $files[0]->result->hunks();
        $this->assertCount(1, $hunks);
        $this->assertCount(1, $hunks[0]->edits);
        $this->assertSame('add', $hunks[0]->edits[0]->op);
        $this->assertSame('new line', $hunks[0]->edits[0]->text);
    }

    public function testParseSimpleDeletion(): void
    {
        $patch = <<<'PATCH'
--- old.txt
+++ new.txt
@@ -1,1 +1,0 @@
-deleted line
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $files = $bundle->files();
        $hunks = $files[0]->result->hunks();
        $this->assertSame('del', $hunks[0]->edits[0]->op);
        $this->assertSame('deleted line', $hunks[0]->edits[0]->text);
    }

    public function testParseWithContext(): void
    {
        $patch = <<<'PATCH'
--- old.txt
+++ new.txt
@@ -1,3 +1,3 @@
 context1
-old line
+new line
 context2
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $hunks = $bundle->files()[0]->result->hunks();
        $this->assertCount(4, $hunks[0]->edits);
        $this->assertSame('eq', $hunks[0]->edits[0]->op);
        $this->assertSame('del', $hunks[0]->edits[1]->op);
        $this->assertSame('add', $hunks[0]->edits[2]->op);
        $this->assertSame('eq', $hunks[0]->edits[3]->op);
    }

    public function testParseWithGitHeader(): void
    {
        $patch = <<<'PATCH'
diff --git a/file.txt b/file.txt
index abcdef..123456 100644
--- a/file.txt
+++ b/file.txt
@@ -1,1 +1,1 @@
-old
+new
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $file = $bundle->files()[0];
        $this->assertSame('file.txt', $file->oldPath);
        $this->assertArrayHasKey('diff', $file->headers);
        $this->assertArrayHasKey('index', $file->headers);
    }

    public function testParseMultipleFiles(): void
    {
        $patch = <<<'PATCH'
diff --git a/file1.txt b/file1.txt
--- file1.txt
+++ file1.txt
@@ -1,1 +1,1 @@
-old1
+new1
diff --git a/file2.txt b/file2.txt
--- file2.txt
+++ file2.txt
@@ -1,1 +1,1 @@
-old2
+new2
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertCount(2, $bundle->files());
        $this->assertSame('file1.txt', $bundle->files()[0]->oldPath);
        $this->assertSame('file2.txt', $bundle->files()[1]->oldPath);
    }

    public function testParseMultipleHunks(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old1
+new1
@@ -10,1 +10,1 @@
-old2
+new2
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $hunks = $bundle->files()[0]->result->hunks();
        $this->assertCount(2, $hunks);
        $this->assertSame(1, $hunks[0]->oldStart);
        $this->assertSame(10, $hunks[1]->oldStart);
    }

    public function testParseWithNewFileMode(): void
    {
        $patch = <<<'PATCH'
diff --git a/new.txt b/new.txt
new file mode 100644
--- /dev/null
+++ b/new.txt
@@ -0,0 +1,1 @@
+content
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertArrayHasKey('new_file_mode', $bundle->files()[0]->headers);
    }

    public function testParseWithDeletedFileMode(): void
    {
        $patch = <<<'PATCH'
diff --git a/deleted.txt b/deleted.txt
deleted file mode 100644
--- a/deleted.txt
+++ /dev/null
@@ -1,1 +0,0 @@
-content
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertArrayHasKey('deleted_file_mode', $bundle->files()[0]->headers);
    }

    public function testParseWithModeChange(): void
    {
        $patch = <<<'PATCH'
diff --git a/script.sh b/script.sh
old mode 100644
new mode 100755
--- a/script.sh
+++ b/script.sh
@@ -1,1 +1,1 @@
 content
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $headers = $bundle->files()[0]->headers;
        $this->assertArrayHasKey('old_mode', $headers);
        $this->assertArrayHasKey('new_mode', $headers);
    }

    public function testParseWithRename(): void
    {
        $patch = <<<'PATCH'
diff --git a/old.txt b/new.txt
similarity index 100%
rename from old.txt
rename to new.txt
--- a/old.txt
+++ b/new.txt
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $headers = $bundle->files()[0]->headers;
        $this->assertArrayHasKey('similarity_index', $headers);
        $this->assertArrayHasKey('rename_from', $headers);
        $this->assertArrayHasKey('rename_to', $headers);
    }

    public function testParseWithCopy(): void
    {
        $patch = <<<'PATCH'
diff --git a/orig.txt b/copy.txt
copy from orig.txt
copy to copy.txt
--- a/orig.txt
+++ b/copy.txt
@@ -1,1 +1,1 @@
 content
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $headers = $bundle->files()[0]->headers;
        $this->assertArrayHasKey('copy_from', $headers);
        $this->assertArrayHasKey('copy_to', $headers);
    }

    public function testParseThrowsOnBinaryFiles(): void
    {
        $patch = 'Binary files old.bin and new.bin differ';

        $parser = new UnifiedParser();

        $this->expectException(BinaryInputException::class);
        $parser->parse($patch);
    }

    public function testParseThrowsOnInvalidHunkHeader(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ invalid @@
PATCH;

        $parser = new UnifiedParser();

        $this->expectException(ParseException::class);
        $parser->parse($patch);
    }

    public function testParseHandlesNoNewlineMarker(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old
\ No newline at end of file
+new
\ No newline at end of file
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertCount(1, $bundle->files());
    }

    public function testParseStripsPathPrefixes(): void
    {
        $patch = <<<'PATCH'
--- a/dir/file.txt
+++ b/dir/file.txt
@@ -1,1 +1,1 @@
-old
+new
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertSame('dir/file.txt', $bundle->files()[0]->oldPath);
        $this->assertSame('dir/file.txt', $bundle->files()[0]->newPath);
    }

    public function testParseHandlesTabInPath(): void
    {
        $patch = <<<'PATCH'
--- file.txt	2024-01-01
+++ file.txt	2024-01-02
@@ -1,1 +1,1 @@
-old
+new
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertSame('file.txt', $bundle->files()[0]->oldPath);
    }

    public function testParseEmptyLineAtEnd(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old
+new

PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $this->assertCount(1, $bundle->files());
    }

    public function testParseContextLineWithoutSpace(): void
    {
        // Some editors might strip trailing spaces, resulting in empty lines for empty context lines
        // or lines without space prefix if they are just context
        $patch = "--- file.txt\n+++ file.txt\n@@ -1,3 +1,3 @@\ncontext1\n-old\n+new\ncontext2\n";

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $hunks = $bundle->files()[0]->result->hunks();
        $this->assertCount(4, $hunks[0]->edits);
        $this->assertSame('eq', $hunks[0]->edits[0]->op);
        $this->assertSame('context1', $hunks[0]->edits[0]->text);
        $this->assertSame('eq', $hunks[0]->edits[3]->op);
        $this->assertSame('context2', $hunks[0]->edits[3]->text);
    }

    public function testParseWithGarbageAfterHunk(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
-old
+new
Garbage line that should be ignored
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $hunks = $bundle->files()[0]->result->hunks();
        $this->assertCount(1, $hunks);
        $this->assertSame('del', $hunks[0]->edits[0]->op);
        $this->assertSame('add', $hunks[0]->edits[1]->op);
    }

    public function testParseTracksTrailingNewlineFlags(): void
    {
        $patch = <<<'PATCH'
--- old.txt
+++ new.txt
@@ -1,1 +1,1 @@
-old
\ No newline at end of file
+new
\ No newline at end of file
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $file = $bundle->files()[0];
        $this->assertFalse($file->result->oldHasTrailingNewline);
        $this->assertFalse($file->result->newHasTrailingNewline);
    }

    public function testParseContextNewlineMarkerMarksBothSides(): void
    {
        $patch = <<<'PATCH'
--- old.txt
+++ new.txt
@@ -1,1 +1,1 @@
 context
\ No newline at end of file
PATCH;

        $parser = new UnifiedParser();
        $bundle = $parser->parse($patch);

        $file = $bundle->files()[0];
        $this->assertFalse($file->result->oldHasTrailingNewline);
        $this->assertFalse($file->result->newHasTrailingNewline);
    }

    public function testParseThrowsOnHunkLengthMismatch(): void
    {
        $patch = <<<'PATCH'
--- file.txt
+++ file.txt
@@ -1,1 +1,1 @@
PATCH;

        $parser = new UnifiedParser();

        $this->expectException(ParseException::class);
        $parser->parse($patch);
    }

    public function testNewlineMarkerWithoutEditThrowsParseException(): void
    {
        $parser = new UnifiedParser();

        $patch = implode("\n", [
            'diff --git a/file.txt b/file.txt',
            '--- a/file.txt',
            '+++ b/file.txt',
            '@@ -1 +1 @@',
            '\ No newline at end of file',
            '',
        ]);

        $this->expectException(ParseException::class);
        $parser->parse($patch);
    }
}
