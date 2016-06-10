<?php

use React\EventLoop\Factory;
use Clue\React\Stdio\Stdio;

class StdioTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = Factory::create();
    }

    public function testCtor()
    {
        $stdio = new Stdio($this->loop);
    }
}
