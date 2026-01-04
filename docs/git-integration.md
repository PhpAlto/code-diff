# Git Integration

Use Alto Code Diff alongside git output and metadata.

## Parse Git Diff Output

```php
use Alto\Code\Diff\Patch\UnifiedParser;

// --- Sample Git Diff Output ---
// In a real scenario: $gitDiff = shell_exec('git diff HEAD~1 HEAD');
$gitDiff = <<< 'DIFF'
diff --git a/src/App.php b/src/App.php
index 83a4c02..4c3b2f9 100644
--- a/src/App.php
+++ b/src/App.php
@@ -10,4 +10,4 @@
    public function run()
    {
-        return 'foo';
+        return 'bar';
     }
 }
DIFF;
// ------------------------------

$parser = new UnifiedParser();
$bundle = $parser->parse($gitDiff);

// Process each changed file
foreach ($bundle->files() as $file) {
    echo "Changed: {$file->oldPath}\n";

    // Access git metadata
    if (isset($file->headers['index'])) {
        echo "  Index: {$file->headers['index']}\n";
    }

    echo "  Hunks: " . count($file->result->hunks()) . "\n";
}
```

## Generate Git-Compatible Patch

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Patch\UnifiedEmitter;

// --- Define Content ---
$old = "function test() { return false; }";
$new = "function test() { return true; }";
// ---------------------

$result = Diff::build()->compare($old, $new);

$headers = [
    'diff' => 'diff --git a/file.txt b/file.txt',
    'index' => 'index abc123..def456 100644',
];

$file = new DiffFile('file.txt', 'file.txt', $result, $headers);
$bundle = new DiffBundle([$file]);

$emitter = new UnifiedEmitter();
$patch = $emitter->emit($bundle);

// This patch is compatible with git apply
echo $patch;
```

## Track File Renames

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Patch\UnifiedEmitter;

// --- Content (Unchanged) ---
$content = "This content moved to a new file.";
// ---------------------------

$headers = [
    'diff' => 'diff --git a/old.txt b/new.txt',
    'similarity_index' => 'similarity index 100%',
    'rename_from' => 'rename from old.txt',
    'rename_to' => 'rename to new.txt',
];

$result = Diff::build()->compare($content, $content);
$file = new DiffFile('old.txt', 'new.txt', $result, $headers);

$bundle = new DiffBundle([$file]);
$emitter = new UnifiedEmitter();
echo $emitter->emit($bundle);
```