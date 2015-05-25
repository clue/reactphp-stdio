<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline\Autocomplete;
use Clue\React\Stdio\Readline;

class WordAutocomplete implements Autocomplete
{
    private $charset = 'UTF-8';

    public function __construct(array $words)
    {
        $this->words = $words;
    }

    public function go(Readline $readline)
    {
        $input = $readline->getInput();
        $cursor = $readline->getCursorPosition();

        $search = mb_substr($input, 0, $cursor, $this->charset);
        $prefix = '';
        $postfix = (string)mb_substr($input, $cursor, null, $this->charset);

        // skip everything before last space
        $pos = strrpos($search, ' ');
        if ($pos !== false) {
            $prefix = substr($search, 0, $pos + 1);
            $search = (string)substr($search, $pos + 1);
        }

        $len = strlen($search);

        if ($len === 0) {
            // cursor at the beginning => do not match against anything
            return;
        }

        $found = array();

        foreach ($this->words as $word) {
            // TODO: only check for leading substring
            if ($search === substr($word, 0, $len)) {
                $found []= $word;
            }
        }

        if ($found) {
            // TODO: always picks first match for now

            $found = $found[0];

            if ($postfix === '') {
                $found .= ' ';
            }

            $readline->setInput($prefix . $found . $postfix);
            $readline->moveCursorTo($cursor + $this->strlen($found) - $this->strlen($search));
        }
    }

    private function strlen($str)
    {
        return mb_strlen($str, $this->charset);
    }
}
