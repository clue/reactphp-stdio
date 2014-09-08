<?php

namespace Clue\React\Stdio;

use React\Stream\WritableStream;

class Stdout extends WritableStream
{
    public function write($data)
    {
        // TODO: use non-blocking output instead

        fwrite(STDOUT, $data);

        return true;
    }
}
