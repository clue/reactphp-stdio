<?php

use Clue\React\Stdio\Stdio;
use React\EventLoop\LoopInterface;

class Buffer
{
    private $stdio;
    private $loop;
    private $delay = 0.2;
    private $lock = null;
    private $buffer = '';

    public function __construct(Stdio $stdio, LoopInterface $loop)
    {
        $this->stdio = $stdio;
        $this->loop = $loop;
    }

    public function write($data)
    {
        $this->buffer .= $data;

        if ($this->lock === null) {
            $this->flush();
        }
    }

    public function flush()
    {
        if ($this->lock !== null) {
            $this->loop->cancelTimer($this->lock);
            $this->lock = null;
        }

        if ($this->buffer !== '') {
            $this->stdio->write($this->buffer);
            $this->buffer = '';

            $this->loop->addTimer($this->delay, array($this, 'flush'));
        }
    }
}