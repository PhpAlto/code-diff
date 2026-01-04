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

require __DIR__.'/../../vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Exception\PatchApplyException;
use Alto\Code\Diff\Patch\PatchApplier;
use Alto\Code\Diff\Patch\UnifiedEmitter;
use Alto\Code\Diff\Patch\UnifiedParser;

echo "=== 1. Create and Apply Patch ===\n";

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

// Create patch
$result = Diff::build()->compare($originalContent, $modifiedContent);
$emitter = new UnifiedEmitter();
$patch = $emitter->emit($result);

echo "Generated Patch:\n$patch\n";

// Apply patch
$applier = new PatchApplier();
$patched = $applier->apply($originalContent, $patch);

echo "Patched Content:\n$patched\n";

echo "\n=== 2. Apply Patch with Fuzz Factor ===\n";

$original = "Line 1\nLine 2\nLine 3\n";
// Patch expects "Line 2" to be removed, but we'll try to apply it to a slightly different context if needed.
// Here we use a standard patch.

$patch = <<< 'PATCH'
@@ -1,3 +1,3 @@
 Line 1
-Line 2
+Line 2 modified
 Line 3
PATCH;

$applier = new PatchApplier(fuzz: 2);

try {
    $patched = $applier->apply($original, $patch);
    echo "Patch applied successfully.\n";
} catch (PatchApplyException $e) {
    echo "Failed to apply patch: {$e->getMessage()}\n";
}

echo "\n=== 3. Apply Patch Bundle (Multi-file) ===\n";

$files = [
    'src/File1.php' => "<?php\nreturn 'Original A';\n",
    'src/File2.php' => "<?php\nreturn 'Original B';\n",
];

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

$parser = new UnifiedParser();
$bundle = $parser->parse($patchContent);

$applier = new PatchApplier();
$patchedFiles = $applier->applyBundle($files, $bundle);

foreach ($patchedFiles as $filename => $content) {
    echo "File: $filename -> ".trim($content)."\n";
}
