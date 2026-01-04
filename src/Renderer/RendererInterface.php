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
 * Interface for diff renderers.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface RendererInterface
{
    /**
     * Render a diff result or bundle.
     */
    public function render(DiffResult|DiffBundle $diff): string;
}
