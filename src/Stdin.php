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

        if ($this->isTty()) {
            $this->oldMode = shell_exec('stty -g');

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            shell_exec('stty -icanon -echo');
        }
    }

    public function close()
    {
        $this->restore();
        parent::close();
    }

    public function __destruct()
    {
        $this->restore();
    }

    private function restore()
    {
        if ($this->oldMode !== null && $this->isTty()) {
            // Reset stty so it behaves normally again
            shell_exec(sprintf('stty %s', $this->oldMode));
            $this->oldMode = null;
        }
    }

    private function isTty()
    {
        return (is_resource(STDIN) && function_exists('posix_isatty') && posix_isatty(STDIN));
    }
}
