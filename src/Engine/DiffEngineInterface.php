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

use Alto\Code\Diff\Model\DiffResult;
use Alto\Code\Diff\Options\Options;

/**
 * Interface for diff engines.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface DiffEngineInterface
{
    /**
     * Compute the difference between two strings.
     */
    public function diff(string $old, string $new, Options $opts): DiffResult;
}
