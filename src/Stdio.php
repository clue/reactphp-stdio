<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;
use React\Stream\DuplexStreamInterface;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class Stdio extends EventEmitter implements DuplexStreamInterface
{
    private $input;
    private $output;
    private $readline;

    private $ending = false;
    private $closed = false;
    private $incompleteLine = '';

    public function __construct(LoopInterface $loop, ReadableStreamInterface $input = null, WritableStreamInterface $output = null, Readline $readline = null)
    {
        if ($input === null) {
            $input = new Stdin($loop);
        }

        if ($output === null) {
            $output = new Stdout();
        }

        if ($readline === null) {
            $readline = new Readline($input, $output);
        }

        $this->input = $input;
        $this->output = $output;
        $this->readline = $readline;

        $that = $this;

        // readline data emits a new line
        $incomplete =& $this->incompleteLine;
        $this->readline->on('data', function($line) use ($that, &$incomplete) {
            // readline emits a new line on enter, so start with a blank line
            $incomplete = '';

            // emit data with trailing newline in order to preserve readable API
            $that->emit('data', array($line . PHP_EOL));

            // emit custom line event for ease of use
            $that->emit('line', array($line, $that));
        });

        // handle all input events (readline forwards all input events)
        $this->readline->on('error', array($this, 'handleError'));
        $this->readline->on('end', array($this, 'handleEnd'));
        $this->readline->on('close', array($this, 'handleCloseInput'));

        // handle all output events
        $this->output->on('error', array($this, 'handleError'));
        $this->output->on('close', array($this, 'handleCloseOutput'));
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function isReadable()
    {
        return $this->input->isReadable();
    }

    public function isWritable()
    {
        return $this->output->isWritable();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function write($data)
    {
        // return false if already ended, return true if writing empty string
        if ($this->ending || $data === '') {
            return !$this->ending;
        }

        $out = $data;

        $lastNewline = strrpos($data, "\n");

        $restoreReadline = false;

        if ($this->incompleteLine !== '') {
            // the last write did not end with a newline => append to existing row

            // move one line up and move cursor to last position before writing data
            $out = "\033[A" . "\r\033[" . $this->width($this->incompleteLine) . "C" . $out;

            // data contains a newline, so this will overwrite the readline prompt
            if ($lastNewline !== false) {
                // move cursor to beginning of readline prompt and clear line
                // clearing is important because $data may not overwrite the whole line
                $out = "\r\033[K" . $out;

                // make sure to restore readline after this output
                $restoreReadline = true;
            }
        } else {
            // here, we're writing to a new line => overwrite readline prompt

            // move cursor to beginning of readline prompt and clear line
            $out = "\r\033[K" . $out;

            // we always overwrite the readline prompt, so restore it on next line
            $restoreReadline = true;
        }

        // following write will have have to append to this line if it does not end with a newline
        $endsWithNewline = substr($data, -1) === "\n";

        if ($endsWithNewline) {
            // line ends with newline, so this is line is considered complete
            $this->incompleteLine = '';
        } else {
            // always end data with newline in order to append readline on next line
            $out .= "\n";

            if ($lastNewline === false) {
                // contains no newline at all, everything is incomplete
                $this->incompleteLine .= $data;
            } else {
                // contains a newline, everything behind it is incomplete
                $this->incompleteLine = (string)substr($data, $lastNewline + 1);
            }
        }

        if ($restoreReadline) {
            // write output and restore original readline prompt and line buffer
            return $this->output->write($out . $this->readline->getDrawString());
        } else {
            // restore original cursor position in readline prompt
            $pos = $this->width($this->readline->getPrompt()) + $this->readline->getCursorCell();
            if ($pos !== 0) {
                // we always start at beginning of line, move right by X
                $out .= "\033[" . $pos . "C";
            }

            // write to actual output stream
            return $this->output->write($out);
        }
    }

    /**
     * @deprecated
     */
    public function writeln($line)
    {
        $this->write($line . PHP_EOL);
    }

    /**
     * @deprecated
     */
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
        if ($this->ending) {
            return;
        }

        if ($data !== null) {
            $this->write($data);
        }

        $this->ending = true;

        // clear readline output, close input and end output
        $this->readline->setInput('')->setPrompt('')->clear();
        $this->input->close();
        $this->output->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->ending = true;
        $this->closed = true;

        // clear readline output and then close
        $this->readline->setInput('')->setPrompt('')->clear()->close();
        $this->input->close();
        $this->output->close();
    }

    /**
     * @deprecated
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @deprecated
     */
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
        return $this->readline->strwidth($str) - 2 * substr_count($str, "\x08");
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleEnd()
    {
        $this->emit('end');
    }

    /** @internal */
    public function handleCloseInput()
    {
        if (!$this->output->isWritable()) {
            $this->close();
        }
    }

    /** @internal */
    public function handleCloseOutput()
    {
        if (!$this->input->isReadable()) {
            $this->close();
        }
    }
}
