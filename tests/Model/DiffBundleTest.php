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

use Alto\Code\Diff\Model\DiffBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiffBundle::class)]
final class DiffBundleTest extends TestCase
{
    public function testCreateDiffBundle(): void
    {
        $bundle = new DiffBundle([]);

        $this->assertSame([], $bundle->files());
    }
}
