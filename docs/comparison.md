# Comparison

`Diff::compare(string $old, string $new): DiffResult` compares two text strings line by line. The default Myers engine returns only changed regions and three surrounding context lines.

## Options

All options are immutable and can be combined:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;

$diff = Diff::build()
    ->withWordDiff()
    ->ignoreWhitespace()
    ->contextLines(5)
    ->maxBytes(10_000_000);

$result = $diff->compare(
    "The quick brown fox\n",
    "The fast brown fox\n",
);

echo count($result->hunks())." changed region(s)\n";
```

| Method | Default | Effect |
| --- | --- | --- |
| `withWordDiff(bool $on = true)` | `false` | Adds word spans to paired changed lines. |
| `ignoreWhitespace(bool $on = true)` | `false` | Compares trimmed lines after collapsing whitespace runs. Original text is retained in the result. |
| `contextLines(int $n)` | `3` | Sets unchanged lines around each change. The value cannot be negative. |
| `maxBytes(int $bytes)` | `5_000_000` | Limits each input string. The value must be positive. |
| `withEngine(DiffEngineInterface $engine)` | Myers | Selects another comparison engine. |

`compare()` throws `SizeLimitException` when either input exceeds the configured limit and `BinaryInputException` when an input appears to contain binary data. Binary detection checks null bytes and excessive control characters near the beginning of the input.

## Inspect the result

`DiffResult::isEmpty()` reports whether the inputs differ. `hunks()` returns the changed regions, while the newline flags preserve whether each input ended with a line break.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;

$result = Diff::build()
    ->withWordDiff()
    ->compare("The quick fox\n", "The fast fox\n");

foreach ($result->hunks() as $hunk) {
    printf(
        "old %d,%d; new %d,%d\n",
        $hunk->oldStart,
        $hunk->oldLen,
        $hunk->newStart,
        $hunk->newLen,
    );

    foreach ($hunk->edits as $edit) {
        printf("%s %s\n", $edit->op, $edit->text);

        foreach ($edit->wordSpans as $span) {
            printf("  %s %s\n", $span->op, $span->text);
        }
    }
}
```

The result model contains:

- `DiffResult`: hunks, optional labels, and trailing-newline flags.
- `Hunk`: old and new ranges plus its edits.
- `Edit`: an `add`, `del`, or `eq` operation and its original text.
- `WordSpan`: an `add`, `del`, or `eq` token produced by word-level comparison.

Use [Rendering](rendering.md) when you need formatted output.
