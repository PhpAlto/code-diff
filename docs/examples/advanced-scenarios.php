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
use Alto\Code\Diff\Exception\BinaryInputException;
use Alto\Code\Diff\Exception\SizeLimitException;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

echo "=== 1. Compare with Size Limits ===\n";

$largeFile1 = str_repeat('A', 200000); // 200KB
$largeFile2 = str_repeat('B', 200000);

// Set limit to 100KB (100000 bytes)
$diff = Diff::build()->maxBytes(100000);

try {
    $result = $diff->compare($largeFile1, $largeFile2);
} catch (SizeLimitException $e) {
    echo "Caught expected exception: {$e->getMessage()}\n";
}

echo "\n=== 2. Binary File Detection ===\n";

$file1 = 'Text content';
$file2 = "Binary \0 content";

try {
    $result = Diff::build()->compare($file1, $file2);
} catch (BinaryInputException $e) {
    echo "Caught expected exception: {$e->getMessage()}\n";
}

echo "\n=== 3. Custom Context Lines ===\n";

$old = implode("\n", range(1, 10));
$new = implode("\n", array_merge(range(1, 4), ['5 modified'], range(6, 10)));

$result = Diff::build()
    ->contextLines(1)
    ->compare($old, $new);

echo (new UnifiedRenderer())->render($result)."\n";

echo "\n=== 4. No Trailing Newline ===\n";

$old = "line1\nline2";
$new = "line1\nline2\n";

$result = Diff::build()->compare($old, $new);

echo 'Old has trailing newline: '.($result->oldHasTrailingNewline ? 'yes' : 'no')."\n";
echo 'New has trailing newline: '.($result->newHasTrailingNewline ? 'yes' : 'no')."\n";
echo (new UnifiedRenderer())->render($result)."\n";
