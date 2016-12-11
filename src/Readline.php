<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use Clue\React\Utf8\Sequencer as Utf8Sequencer;
use Clue\React\Term\ControlCodeParser;

class Readline extends EventEmitter implements ReadableStreamInterface
{
    private $prompt = '';
    private $linebuffer = '';
    private $linepos = 0;
    private $echo = true;
    private $autocomplete = null;
    private $move = true;
    private $encoding = 'utf-8';

    private $input;
    private $output;
    private $sequencer;
    private $closed = false;

    private $historyLines = array();
    private $historyPosition = null;
    private $historyUnsaved = null;

    public function __construct(ReadableStreamInterface $input, WritableStreamInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        if (!$this->input->isReadable()) {
            return $this->close();
        }

        // push input through control code parser
        $parser = new ControlCodeParser($input);

        $that = $this;
        $codes = array(
            "\n" => 'onKeyEnter',
            "\x7f" => 'onKeyBackspace',
            "\t" => 'onKeyTab',

            "\033[A" => 'onKeyUp',
            "\033[B" => 'onKeyDown',
            "\033[C" => 'onKeyRight',
            "\033[D" => 'onKeyLeft',

            "\033[1~" => 'onKeyHome',
            "\033[2~" => 'onKeyInsert',
            "\033[3~" => 'onKeyDelete',
            "\033[4~" => 'onKeyEnd',

//          "\033[20~" => 'onKeyF10',
        );
        $decode = function ($code) use ($codes, $that) {
            if (isset($codes[$code])) {
                $method = $codes[$code];
                $that->$method($code);
                return;
            }
        };

        $parser->on('csi', $decode);
        $parser->on('c0', $decode);

        // push resulting data through utf8 sequencer
        $utf8 = new Utf8Sequencer($parser);
        $utf8->on('data', array($this, 'onFallback'));

        // process all stream events (forwarded from input stream)
        $utf8->on('end', array($this, 'handleEnd'));
        $utf8->on('error', array($this, 'handleError'));
        $utf8->on('close', array($this, 'close'));
    }

    /**
     * prompt to prepend to input line
     *
     * Will redraw the current input prompt with the current input buffer.
     *
     * @param string $prompt
     * @return self
     * @uses self::redraw()
     */
    public function setPrompt($prompt)
    {
        if ($prompt === $this->prompt) {
            return $this;
        }

        $this->prompt = $prompt;

        return $this->redraw();
    }

    /**
     * returns the prompt to prepend to input line
     *
     * @return string
     * @see self::setPrompt()
     */
    public function getPrompt()
    {
        return $this->prompt;
    }

    /**
     * sets whether/how to echo text input
     *
     * The default setting is `true`, which means that every character will be
     * echo'ed as-is, i.e. you can see what you're typing.
     * For example: Typing "test" shows "test".
     *
     * You can turn this off by supplying `false`, which means that *nothing*
     * will be echo'ed while you're typing. This could be a good idea for
     * password prompts. Note that this could be confusing for users, so using
     * a character replacement as following is often preferred.
     * For example: Typing "test" shows "" (nothing).
     *
     * Alternative, you can supply a single character replacement character
     * that will be echo'ed for each character in the text input. This could
     * be a good idea for password prompts, where an asterisk character ("*")
     * is often used to indicate typing activity and password length.
     * For example: Typing "test" shows "****" (with asterisk replacement)
     *
     * Changing this setting will redraw the current prompt and echo the current
     * input buffer according to the new setting.
     *
     * @param boolean|string $echo echo can be turned on (boolean true) or off (boolean true), or you can supply a single character replacement string
     * @return self
     * @uses self::redraw()
     */
    public function setEcho($echo)
    {
        if ($echo === $this->echo) {
            return $this;
        }

        $this->echo = $echo;

        // only redraw if there is any input
        if ($this->linebuffer !== '') {
            $this->redraw();
        }

        return $this;
    }

    /**
     * whether or not to support moving cursor left and right
     *
     * switching cursor support moves the cursor to the end of the current
     * input buffer (if any).
     *
     * @param boolean $move
     * @return self
     * @uses self::redraw()
     */
    public function setMove($move)
    {
        $this->move = !!$move;

        return $this->moveCursorTo($this->strlen($this->linebuffer));
    }

    /**
     * Gets current cursor position measured in number of text characters.
     *
     * Note that the number of text characters doesn't necessarily reflect the
     * number of monospace cells occupied by the text characters. If you want
     * to know the latter, use `self::getCursorCell()` instead.
     *
     * @return int
     * @see self::getCursorCell() to get the position measured in monospace cells
     * @see self::moveCursorTo() to move the cursor to a given character position
     * @see self::moveCursorBy() to move the cursor by given number of characters
     * @see self::setMove() to toggle whether the user can move the cursor position
     */
    public function getCursorPosition()
    {
        return $this->linepos;
    }

    /**
     * Gets current cursor position measured in monospace cells.
     *
     * Note that the cell position doesn't necessarily reflect the number of
     * text characters. If you want to know the latter, use
     * `self::getCursorPosition()` instead.
     *
     * Most "normal" characters occupy a single monospace cell, i.e. the ASCII
     * sequence for "A" requires a single cell, as do most UTF-8 sequences
     * like "Ä".
     *
     * However, there are a number of code points that do not require a cell
     * (i.e. invisible surrogates) or require two cells (e.g. some asian glyphs).
     *
     * Also note that this takes the echo mode into account, i.e. the cursor is
     * always at position zero if echo is off. If using a custom echo character
     * (like asterisk), it will take its width into account instead of the actual
     * input characters.
     *
     * @return int
     * @see self::getCursorPosition() to get current cursor position measured in characters
     * @see self::moveCursorTo() to move the cursor to a given character position
     * @see self::moveCursorBy() to move the cursor by given number of characters
     * @see self::setMove() to toggle whether the user can move the cursor position
     * @see self::setEcho()
     */
    public function getCursorCell()
    {
        if ($this->echo === false) {
            return 0;
        }
        if ($this->echo !== true) {
            return $this->strwidth($this->echo) * $this->linepos;
        }
        return $this->strwidth($this->substr($this->linebuffer, 0, $this->linepos));
    }

    /**
     * Moves cursor to right by $n chars (or left if $n is negative).
     *
     * Zero value or values out of range (exceeding current input buffer) are
     * simply ignored.
     *
     * Will redraw() the readline only if the visible cell position changes,
     * see `self::getCursorCell()` for more details.
     *
     * @param int $n
     * @return self
     * @uses self::moveCursorTo()
     * @uses self::redraw()
     */
    public function moveCursorBy($n)
    {
        return $this->moveCursorTo($this->linepos + $n);
    }

    /**
     * Moves cursor to given position in current line buffer.
     *
     * Values out of range (exceeding current input buffer) are simply ignored.
     *
     * Will redraw() the readline only if the visible cell position changes,
     * see `self::getCursorCell()` for more details.
     *
     * @param int $n
     * @return self
     * @uses self::redraw()
     */
    public function moveCursorTo($n)
    {
        if ($n < 0 || $n === $this->linepos || $n > $this->strlen($this->linebuffer)) {
            return $this;
        }

        $old = $this->getCursorCell();
        $this->linepos = $n;

        // only redraw if visible cell position change (implies cursor is actually visible)
        if ($this->getCursorCell() !== $old) {
            $this->redraw();
        }

        return $this;
    }

    /**
     * set current text input buffer
     *
     * this moves the cursor to the end of the current
     * input buffer (if any).
     *
     * @param string $input
     * @return self
     * @uses self::redraw()
     */
    public function setInput($input)
    {
        if ($this->linebuffer === $input) {
            return $this;
        }

        // remember old input length if echo replacement is used
        $oldlen = (is_string($this->echo)) ? $this->strlen($this->linebuffer) : null;

        $this->linebuffer = $input;
        $this->linepos = $this->strlen($this->linebuffer);

        // only redraw if input should be echo'ed (i.e. is not hidden anyway)
        // and echo replacement is used, make sure the input length changes
        if ($this->echo !== false && $this->linepos !== $oldlen) {
            $this->redraw();
        }

        return $this;
    }

    /**
     * get current text input buffer
     *
     * @return string
     */
    public function getInput()
    {
        return $this->linebuffer;
    }

    /**
     * Adds a new line to the (bottom position of the) history list
     *
     * @param string $line
     * @return self
     */
    public function addHistory($line)
    {
        $this->historyLines []= $line;

        return $this;
    }

    /**
     * Clears the complete history list
     *
     * @return self
     */
    public function clearHistory()
    {
        $this->historyLines = array();
        $this->historyPosition = null;

        if ($this->historyUnsaved !== null) {
            $this->setInput($this->historyUnsaved);
            $this->historyUnsaved = null;
        }

        return $this;
    }

    /**
     * Returns an array with all lines in the history
     *
     * @return string[]
     */
    public function listHistory()
    {
        return $this->historyLines;
    }

    /**
     * set autocompletion handler to use (or none)
     *
     * The autocomplete handler will be called whenever the user hits the TAB
     * key.
     *
     * @param AutocompleteInterface|null $autocomplete
     * @return self
     */
    public function setAutocomplete(AutocompleteInterface $autocomplete = null)
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }

    /**
     * redraw the current input prompt
     *
     * Usually, there should be no need to call this method manually. It will
     * be invoked automatically whenever we detect the readline input needs to
     * be (re)written to the output.
     *
     * Clear the current line and draw the input prompt. If input echo is
     * enabled, will also draw the current input buffer and move to the current
     * input buffer position.
     *
     * @return self
     * @internal
     */
    public function redraw()
    {
        // Erase characters from cursor to end of line
        $output = "\r\033[K" . $this->prompt;
        if ($this->echo !== false) {
            if ($this->echo === true) {
                $buffer = $this->linebuffer;
            } else {
                $buffer = str_repeat($this->echo, $this->strlen($this->linebuffer));
            }

            // write output, then move back $reverse chars (by sending backspace)
            $output .= $buffer . str_repeat("\x08", $this->strwidth($buffer) - $this->getCursorCell());
        }
        $this->output->write($output);

        return $this;
    }

    /**
     * Clears the current input prompt (if any)
     *
     * Usually, there should be no need to call this method manually. It will
     * be invoked automatically whenever we detect that output needs to be
     * written in place of the current prompt. The prompt will be rewritten
     * after clearing the prompt and writing the output.
     *
     * @return self
     * @see Stdio::write() which is responsible for invoking this method
     * @internal
     */
    public function clear()
    {
        if ($this->prompt !== '' || ($this->echo !== false && $this->linebuffer !== '')) {
            $this->output->write("\r\033[K");
        }

        return $this;
    }

    /** @internal */
    public function onKeyBackspace()
    {
        // left delete only if not at the beginning
        $this->deleteChar($this->linepos - 1);
    }

    /** @internal */
    public function onKeyDelete()
    {
        // right delete only if not at the end
        $this->deleteChar($this->linepos);
    }

    /** @internal */
    public function onKeyInsert()
    {
        // TODO: toggle insert mode
    }

    /** @internal */
    public function onKeyHome()
    {
        if ($this->move) {
            $this->moveCursorTo(0);
        }
    }

    /** @internal */
    public function onKeyEnd()
    {
        if ($this->move) {
            $this->moveCursorTo($this->strlen($this->linebuffer));
        }
    }

    /** @internal */
    public function onKeyTab()
    {
        if ($this->autocomplete !== null) {
            $this->autocomplete->run();
        }
    }

    /** @internal */
    public function onKeyEnter()
    {
        if ($this->echo !== false) {
            $this->output->write("\n");
        }
        $this->processLine();
    }

    /** @internal */
    public function onKeyLeft()
    {
        if ($this->move) {
            $this->moveCursorBy(-1);
        }
    }

    /** @internal */
    public function onKeyRight()
    {
        if ($this->move) {
            $this->moveCursorBy(1);
        }
    }

    /** @internal */
    public function onKeyUp()
    {
        // ignore if already at top or history is empty
        if ($this->historyPosition === 0 || !$this->historyLines) {
            return;
        }

        if ($this->historyPosition === null) {
            // first time up => move to last entry
            $this->historyPosition = count($this->historyLines) - 1;
            $this->historyUnsaved = $this->getInput();
        } else {
            // somewhere in the list => move by one
            $this->historyPosition--;
        }

        $this->setInput($this->historyLines[$this->historyPosition]);
    }

    /** @internal */
    public function onKeyDown()
    {
        // ignore if not currently cycling through history
        if ($this->historyPosition === null) {
            return;
        }

        if (($this->historyPosition + 1) < count($this->historyLines)) {
            // this is still a valid position => advance by one and apply
            $this->historyPosition++;
            $this->setInput($this->historyLines[$this->historyPosition]);
        } else {
            // moved beyond bottom => restore original unsaved input
            $this->setInput($this->historyUnsaved);
            $this->historyPosition = null;
        }
    }

    /**
     * Will be invoked for character(s) that could not otherwise be processed by the sequencer
     *
     * @internal
     */
    public function onFallback($chars)
    {
        // read everything up until before current position
        $pre  = $this->substr($this->linebuffer, 0, $this->linepos);
        $post = $this->substr($this->linebuffer, $this->linepos);

        $this->linebuffer = $pre . $chars . $post;
        $this->linepos += $this->strlen($chars);

        $this->redraw();
    }

    /**
     * delete a character at the given position
     *
     * Removing a character left to the current cursor will also move the cursor
     * to the left.
     *
     * indices out of range (exceeding current input buffer) are simply ignored
     *
     * @param int $n
     * @internal
     */
    public function deleteChar($n)
    {
        $len = $this->strlen($this->linebuffer);
        if ($n < 0 || $n >= $len) {
            return;
        }

        // read everything up until before current position
        $pre  = $this->substr($this->linebuffer, 0, $n);
        $post = $this->substr($this->linebuffer, $n + 1);

        $this->linebuffer = $pre . $post;

        // move cursor one cell to the left if we're deleting in front of the cursor
        if ($n < $this->linepos) {
            --$this->linepos;
        }

        $this->redraw();
    }

    /**
     * process the current line buffer, emit event and redraw empty line
     *
     * @uses self::setInput()
     */
    protected function processLine()
    {
        // reset history cycle position
        $this->historyPosition = null;
        $this->historyUnsaved = null;

        // store and reset/clear/redraw current input
        $line = $this->linebuffer;
        if ($line !== '') {
            // the line is not empty, reset it (and implicitly redraw prompt)
            $this->setInput('');
        } elseif ($this->echo !== false) {
            // explicitly redraw prompt after empty line
            $this->redraw();
        }

        // process stored input buffer
        $this->emit('data', array($line));
    }

    protected function strlen($str)
    {
        return mb_strlen($str, $this->encoding);
    }

    protected function substr($str, $start = 0, $len = null)
    {
        if ($len === null) {
            $len = $this->strlen($str) - $start;
        }
        return (string)mb_substr($str, $start, $len, $this->encoding);
    }

    private function strwidth($str)
    {
        return mb_strwidth($str, $this->encoding);
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }

    public function isReadable()
    {
        return !$this->closed && $this->input->isReadable();
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->input->close();

        $this->emit('close');
    }
}
