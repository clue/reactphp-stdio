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

    public function testWriteSimpleCharWritesOnce()
    {
        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "k"));

        $this->pushInputBytes($this->readline, 'k');
    }

    public function testWriteMultiByteCharWritesOnce()
    {
        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "\xF0\x9D\x84\x9E"));

        // "𝄞" – U+1D11E MUSICAL SYMBOL G CLEF
        $this->pushInputBytes($this->readline, "\xF0\x9D\x84\x9E");
    }

    public function testKeysSimpleChars()
    {
        $this->pushInputBytes($this->readline, 'hi!');

        $this->assertEquals('hi!', $this->readline->getInput());
        $this->assertEquals(3, $this->readline->getCursorPosition());

        return $this->readline;
    }

    /**
     * @depends testKeysSimpleChars
     * @param Readline $readline
     */
    public function testKeysBackspaceDeletesLastCharacter(Readline $readline)
    {
        $readline->onKeyBackspace();

        $this->assertEquals('hi', $readline->getInput());
        $this->assertEquals(2, $readline->getCursorPosition());
    }

    public function testKeysMultiByteInput()
    {
        $this->pushInputBytes($this->readline, 'hä');

        $this->assertEquals('hä', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());

        return $this->readline;
    }

    /**
     * @depends testKeysMultiByteInput
     * @param Readline $readline
     */
    public function testKeysBackspaceDeletesWholeMultibyteCharacter(Readline $readline)
    {
        $readline->onKeyBackspace();

        $this->assertEquals('h', $readline->getInput());
    }

    public function testKeysBackspaceMiddle()
    {
        $this->readline->setInput('test');
        $this->readline->moveCursorTo(2);

        $this->readline->onKeyBackspace();

        $this->assertEquals('tst', $this->readline->getInput());
        $this->assertEquals(1, $this->readline->getCursorPosition());
    }

    public function testKeysBackspaceFrontDoesNothing()
    {
        $this->readline->setInput('test');
        $this->readline->moveCursorTo(0);

        $this->readline->onKeyBackspace();

        $this->assertEquals('test', $this->readline->getInput());
        $this->assertEquals(0, $this->readline->getCursorPosition());
    }

    public function testKeysDeleteMiddle()
    {
        $this->readline->setInput('test');
        $this->readline->moveCursorTo(2);

        $this->readline->onKeyDelete();

        $this->assertEquals('tet', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());
    }

    public function testKeysDeleteEndDoesNothing()
    {
        $this->readline->setInput('test');

        $this->readline->onKeyDelete();

        $this->assertEquals('test', $this->readline->getInput());
        $this->assertEquals(4, $this->readline->getCursorPosition());
    }

    public function testKeysPrependCharacterInFrontOfMultiByte()
    {
        $this->readline->setInput('ü');
        $this->readline->moveCursorTo(0);

        $this->pushInputBytes($this->readline, 'h');

        $this->assertEquals('hü', $this->readline->getInput());
        $this->assertEquals(1, $this->readline->getCursorPosition());
    }

    public function testKeysWriteMultiByteAfterMultiByte()
    {
        $this->readline->setInput('ü');

        $this->pushInputBytes($this->readline, 'ä');

        $this->assertEquals('üä', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());
    }

    public function testKeysPrependMultiByteInFrontOfMultiByte()
    {
        $this->readline->setInput('ü');
        $this->readline->moveCursorTo(0);

        $this->pushInputBytes($this->readline, 'ä');

        $this->assertEquals('äü', $this->readline->getInput());
        $this->assertEquals(1, $this->readline->getCursorPosition());
    }

    private function pushInputBytes(Readline $readline, $bytes)
    {
        foreach (str_split($bytes, 1) as $byte) {
            $readline->onChar($byte);
        }
    }
}
