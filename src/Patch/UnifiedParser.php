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

namespace Alto\Code\Diff\Patch;

use Alto\Code\Diff\Exception\BinaryInputException;
use Alto\Code\Diff\Exception\ParseException;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;

/**
 * Parses unified diff output.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class UnifiedParser
{
    private const string STATE_HEADER = 'header';
    private const string STATE_FILE_HEADER = 'file_header';
    private const string STATE_HUNK_HEADER = 'hunk_header';
    private const string STATE_HUNK_BODY = 'hunk_body';

    public function parse(string $patch): DiffBundle
    {
        $lines = explode("\n", $patch);
        $files = [];

        $state = self::STATE_HEADER;
        $currentHeaders = [];
        /** @var string|null $oldPath */
        $oldPath = null;
        /** @var string|null $newPath */
        $newPath = null;
        $hunks = [];
        /** @var list<Edit> $currentEdits */
        $currentEdits = [];
        $expectedOld = 0;
        $expectedNew = 0;
        $oldStart = 0;
        $oldLen = 0;
        $newStart = 0;
        $newLen = 0;
        $seenOld = 0;
        $seenNew = 0;
        $oldHasTrailingNewline = true;
        $newHasTrailingNewline = true;
        $inHunk = false;

        $finishHunk = function () use (&$hunks, &$currentEdits, &$oldStart, &$newStart, &$seenOld, &$seenNew, &$expectedOld, &$expectedNew, &$inHunk) {
            // @phpstan-ignore-next-line
            if (!$inHunk) {
                return;
            }

            // @phpstan-ignore-next-line
            if ($seenOld !== $expectedOld || $seenNew !== $expectedNew) {
                throw new ParseException(sprintf('Hunk header counts do not match body (expected -%d,+%d, got -%d,+%d)', $expectedOld, $expectedNew, $seenOld, $seenNew));
            }

            if ([] !== $currentEdits) {
                $hunks[] = new Hunk($oldStart, $seenOld, $newStart, $seenNew, $currentEdits);
            }

            $currentEdits = [];
            $inHunk = false;
            $expectedOld = 0;
            $expectedNew = 0;
            $seenOld = 0;
            $seenNew = 0;
        };

        $finishFile = function () use (&$files, &$hunks, &$oldPath, &$newPath, &$currentHeaders, &$finishHunk, &$oldHasTrailingNewline, &$newHasTrailingNewline, &$inHunk) {
            $finishHunk();
            if (null !== $oldPath && null !== $newPath) {
                $files[] = new DiffFile(
                    $oldPath,
                    $newPath,
                    new DiffResult(
                        $hunks,
                        oldHasTrailingNewline: $oldHasTrailingNewline,
                        newHasTrailingNewline: $newHasTrailingNewline,
                    ),
                    $currentHeaders
                );
            }
            $hunks = [];
            $currentHeaders = [];
            $oldPath = null;
            $newPath = null;
            $oldHasTrailingNewline = true;
            $newHasTrailingNewline = true;
            $inHunk = false;
        };

        $lineCount = count($lines);
        $i = 0;

        while ($i < $lineCount) {
            $line = $lines[$i];

            if (str_starts_with($line, 'Binary files ')) {
                throw new BinaryInputException('Patch contains binary files');
            }

            if (str_starts_with($line, 'diff --git ')) {
                $finishFile();
                $state = self::STATE_FILE_HEADER;
                $currentHeaders['diff'] = $line;
                ++$i;
                continue;
            }

            if (self::STATE_HEADER === $state || self::STATE_FILE_HEADER === $state) {
                if (str_starts_with($line, 'index ')) {
                    $currentHeaders['index'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'new file mode ')) {
                    $currentHeaders['new_file_mode'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'deleted file mode ')) {
                    $currentHeaders['deleted_file_mode'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'old mode ')) {
                    $currentHeaders['old_mode'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'new mode ')) {
                    $currentHeaders['new_mode'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'similarity index ')) {
                    $currentHeaders['similarity_index'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'rename from ')) {
                    $currentHeaders['rename_from'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'rename to ')) {
                    $currentHeaders['rename_to'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'copy from ')) {
                    $currentHeaders['copy_from'] = $line;
                    ++$i;
                    continue;
                }
                if (str_starts_with($line, 'copy to ')) {
                    $currentHeaders['copy_to'] = $line;
                    ++$i;
                    continue;
                }
            }

            if (str_starts_with($line, '--- ')) {
                $oldPath = $this->extractPath($line, '--- ');
                $state = self::STATE_FILE_HEADER;
                ++$i;
                continue;
            }

            if (str_starts_with($line, '+++ ')) {
                $newPath = $this->extractPath($line, '+++ ');
                $state = self::STATE_HUNK_HEADER;
                ++$i;
                continue;
            }

            if (str_starts_with($line, '@@ ')) {
                $finishHunk();

                $parsed = $this->parseHunkHeader($line);
                if (null === $parsed) {
                    throw new ParseException("Invalid hunk header: $line");
                }

                [$oldStart, $oldLen, $newStart, $newLen] = $parsed;
                $expectedOld = $oldLen;
                $expectedNew = $newLen;
                $seenOld = 0;
                $seenNew = 0;
                $inHunk = true;
                $state = self::STATE_HUNK_BODY;
                ++$i;
                continue;
            }

            if (self::STATE_HUNK_BODY === $state) {
                if ('' === $line && $i === $lineCount - 1) {
                    ++$i;
                    continue;
                }

                if (str_starts_with($line, '\\ ')) {
                    $lastEdit = $currentEdits[count($currentEdits) - 1] ?? null;
                    if (null === $lastEdit) {
                        throw new ParseException('No edit available for newline marker.');
                    }

                    if ('del' === $lastEdit->op) {
                        $oldHasTrailingNewline = false;
                    } elseif ('add' === $lastEdit->op) {
                        $newHasTrailingNewline = false;
                    } else {
                        $oldHasTrailingNewline = false;
                        $newHasTrailingNewline = false;
                    }

                    ++$i;
                    continue;
                }

                $prefix = $line[0] ?? ' ';
                $content = strlen($line) > 1 ? substr($line, 1) : '';

                if (' ' === $prefix) {
                    $currentEdits[] = new Edit('eq', $content);
                    ++$seenOld;
                    ++$seenNew;
                } elseif ('-' === $prefix) {
                    $currentEdits[] = new Edit('del', $content);
                    ++$seenOld;
                } elseif ('+' === $prefix) {
                    $currentEdits[] = new Edit('add', $content);
                    ++$seenNew;
                } else {
                    if ($seenOld >= $expectedOld && $seenNew >= $expectedNew) {
                        $finishHunk();
                        $state = self::STATE_HUNK_HEADER;
                        continue;
                    }
                    $currentEdits[] = new Edit('eq', $line);
                    ++$seenOld;
                    ++$seenNew;
                }

                ++$i;
                continue;
            }

            ++$i;
        }

        $finishFile();

        return new DiffBundle($files);
    }

    private function extractPath(string $line, string $prefix): string
    {
        $path = substr($line, strlen($prefix));

        if (str_starts_with($path, 'a/') || str_starts_with($path, 'b/')) {
            $path = substr($path, 2);
        }

        $tabPos = strpos($path, "\t");
        if (false !== $tabPos) {
            $path = substr($path, 0, $tabPos);
        }

        return $path;
    }

    /**
     * @return array{int, int, int, int}|null
     */
    private function parseHunkHeader(string $line): ?array
    {
        if (!preg_match('/@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $matches)) {
            return null;
        }

        $oldStart = (int) $matches[1];
        $oldLen = '' !== $matches[2] ? (int) $matches[2] : 1;
        $newStart = (int) $matches[3];
        $newLen = isset($matches[4]) ? (int) $matches[4] : 1;

        return [$oldStart, $oldLen, $newStart, $newLen];
    }
}
