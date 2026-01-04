# Basic Text Comparison

These examples cover simple text-to-text comparisons using the Myers diff engine.

## Simple Line Diff

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

// --- Input Strings ---
$old = <<< 'EOF'
Hello
World
EOF;

$new = <<< 'EOF'
Hello
PHP
World
EOF;
// ---------------------

$result = Diff::build()->compare($old, $new);

$renderer = new UnifiedRenderer();
echo $renderer->render($result);
```

Output:
```diff
@@ -1,2 +1,3 @@
 Hello
+PHP
 World
```

## Word-Level Changes

```php
use Alto\Code\Diff\Diff;

// --- Input Strings ---
$old = "The quick brown fox";
$new = "The fast brown fox";
// ---------------------

$result = Diff::build()
    ->withWordDiff()
    ->compare($old, $new);

// Word-level differences are stored in Edit->wordSpans
foreach ($result->hunks() as $hunk) {
    foreach ($hunk->edits as $edit) {
        if ($edit->wordSpans) {
            foreach ($edit->wordSpans as $span) {
                echo "{$span->op}: {$span->text}\n";
            }
        }
    }
}
```

Output:

```
eq: The
del: quick
add: fast
eq: brown
eq: fox
```

## Ignore Whitespace

```php
use Alto\Code\Diff\Diff;

// --- Input Strings ---
$old = <<< 'EOF'
line1
  line2
EOF;

$new = <<< 'EOF'
line1
line2
EOF;
// ---------------------

$result = Diff::build()
    ->ignoreWhitespace()
    ->compare($old, $new);

// Result will be empty since only whitespace differs
var_dump($result->isEmpty()); // true
```

```