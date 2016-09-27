<?php

namespace Clue\React\Stdio;

use React\Stream\ReadableStream;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;

// TODO: only implement ReadableStream
class Stdin extends Stream
{
    private $oldMode = null;

    public function __construct(LoopInterface $loop)
    {
        parent::__construct(STDIN, $loop);
    }

    public function resume()
    {
        if ($this->oldMode === null) {
            $this->oldMode = shell_exec('stty -g');

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            shell_exec('stty -icanon -echo');

            parent::resume();
        }
    }

    public function pause()
    {
        if ($this->oldMode !== null) {
            // Reset stty so it behaves normally again
            shell_exec(sprintf('stty %s', $this->oldMode));

            $this->oldMode = null;
            parent::pause();
        }
    }

    public function close()
    {
        $this->pause();
        parent::close();
    }

    public function __destruct()
    {
        $this->pause();
    }
}
