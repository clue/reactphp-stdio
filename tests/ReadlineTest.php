<?php

use Clue\React\Stdio\Readline;

class ReadlineTest extends TestCase
{
    public function setUp()
    {
        $output = $this->getMockBuilder('Clue\React\Stdio\Stdout')->disableOriginalConstructor()->getMock();
        $this->readline = new Readline($output);
    }

    public function testSettersReturnSelf()
    {
        $this->assertSame($this->readline, $this->readline->setEcho(true));
        $this->assertSame($this->readline, $this->readline->setMove(true));
        $this->assertSame($this->readline, $this->readline->setPrompt(''));
    }

    public function testInputStartsEmpty()
    {
        $this->assertEquals('', $this->readline->getInput());
    }

    public function testGetInputAfterSetting()
    {
        $this->assertSame($this->readline, $this->readline->setInput('hello'));
        $this->assertEquals('hello', $this->readline->getInput());
    }
}
