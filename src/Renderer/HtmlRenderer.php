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
use Alto\Code\Diff\Model\Edit;
use Alto\Code\Diff\Model\Hunk;

/**
 * Renders diffs as HTML.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class HtmlRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $showLineNumbers = true,
        private readonly bool $wrapLines = false,
        private readonly string $classPrefix = 'diff-',
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

        return $this->renderResult($diff);
    }

    private function renderBundle(DiffBundle $bundle): string
    {
        $html = [];

        foreach ($bundle->files() as $file) {
            $html[] = sprintf(
                '<div class="%sfile">',
                $this->e($this->classPrefix)
            );
            $html[] = sprintf(
                '<div class="%sfile-header">%s &rarr; %s</div>',
                $this->e($this->classPrefix),
                $this->e($file->oldPath),
                $this->e($file->newPath)
            );
            $html[] = $this->renderResult($file->result);
            $html[] = '</div>';
        }

        return implode("\n", $html);
    }

    private function renderResult(DiffResult $result): string
    {
        if ($result->isEmpty()) {
            return '';
        }

        $html = [];
        $html[] = sprintf('<table class="%stable">', $this->e($this->classPrefix));

        $oldLine = 0;
        $newLine = 0;

        foreach ($result->hunks() as $hunk) {
            $html[] = $this->renderHunkHeader($hunk);
            $oldLine = $hunk->oldStart;
            $newLine = $hunk->newStart;

            foreach ($hunk->edits as $edit) {
                $html[] = $this->renderEdit($edit, $oldLine, $newLine);

                if ('eq' === $edit->op) {
                    ++$oldLine;
                    ++$newLine;
                } elseif ('del' === $edit->op) {
                    ++$oldLine;
                } else {
                    ++$newLine;
                }
            }
        }

        $html[] = '</table>';

        return implode("\n", $html);
    }

    private function renderHunkHeader(Hunk $hunk): string
    {
        $headerText = sprintf(
            '@@ -%d,%d +%d,%d @@',
            $hunk->oldStart,
            $hunk->oldLen,
            $hunk->newStart,
            $hunk->newLen
        );

        $colspan = $this->showLineNumbers ? '4' : '2';

        return sprintf(
            '<tr class="%shunk-header"><td colspan="%s">%s</td></tr>',
            $this->e($this->classPrefix),
            $colspan,
            $this->e($headerText)
        );
    }

    private function renderEdit(Edit $edit, int $oldLine, int $newLine): string
    {
        $rowClass = match ($edit->op) {
            'add' => 'add',
            'del' => 'del',
            'eq' => 'ctx',
        };

        $html = sprintf('<tr class="%s%s">', $this->e($this->classPrefix), $rowClass);

        if ($this->showLineNumbers) {
            $oldNum = 'add' === $edit->op ? '' : (string) $oldLine;
            $newNum = 'del' === $edit->op ? '' : (string) $newLine;

            $html .= sprintf(
                '<td class="%sline-num %sold">%s</td>',
                $this->e($this->classPrefix),
                $this->e($this->classPrefix),
                $oldNum
            );
            $html .= sprintf(
                '<td class="%sline-num %snew">%s</td>',
                $this->e($this->classPrefix),
                $this->e($this->classPrefix),
                $newNum
            );
        }

        $prefix = match ($edit->op) {
            'add' => '+',
            'del' => '-',
            'eq' => ' ',
        };

        $html .= sprintf(
            '<td class="%sprefix">%s</td>',
            $this->e($this->classPrefix),
            $this->e($prefix)
        );

        $content = $this->renderEditContent($edit);
        $wrapClass = $this->wrapLines ? ' '.$this->e($this->classPrefix).'wrap' : '';

        $html .= sprintf(
            '<td class="%scontent%s">%s</td>',
            $this->e($this->classPrefix),
            $wrapClass,
            $content
        );

        $html .= '</tr>';

        return $html;
    }

    private function renderEditContent(Edit $edit): string
    {
        if ([] === $edit->wordSpans) {
            return $this->e($edit->text);
        }

        $html = '';
        foreach ($edit->wordSpans as $span) {
            if ('eq' === $span->op) {
                $html .= $this->e($span->text);
            } else {
                $html .= sprintf(
                    '<span class="%sword-%s">%s</span>',
                    $this->e($this->classPrefix),
                    $span->op,
                    $this->e($span->text)
                );
            }
        }

        return $html;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
