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

/**
 * Renders diffs as JSON.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class JsonRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $prettyPrint = false,
    ) {
    }

    /**
     * Render a diff result or bundle.
     */
    public function render(DiffResult|DiffBundle $diff): string
    {
        $data = $diff instanceof DiffBundle
            ? $this->serializeBundle($diff)
            : $this->serializeResult($diff);

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBundle(DiffBundle $bundle): array
    {
        return [
            'type' => 'bundle',
            'files' => array_map(
                fn ($file) => [
                    'oldPath' => $file->oldPath,
                    'newPath' => $file->newPath,
                    'headers' => $file->headers,
                    'diff' => $this->serializeResult($file->result),
                ],
                $bundle->files()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(DiffResult $result): array
    {
        return [
            'type' => 'result',
            'oldLabel' => $result->oldLabel,
            'newLabel' => $result->newLabel,
            'oldHasTrailingNewline' => $result->oldHasTrailingNewline,
            'newHasTrailingNewline' => $result->newHasTrailingNewline,
            'hunks' => array_map(
                fn ($hunk) => [
                    'oldStart' => $hunk->oldStart,
                    'oldLen' => $hunk->oldLen,
                    'newStart' => $hunk->newStart,
                    'newLen' => $hunk->newLen,
                    'edits' => array_map(
                        fn ($edit) => [
                            'op' => $edit->op,
                            'text' => $edit->text,
                            'wordSpans' => [] !== $edit->wordSpans
                                ? array_map(
                                    fn ($span) => [
                                        'op' => $span->op,
                                        'text' => $span->text,
                                    ],
                                    $edit->wordSpans
                                )
                                : null,
                        ],
                        $hunk->edits
                    ),
                ],
                $result->hunks()
            ),
        ];
    }
}
