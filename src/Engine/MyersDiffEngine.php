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

namespace Alto\Code\Diff\Engine;

use Alto\Code\Diff\Exception\SizeLimitException;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;
use Alto\Code\Diff\Model\WordSpan;
use Alto\Code\Diff\Options\Options;
use Alto\Code\Diff\Util\BinaryGuard;
use Alto\Code\Diff\Util\NewlineNormalizer;

/**
 * Implements the Myers diff algorithm.
 *
 * This engine uses the O(ND) difference algorithm by Eugene W. Myers.
 * It is generally faster than the standard LCS algorithm for files with
 * few differences, and produces minimal diffs.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class MyersDiffEngine implements DiffEngineInterface
{
    public function __construct(
        private readonly ?TokenizerInterface $wordTokenizer = new WordTokenizer(),
    ) {
    }

    public function diff(string $old, string $new, Options $opts): DiffResult
    {
        if (strlen($old) > $opts->maxBytes || strlen($new) > $opts->maxBytes) {
            throw new SizeLimitException(sprintf('Input exceeds maximum size of %d bytes', $opts->maxBytes));
        }

        BinaryGuard::assertText($old);
        BinaryGuard::assertText($new);

        if ($old === $new) {
            $hasTrailingNewline = '' !== $old && str_ends_with($old, "\n");

            return new DiffResult(
                [],
                oldHasTrailingNewline: $hasTrailingNewline,
                newHasTrailingNewline: $hasTrailingNewline,
            );
        }

        [$oldLines, $oldHasTrailingNewline] = NewlineNormalizer::splitLines($old);
        [$newLines, $newHasTrailingNewline] = NewlineNormalizer::splitLines($new);

        $oldCompare = $oldLines;
        $newCompare = $newLines;

        if ($opts->ignoreWhitespace) {
            $oldCompare = [];
            foreach ($oldLines as $line) {
                $oldCompare[] = (string) preg_replace('/\s+/', ' ', trim($line));
            }

            $newCompare = [];
            foreach ($newLines as $line) {
                $newCompare[] = (string) preg_replace('/\s+/', ' ', trim($line));
            }
        }

        $editScript = $this->computeEditScript($oldCompare, $newCompare);

        $hunks = $this->buildHunks(
            $oldLines,
            $newLines,
            $editScript,
            $opts->contextLines,
            $opts->wordDiff ? $this->wordTokenizer : null
        );

        return new DiffResult(
            $hunks,
            oldHasTrailingNewline: $oldHasTrailingNewline,
            newHasTrailingNewline: $newHasTrailingNewline,
        );
    }

    /**
     * Myers shortest edit script with backtracking, emitting eq/del/add with indices.
     *
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<array{op: 'eq'|'del'|'add', oldIdx?: int, newIdx?: int}>
     */
    private function computeEditScript(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if (0 === $n && 0 === $m) {
            return [];
        }

        if (0 === $n) {
            $out = [];
            for ($j = 0; $j < $m; ++$j) {
                $out[] = ['op' => 'add', 'newIdx' => $j];
            }

            return $out;
        }

        if (0 === $m) {
            $out = [];
            for ($i = 0; $i < $n; ++$i) {
                $out[] = ['op' => 'del', 'oldIdx' => $i];
            }

            return $out;
        }

        $max = $n + $m;

        $trace = [];
        $v = [0 => 0];

        $dMax = 0;

        for ($d = 0; $d <= $max; ++$d) {
            $vNext = [];

            for ($k = -$d; $k <= $d; $k += 2) {
                $fromInsert = $k === -$d || ($k !== $d && (($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1)));

                $x = $fromInsert
                    ? ($v[$k + 1] ?? 0)
                    : (($v[$k - 1] ?? 0) + 1);

                $y = $x - $k;

                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    ++$x;
                    ++$y;
                }

                $vNext[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    $v = $vNext;
                    $trace[$d] = $v;
                    $dMax = $d;
                    break 2;
                }
            }

            $v = $vNext;
            $trace[$d] = $v;
            $dMax = $d;
        }

        if (0 === $dMax) {
            $out = [];
            for ($i = 0; $i < $n; ++$i) {
                $out[] = ['op' => 'eq', 'oldIdx' => $i, 'newIdx' => $i];
            }

            return $out;
        }

        $rev = [];
        $x = $n;
        $y = $m;

        for ($d = $dMax; $d > 0; --$d) {
            $vPrev = $trace[$d - 1];

            $k = $x - $y;

            $fromInsert = $k === -$d || ($k !== $d && (($vPrev[$k - 1] ?? -1) < ($vPrev[$k + 1] ?? -1)));

            $prevK = $fromInsert ? ($k + 1) : ($k - 1);
            $prevX = $vPrev[$prevK] ?? 0;
            $prevY = $prevX - $prevK;

            while ($x > $prevX && $y > $prevY) {
                --$x;
                --$y;
                $rev[] = ['op' => 'eq', 'oldIdx' => $x, 'newIdx' => $y];
            }

            if ($fromInsert) {
                $rev[] = ['op' => 'add', 'newIdx' => $y - 1];
            } else {
                $rev[] = ['op' => 'del', 'oldIdx' => $x - 1];
            }

            $x = $prevX;
            $y = $prevY;
        }

        while ($x > 0 && $y > 0) {
            --$x;
            --$y;
            $rev[] = ['op' => 'eq', 'oldIdx' => $x, 'newIdx' => $y];
        }

        return array_reverse($rev);
    }

    /**
     * @param list<string>                                                  $oldLines
     * @param list<string>                                                  $newLines
     * @param list<array{op: 'eq'|'del'|'add', oldIdx?: int, newIdx?: int}> $editScript
     *
     * @return list<Hunk>
     */
    private function buildHunks(
        array $oldLines,
        array $newLines,
        array $editScript,
        int $contextLines,
        ?TokenizerInterface $wordTokenizer,
    ): array {
        if ([] === $editScript) {
            return [];
        }

        $hasChanges = false;
        foreach ($editScript as $edit) {
            if ('eq' !== $edit['op']) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return [];
        }

        $changeIndices = [];
        foreach ($editScript as $i => $edit) {
            if ('eq' !== $edit['op']) {
                $changeIndices[] = $i;
            }
        }

        $groups = [];
        $currentGroup = [$changeIndices[0]];

        $changeCount = count($changeIndices);
        for ($i = 1; $i < $changeCount; ++$i) {
            $gap = $changeIndices[$i] - $changeIndices[$i - 1];
            if ($gap <= 2 * $contextLines + 1) {
                $currentGroup[] = $changeIndices[$i];
            } else {
                $groups[] = $currentGroup;
                $currentGroup = [$changeIndices[$i]];
            }
        }
        $groups[] = $currentGroup;

        $hunks = [];
        $scriptCount = count($editScript);

        foreach ($groups as $group) {
            $firstChange = $group[0];
            $lastChange = $group[count($group) - 1];

            $start = max(0, $firstChange - $contextLines);
            $end = min($scriptCount, $lastChange + $contextLines + 1);

            $edits = [];
            $delBuffer = [];
            $addBuffer = [];

            for ($i = $start; $i < $end; ++$i) {
                $scriptEdit = $editScript[$i];

                if ('eq' === $scriptEdit['op']) {
                    $edits = $this->flushBuffers($edits, $delBuffer, $addBuffer, $oldLines, $newLines, $wordTokenizer);
                    $delBuffer = [];
                    $addBuffer = [];
                    assert(isset($scriptEdit['oldIdx']) && isset($scriptEdit['newIdx']));
                    $edits[] = new Edit('eq', $oldLines[$scriptEdit['oldIdx']]);
                } elseif ('del' === $scriptEdit['op']) {
                    assert(isset($scriptEdit['oldIdx']));
                    $delBuffer[] = $scriptEdit['oldIdx'];
                } else {
                    assert(isset($scriptEdit['newIdx']));
                    $addBuffer[] = $scriptEdit['newIdx'];
                }
            }

            $edits = $this->flushBuffers($edits, $delBuffer, $addBuffer, $oldLines, $newLines, $wordTokenizer);

            $oldStart = 0;
            $newStart = 0;

            if (isset($editScript[$start])) {
                $firstEdit = $editScript[$start];

                if ('eq' === $firstEdit['op']) {
                    assert(isset($firstEdit['oldIdx']) && isset($firstEdit['newIdx']));
                    $oldStart = $firstEdit['oldIdx'] + 1;
                    $newStart = $firstEdit['newIdx'] + 1;
                } elseif ('del' === $firstEdit['op']) {
                    assert(isset($firstEdit['oldIdx']));
                    $oldStart = $firstEdit['oldIdx'] + 1;
                    $newStart = $this->findNewStartForDel($editScript, $start) + 1;
                } else {
                    assert(isset($firstEdit['newIdx']));
                    $oldStart = $this->findOldStartForAdd($editScript, $start) + 1;
                    $newStart = $firstEdit['newIdx'] + 1;
                }
            }

            $oldLen = 0;
            $newLen = 0;

            foreach ($edits as $edit) {
                if ('eq' === $edit->op || 'del' === $edit->op) {
                    ++$oldLen;
                }
                if ('eq' === $edit->op || 'add' === $edit->op) {
                    ++$newLen;
                }
            }

            $hunks[] = new Hunk($oldStart, $oldLen, $newStart, $newLen, $edits);
        }

        return $hunks;
    }

    /**
     * @param list<Edit>   $edits
     * @param list<int>    $delBuffer
     * @param list<int>    $addBuffer
     * @param list<string> $oldLines
     * @param list<string> $newLines
     *
     * @return list<Edit>
     */
    private function flushBuffers(
        array $edits,
        array $delBuffer,
        array $addBuffer,
        array $oldLines,
        array $newLines,
        ?TokenizerInterface $wordTokenizer,
    ): array {
        if ([] === $delBuffer && [] === $addBuffer) {
            return $edits;
        }

        if (null !== $wordTokenizer && [] !== $delBuffer && [] !== $addBuffer) {
            foreach ($this->computeWordDiff($delBuffer, $addBuffer, $oldLines, $newLines, $wordTokenizer) as $edit) {
                $edits[] = $edit;
            }

            return $edits;
        }

        foreach ($delBuffer as $idx) {
            $edits[] = new Edit('del', $oldLines[$idx]);
        }

        foreach ($addBuffer as $idx) {
            $edits[] = new Edit('add', $newLines[$idx]);
        }

        return $edits;
    }

    /**
     * @param list<array{op: 'eq'|'del'|'add', oldIdx?: int, newIdx?: int}> $editScript
     */
    private function findNewStartForDel(array $editScript, int $start): int
    {
        for ($i = $start - 1; $i >= 0; --$i) {
            if (isset($editScript[$i]['newIdx'])) {
                return $editScript[$i]['newIdx'] + 1;
            }
        }

        return 0;
    }

    /**
     * @param list<array{op: 'eq'|'del'|'add', oldIdx?: int, newIdx?: int}> $editScript
     */
    private function findOldStartForAdd(array $editScript, int $start): int
    {
        for ($i = $start - 1; $i >= 0; --$i) {
            if (isset($editScript[$i]['oldIdx'])) {
                return $editScript[$i]['oldIdx'] + 1;
            }
        }

        return 0;
    }

    /**
     * @param list<int>    $delIndices
     * @param list<int>    $addIndices
     * @param list<string> $oldLines
     * @param list<string> $newLines
     *
     * @return list<Edit>
     */
    private function computeWordDiff(
        array $delIndices,
        array $addIndices,
        array $oldLines,
        array $newLines,
        TokenizerInterface $wordTokenizer,
    ): array {
        $edits = [];

        $pairCount = min(count($delIndices), count($addIndices));

        for ($i = 0; $i < $pairCount; ++$i) {
            $oldLine = $oldLines[$delIndices[$i]];
            $newLine = $newLines[$addIndices[$i]];

            $oldWords = $wordTokenizer->tokenize($oldLine);
            $newWords = $wordTokenizer->tokenize($newLine);

            $wordScript = $this->computeEditScript($oldWords, $newWords);

            $delSpans = [];
            $addSpans = [];

            foreach ($wordScript as $we) {
                if ('eq' === $we['op']) {
                    assert(isset($we['oldIdx']) && isset($we['newIdx']));
                    $delSpans[] = new WordSpan('eq', $oldWords[$we['oldIdx']]);
                    $addSpans[] = new WordSpan('eq', $newWords[$we['newIdx']]);
                } elseif ('del' === $we['op']) {
                    assert(isset($we['oldIdx']));
                    $delSpans[] = new WordSpan('del', $oldWords[$we['oldIdx']]);
                } else {
                    assert(isset($we['newIdx']));
                    $addSpans[] = new WordSpan('add', $newWords[$we['newIdx']]);
                }
            }

            $edits[] = new Edit('del', $oldLine, $delSpans);
            $edits[] = new Edit('add', $newLine, $addSpans);
        }

        $delCount = count($delIndices);
        for ($i = $pairCount; $i < $delCount; ++$i) {
            $edits[] = new Edit('del', $oldLines[$delIndices[$i]]);
        }

        $addCount = count($addIndices);
        for ($i = $pairCount; $i < $addCount; ++$i) {
            $edits[] = new Edit('add', $newLines[$addIndices[$i]]);
        }

        return $edits;
    }
}
