<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

class Readline extends EventEmitter
{
    const KEY_BACKSPACE = "\x7f";
    const KEY_ENTER = "\n";
    const KEY_TAB = "\t";

    const ESC_SEQUENCE = "\033[";
    const ESC_LEFT     = "D";
    const ESC_RIGHT    = "C";
    const ESC_UP       = "A";
    const ESC_DOWN     = "B";
    const ESC_HOME     = "1~";
    const ESC_INS      = "2~";
    const ESC_DEL      = "3~";
    const ESC_END      = "4~";

    const ESC_F10 = "20~";

    private $prompt = '';
    private $linebuffer = '';
    private $linepos = 0;
    private $echo = true;
    private $autocomplete = null;
    private $move = true;
    private $history = null;
    private $encoding = 'utf-8';

    private $input;
    private $output;
    private $sequencer;

    public function __construct(ReadableStreamInterface $input, WritableStreamInterface $output)
    {
        $this->output = $output;

        $this->sequencer = new Sequencer();
        $this->sequencer->addSequence(self::KEY_ENTER, array($this, 'onKeyEnter'));
        $this->sequencer->addSequence(self::KEY_BACKSPACE, array($this, 'onKeyBackspace'));
        $this->sequencer->addSequence(self::KEY_TAB, array($this, 'onKeyTab'));

        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_LEFT, array($this, 'onKeyLeft'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_RIGHT, array($this, 'onKeyRight'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_UP, array($this, 'onKeyUp'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_DOWN, array($this, 'onKeyDown'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_HOME, array($this, 'onKeyHome'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_INS, array($this, 'onKeyInsert'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_DEL, array($this, 'onKeyDelete'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_END, array($this, 'onKeyEnd'));

        $expect = 0;
        $char = '';
        $that = $this;
        $this->sequencer->addFallback('', function ($byte) use (&$expect, &$char, $that) {
            if ($expect === 0) {
                $code = ord($byte);
                // count number of bytes expected for this UTF-8 multi-byte character
                $expect = 1;
                if ($code & 128 && $code & 64) {
                    ++$expect;
                    if ($code & 32) {
                        ++$expect;
                        if ($code & 16) {
                            ++$expect;
                        }
                    }
                }
            }
            $char .= $byte;
            --$expect;

            // forward buffered bytes as a single multi byte character once last byte has been read
            if ($expect === 0) {
                $save = $char;
                $char = '';
                $that->onFallback($save);
            }
        });

        $this->sequencer->addFallback(self::ESC_SEQUENCE, function ($bytes) {
            echo 'unknown sequence: ' . ord($bytes) . PHP_EOL;
        });

        // input data emits a single char into readline
        $input->on('data', array($this->sequencer, 'push'));
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
     * set history handler to use (or none)
     *
     * The history handler will be called whenever the user hits the UP or DOWN
     * arrow keys.
     *
     * @param HistoryInterface|null $history
     * @return self
     */
    public function setHistory(HistoryInterface $history = null)
    {
        $this->history = $history;

        return $this;
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
        if ($this->history !== null) {
            $this->history->up();
        }
    }

    /** @internal */
    public function onKeyDown()
    {
        if ($this->history !== null) {
            $this->history->down();
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
        ++$this->linepos;

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
        // store and reset/clear/redraw current input
        $line = $this->linebuffer;
        $this->setInput('');

        // process stored input buffer
        if ($this->history !== null) {
            $this->history->addLine($line);
        }
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
}
