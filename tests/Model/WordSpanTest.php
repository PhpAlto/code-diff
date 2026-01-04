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

namespace Alto\Code\Diff\Tests\Model;

use Alto\Code\Diff\Model\WordSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordSpan::class)]
final class WordSpanTest extends TestCase
{
    public function testCreateWordSpan(): void
    {
        $span = new WordSpan('add', 'word');

        $this->assertSame('add', $span->op);
        $this->assertSame('word', $span->text);
    }
}
