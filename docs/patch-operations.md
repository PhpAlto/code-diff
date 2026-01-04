# Patch Operations

Create, emit, and apply unified patches using Alto Code Diff.

## Create and Apply Patch

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Patch\UnifiedEmitter;
use Alto\Code\Diff\Patch\PatchApplier;

// --- Define Content ---
$originalContent = <<< 'EOF'
Line 1
Line 2
Line 3
EOF;

$modifiedContent = <<< 'EOF'
Line 1
Line 2 modified
Line 3
EOF;
// --------------------

// 1. Create a patch from differences
$result = Diff::build()->compare($originalContent, $modifiedContent);
$emitter = new UnifiedEmitter();
$patch = $emitter->emit($result);

echo "Generated Patch:\n" . $patch . "\n";

// 2. Apply the patch back to the original
$applier = new PatchApplier();
$patched = $applier->apply($originalContent, $patch);

echo "Patched Content:\n" . $patched;
```

## Apply Patch with Error Handling

```php
use Alto\Code\Diff\Patch\PatchApplier;
use Alto\Code\Diff\Exception\PatchApplyException;

// --- Define Content & Patch ---
$original = "Line 1\nLine 2\nLine 3\n";

$patch = <<< 'PATCH'
@@ -1,3 +1,3 @@
 Line 1
-Line 2
+Line 2 modified
 Line 3
PATCH;
// ------------------------------

$applier = new PatchApplier(fuzz: 2);

try {
    $patched = $applier->apply($original, $patch);
    echo "Patch applied successfully:\n$patched";
} catch (PatchApplyException $e) {
    echo "Failed to apply patch: {$e->getMessage()}\n";
    echo "You may need to resolve conflicts manually\n";
}
```

## Apply Patch to Multiple Files

```php
use Alto\Code\Diff\Patch\UnifiedParser;
use Alto\Code\Diff\Patch\PatchApplier;

// --- Simulated File System ---
$files = [
    'src/File1.php' => "<?php\nreturn 'Original A';\n",
    'src/File2.php' => "<?php\nreturn 'Original B';\n",
];

// --- The Multi-File Patch ---
$patchContent = <<< 'PATCH'
diff --git a/src/File1.php b/src/File1.php
--- a/src/File1.php
+++ b/src/File1.php
@@ -1,2 +1,2 @@
 <?php
-return 'Original A';
+return 'Modified A';
diff --git a/src/File2.php b/src/File2.php
--- a/src/File2.php
+++ b/src/File2.php
@@ -1,2 +1,2 @@
 <?php
-return 'Original B';
+return 'Modified B';
PATCH;
// -----------------------------

// Parse and apply patch
$parser = new UnifiedParser();
$bundle = $parser->parse($patchContent);

$applier = new PatchApplier();
$patchedFiles = $applier->applyBundle($files, $bundle);

// Output results
foreach ($patchedFiles as $filename => $content) {
    echo "File: $filename\n";
    echo $content . "\n";
}
```