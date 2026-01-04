# Advanced Scenarios

Handle large inputs, binary detection, custom options, and other nuanced flows.

## Compare with Size Limits

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Exception\SizeLimitException;

// --- Generate Large Content ---
$largeFile1 = str_repeat("A", 200_000); // 200KB
$largeFile2 = str_repeat("B", 200_000);
// ------------------------------

// Set limit to 100KB
$diff = Diff::build()->maxBytes(100_000);

try {
    $result = $diff->compare($largeFile1, $largeFile2);
} catch (SizeLimitException $e) {
    echo "Files too large to compare: {$e->getMessage()}\n";
}
```

## Binary File Detection

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Exception\BinaryInputException;

// --- Create Binary Content ---
$file1 = "Text content";
$file2 = "Binary \0 content"; // Null byte indicates binary
// -----------------------------

try {
    $result = Diff::build()->compare($file1, $file2);
} catch (BinaryInputException $e) {
    echo "Cannot diff binary files\n";
}
```

## Custom Context Lines

```php
use Alto\Code\Diff\Diff;

// --- Define Content ---
$old = <<< 'EOF'
Line 1
Line 2
Line 3
Line 4 (changed)
Line 5
Line 6
Line 7
EOF;

$new = <<< 'EOF'
Line 1
Line 2
Line 3
Line 4 (modified)
Line 5
Line 6
Line 7
EOF;
// ---------------------

// Show more context (e.g., 5 lines) around changes
$result = Diff::build()
    ->contextLines(5)
    ->compare($old, $new);

// Show minimal context (0 lines)
$resultZero = Diff::build()
    ->contextLines(0)
    ->compare($old, $new);
```

## Combine Multiple Options

```php
use Alto\Code\Diff\Diff;

$old = " The  quick  brown  fox ";
$new = " The  fast  brown  fox ";

$result = Diff::build()
    ->withWordDiff()
    ->ignoreWhitespace()
    ->contextLines(5)
    ->maxBytes(10_000_000)
    ->compare($old, $new);
```

## Working with No Trailing Newline

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

// --- Define Content ---
$old = "line1\nline2";  // No trailing newline
$new = "line1\nline2\n"; // Has trailing newline
// ---------------------

$result = Diff::build()->compare($old, $new);

echo "Old has trailing newline: " .
    ($result->oldHasTrailingNewline ? 'yes' : 'no') . "\n";
echo "New has trailing newline: " .
    ($result->newHasTrailingNewline ? 'yes' : 'no') . "\n";

// Unified renderer will show "\ No newline at end of file"
$renderer = new UnifiedRenderer();
echo $renderer->render($result);
```

## Inspect Diff Structure

```php
use Alto\Code\Diff\Diff;

$old = "A\nB\nC";
$new = "A\nX\nC";

$result = Diff::build()->compare($old, $new);

foreach ($result->hunks() as $i => $hunk) {
    echo "Hunk #{$i}:\n";
    echo "  Old: lines {$hunk->oldStart}-" .
        ($hunk->oldStart + $hunk->oldLen - 1) . "\n";
    echo "  New: lines {$hunk->newStart}-" .
        ($hunk->newStart + $hunk->newLen - 1) . "\n";

    foreach ($hunk->edits as $edit) {
        $symbol = match($edit->op) {
            'add' => '+',
            'del' => '-',
            'eq' => ' ',
        };
        echo "  {$symbol} {$edit->text}\n";
    }
}
```