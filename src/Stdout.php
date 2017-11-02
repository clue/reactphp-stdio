<?php

namespace Clue\React\Stdio;

use React\Stream\WritableStream;

/**
 * @deprecated
 */
class Stdout extends WritableStream
{
    public function __construct()
    {
        // STDOUT not defined ("php -a") or already closed (`fclose(STDOUT)`)
        if (!defined('STDOUT') || !is_resource(STDOUT)) {
            return $this->close();
        }
    }

    public function write($data)
    {
        if ($this->closed) {
            return false;
        }

        // TODO: use non-blocking output instead
        fwrite(STDOUT, $data);

        return true;
    }
}
