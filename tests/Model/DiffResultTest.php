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

use Alto\Code\Diff\Model\DiffResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiffResult::class)]
final class DiffResultTest extends TestCase
{
    public function testIsEmptyWithNoHunks(): void
    {
        $result = new DiffResult([]);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], $result->hunks());
    }

    public function testProperties(): void
    {
        $result = new DiffResult(
            [],
            'old',
            'new',
            false,
            false
        );

        $this->assertSame('old', $result->oldLabel);
        $this->assertSame('new', $result->newLabel);
        $this->assertFalse($result->oldHasTrailingNewline);
        $this->assertFalse($result->newHasTrailingNewline);
    }
}
