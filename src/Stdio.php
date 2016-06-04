<?php

namespace Clue\React\Stdio;

use React\Stream\CompositeStream;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

class Stdio extends CompositeStream
{
    private $input;
    private $output;

    private $readline;
    private $needsNewline = false;

    public function __construct(LoopInterface $loop, ReadableStreamInterface $input = null, WritableStreamInterface $output = null, Readline $readline = null)
    {
        if ($input === null) {
            $input = new Stdin($loop);
        }

        if ($output === null) {
            $output = new Stdout(STDOUT);
        }

        if ($readline === null) {
            $readline = new Readline($input, $output);
        }

        $this->input = $input;
        $this->output = $output;
        $this->readline = $readline;

        $that = $this;

        // stdin emits single chars
        $this->input->on('data', function ($data) use ($that) {
            $that->emit('char', array($data, $that));
        });

        // readline data emits a new line
        $this->readline->on('data', function($line) use ($that) {
            $that->emit('line', array($line, $that));
        });
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
