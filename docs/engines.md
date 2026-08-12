# Engines

The default `MyersDiffEngine` computes a minimal edit script in `O(ND)` time and is suitable for general use.

`LcsDiffEngine` uses the classic longest common subsequence algorithm. Its `O(MN)` time and memory cost makes it appropriate only for small, controlled inputs.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Engine\LcsDiffEngine;

$result = Diff::build()
    ->withEngine(new LcsDiffEngine())
    ->compare("A\nB\n", "A\nC\n");

echo count($result->hunks())." changed region(s)\n";
```

Both built-in engines enforce the configured size limit, reject binary input, honor whitespace and context options, and can compute word-level spans.

## Custom engine

An engine implements one method:

`DiffEngineInterface::diff(string $old, string $new, Options $opts): DiffResult`

Pass the implementation to `Diff::withEngine()`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Engine\DiffEngineInterface;
use Alto\Code\Diff\Engine\MyersDiffEngine;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Options\Options;

final class AuditedEngine implements DiffEngineInterface
{
    public function diff(string $old, string $new, Options $opts): DiffResult
    {
        error_log(sprintf('Comparing %d and %d bytes', strlen($old), strlen($new)));

        return (new MyersDiffEngine())->diff($old, $new, $opts);
    }
}

$result = Diff::build()
    ->withEngine(new AuditedEngine())
    ->compare("old\n", "new\n");

echo count($result->hunks())." changed region(s)\n";
```

## Custom word tokenizer

`MyersDiffEngine` and `LcsDiffEngine` accept a `TokenizerInterface` in their constructors. Its `tokenize(string $input): array` method must return a list of tokens. Pass `null` to disable word spans even when `withWordDiff()` is enabled.

The built-in `WordTokenizer` keeps whitespace as tokens, allowing renderers to reconstruct the original line exactly.
