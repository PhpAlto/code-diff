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
use Alto\Code\Diff\Renderer\UnifiedRenderer;

echo "=== Compare Two Files (using fixtures) ===\n";

$oldPath = __DIR__.'/fixtures/file1.txt';
$newPath = __DIR__.'/fixtures/file2.txt';

$oldContent = file_get_contents($oldPath);
$newContent = file_get_contents($newPath);

$result = Diff::build()
    ->contextLines(3)
    ->compare($oldContent, $newContent);

$renderer = new UnifiedRenderer('fixtures/file1.txt', 'fixtures/file2.txt');
echo $renderer->render($result)."\n";

echo "\n=== Compare Multiple Files (Memory Bundle) ===\n";

// Define content for virtual files
$file1Old = "A\nB\nC";
$file1New = "A\nX\nC";

$file2Old = 'Foo';
$file2New = 'Bar';

$diffFiles = [];

// Compare file1
$result1 = Diff::build()->compare($file1Old, $file1New);
$diffFiles[] = new DiffFile('src/File1.php', 'src/File1.php', $result1);

// Compare file2
$result2 = Diff::build()->compare($file2Old, $file2New);
$diffFiles[] = new DiffFile('src/File2.php', 'src/File2.php', $result2);

$bundle = new DiffBundle($diffFiles);
$renderer = new UnifiedRenderer();
echo $renderer->render($bundle)."\n";
