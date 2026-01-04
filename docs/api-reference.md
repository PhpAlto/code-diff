# API Reference

Complete API documentation for Alto Code Diff.

## Table of Contents

- [Core Classes](#core-classes)
- [Model Classes](#model-classes)
- [Renderers](#renderers)
- [Patch Classes](#patch-classes)
- [Engine Classes](#engine-classes)
- [Exceptions](#exceptions)
- [Utilities](#utilities)

## Core Classes

### Diff

Main entry point for creating diffs.

```php
namespace Alto\Code\Diff;

final class Diff
{
    public static function build(): self;

    public function withWordDiff(bool $on = true): self;
    public function ignoreWhitespace(bool $on = true): self;
    public function contextLines(int $n): self;
    public function maxBytes(int $bytes): self;
    public function withEngine(DiffEngineInterface $engine): self;

    public function compare(string $old, string $new): DiffResult;
}
```

**Methods:**

- `build(): self` - Create a new Diff instance
- `withWordDiff(bool $on = true): self` - Enable word-level diffing
- `ignoreWhitespace(bool $on = true): self` - Ignore whitespace differences
- `contextLines(int $n): self` - Set number of context lines (default: 3)
- `maxBytes(int $bytes): self` - Set maximum input size (default: 5_000_000)
- `withEngine(DiffEngineInterface $engine): self` - Use custom [diff engine](#diffengineinterface)
- `compare(string $old, string $new): DiffResult` - Compare two strings

**Throws:**

- `BinaryInputException` - When input contains binary data
- `SizeLimitException` - When input exceeds maxBytes

### Options

Configuration object for diff operations.

```php
namespace Alto\Code\Diff\Options;

final readonly class Options
{
    public function __construct(
        public int $contextLines = 3,
        public bool $wordDiff = false,
        public bool $ignoreWhitespace = false,
        public int $maxBytes = 5_000_000,
    );

    public function withContextLines(int $n): self;
    public function withWordDiff(bool $on = true): self;
    public function withIgnoreWhitespace(bool $on = true): self;
    public function withMaxBytes(int $bytes): self;
}
```

## Model Classes

### DiffResult

Result of a diff operation.

```php
namespace Alto\Code\Diff\Model;

final readonly class DiffResult
{
    public function __construct(
        public array $hunks,                           // list<Hunk>
        public ?string $oldLabel = null,
        public ?string $newLabel = null,
        public bool $oldHasTrailingNewline = true,
        public bool $newHasTrailingNewline = true,
    );

    public function hunks(): array;  // list<Hunk>
    public function isEmpty(): bool;
}
```

**Properties:**

- `hunks` - Array of Hunk objects representing changes
- `oldLabel` - Optional label for old version
- `newLabel` - Optional label for new version
- `oldHasTrailingNewline` - Whether old content has trailing newline
- `newHasTrailingNewline` - Whether new content has trailing newline

**Methods:**

- `hunks(): array` - Get all hunks
- `isEmpty(): bool` - Check if there are no changes

### Hunk

A continuous block of changes.

```php
namespace Alto\Code\Diff\Model;

final readonly class Hunk
{
    public function __construct(
        public int $oldStart,
        public int $oldLen,
        public int $newStart,
        public int $newLen,
        public array $edits,  // list<Edit>
    );
}
```

**Properties:**

- `oldStart` - Starting line number in old file (1-indexed)
- `oldLen` - Number of lines from old file in this hunk
- `newStart` - Starting line number in new file (1-indexed)
- `newLen` - Number of lines from new file in this hunk
- `edits` - Array of Edit objects

### Edit

A single line change.

```php
namespace Alto\Code\Diff\Model;

final readonly class Edit
{
    public function __construct(
        public string $op,        // 'add'|'del'|'eq'
        public string $text,
        public array $wordSpans = [],  // list<WordSpan>
    );
}
```

**Properties:**

- `op` - Operation type: 'add' (addition), 'del' (deletion), or 'eq' (equal/context)
- `text` - The line content
- `wordSpans` - Word-level changes (populated when word diff is enabled)

### WordSpan

Word-level change within a line.

```php
namespace Alto\Code\Diff\Model;

final readonly class WordSpan
{
    public function __construct(
        public string $op,    // 'add'|'del'|'eq'
        public string $text,
    );
}
```

**Properties:**

- `op` - Operation type
- `text` - The word/token text

### DiffFile

Represents a single file in a diff bundle.

```php
namespace Alto\Code\Diff\Model;

final readonly class DiffFile
{
    public function __construct(
        public string $oldPath,
        public string $newPath,
        public DiffResult $result,
        public array $headers = [],  // array<string, string>
    );
}
```

**Properties:**

- `oldPath` - Path to old version of file
- `newPath` - Path to new version of file
- `result` - DiffResult for this file
- `headers` - Git-style headers (diff, index, mode changes, etc.)

### DiffBundle

Collection of multiple file diffs.

```php
namespace Alto\Code\Diff\Model;

final readonly class DiffBundle
{
    public function __construct(
        public array $files,  // list<DiffFile>
    );

    public function files(): array;  // list<DiffFile>
}
```

**Methods:**

- `files(): array` - Get all files in the bundle

## Renderers

All renderers implement the `Renderer` interface:

```php
namespace Alto\Code\Diff\Renderer;

interface Renderer
{
    public function render(DiffResult|DiffBundle $diff): string;
}
```

### UnifiedRenderer

Renders diffs in unified format.

```php
namespace Alto\Code\Diff\Renderer;

final class UnifiedRenderer implements RendererInterface
{
    public function __construct(
        private readonly ?string $oldLabel = null,
        private readonly ?string $newLabel = null,
    );

    public function render(DiffResult|DiffBundle $diff): string;
}
```

**Constructor Parameters:**

- `oldLabel` - Label for old version (e.g., 'a/file.txt')
- `newLabel` - Label for new version (e.g., 'b/file.txt')

**Output Format:**

```diff
--- old.txt
+++ new.txt
@@ -1,3 +1,3 @@
 context
-removed
+added
 context
```

### HtmlRenderer

Renders diffs as HTML tables.

```php
namespace Alto\Code\Diff\Renderer;

final class HtmlRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $showLineNumbers = true,
        private readonly bool $wrapLines = false,
        private readonly string $classPrefix = 'diff-',
    );

    public function render(DiffResult|DiffBundle $diff): string;
}
```

**Constructor Parameters:**

- `showLineNumbers` - Whether to show line numbers
- `wrapLines` - Whether to wrap long lines
- `classPrefix` - CSS class prefix for styling

**CSS Classes:**

- `{prefix}table` - The main table
- `{prefix}hunk-header` - Hunk header rows
- `{prefix}add` - Addition rows
- `{prefix}del` - Deletion rows
- `{prefix}ctx` - Context rows
- `{prefix}line-num` - Line number cells
- `{prefix}prefix` - The +/- prefix cell
- `{prefix}content` - Content cell
- `{prefix}word-add` - Added words (word diff)
- `{prefix}word-del` - Deleted words (word diff)

### JsonRenderer

Renders diffs as JSON.

```php
namespace Alto\Code\Diff\Renderer;

final class JsonRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $prettyPrint = false,
    );

    public function render(DiffResult|DiffBundle $diff): string;
}
```

**Constructor Parameters:**

- `prettyPrint` - Whether to format with indentation

**JSON Structure:**

```json
{
  "type": "result",
  "oldLabel": "old.txt",
  "newLabel": "new.txt",
  "oldHasTrailingNewline": true,
  "newHasTrailingNewline": true,
  "hunks": [
    {
      "oldStart": 1,
      "oldLen": 3,
      "newStart": 1,
      "newLen": 3,
      "edits": [
        {
          "op": "eq",
          "text": "context",
          "wordSpans": null
        }
      ]
    }
  ]
}
```

### AnsiSideBySideRenderer

Renders side-by-side diffs with ANSI colors for terminal.

```php
namespace Alto\Code\Diff\Renderer;

final class AnsiSideBySideRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $showLineNumbers = true,
        private readonly int $width = 80,
    );

    public function render(DiffResult|DiffBundle $diff): string;
}
```

**Constructor Parameters:**

- `showLineNumbers` - Whether to show line numbers
- `width` - Total terminal width in characters

**ANSI Codes Used:**

- Red (deletions)
- Green (additions)
- Cyan (headers)
- Dim (line numbers)
- Red/Green backgrounds (word highlights)

## Patch Classes

### UnifiedParser

Parses unified diff patches.

```php
namespace Alto\Code\Diff\Patch;

final class UnifiedParser
{
    public function parse(string $patch): DiffBundle;
}
```

**Methods:**

- `parse(string $patch): DiffBundle` - Parse a unified diff patch

**Throws:**

- `BinaryInputException` - When patch contains binary files
- `ParseException` - When patch format is invalid

**Supported Headers:**

- `diff --git`
- `index`
- `old mode` / `new mode`
- `new file mode` / `deleted file mode`
- `similarity index`
- `rename from` / `rename to`
- `copy from` / `copy to`

### UnifiedEmitter

Emits unified diff patches.

```php
namespace Alto\Code\Diff\Patch;

final class UnifiedEmitter
{
    public function emit(DiffResult|DiffBundle $diff): string;
}
```

**Methods:**

- `emit(DiffResult|DiffBundle $diff): string` - Generate unified diff patch

### PatchApplier

Applies patches to content.

```php
namespace Alto\Code\Diff\Patch;

final class PatchApplier
{
    public function __construct(
        private readonly int $fuzz = 0,
        private readonly int $maxBytes = 5_000_000,
    );

    public function apply(string $original, string $unifiedPatch): string;

    public function applyBundle(
        array $files,         // array<string, string> (filename => content)
        DiffBundle $bundle
    ): array;                // array<string, string> (filename => patched_content)
}
```

**Constructor Parameters:**

- `fuzz` - Number of lines to search around expected position
- `maxBytes` - Maximum size of original content

**Methods:**

- `apply(string $original, string $unifiedPatch): string` - Apply patch to string
- `applyBundle(array $files, DiffBundle $bundle): array` - Apply patches to multiple files

**Throws:**

- `PatchApplyException` - When patch cannot be applied
- `SizeLimitException` - When input exceeds maxBytes

## Engine Classes

### DiffEngineInterface

Interface for diff algorithms.

```php
namespace Alto\Code\Diff\Engine;

interface DiffEngineInterface
{
    public function diff(string $old, string $new, Options $opts): DiffResult;
}
```

### MyersDiffEngine

Default diff engine using Myers algorithm.

```php
namespace Alto\Code\Diff\Engine;

final class MyersDiffEngine implements DiffEngineInterface
{
    public function __construct(
        private readonly ?TokenizerInterface $wordTokenizer = new WordTokenizer(),
    );

    public function diff(string $old, string $new, Options $opts): DiffResult;
}
```

### TokenizerInterface

Interface for tokenizers.

```php
namespace Alto\Code\Diff\Engine;

interface TokenizerInterface
{
    public function tokenize(string $input): array;  // list<string>
}
```



### WordTokenizer

Splits text into words and whitespace.

```php
namespace Alto\Code\Diff\Engine;

final class WordTokenizer implements TokenizerInterface
{
    public function tokenize(string $input): array;  // list<string>
}
```

## Exceptions

All exceptions extend `RuntimeException`.

### BinaryInputException

Thrown when binary input is detected.

```php
namespace Alto\Code\Diff\Exception;

final class BinaryInputException extends RuntimeException
{
}
```

### SizeLimitException

Thrown when input exceeds size limit.

```php
namespace Alto\Code\Diff\Exception;

final class SizeLimitException extends RuntimeException
{
}
```

### PatchApplyException

Thrown when patch cannot be applied.

```php
namespace Alto\Code\Diff\Exception;

final class PatchApplyException extends RuntimeException
{
    public function __construct(
        public readonly int $hunkIndex,
        string $message,
    );
}
```

**Properties:**

- `hunkIndex` - Index of the hunk that failed to apply

### ParseException

Thrown when patch parsing fails.

```php
namespace Alto\Code\Diff\Exception;

final class ParseException extends RuntimeException
{
}
```

## Utilities

### NewlineNormalizer

Normalizes line endings and splits into lines.

```php
namespace Alto\Code\Diff\Util;

final class NewlineNormalizer
{
    public static function normalize(string $s): array;  // [string, bool]

    public static function splitLines(string $s): array; // [list<string>, bool]
}
```

**Methods:**

- `normalize(string $s): array` - Returns [normalized string, hasTrailingNewline]
- `splitLines(string $s): array` - Returns [array of lines, hasTrailingNewline]

### BinaryGuard

Detects binary content.

```php
namespace Alto\Code\Diff\Util;

final class BinaryGuard
{
    public static function assertText(string $s): void;
}
```

**Methods:**

- `assertText(string $s): void` - Throws if input appears to be binary

**Throws:**

- `BinaryInputException` - When binary content detected

**Detection Logic:**

- Checks for null bytes
- Checks for excessive non-printable characters (>30%)
- Only checks first 8KB for performance
