<?php

use Clue\React\Stdio\Readline;

class ReadlineTest extends TestCase
{
    public function setUp()
    {
        $this->output = $this->getMockBuilder('Clue\React\Stdio\Stdout')->disableOriginalConstructor()->getMock();
        $this->readline = new Readline($this->output);
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
        $this->assertEquals(0, $this->readline->getCursorPosition());
    }

    public function testGetInputAfterSetting()
    {
        $this->assertSame($this->readline, $this->readline->setInput('hello'));
        $this->assertEquals('hello', $this->readline->getInput());
        $this->assertEquals(5, $this->readline->getCursorPosition());
    }

    public function testSettingInputMovesCursorToEnd()
    {
        $this->readline->setInput('hello');
        $this->readline->moveCursorTo(3);

        $this->readline->setInput('testing');
        $this->assertEquals(7, $this->readline->getCursorPosition());
    }

    public function testMultiByteInput()
    {
        $this->readline->setInput('täst');
        $this->assertEquals('täst', $this->readline->getInput());
        $this->assertEquals(4, $this->readline->getCursorPosition());
    }

    public function testRedrawingReadlineWritesToOutputOnce()
    {
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');
        $this->readline->moveCursorBy(-2);

        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "> " . "test" . "\x08\x08"));
        $this->assertSame($this->readline, $this->readline->redraw());
    }

    public function testSettingSameSettingsDoesNotNeedToRedraw()
    {
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');
        $this->readline->moveCursorBy(-2);

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->setPrompt('> '));
        $this->assertSame($this->readline, $this->readline->setInput('test'));
        $this->assertSame($this->readline, $this->readline->moveCursorTo(2));
    }

    public function testSettingEchoOnWithInputDoesRedraw()
    {
        $this->readline->setEcho(false);
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');

        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "> " . "test"));

        $this->assertSame($this->readline, $this->readline->setEcho(true));
    }

    public function testSettingEchoAsteriskWithInputDoesRedraw()
    {
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');

        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "> " . "****"));

        $this->assertSame($this->readline, $this->readline->setEcho('*'));
    }

    public function testSettingEchoOffWithInputDoesRedraw()
    {
        $this->readline->setEcho(true);
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');

        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "> "));

        $this->assertSame($this->readline, $this->readline->setEcho(false));
    }

    public function testSettingEchoWithoutInputDoesNotNeedToRedraw()
    {
        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->setEcho(false));
        $this->assertSame($this->readline, $this->readline->setEcho(true));
    }

    public function testSettingInputDoesRedraw()
    {
        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "test"));
        $this->assertSame($this->readline, $this->readline->setInput('test'));
    }

    public function testSettingInputWithEchoAsteriskDoesRedraw()
    {
        $this->readline->setEcho('*');

        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "****"));

        $this->assertSame($this->readline, $this->readline->setInput('test'));
    }

    public function testSettingInputWithSameLengthWithEchoAsteriskDoesNotNeedToRedraw()
    {
        $this->readline->setInput('test');
        $this->readline->setEcho('*');

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->setInput('demo'));
    }

    public function testSettingInputWithoutEchoDoesNotNeedToRedraw()
    {
        $this->readline->setEcho(false);

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->setInput('test'));
    }

    public function testMovingCursorWithoutEchoDoesNotNeedToRedraw()
    {
        $this->readline->setEcho(false);
        $this->readline->setInput('test');

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->moveCursorTo(0));
        $this->assertSame($this->readline, $this->readline->moveCursorBy(2));
    }
}
