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

namespace Alto\Code\Diff\Tests\Engine;

use Alto\Code\Diff\Engine\WordTokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordTokenizer::class)]
final class WordTokenizerTest extends TestCase
{
    public function testTokenizeSimpleWords(): void
    {
        $tokenizer = new WordTokenizer();
        $result = $tokenizer->tokenize('hello world test');

        $this->assertSame(['hello', ' ', 'world', ' ', 'test'], $result);
    }

    public function testTokenizeEmptyString(): void
    {
        $tokenizer = new WordTokenizer();
        $this->assertSame([], $tokenizer->tokenize(''));
    }
}
