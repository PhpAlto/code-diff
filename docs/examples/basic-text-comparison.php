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
use Alto\Code\Diff\Renderer\UnifiedRenderer;

echo "=== 1. Simple Line Diff ===\n";

$old = <<< 'EOF'
Hello
World
EOF;

$new = <<< 'EOF'
Hello
PHP
World
EOF;

$result = Diff::build()->compare($old, $new);

$renderer = new UnifiedRenderer();
echo $renderer->render($result)."\n";

echo "\n=== 2. Word-Level Changes ===\n";

$old = 'The quick brown fox';
$new = 'The fast brown fox';

$result = Diff::build()
    ->withWordDiff()
    ->compare($old, $new);

foreach ($result->hunks() as $hunk) {
    foreach ($hunk->edits as $edit) {
        if ($edit->wordSpans) {
            foreach ($edit->wordSpans as $span) {
                echo "{$span->op}: {$span->text}\n";
            }
        }
    }
}

echo "\n=== 3. Ignore Whitespace ===\n";

$old = <<< 'EOF'
line1
  line2
EOF;

$new = <<< 'EOF'
line1
line2
EOF;

$result = Diff::build()
    ->ignoreWhitespace()
    ->compare($old, $new);

echo 'Is empty? '.($result->isEmpty() ? 'Yes' : 'No')."\n";
