<?php

namespace Clue\Stdio\React;

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
