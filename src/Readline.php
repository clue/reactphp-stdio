<?php

namespace Clue\React\Stdio;

use Clue\React\Term\ControlCodeParser;
use Clue\React\Utf8\Sequencer as Utf8Sequencer;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @deprecated use Stdio instead
 * @see Stdio
 */
class Readline extends EventEmitter implements ReadableStreamInterface
{
    private $prompt = '';
    private $linebuffer = '';
    private $linepos = 0;
    private $echo = true;
    private $move = true;
    private $bell = true;
    private $encoding = 'utf-8';

    private $input;
    private $output;
    private $sequencer;
    private $closed = false;

    private $historyLines = array();
    private $historyPosition = null;
    private $historyUnsaved = null;
    private $historyLimit = 500;

    private $autocomplete = null;
    private $autocompleteSuggestions = 8;

    public function __construct(ReadableStreamInterface $input, WritableStreamInterface $output, EventEmitterInterface $base = null)
    {
        $this->input = $input;
        $this->output = $output;

        if (!$this->input->isReadable()) {
            $this->close();
            return;
        }
        // push input through control code parser
        $parser = new ControlCodeParser($input);

        $that = $this;
        $codes = array(
            "\n"   => 'onKeyEnter', // ^J
            "\x7f" => 'onKeyBackspace', // ^?
            "\t"   => 'onKeyTab', // ^I
            "\x04" => 'handleEnd', // ^D

            "\033[A" => 'onKeyUp',
            "\033[B" => 'onKeyDown',
            "\033[C" => 'onKeyRight',
            "\033[D" => 'onKeyLeft',

            "\033[1~" => 'onKeyHome',
//          "\033[2~" => 'onKeyInsert',
            "\033[3~" => 'onKeyDelete',
            "\033[4~" => 'onKeyEnd',

//          "\033[20~" => 'onKeyF10',
        );
        $decode = function ($code) use ($codes, $that, $base) {
            // The user confirms input with enter key which should usually
            // generate a NL (`\n`) character. Common terminals also seem to
            // accept a CR (`\r`) character in place and handle this just like a
            // NL. Similarly `ext-readline` uses different `icrnl` and `igncr`
            // TTY settings on some platforms, so we also accept CR as an alias
            // for NL here. This implies key binding for NL will also trigger.
            if ($code === "\r") {
                $code = "\n";
            }

            // forward compatibility: check if any key binding exists on base Stdio instance
            if ($base !== null && $base->listeners($code)) {
                $base->emit($code, array($code));
                return;
            }

            // deprecated: check if any key binding exists on this Readline instance
            if ($that->listeners($code)) {
                $that->emit($code, array($code));
                return;
            }

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
        $utf8->on('data', function ($data) use ($that, $base) {
            $that->onFallback($data, $base);
        });

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
     * @deprecated use Stdio::setPrompt() instead
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
     * @deprecated use Stdio::getPrompt() instead
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
     * @deprecated use Stdio::setEcho() instead
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
     * @deprecated use Stdio::setMove() instead
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
     * @deprecated use Stdio::getCursorPosition() instead
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
     * @deprecated use Stdio::getCursorCell() instead
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
     * @deprecated use Stdio::moveCursorBy() instead
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
     * @deprecated use Stdio::moveCursorTo() instead
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
     * Appends the given input to the current text input buffer at the current position
     *
     * This moves the cursor accordingly to the number of characters added.
     *
     * @param string $input
     * @return self
     * @uses self::redraw()
     * @deprecated use Stdio::addInput() instead
     */
    public function addInput($input)
    {
        if ($input === '') {
            return $this;
        }

        // read everything up until before current position
        $pre  = $this->substr($this->linebuffer, 0, $this->linepos);
        $post = $this->substr($this->linebuffer, $this->linepos);

        $this->linebuffer = $pre . $input . $post;
        $this->linepos += $this->strlen($input);

        // only redraw if input should be echo'ed (i.e. is not hidden anyway)
        if ($this->echo !== false) {
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
     * @deprecated use Stdio::setInput() instead
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
     * @deprecated use Stdio::getInput() instead
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
     * @uses self::limitHistory() to make sure list does not exceed limits
     * @deprecated use Stdio::addHistory() instead
     */
    public function addHistory($line)
    {
        $this->historyLines []= $line;

        return $this->limitHistory($this->historyLimit);
    }

    /**
     * Clears the complete history list
     *
     * @return self
     * @deprecated use Stdio::clearHistory() instead
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
     * @deprecated use Stdio::listHistory() instead
     */
    public function listHistory()
    {
        return $this->historyLines;
    }

    /**
     * Limits the history to a maximum of N entries and truncates the current history list accordingly
     *
     * @param int|null $limit
     * @return self
     * @deprecated use Stdio::limitHistory() instead
     */
    public function limitHistory($limit)
    {
        $this->historyLimit = $limit === null ? null : $limit;

        // limit send and currently exceeded
        if ($this->historyLimit !== null && isset($this->historyLines[$this->historyLimit])) {
            // adjust position in history according to new position after applying limit
            if ($this->historyPosition !== null) {
                $this->historyPosition -= count($this->historyLines) - $this->historyLimit;

                // current position will drop off from list => restore original
                if ($this->historyPosition < 0) {
                    $this->setInput($this->historyUnsaved);
                    $this->historyPosition = null;
                    $this->historyUnsaved = null;
                }
            }

            $this->historyLines = array_slice($this->historyLines, -$this->historyLimit, $this->historyLimit);
        }

        return $this;
    }

    /**
     * set autocompletion handler to use
     *
     * The autocomplete handler will be called whenever the user hits the TAB
     * key.
     *
     * @param callable|null $autocomplete
     * @return self
     * @throws \InvalidArgumentException if the given callable is invalid
     * @deprecated use Stdio::setAutocomplete() instead
     */
    public function setAutocomplete($autocomplete)
    {
        if ($autocomplete !== null && !is_callable($autocomplete)) {
            throw new \InvalidArgumentException('Invalid autocomplete function given');
        }

        $this->autocomplete = $autocomplete;

        return $this;
    }

    /**
     * Whether or not to emit a audible/visible BELL signal when using a disabled function
     *
     * By default, this class will emit a BELL signal when using a disable function,
     * such as using the <kbd>left</kbd> or <kbd>backspace</kbd> keys when
     * already at the beginning of the line.
     *
     * Whether or not the BELL is audible/visible depends on the termin and its
     * settings, i.e. some terminals may "beep" or flash the screen or emit a
     * short vibration.
     *
     * @param bool $bell
     * @return void
     * @internal use Stdio::setBell() instead
     */
    public function setBell($bell)
    {
        $this->bell = (bool)$bell;
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
        // Erase characters from cursor to end of line and then redraw actual input
        $this->output->write("\r\033[K" . $this->getDrawString());

        return $this;
    }

    /**
     * Returns the string that is used to draw the input prompt
     *
     * @return string
     * @internal
     */
    public function getDrawString()
    {
        $output = $this->prompt;
        if ($this->echo !== false) {
            if ($this->echo === true) {
                $buffer = $this->linebuffer;
            } else {
                $buffer = str_repeat($this->echo, $this->strlen($this->linebuffer));
            }

            // write output, then move back $reverse chars (by sending backspace)
            $output .= $buffer . str_repeat("\x08", $this->strwidth($buffer) - $this->getCursorCell());
        }

        return $output;
    }

    /** @internal */
    public function onKeyBackspace()
    {
        // left delete only if not at the beginning
        if ($this->linepos === 0) {
            $this->bell();
        } else {
            $this->deleteChar($this->linepos - 1);
        }
    }

    /** @internal */
    public function onKeyDelete()
    {
        // right delete only if not at the end
        if ($this->isEol()) {
            $this->bell();
        } else {
            $this->deleteChar($this->linepos);
        }
    }

    /** @internal */
    public function onKeyHome()
    {
        if ($this->move && $this->linepos !== 0) {
            $this->moveCursorTo(0);
        } else {
            $this->bell();
        }
    }

    /** @internal */
    public function onKeyEnd()
    {
        if ($this->move && !$this->isEol()) {
            $this->moveCursorTo($this->strlen($this->linebuffer));
        } else {
            $this->bell();
        }
    }

    /** @internal */
    public function onKeyTab()
    {
        if ($this->autocomplete === null) {
            $this->bell();
            return;
        }

        // current word prefix and offset for start of word in input buffer
        // "echo foo|bar world" will return just "foo" with word offset 5
        $word = $this->substr($this->linebuffer, 0, $this->linepos);
        $start = 0;
        $end = $this->linepos;

        // buffer prefix and postfix for everything that will *not* be matched
        // above example will return "echo " and "bar world"
        $prefix = '';
        $postfix = $this->substr($this->linebuffer, $this->linepos);

        // skip everything before last space
        $pos = strrpos($word, ' ');
        if ($pos !== false) {
            $prefix = (string)substr($word, 0, $pos + 1);
            $word = (string)substr($word, $pos + 1);
            $start = $this->strlen($prefix);
        }

        // skip double quote (") or single quote (') from argument
        $quote = null;
        if (isset($word[0]) && ($word[0] === '"' || $word[0] === '\'')) {
            $quote = $word[0];
            ++$start;
            $prefix .= $word[0];
            $word = (string)substr($word, 1);
        }

        // invoke autocomplete callback
        $words = call_user_func($this->autocomplete, $word, $start, $end);

        // return early if autocomplete does not return anything
        if ($words === null) {
            return;
        }

        // remove from list of possible words that do not start with $word or are duplicates
        $words = array_unique($words);
        if ($word !== '' && $words) {
            $words = array_filter($words, function ($w) use ($word) {
                return strpos($w, $word) === 0;
            });
        }

        // return if neither of the possible words match
        if (!$words) {
            $this->bell();
            return;
        }

        // search longest common prefix among all possible matches
        $found = reset($words);
        $all = count($words);
        if ($all > 1) {
            while ($found !== '') {
                // count all words that start with $found
                $matches = count(array_filter($words, function ($w) use ($found) {
                    return strpos($w, $found) === 0;
                }));

                // ALL words match $found => common substring found
                if ($all === $matches) {
                    break;
                }

                // remove last letter from $found and try again
                $found = $this->substr($found, 0, -1);
            }

            // found more than one possible match with this prefix => print options
            if ($found === $word || $found === '') {
                // limit number of possible matches
                if (count($words) > $this->autocompleteSuggestions) {
                    $more = count($words) - ($this->autocompleteSuggestions - 1);
                    $words = array_slice($words, 0, $this->autocompleteSuggestions - 1);
                    $words []= '(+' . $more . ' others)';
                }

                $this->output->write("\n" . implode('  ', $words) . "\n");
                $this->redraw();

                return;
            }
        }

        if ($quote !== null && $all === 1 && (strpos($postfix, $quote) === false || strpos($postfix, $quote) > strpos($postfix, ' '))) {
            // add closing quote if word started in quotes and postfix does not already contain closing quote before next space
            $found .= $quote;
        } elseif ($found === '') {
            // add single quotes around empty match
            $found = '\'\'';
        }

        if ($postfix === '' && $all === 1) {
            // append single space after match unless there's a postfix or there are multiple completions
            $found .= ' ';
        }

        // replace word in input with best match and adjust cursor
        $this->linebuffer = $prefix . $found . $postfix;
        $this->moveCursorBy($this->strlen($found) - $this->strlen($word));
    }

    /** @internal */
    public function onKeyEnter()
    {
        if ($this->echo !== false) {
            $this->output->write("\n");
        }
        $this->processLine("\n");
    }

    /** @internal */
    public function onKeyLeft()
    {
        if ($this->move && $this->linepos !== 0) {
            $this->moveCursorBy(-1);
        } else {
            $this->bell();
        }
    }

    /** @internal */
    public function onKeyRight()
    {
        if ($this->move && !$this->isEol()) {
            $this->moveCursorBy(1);
        } else {
            $this->bell();
        }
    }

    /** @internal */
    public function onKeyUp()
    {
        // ignore if already at top or history is empty
        if ($this->historyPosition === 0 || !$this->historyLines) {
            $this->bell();
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
            $this->bell();
            return;
        }

        if (isset($this->historyLines[$this->historyPosition + 1])) {
            // this is still a valid position => advance by one and apply
            $this->historyPosition++;
            $this->setInput($this->historyLines[$this->historyPosition]);
        } else {
            // moved beyond bottom => restore original unsaved input
            $this->setInput($this->historyUnsaved);
            $this->historyPosition = null;
            $this->historyUnsaved = null;
        }
    }

    /**
     * Will be invoked for character(s) that could not otherwise be processed by the sequencer
     *
     * @internal
     */
    public function onFallback($chars, EventEmitterInterface $base = null)
    {
        // check if there's any special key binding for any of the chars
        $buffer = '';
        foreach ($this->strsplit($chars) as $char) {
            // forward compatibility: check if any key binding exists on base Stdio instance
            // deprecated: check if any key binding exists on this Readline instance
            $emit = null;
            if ($base !== null && $base->listeners($char)) {
                $emit = $base;
            } else if ($this->listeners($char)) {
                $emit = $this;
            }

            if ($emit !== null) {
                // special key binding for this character found
                // process all characters before this one before invoking function
                if ($buffer !== '') {
                    $this->addInput($buffer);
                    $buffer = '';
                }
                $emit->emit($char, array($char));
            } else {
                $buffer .= $char;
            }
        }

        // process remaining input characters after last special key binding
        if ($buffer !== '') {
            $this->addInput($buffer);
        }
    }

    /**
     * delete a character at the given position
     *
     * Removing a character left to the current cursor will also move the cursor
     * to the left.
     *
     * @param int $n
     */
    private function deleteChar($n)
    {
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
    protected function processLine($eol)
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
        $this->emit('data', array($line . $eol));
    }

    /**
     * @param string $str
     * @return int
     * @codeCoverageIgnore
     */
    private function strlen($str)
    {
        // prefer mb_strlen() if available
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, $this->encoding);
        }

        // otherwise replace all unicode chars with dots and count dots
        return strlen(preg_replace('/./us', '.', $str));
    }

    /**
     * @param string $str
     * @param int    $start
     * @param ?int   $len
     * @return string
     * @codeCoverageIgnore
     */
    private function substr($str, $start = 0, $len = null)
    {
        if ($len === null) {
            $len = $this->strlen($str) - $start;
        }

        // prefer mb_substr() if available
        if (function_exists('mb_substr')) {
            return (string)mb_substr($str, $start, $len, $this->encoding);
        }

        // otherwise build array with all unicode chars and slice array
        preg_match_all('/./us', $str, $matches);

        return implode('', array_slice($matches[0], $start, $len));
    }

    /**
     * @internal
     * @param string $str
     * @return int
     * @codeCoverageIgnore
     */
    public function strwidth($str)
    {
        // prefer mb_strwidth() if available
        if (function_exists('mb_strwidth')) {
            return mb_strwidth($str, $this->encoding);
        }

        // otherwise replace each double-width unicode graphemes with two dots, all others with single dot and count number of dots
        // mbstring's list of double-width graphemes is *very* long: https://3v4l.org/GEg3u
        // let's use symfony's list from https://github.com/symfony/polyfill-mbstring/blob/e79d363049d1c2128f133a2667e4f4190904f7f4/Mbstring.php#L523
        // which looks like they originally came from http://www.cl.cam.ac.uk/~mgk25/ucs/wcwidth.c
        return strlen(preg_replace(
            array(
                '/[\x{1100}-\x{115F}\x{2329}\x{232A}\x{2E80}-\x{303E}\x{3040}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}]/u',
                '/./us',
            ),
            array(
                '..',
                '.',
            ),
            $str
        ));
    }

    /**
     * @param string $str
     * @return string[]
     */
    private function strsplit($str)
    {
        return preg_split('//u', $str, null, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @return bool
     */
    private function isEol()
    {
        return $this->linepos === $this->strlen($this->linebuffer);
    }

    /**
     * @return void
     */
    private function bell()
    {
        if ($this->bell) {
            $this->output->write("\x07"); // BEL a.k.a. \a
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if ($this->linebuffer !== '') {
            $this->processLine('');
        }

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
        $this->removeAllListeners();
    }
}
