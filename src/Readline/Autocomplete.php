<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline;

interface Autocomplete
{
    public function go(Readline $readline);
}
