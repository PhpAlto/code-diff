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
 * Implements the standard Longest Common Subsequence (LCS) diff algorithm.
 *
 * This engine uses a dynamic programming approach to find the LCS.
 * It has O(MN) time and space complexity, where M and N are the lengths
 * of the input sequences. It is robust but may be slower than Myers
 * for large inputs with few changes.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class LcsDiffEngine implements DiffEngineInterface
{
    public function __construct(
        private readonly ?TokenizerInterface $wordTokenizer = new WordTokenizer(),
    ) {
    }

    /**
     * Compute the difference between two strings.
     */
    public function diff(string $old, string $new, Options $opts): DiffResult
    {
        if (strlen($old) > $opts->maxBytes || strlen($new) > $opts->maxBytes) {
            throw new SizeLimitException(sprintf('Input exceeds maximum size of %d bytes', $opts->maxBytes));
        }

        BinaryGuard::assertText($old);
        BinaryGuard::assertText($new);

        [$oldLines, $oldHasTrailingNewline] = NewlineNormalizer::splitLines($old);
        [$newLines, $newHasTrailingNewline] = NewlineNormalizer::splitLines($new);

        $oldCompare = $oldLines;
        $newCompare = $newLines;

        if ($opts->ignoreWhitespace) {
            $oldCompare = array_map(fn (string $line): string => (string) preg_replace('/\s+/', ' ', trim($line)), $oldLines);
            $newCompare = array_map(fn (string $line): string => (string) preg_replace('/\s+/', ' ', trim($line)), $newLines);
        }

        $editScript = $this->computeLcs($oldCompare, $newCompare);

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
     * Compute edit script using LCS algorithm.
     *
     * @param list<string> $a Old lines
     * @param list<string> $b New lines
     *
     * @return list<array{op: 'eq'|'del'|'add', oldIdx?: int, newIdx?: int}>
     */
    private function computeLcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if (0 === $n && 0 === $m) {
            return [];
        }

        if (0 === $n) {
            $result = [];
            for ($j = 0; $j < $m; ++$j) {
                $result[] = ['op' => 'add', 'newIdx' => $j];
            }

            return $result;
        }

        if (0 === $m) {
            $result = [];
            for ($i = 0; $i < $n; ++$i) {
                $result[] = ['op' => 'del', 'oldIdx' => $i];
            }

            return $result;
        }

        // Build LCS table
        $dp = [];
        for ($i = 0; $i <= $n; ++$i) {
            $dp[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 1; $i <= $n; ++$i) {
            for ($j = 1; $j <= $m; ++$j) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // Backtrack to build edit script
        $script = [];
        $i = $n;
        $j = $m;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                array_unshift($script, ['op' => 'eq', 'oldIdx' => $i - 1, 'newIdx' => $j - 1]);
                --$i;
                --$j;
            } elseif (0 === $i || ($j > 0 && $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                array_unshift($script, ['op' => 'add', 'newIdx' => $j - 1]);
                --$j;
            } else {
                array_unshift($script, ['op' => 'del', 'oldIdx' => $i - 1]);
                --$i;
            }
        }

        return $script;
    }

    /**
     * Build hunks from edit script with context lines.
     *
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

        $count = count($changeIndices);
        for ($i = 1; $i < $count; ++$i) {
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
        foreach ($groups as $group) {
            $firstChange = $group[0];
            $lastChange = $group[count($group) - 1];

            $start = max(0, $firstChange - $contextLines);
            $end = min(count($editScript), $lastChange + $contextLines + 1);

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
            $oldLen = 0;
            $newLen = 0;

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
     * Flush buffered del/add edits.
     *
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
            $wordEdits = $this->computeWordDiff(
                $delBuffer,
                $addBuffer,
                $oldLines,
                $newLines,
                $wordTokenizer
            );
            foreach ($wordEdits as $edit) {
                $edits[] = $edit;
            }
        } else {
            foreach ($delBuffer as $idx) {
                $edits[] = new Edit('del', $oldLines[$idx]);
            }
            foreach ($addBuffer as $idx) {
                $edits[] = new Edit('add', $newLines[$idx]);
            }
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
     * Compute word-level diff for paired del/add lines.
     *
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

            $wordScript = $this->computeLcs($oldWords, $newWords);

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
