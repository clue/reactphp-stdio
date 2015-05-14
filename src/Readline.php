<?php

namespace Clue\React\Stdio;

use Evenement\EventEmitter;

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
    const ESC_DEL      = "3~";
    const ESC_INS      = "2~";

    const ESC_F10 = "20~";

    private $prompt = '';
    private $linebuffer = '';
    private $linepos = 0;
    private $echo = true;
    private $autocomplete = null;
    private $move = true;
    private $history = null;
    private $encoding = 'utf-8';

    private $output;
    private $sequencer;

    public function __construct($output)
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
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_DEL, array($this, 'onKeyDelete'));
        $this->sequencer->addSequence(self::ESC_SEQUENCE . self::ESC_INS, array($this, 'onKeyInsert'));

        $this->sequencer->addFallback('', array($this, 'onFallback'));
        $this->sequencer->addFallback(self::ESC_SEQUENCE, function ($bytes) {
            echo 'unknown sequence: ' . ord($bytes) . PHP_EOL;
        });
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
        $this->prompt = $prompt;
        $this->redraw();

        return $this;
    }

    /**
     * whether or not to echo input
     *
     * disabling echo output is a good idea for password prompts. Will redraw
     * the current prompt and only echo the current input buffer according to
     * the new setting.
     *
     * @param boolean $echo
     * @return self
     * @uses self::redraw()
     */
    public function setEcho($echo)
    {
        $this->echo = !!$echo;
        $this->redraw();

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

        $this->linepos = $this->strlen($this->linebuffer);
        $this->redraw();

        return $this;
    }

    /**
     * get current cursor position
     *
     * cursor position is measured in number of text characters
     *
     * @return int
     * @see self::moveCursorTo() to move the cursor to a given position
     * @see self::moveCursorBy() to move the cursor by given number of characters
     * @see self::setMove() to toggle whether the user can move the cursor position
     */
    public function getCursorPosition()
    {
        return $this->linepos;
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
        $this->linebuffer = $input;
        $this->linepos = $this->strlen($this->linebuffer);
        $this->redraw();

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
     * Clear the current line and draw the input prompt. If input echo is
     * enabled, will also draw the current input buffer and move to the current
     * input buffer position.
     */
    public function redraw()
    {
        // Erase characters from cursor to end of line
        $output = "\r\033[K" . $this->prompt;
        if ($this->echo) {
            $output .= $this->linebuffer;

            $len = $this->strlen($this->linebuffer);
            if ($this->linepos !== $len) {
                $reverse = $len - $this->linepos;

                // move back $reverse chars (by sending backspace)
                $output .= str_repeat("\x08", $reverse);
            }
        }
        $this->write($output);
    }

    public function clear()
    {
        if ($this->prompt !== '' || ($this->echo && $this->linebuffer !== '')) {
            $this->write("\r\033[K");
        }
        // $output = str_repeat("\x09 \x09", strlen($this->prompt . $this->linebuffer));
        // $this->write($output);
    }

    public function onChar($char)
    {
        $this->sequencer->push($char);
    }

    public function onKeyBackspace()
    {
        // left delete only if not at the beginning
        $this->deleteChar($this->linepos - 1);
    }

    public function onKeyDelete()
    {
        // right delete only if not at the end
        $this->deleteChar($this->linepos);
    }

    public function onKeyInsert()
    {
        // TODO: toggle insert mode
    }

    public function onKeyTab()
    {
        if ($this->autocomplete !== null) {
            $this->autocomplete->run();
        }
    }

    public function onKeyEnter()
    {
        if ($this->echo) {
            $this->write("\n");
        }
        $this->processLine();
    }

    public function onKeyLeft()
    {
        if ($this->move) {
            $this->moveCursorBy(-1);
        }
    }

    public function onKeyRight()
    {
        if ($this->move) {
            $this->moveCursorBy(1);
        }
    }

    public function onKeyUp()
    {
        if ($this->history !== null) {
            $this->history->up();
        }
    }

    public function onKeyDown()
    {
        if ($this->history !== null) {
            $this->history->down();
        }
    }

    // character(s) that could not be processed by the sequencer
    public function onFallback($chars)
    {
        $pre  = $this->substr($this->linebuffer, 0, $this->linepos); // read everything up until before backspace
        $post = $this->substr($this->linebuffer, $this->linepos);

        $this->linebuffer = $pre . $chars . $post;

        // TODO: fix lineposition for partial multibyte characters
        ++$this->linepos;
        if ($this->linepos >= $this->strlen($this->linebuffer)) {
            $this->linepos = $this->strlen($this->linebuffer);
        }

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
     */
    public function deleteChar($n)
    {
        $len = $this->strlen($this->linebuffer);
        if ($n < 0 || $n > $len) {
            return;
        }

        // TODO: multibyte-characters

        $pre  = $this->substr($this->linebuffer, 0, $n); // read everything up until before current position
        $post = $this->substr($this->linebuffer, $n + 1);
        $this->linebuffer = $pre . $post;

        if ($n < $this->linepos) {
            --$this->linepos;
        }

        $this->redraw();
    }

    /**
     * move cursor to right by $n chars (or left if $n is negative)
     *
     * zero or out of range moves are simply ignored
     *
     * @param int $n
     * @return self
     * @uses self::moveCursorTo()
     */
    public function moveCursorBy($n)
    {
        return $this->moveCursorTo($this->linepos + $n);
    }

    /**
     * move cursor to given position in current line buffer
     *
     * out of range (exceeding current input buffer) are simply ignored
     *
     * @param int $n
     * @return self
     * @uses self::redraw()
     */
    public function moveCursorTo($n)
    {
        if ($n < 0 || $n === $this->linepos || $n > $this->strlen($this->linebuffer)) {
            return;
        }

        $this->linepos = $n;
        $this->redraw();

        return $this;
    }

    /**
     * process the current line buffer, emit event and redraw empty line
     */
    protected function processLine()
    {
        $line = $this->linebuffer;

        $this->emit('data', array($line));

        if ($this->history !== null) {
            $this->history->addLine($line);
        }

        $this->linebuffer = '';
        $this->linepos = 0;

        $this->redraw();
    }

    protected function readEscape($char)
    {
        $this->inEscape = false;

        if($char === self::ESC_LEFT && $this->move) {
            $this->moveCursorBy(-1);
        } else if($char === self::ESC_RIGHT && $this->move) {
            $this->moveCursorBy(1);
        } else if ($char === self::ESC_UP && $this->history !== null) {
            $this->history->moveUp();
        } else if ($char === self::ESC_DOWN && $this->history !== null) {
            $this->history->moveDown();
        } else {
            $this->write('invalid char');
            // ignore unknown escape code
        }
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

    protected function write($data)
    {
        $this->output->write($data);
    }
}
