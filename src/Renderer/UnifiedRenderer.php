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

namespace Alto\Code\Diff\Renderer;

use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Model\Hunk;

/**
 * Renders diffs in unified format.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class UnifiedRenderer implements RendererInterface
{
    public function __construct(
        private readonly ?string $oldLabel = null,
        private readonly ?string $newLabel = null,
    ) {
    }

    /**
     * Render a diff result or bundle.
     */
    public function render(DiffResult|DiffBundle $diff): string
    {
        if ($diff instanceof DiffBundle) {
            return $this->renderBundle($diff);
        }

        return $this->renderResult($diff, $this->oldLabel, $this->newLabel);
    }

    private function renderBundle(DiffBundle $bundle): string
    {
        $output = [];

        foreach ($bundle->files() as $file) {
            $output[] = $this->renderResult(
                $file->result,
                $file->oldPath,
                $file->newPath
            );
        }

        return implode("\n", $output);
    }

    private function renderResult(DiffResult $result, ?string $oldLabel, ?string $newLabel): string
    {
        $lines = [];

        if (null !== $oldLabel || null !== $newLabel) {
            $lines[] = '--- '.($oldLabel ?? 'a');
            $lines[] = '+++ '.($newLabel ?? 'b');
        }

        if ($result->isEmpty()) {
            return implode("\n", $lines);
        }

        $hunks = $result->hunks();
        $lastHunkIndex = count($hunks) - 1;

        foreach ($hunks as $hunkIndex => $hunk) {
            $lines[] = $this->renderHunkHeader($hunk);

            $edits = $hunk->edits;
            $lastEditIndex = count($edits) - 1;
            $isLastHunk = $hunkIndex === $lastHunkIndex;

            foreach ($edits as $editIndex => $edit) {
                $isLastEdit = $editIndex === $lastEditIndex;
                $prefix = match ($edit->op) {
                    'add' => '+',
                    'del' => '-',
                    'eq' => ' ',
                };

                $lines[] = $prefix.$edit->text;

                if ($isLastHunk && $isLastEdit) {
                    if ('del' === $edit->op && !$result->oldHasTrailingNewline) {
                        $lines[] = '\\ No newline at end of file';
                    } elseif ('add' === $edit->op && !$result->newHasTrailingNewline) {
                        $lines[] = '\\ No newline at end of file';
                    } elseif ('eq' === $edit->op) {
                        if (!$result->oldHasTrailingNewline || !$result->newHasTrailingNewline) {
                            $lines[] = '\\ No newline at end of file';
                        }
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    private function renderHunkHeader(Hunk $hunk): string
    {
        $oldRange = 1 === $hunk->oldLen
            ? (string) $hunk->oldStart
            : $hunk->oldStart.','.$hunk->oldLen;

        $newRange = 1 === $hunk->newLen
            ? (string) $hunk->newStart
            : $hunk->newStart.','.$hunk->newLen;

        return sprintf('@@ -%s +%s @@', $oldRange, $newRange);
    }
}
