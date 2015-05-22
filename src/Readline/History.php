<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline;

interface History
{
    public function addLine($line);

    public function moveUp(Readline $readline);

    public function moveDown(Readline $readline);
}
