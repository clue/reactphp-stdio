<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\Util;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;

class Stdout extends EventEmitter implements WritableStreamInterface
{
    private $stream;

    public function __construct(LoopInterface $loop)
    {
        $this->stream = new WritableResourceStream(STDOUT, $loop);
        Util::forwardEvents($this->stream, $this, array('data', 'error', 'end', 'close'));
    }

    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    public function write($data)
    {
        return $this->stream->write($data);
    }

    public function end($data = null)
    {
        return $this->stream->end($data);
    }

    public function close()
    {
        return $this->stream->close();
    }
}
