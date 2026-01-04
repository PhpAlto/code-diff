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

use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

/**
 * Generates unified diff output.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class UnifiedEmitter
{
    public function emit(DiffResult|DiffBundle $diff): string
    {
        if ($diff instanceof DiffBundle) {
            return $this->emitBundle($diff);
        }

        return $this->emitResult($diff);
    }

    private function emitBundle(DiffBundle $bundle): string
    {
        $output = [];

        foreach ($bundle->files() as $file) {
            if (isset($file->headers['diff'])) {
                $output[] = $file->headers['diff'];
            } else {
                $output[] = sprintf('diff --git a/%s b/%s', $file->oldPath, $file->newPath);
            }

            foreach (['index', 'old_mode', 'new_mode', 'new_file_mode', 'deleted_file_mode', 'similarity_index', 'rename_from', 'rename_to', 'copy_from', 'copy_to'] as $header) {
                if (isset($file->headers[$header])) {
                    $output[] = $file->headers[$header];
                }
            }

            $renderer = new UnifiedRenderer('a/'.$file->oldPath, 'b/'.$file->newPath);
            $output[] = $renderer->render($file->result);
        }

        return implode("\n", $output);
    }

    private function emitResult(DiffResult $result): string
    {
        $renderer = new UnifiedRenderer(
            $result->oldLabel ?? 'a',
            $result->newLabel ?? 'b'
        );

        return $renderer->render($result);
    }
}
