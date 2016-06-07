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

    private $incompleteLine = '';

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
        $incomplete =& $this->incompleteLine;
        $this->readline->on('data', function($line) use ($that, &$incomplete) {
            // readline emits a new line on enter, so start with a blank line
            $incomplete = '';

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
        // clear readline prompt in order to overwrite with data
        $this->readline->clear();

        // move one line up if the last write did not end with a newline
        if ($this->incompleteLine !== '') {
            $this->output->write("\033[A");
            $this->output->write("\r\033[" . $this->width($this->incompleteLine) . "C");
        }

        // write actual data
        $this->output->write($data);

        // following write will have have to append to this line if it does not end with a newline
        $endsWithNewline = substr($data, -1) === "\n";

        if ($endsWithNewline) {
            // line ends with newline, so this is line is considered complete
            $this->incompleteLine = '';
        } else {
            // always end data with newline in order to append readline on next line
            $this->output->write("\n");

            $lastNewline = strrpos($data, "\n");

            if ($lastNewline === false) {
                // contains no newline at all, everything is incomplete
                $this->incompleteLine .= $data;
            } else {
                // contains a newline, everything behind it is incomplete
                $this->incompleteLine = (string)substr($data, $lastNewline + 1);
            }
        }

        // restore original readline prompt and line buffer
        $this->readline->redraw();
    }

    public function writeln($line)
    {
        $this->write($line . PHP_EOL);
    }

    public function overwrite($data = '')
    {
        if ($this->incompleteLine !== '') {
            // move one line up, move to start of line and clear everything
            $data = "\033[A\r\033[K" . $data;
            $this->incompleteLine = '';
        }

        $this->write($data);
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

    private function width($str)
    {
        return mb_strwidth($str, 'utf-8') - 2 * substr_count($str, "\x08");
    }
}
