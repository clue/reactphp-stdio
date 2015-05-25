<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline;

class NullAutocomplete implements Autocomplete
{
    public function go(Readline $readline)
    {
        // NOOP
    }
}
