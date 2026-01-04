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
use Alto\Code\Diff\Renderer\AnsiSideBySideRenderer;
use Alto\Code\Diff\Renderer\HtmlRenderer;
use Alto\Code\Diff\Renderer\JsonRenderer;

$old = <<< 'EOF'
function hello() {
    return "world";
}
EOF;

$new = <<< 'EOF'
function hello() {
    return "PHP";
}
EOF;

$result = Diff::build()->compare($old, $new);

echo "=== 1. ANSI Side-by-Side (Terminal) ===\n";
$ansiRenderer = new AnsiSideBySideRenderer(
    showLineNumbers: true,
    width: 60
);
echo $ansiRenderer->render($result)."\n\n";

echo "=== 2. HTML Output ===\n";
$htmlRenderer = new HtmlRenderer(
    showLineNumbers: true,
    wrapLines: true,
    classPrefix: 'code-diff-'
);
echo $htmlRenderer->render($result)."\n\n";

echo "=== 3. JSON Output ===\n";
$jsonRenderer = new JsonRenderer(prettyPrint: true);
echo $jsonRenderer->render($result)."\n";
