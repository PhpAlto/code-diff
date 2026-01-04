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
use Alto\Code\Diff\Model\WordSpan;

/**
 * Renders diffs side-by-side with ANSI colors.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class AnsiSideBySideRenderer implements RendererInterface
{
    private const string RESET = "\033[0m";
    private const string RED = "\033[31m";
    private const string GREEN = "\033[32m";
    private const string CYAN = "\033[36m";
    private const string DIM = "\033[2m";
    private const string RED_BG = "\033[41m";
    private const string GREEN_BG = "\033[42m";

    public function __construct(
        private readonly bool $showLineNumbers = true,
        private readonly int $width = 80,
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
        $output = [];

        foreach ($bundle->files() as $file) {
            $output[] = self::CYAN.str_repeat('=', $this->width).self::RESET;
            $output[] = self::CYAN.$file->oldPath.' -> '.$file->newPath.self::RESET;
            $output[] = self::CYAN.str_repeat('=', $this->width).self::RESET;
            $output[] = $this->renderResult($file->result);
        }

        return implode("\n", $output);
    }

    private function renderResult(DiffResult $result): string
    {
        if ($result->isEmpty()) {
            return '';
        }

        $lines = [];
        $colWidth = $this->showLineNumbers
            ? (int) (($this->width - 13) / 2)
            : (int) (($this->width - 3) / 2);

        $lineNumWidth = 4;

        foreach ($result->hunks() as $hunk) {
            $lines[] = $this->renderHunkHeader($hunk);
            $lines = array_merge($lines, $this->renderHunkEdits($hunk, $colWidth, $lineNumWidth));
        }

        return implode("\n", $lines);
    }

    private function renderHunkHeader(Hunk $hunk): string
    {
        $header = sprintf(
            '@@ -%d,%d +%d,%d @@',
            $hunk->oldStart,
            $hunk->oldLen,
            $hunk->newStart,
            $hunk->newLen
        );

        return self::CYAN.$header.self::RESET;
    }

    /**
     * @return list<string>
     */
    private function renderHunkEdits(Hunk $hunk, int $colWidth, int $lineNumWidth): array
    {
        $lines = [];
        $oldLine = $hunk->oldStart;
        $newLine = $hunk->newStart;

        $edits = $hunk->edits;
        $editsCount = count($edits);
        $i = 0;

        while ($i < $editsCount) {
            $edit = $edits[$i];

            if ('eq' === $edit->op) {
                $lines[] = $this->renderSideBySideLine(
                    $edit->text,
                    $edit->text,
                    ' ',
                    $oldLine,
                    $newLine,
                    $colWidth,
                    $lineNumWidth
                );
                ++$oldLine;
                ++$newLine;
                ++$i;
            } elseif ('del' === $edit->op) {
                $delEdit = $edit;
                $addEdit = null;

                if ($i + 1 < $editsCount && 'add' === $edits[$i + 1]->op) {
                    $addEdit = $edits[$i + 1];
                    ++$i;
                }

                $lines[] = $this->renderSideBySideLine(
                    $delEdit->text,
                    $addEdit?->text,
                    null !== $addEdit ? '|' : '<',
                    $oldLine,
                    null !== $addEdit ? $newLine : null,
                    $colWidth,
                    $lineNumWidth,
                    $delEdit->wordSpans,
                    null !== $addEdit ? $addEdit->wordSpans : []
                );

                ++$oldLine;
                if (null !== $addEdit) {
                    ++$newLine;
                }
                ++$i;
            } else {
                $lines[] = $this->renderSideBySideLine(
                    null,
                    $edit->text,
                    '>',
                    null,
                    $newLine,
                    $colWidth,
                    $lineNumWidth
                );
                ++$newLine;
                ++$i;
            }
        }

        return $lines;
    }

    /**
     * @param list<WordSpan> $oldWordSpans
     * @param list<WordSpan> $newWordSpans
     */
    private function renderSideBySideLine(
        ?string $oldText,
        ?string $newText,
        string $gutter,
        ?int $oldLineNum,
        ?int $newLineNum,
        int $colWidth,
        int $lineNumWidth,
        array $oldWordSpans = [],
        array $newWordSpans = [],
    ): string {
        $line = '';

        if ($this->showLineNumbers) {
            $oldNum = null !== $oldLineNum ? str_pad((string) $oldLineNum, $lineNumWidth, ' ', STR_PAD_LEFT) : str_repeat(' ', $lineNumWidth);
            $line .= self::DIM.$oldNum.self::RESET.' ';
        }

        if (null !== $oldText) {
            $oldDisplay = $this->truncate($oldText, $colWidth);
            if ('<' === $gutter || '|' === $gutter) {
                if ([] !== $oldWordSpans) {
                    $oldDisplay = $this->applyWordHighlights($oldWordSpans, $colWidth, self::RED_BG);
                } else {
                    $oldDisplay = self::RED.$oldDisplay.self::RESET;
                }
            }
            $line .= $this->pad($oldDisplay, $colWidth);
        } else {
            $line .= str_repeat(' ', $colWidth);
        }

        $gutterColor = match ($gutter) {
            '<' => self::RED,
            '>' => self::GREEN,
            '|' => self::CYAN,
            default => self::DIM,
        };
        $line .= ' '.$gutterColor.$gutter.self::RESET.' ';

        if ($this->showLineNumbers) {
            $newNum = null !== $newLineNum ? str_pad((string) $newLineNum, $lineNumWidth, ' ', STR_PAD_LEFT) : str_repeat(' ', $lineNumWidth);
            $line .= self::DIM.$newNum.self::RESET.' ';
        }

        if (null !== $newText) {
            $newDisplay = $this->truncate($newText, $colWidth);
            if ('>' === $gutter || '|' === $gutter) {
                if ([] !== $newWordSpans) {
                    $newDisplay = $this->applyWordHighlights($newWordSpans, $colWidth, self::GREEN_BG);
                } else {
                    $newDisplay = self::GREEN.$newDisplay.self::RESET;
                }
            }
            $line .= $newDisplay;
        }

        return $line;
    }

    private function truncate(string $text, int $maxLen): string
    {
        if ($maxLen <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen - 1).'~';
    }

    private function pad(string $display, int $width): string
    {
        $visibleLength = $this->visibleLength($display);
        if ($visibleLength >= $width) {
            return $display;
        }

        return $display.str_repeat(' ', $width - $visibleLength);
    }

    /**
     * @param list<WordSpan> $wordSpans
     */
    private function applyWordHighlights(array $wordSpans, int $maxLen, string $highlightColor): string
    {
        $result = '';
        $currentLen = 0;

        foreach ($wordSpans as $span) {
            $remaining = $maxLen - $currentLen;
            if ($remaining <= 0) {
                break;
            }

            $text = $span->text;
            if (mb_strlen($text) > $remaining) {
                if ($remaining <= 1) {
                    $text = mb_substr($text, 0, $remaining);
                } else {
                    $text = mb_substr($text, 0, $remaining - 1).'~';
                }
            }

            $segment = 'eq' === $span->op ? $text : $highlightColor.$text.self::RESET;

            $result .= $segment;
            $currentLen += $this->visibleLength($segment);
        }

        return $result;
    }

    private function visibleLength(string $value): int
    {
        return mb_strlen($this->stripAnsi($value));
    }

    private function stripAnsi(string $value): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $value) ?? $value;
    }
}
