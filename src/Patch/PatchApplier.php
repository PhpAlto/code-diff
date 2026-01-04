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

use Alto\Code\Diff\Exception\PatchApplyException;
use Alto\Code\Diff\Exception\SizeLimitException;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Hunk;
use Alto\Code\Diff\Util\NewlineNormalizer;

/**
 * Applies patches to text.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class PatchApplier
{
    public function __construct(
        private readonly int $fuzz = 0,
        private readonly int $maxBytes = 5_000_000,
    ) {
    }

    /**
     * Apply a unified patch to original content.
     *
     * @return string The patched content
     *
     * @throws PatchApplyException
     * @throws SizeLimitException
     */
    public function apply(string $original, string $unifiedPatch): string
    {
        $this->assertWithinLimit($original);

        $parser = new UnifiedParser();
        $bundle = $parser->parse($unifiedPatch);

        $files = $bundle->files();
        if ([] === $files) {
            return $original;
        }

        if (1 !== count($files)) {
            throw new PatchApplyException(0, 'Patch contains multiple files; use applyBundle() for multi-file patches.');
        }

        return $this->applyDiffResult($original, $files[0]->result, true);
    }

    /**
     * Apply patch to multiple files.
     *
     * @param array<string, string> $files Map of path => content
     *
     * @return array<string, string> Map of path => patched content
     *
     * @throws PatchApplyException
     */
    public function applyBundle(array $files, DiffBundle $bundle): array
    {
        $result = $files;

        foreach ($bundle->files() as $diffFile) {
            $oldPath = $diffFile->oldPath;
            $newPath = $diffFile->newPath;

            $originalContent = $result[$oldPath] ?? null;
            if (null === $originalContent) {
                if ('/dev/null' === $oldPath) {
                    $originalContent = '';
                } elseif ($diffFile->result->isEmpty()) {
                    continue;
                } else {
                    throw new PatchApplyException(0, sprintf('File not found: %s', $oldPath));
                }
            }

            $patchedContent = $this->applyDiffResult($originalContent, $diffFile->result);

            $this->updateBundleResult($result, $oldPath, $newPath, $patchedContent);
        }

        return $result;
    }

    private function applyDiffResult(string $original, DiffResult $result, bool $skipSizeCheck = false): string
    {
        if (!$skipSizeCheck) {
            $this->assertWithinLimit($original);
        }

        [$lines] = NewlineNormalizer::splitLines($original);
        $offset = 0;

        foreach ($result->hunks() as $hunkIndex => $hunk) {
            $start = $hunk->oldStart > 0 ? $hunk->oldStart - 1 : 0;
            $targetLine = $start + $offset;

            $matchOffset = $this->findMatchingPosition($lines, $hunk, $targetLine);

            if (null === $matchOffset) {
                throw new PatchApplyException($hunkIndex, sprintf('Hunk #%d failed to apply at line %d', $hunkIndex + 1, $hunk->oldStart));
            }

            $actualLine = $targetLine + $matchOffset;

            [$oldLines, $newLines] = $this->prepareHunkLines($hunk);

            $linesToRemove = count($oldLines);

            array_splice($lines, $actualLine, $linesToRemove, $newLines);

            $offset += count($newLines) - $linesToRemove;
        }

        if ([] === $lines) {
            return '';
        }

        $output = implode("\n", $lines);
        if ($result->newHasTrailingNewline) {
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Extract old and new line arrays from a hunk.
     *
     * Only 'eq', 'del', and 'add' operations are allowed.
     *
     * @return array{list<string>, list<string>} [oldLines, newLines]
     */
    private function prepareHunkLines(Hunk $hunk): array
    {
        $oldLines = [];
        $newLines = [];

        foreach ($hunk->edits as $edit) {
            match ($edit->op) {
                'eq' => (function () use ($edit, &$oldLines, &$newLines) {
                    $oldLines[] = $edit->text;
                    $newLines[] = $edit->text;
                })(),
                'del' => $oldLines[] = $edit->text,
                'add' => $newLines[] = $edit->text,
            };
        }

        return [$oldLines, $newLines];
    }

    /**
     * Update the bundle result with patched content.
     *
     * Handles file creation, modification, deletion, and renaming.
     *
     * @param array<string, string> $result
     */
    private function updateBundleResult(array &$result, string $oldPath, string $newPath, string $patchedContent): void
    {
        $isCreate = '/dev/null' === $oldPath;
        $isDelete = '/dev/null' === $newPath;
        $isRename = $oldPath !== $newPath;

        if (!$isDelete) {
            $result[$newPath] = $patchedContent;
        }

        if (!$isCreate && ($isDelete || $isRename) && isset($result[$oldPath])) {
            unset($result[$oldPath]);
        }
    }

    private function assertWithinLimit(string $content): void
    {
        if (strlen($content) > $this->maxBytes) {
            throw new SizeLimitException(sprintf('Original content exceeds maximum size of %d bytes', $this->maxBytes));
        }
    }

    /**
     * Find the position where the hunk context matches.
     *
     * @param list<string> $lines
     *
     * @return int|null Offset from target line, or null if no match
     */
    private function findMatchingPosition(array $lines, Hunk $hunk, int $targetLine): ?int
    {
        $contextLines = [];
        foreach ($hunk->edits as $edit) {
            if ('eq' === $edit->op || 'del' === $edit->op) {
                $contextLines[] = $edit->text;
            }
        }

        if ($this->matchesAt($lines, $contextLines, $targetLine)) {
            return 0;
        }

        for ($offset = 1; $offset <= $this->fuzz; ++$offset) {
            if ($targetLine - $offset >= 0 && $this->matchesAt($lines, $contextLines, $targetLine - $offset)) {
                return -$offset;
            }

            if ($this->matchesAt($lines, $contextLines, $targetLine + $offset)) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @param list<string> $contextLines
     */
    private function matchesAt(array $lines, array $contextLines, int $position): bool
    {
        if ($position < 0) {
            return false;
        }

        $contextCount = count($contextLines);

        if ($position + $contextCount > count($lines)) {
            return false;
        }

        for ($i = 0; $i < $contextCount; ++$i) {
            if ($lines[$position + $i] !== $contextLines[$i]) {
                return false;
            }
        }

        return true;
    }
}
