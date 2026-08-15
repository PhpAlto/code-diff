# Patches

Alto Code Diff understands standard unified diffs and common Git headers. It operates on strings and associative arrays; your application remains responsible for file-system access.

## Emit a patch

`UnifiedEmitter::emit()` accepts a `DiffResult` or a multi-file `DiffBundle`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Patch\UnifiedEmitter;

$result = Diff::build()->compare(
    "Line one\nLine two\n",
    "Line one\nLine two changed\n",
);

$patch = (new UnifiedEmitter())->emit($result);
echo $patch;
```

A bare result uses `a` and `b` as labels. To control paths or represent multiple files, construct `DiffFile` objects and place them in a `DiffBundle`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Patch\UnifiedEmitter;

$result = Diff::build()->compare("old\n", "new\n");
$file = new DiffFile(
    oldPath: 'src/example.txt',
    newPath: 'src/example.txt',
    result: $result,
    headers: [
        'diff' => 'diff --git a/src/example.txt b/src/example.txt',
        'index' => 'index 3367afd..3e75765 100644',
    ],
);

echo (new UnifiedEmitter())->emit(new DiffBundle([$file]));
```

Supported metadata keys are `diff`, `index`, `old_mode`, `new_mode`, `new_file_mode`, `deleted_file_mode`, `similarity_index`, `rename_from`, `rename_to`, `copy_from`, and `copy_to`.

## Parse a patch

`UnifiedParser::parse(string $patch): DiffBundle` validates hunk lengths and returns files, paths, headers, results, hunks, and edits. Leading `a/` and `b/` path prefixes are removed.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Patch\UnifiedParser;

$patch = <<<'PATCH'
diff --git a/example.txt b/example.txt
index 3367afd..3e75765 100644
--- a/example.txt
+++ b/example.txt
@@ -1 +1 @@
-old
+new
PATCH;

$bundle = (new UnifiedParser())->parse($patch);
$file = $bundle->files()[0];

printf("%s -> %s\n", $file->oldPath, $file->newPath);
```

The parser recognizes file modes, creation, deletion, rename, copy, similarity, and index headers. It preserves no-trailing-newline markers. It throws `ParseException` for malformed hunks and `BinaryInputException` for binary patch markers.

## Apply a single-file patch

`PatchApplier::apply(string $original, string $unifiedPatch): string` accepts exactly one patched file. An empty patch returns the original string.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Patch\PatchApplier;

$original = "Line one\nLine two\n";
$patch = <<<'PATCH'
--- a/example.txt
+++ b/example.txt
@@ -1,2 +1,2 @@
 Line one
-Line two
+Line two changed
PATCH;

$updated = (new PatchApplier())->apply($original, $patch);
echo $updated;
```

The constructor accepts `fuzz` and `maxBytes`, both defaulting to `0` and `5_000_000`. Fuzz searches that many lines before and after a hunk's expected position. `PatchApplyException` exposes the failed zero-based `hunkIndex`; `SizeLimitException` reports oversized source content.

## Apply a bundle

Use `applyBundle(array $files, DiffBundle $bundle): array` for multiple files. The input and result use `path => content` maps.

The method handles modifications, renames, creations from `/dev/null`, and deletions to `/dev/null`. It throws `PatchApplyException` when a required source path is missing or a hunk cannot be matched. The library returns updated content but never writes it to disk.
