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
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Patch\UnifiedEmitter;
use Alto\Code\Diff\Patch\UnifiedParser;

echo "=== 1. Parse Git Diff Output ===\n";

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

$parser = new UnifiedParser();
$bundle = $parser->parse($gitDiff);

foreach ($bundle->files() as $file) {
    echo "Changed: {$file->oldPath}\n";
    if (isset($file->headers['index'])) {
        echo "  Index: {$file->headers['index']}\n";
    }
    echo '  Hunks: '.count($file->result->hunks())."\n";
}

echo "\n=== 2. Generate Git-Compatible Patch ===\n";

$old = 'function test() { return false; }';
$new = 'function test() { return true; }';

$result = Diff::build()->compare($old, $new);

$headers = [
    'diff' => 'diff --git a/file.txt b/file.txt',
    'index' => 'index abc123..def456 100644',
];

$file = new DiffFile('file.txt', 'file.txt', $result, $headers);
$bundle = new DiffBundle([$file]);

$emitter = new UnifiedEmitter();
echo $emitter->emit($bundle)."\n";
