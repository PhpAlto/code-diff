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

namespace Alto\Code\Diff\Model;

/**
 * Represents a single edit operation (add, del, eq).
 *
 * An edit is the atomic unit of a diff, representing a single line that was
 * either added, deleted, or remained equal (context).
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class Edit
{
    /**
     * @param 'add'|'del'|'eq' $op
     * @param list<WordSpan>   $wordSpans
     */
    public function __construct(
        public string $op,
        public string $text,
        public array $wordSpans = [],
    ) {
    }
}
