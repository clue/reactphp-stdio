<?php

namespace Clue\React\Stdio;

class History
{
    private $lines = array();

    public function addLine($line)
    {
        $this->history []= $line;
    }

    public function moveUp()
    {

    }

    public function moveDown()
    {

    }
}
