<?php

namespace Clue\React\Stdio;

use React\Stream\StreamInterface;
use React\Stream\CompositeStream;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStream;
use React\Stream\Stream;

class Stdio extends CompositeStream
{
    private $input;
    private $output;

    private $readline;
    private $needsNewline = false;

    public function __construct(LoopInterface $loop, $input = true)
    {
        $this->input = new Stdin($loop);

        $this->output = new Stdout(STDOUT);

        $this->readline = $readline = new Readline($this->output);

        $that = $this;

        // input data emits a single char into readline
        $this->input->on('data', function ($data) use ($that, $readline) {
            $that->emit('char', array($data, $that));
            $readline->onChar($data);
        });

        // readline data emits a new line
        $readline->on('data', function($line) use ($that) {
            $that->emit('line', array($line, $that));
        });

        if (!$input) {
            $this->pause();
        }
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function handleBuffer()
    {
        $that = $this;
        ob_start(function ($chunk) use ($that) {
            $that->write($chunk);
        }, 2);
    }

    public function write($data)
    {
        // switch back to last output position
        $this->readline->clear();

        // Erase characters from cursor to end of line
        $this->output->write("\r\033[K");

        // move one line up?
        if ($this->needsNewline) {
            $this->output->write("\033[A");
        }

        $this->output->write($data);

        $this->needsNewline = substr($data, -1) !== "\n";

        // repeat current prompt + linebuffer
        if ($this->needsNewline) {
            $this->output->write("\n");
        }
        $this->readline->redraw();
    }

    public function writeln($line)
    {
        $this->write($line . PHP_EOL);
    }

    public function overwrite($data = '')
    {
        // TODO: remove existing characters

        $this->write("\r" . $data);
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->readline->setInput('')->setPrompt('')->clear();
        $this->input->pause();
        $this->output->end();
    }

    public function close()
    {
        $this->readline->setInput('')->setPrompt('')->clear();
        $this->input->close();
        $this->output->close();
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function getReadline()
    {
        return $this->readline;
    }
}
