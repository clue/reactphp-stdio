<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline;

class NullHistory implements History
{
    public function addLine($line)
    {
        // NOOP
    }

    public function moveUp(Readline $readline)
    {
        // NOOP
    }

    public function moveDown(Readline $readline)
    {
        // NOOP
    }
}
