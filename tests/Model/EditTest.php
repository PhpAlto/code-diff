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

use Alto\Code\Diff\Model\Edit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Edit::class)]
final class EditTest extends TestCase
{
    public function testCreateEdit(): void
    {
        $edit = new Edit('add', 'new line');

        $this->assertSame('add', $edit->op);
        $this->assertSame('new line', $edit->text);
        $this->assertSame([], $edit->wordSpans);
    }

    public function testCreateEditWithWordSpans(): void
    {
        $wordSpans = [];
        $edit = new Edit('add', 'new line', $wordSpans);

        $this->assertSame($wordSpans, $edit->wordSpans);
    }
}
