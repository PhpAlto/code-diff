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

namespace Alto\Code\Diff\Tests\Exception;

use Alto\Code\Diff\Exception\PatchApplyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchApplyException::class)]
final class PatchApplyExceptionTest extends TestCase
{
    public function testExceptionProperties(): void
    {
        $hunkIndex = 5;
        $message = 'Failed to apply hunk';

        $exception = new PatchApplyException($hunkIndex, $message);

        $this->assertSame($hunkIndex, $exception->hunkIndex);
        $this->assertSame($message, $exception->getMessage());
    }
}
