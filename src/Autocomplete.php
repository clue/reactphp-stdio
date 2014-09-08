<?php

namespace Clue\React\Stdio;

class Autocomplete
{
    public function run(Readline $readline)
    {

    }

    protected function getWord(Readline $readline)
    {
        return $readline->getBuffer();
    }
}

class Words extends Autocomplete
{
    private $words;

    public function __construct(array $words)
    {
        $this->words = $words;
    }

    public function run(Readline $readline)
    {

    }
}
