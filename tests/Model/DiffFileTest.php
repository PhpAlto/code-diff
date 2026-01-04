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

use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiffFile::class)]
final class DiffFileTest extends TestCase
{
    public function testCreateDiffFile(): void
    {
        $result = new DiffResult([]);
        $file = new DiffFile('old.txt', 'new.txt', $result);

        $this->assertSame('old.txt', $file->oldPath);
        $this->assertSame('new.txt', $file->newPath);
        $this->assertSame($result, $file->result);
        $this->assertSame([], $file->headers);
    }

    public function testCreateDiffFileWithHeaders(): void
    {
        $result = new DiffResult([]);
        $headers = ['key' => 'value'];
        $file = new DiffFile('old.txt', 'new.txt', $result, $headers);

        $this->assertSame($headers, $file->headers);
    }
}
