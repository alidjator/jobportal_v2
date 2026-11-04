<?php

namespace Src\Assessment\Application\Library\Figural;

/**
 * Draws the figures of the figural reasoning test as PNG files. A frame is a list of elements on a 100 by 100 grid (see `FiguralReasoningContent` for the element shapes).
 *
 * @phpstan-import-type Frame from FiguralItem
 */
interface FigureRenderer
{
    /** @param  Frame  $frame  one option */
    public function frame(array $frame): string;

    /**
     * The stem picture: the frames in the order of the layout, with the question cell added where the answer goes.
     *
     * @param  list<Frame>  $frames
     */
    public function stem(FiguralLayout $layout, array $frames): string;
}
