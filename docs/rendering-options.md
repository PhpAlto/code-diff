# Rendering Options

Examples for producing diff output suitable for web, terminal, and APIs.

## HTML for Web Display

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\HtmlRenderer;

// --- Define Content ---
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
// --------------------

$result = Diff::build()->compare($old, $new);

$renderer = new HtmlRenderer(
    showLineNumbers: true,
    wrapLines: true,
    classPrefix: 'code-diff-'
);

// Generate the HTML table
$diffHtml = $renderer->render($result);

// Embed in a full page
$fullPage = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        .code-diff-table { border-collapse: collapse; font-family: monospace; width: 100%; }
        .code-diff-add { background: #e6ffed; }
        .code-diff-del { background: #ffeef0; }
        .code-diff-line-num { color: #666; padding: 0 10px; border-right: 1px solid #ddd; user-select: none; }
        .code-diff-content { padding-left: 10px; }
    </style>
</head>
<body>
    <h2>Diff Result</h2>
    {$diffHtml}
</body>
</html>
HTML;

echo $fullPage;
```

## Terminal Output with Colors

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\AnsiSideBySideRenderer;

// --- Define Content ---
$old = <<< 'EOF'
Title: Old Version
Status: Draft
EOF;

$new = <<< 'EOF'
Title: New Version
Status: Published
EOF;
// --------------------

$result = Diff::build()->compare($old, $new);

$renderer = new AnsiSideBySideRenderer(
    showLineNumbers: true,
    width: 80
);

// This will display colored side-by-side diff in terminal
echo $renderer->render($result);
```

## JSON for APIs

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\JsonRenderer;

// --- Define Content ---
$old = "Value A";
$new = "Value B";
// --------------------

$result = Diff::build()->compare($old, $new);

$renderer = new JsonRenderer(prettyPrint: true);
$json = $renderer->render($result);

// Send as API response
header('Content-Type: application/json');
echo $json;
```