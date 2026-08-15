# Rendering

Every renderer implements `RendererInterface::render(DiffResult|DiffBundle): string`. An empty result produces an empty string, except that `UnifiedRenderer` can still emit explicitly configured labels.

## Unified diff

Use `UnifiedRenderer` for familiar line-oriented output. Its optional constructor arguments label the old and new versions.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

$result = Diff::build()->compare("before\n", "after\n");
$output = (new UnifiedRenderer('a/example.txt', 'b/example.txt'))->render($result);

echo $output;
```

The renderer preserves `\ No newline at end of file` markers from the result.

## HTML

`HtmlRenderer` escapes labels and content before producing an HTML table.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\HtmlRenderer;

$result = Diff::build()
    ->withWordDiff()
    ->compare('<p>Old</p>', '<p>New</p>');

$html = (new HtmlRenderer(
    showLineNumbers: true,
    wrapLines: true,
    classPrefix: 'review-',
))->render($result);

echo $html;
```

The prefix is applied to `table`, `hunk-header`, `add`, `del`, `ctx`, `line-num`, `old`, `new`, `prefix`, `content`, `wrap`, `word-add`, and `word-del` classes. Supply the CSS for those classes in your application.

## JSON

`JsonRenderer` serializes results and bundles for APIs or storage. It includes paths, headers, hunk ranges, edits, word spans, labels, and trailing-newline flags.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\JsonRenderer;

$result = Diff::build()->compare("one\n", "two\n");
echo (new JsonRenderer(prettyPrint: true))->render($result);
```

Encoding uses `JSON_THROW_ON_ERROR`, unescaped Unicode, and unescaped slashes.

## ANSI side by side

`AnsiSideBySideRenderer` is intended for terminals. It adds ANSI colors and truncates long columns to fit the configured total width.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\AnsiSideBySideRenderer;

$result = Diff::build()
    ->withWordDiff()
    ->compare("Status: draft\n", "Status: published\n");

echo (new AnsiSideBySideRenderer(
    showLineNumbers: true,
    width: 100,
))->render($result);
```

All four renderers also accept a multi-file `DiffBundle`; see [Patches](patches.md) for its structure.
