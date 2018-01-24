<?php

namespace Clue\React\Stdio;

use Clue\React\Stdio\Io\Stdin;
use Clue\React\Stdio\Io\Stdout;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;

class Stdio extends EventEmitter implements DuplexStreamInterface
{
    private $input;
    private $output;
    private $readline;

    private $ending = false;
    private $closed = false;
    private $incompleteLine = '';
    private $originalTtyMode = null;

    public function __construct(LoopInterface $loop, ReadableStreamInterface $input = null, WritableStreamInterface $output = null, Readline $readline = null)
    {
        if ($input === null) {
            $input = $this->createStdin($loop);
        }

        if ($output === null) {
            $output = $this->createStdout($loop);
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
            $that->emit('data', array($line));
        });

        // handle all input events (readline forwards all input events)
        $this->readline->on('error', array($this, 'handleError'));
        $this->readline->on('end', array($this, 'handleEnd'));
        $this->readline->on('close', array($this, 'handleCloseInput'));

        // handle all output events
        $this->output->on('error', array($this, 'handleError'));
        $this->output->on('close', array($this, 'handleCloseOutput'));
    }

    public function __destruct()
    {
        $this->restoreTtyMode();
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
        $this->restoreTtyMode();
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
        $this->restoreTtyMode();
        $this->input->close();
        $this->output->close();
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

    /**
     * @codeCoverageIgnore this is covered by functional tests with/without ext-readline
     */
    private function restoreTtyMode()
    {
        if (function_exists('readline_callback_handler_remove')) {
            // remove dummy readline handler to turn to default input mode
            readline_callback_handler_remove();
        } elseif ($this->originalTtyMode !== null && $this->isTty()) {
            // Reset stty so it behaves normally again
            shell_exec(sprintf('stty %s', $this->originalTtyMode));
            $this->originalTtyMode = null;
        }

        // restore blocking mode so following programs behave normally
        if (defined('STDIN') && is_resource(STDIN)) {
            stream_set_blocking(STDIN, true);
        }
    }

    /**
     * @param LoopInterface $loop
     * @return ReadableStreamInterface
     * @codeCoverageIgnore this is covered by functional tests with/without ext-readline
     */
    private function createStdin(LoopInterface $loop)
    {
        // STDIN not defined ("php -a") or already closed (`fclose(STDIN)`)
        // also support starting program with closed STDIN ("example.php 0<&-")
        // the stream is a valid resource and is not EOF, but fstat fails
        if (!defined('STDIN') || !is_resource(STDIN) || fstat(STDIN) === false) {
            $stream = new ReadableResourceStream(fopen('php://memory', 'r'), $loop);
            $stream->close();
            return $stream;
        }

        $stream = new ReadableResourceStream(STDIN, $loop);

        if (function_exists('readline_callback_handler_install')) {
            // Prefer `ext-readline` to install dummy handler to turn on raw input mode.
            // We will nevery actually feed the readline handler and instead
            // handle all input in our `Readline` implementation.
            readline_callback_handler_install('', function () { });
            return $stream;
        }

        if ($this->isTty()) {
            $this->originalTtyMode = shell_exec('stty -g');

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            shell_exec('stty -icanon -echo');
        }

        // register shutdown function to restore TTY mode in case of unclean shutdown (uncaught exception)
        // this will not trigger on SIGKILL etc., but the terminal should take care of this
        register_shutdown_function(array($this, 'close'));

        return $stream;
    }

    /**
     * @param LoopInterface $loop
     * @return WritableStreamInterface
     * @codeCoverageIgnore this is covered by functional tests
     */
    private function createStdout(LoopInterface $loop)
    {
        // STDOUT not defined ("php -a") or already closed (`fclose(STDOUT)`)
        // also support starting program with closed STDOUT ("example.php >&-")
        // the stream is a valid resource and is not EOF, but fstat fails
        if (!defined('STDOUT') || !is_resource(STDOUT) || fstat(STDOUT) === false) {
            $output = new WritableResourceStream(fopen('php://memory', 'r+'), $loop);
            $output->close();
        } else {
            $output = new WritableResourceStream(STDOUT, $loop);
        }

        return $output;
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    private function isTty()
    {
        if (PHP_VERSION_ID >= 70200) {
            // Prefer `stream_isatty()` (available as of PHP 7.2 only)
            return stream_isatty(STDIN);
        } elseif (function_exists('posix_isatty')) {
            // Otherwise use `posix_isatty` if available (requires `ext-posix`)
            return posix_isatty(STDIN);
        }

        // otherwise try to guess based on stat file mode and device major number
        // Must be special character device: ($mode & S_IFMT) === S_IFCHR
        // And device major number must be allocated to TTYs (2-5 and 128-143)
        // For what it's worth, checking for device gid 5 (tty) is less reliable.
        // @link http://man7.org/linux/man-pages/man7/inode.7.html
        // @link https://www.kernel.org/doc/html/v4.11/admin-guide/devices.html#terminal-devices
        if (is_resource(STDIN)) {
            $stat = fstat(STDIN);
            $mode = isset($stat['mode']) ? ($stat['mode'] & 0170000) : 0;
            $major = isset($stat['dev']) ? (($stat['dev'] >> 8) & 0xff) : 0;

            if ($mode === 0020000 && $major >= 2 && $major <= 143 && ($major <=5 || $major >= 128)) {
                return true;
            }
        }
        return false;
    }
}
