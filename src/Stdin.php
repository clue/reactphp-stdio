<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;
use React\Stream\ReadableResourceStream;
use React\Stream\ReadableStreamInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

class Stdin extends EventEmitter implements ReadableStreamInterface
{
    private $oldMode = null;

    private $stream;

    public function __construct(LoopInterface $loop)
    {
        $this->stream = new ReadableResourceStream(STDIN, $loop);
        Util::forwardEvents($this->stream, $this, array('data', 'error', 'end', 'close'));
    }

    public function resume()
    {
        if ($this->oldMode === null) {
            $this->oldMode = shell_exec('stty -g');

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            shell_exec('stty -icanon -echo');

            $this->stream->resume();
        }
    }

    public function pause()
    {
        if ($this->oldMode !== null) {
            // Reset stty so it behaves normally again
            shell_exec(sprintf('stty %s', $this->oldMode));

            $this->oldMode = null;
            $this->stream->pause();
        }
    }

    public function close()
    {
        $this->stream->close();
    }

    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return $this->stream->pipe($dest, $options);
    }

    public function __destruct()
    {
        $this->pause();
    }
}
