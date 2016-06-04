<?php

use React\EventLoop\Factory;
use Clue\React\Stdio\Stdio;
use Clue\React\Stdio\Readline;

class StdioTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = Factory::create();
    }

    public function testCtorDefaultArgs()
    {
        $stdio = new Stdio($this->loop);
        $stdio->close();
    }

    public function testCtorArgsWillBeReturnedByGetters()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $this->assertSame($input, $stdio->getInput());
        $this->assertSame($output, $stdio->getOutput());
        $this->assertSame($readline, $stdio->getReadline());
    }
}
