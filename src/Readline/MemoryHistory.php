<?php

namespace Clue\React\Stdio\Readline;

use Clue\React\Stdio\Readline;

class MemoryHistory implements History
{
    private $lines = array();
    private $position = null;
    private $unsaved = null;

    public function addLine($line)
    {
        if ($line === '') {
            return;
        }

        $this->lines []= $line;
        $this->position = null;
    }

    public function moveUp(Readline $readline)
    {
        // ignore if already at top or history is empty
        if ($this->position === 0 || !$this->lines) {
            return;
        }

        if ($this->position === null) {
            // first time up => move to last entry
            $this->position = count($this->lines) - 1;
            $this->unsaved = $readline->getInput();
        } else {
            // somewhere in the list => move by one
            $this->position--;
        }

        $readline->setInput($this->lines[$this->position]);
    }

    public function moveDown(Readline $readline)
    {
        if ($this->position === null) {
            return;
        }

        if (($this->position + 1) < count($this->lines)) {
            // this is still a valid position => advance by one and apply
            $this->position++;
            $readline->setInput($this->lines[$this->position]);
        } else {
            // moved beyond bottom => restore original unsaved input
            $readline->setInput($this->unsaved);
            $this->position = null;
        }
    }
}
