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
use Alto\Code\Diff\Model\Hunk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hunk::class)]
final class HunkTest extends TestCase
{
    public function testCreateHunk(): void
    {
        $edits = [new Edit('add', 'new line')];
        $hunk = new Hunk(1, 0, 1, 1, $edits);

        $this->assertSame(1, $hunk->oldStart);
        $this->assertSame(0, $hunk->oldLen);
        $this->assertSame(1, $hunk->newStart);
        $this->assertSame(1, $hunk->newLen);
        $this->assertSame($edits, $hunk->edits);
    }
}
