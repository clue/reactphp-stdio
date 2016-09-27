<?php

use Clue\React\Stdio\Readline;
use React\Stream\ReadableStream;

class ReadlineTest extends TestCase
{
    private $input;
    private $output;
    private $readline;

    public function setUp()
    {
        $this->input = new ReadableStream();
        $this->output = $this->getMock('React\Stream\WritableStreamInterface');

        $this->readline = new Readline($this->input, $this->output);
    }

    public function testSettersReturnSelf()
    {
        $this->assertSame($this->readline, $this->readline->setEcho(true));
        $this->assertSame($this->readline, $this->readline->setMove(true));
        $this->assertSame($this->readline, $this->readline->setPrompt(''));
    }

    public function testInputStartsEmpty()
    {
        $this->assertEquals('', $this->readline->getPrompt());
        $this->assertEquals('', $this->readline->getInput());
        $this->assertEquals(0, $this->readline->getCursorPosition());
        $this->assertEquals(0, $this->readline->getCursorCell());
    }

    public function testGetInputAfterSetting()
    {
        $this->assertSame($this->readline, $this->readline->setInput('hello'));
        $this->assertEquals('hello', $this->readline->getInput());
        $this->assertEquals(5, $this->readline->getCursorPosition());
        $this->assertEquals(5, $this->readline->getCursorCell());
    }

    public function testPromptAfterSetting()
    {
        $this->assertSame($this->readline, $this->readline->setPrompt('> '));
        $this->assertEquals('> ' , $this->readline->getPrompt());
    }

    public function testSettingInputMovesCursorToEnd()
    {
        $this->readline->setInput('hello');
        $this->readline->moveCursorTo(3);

        $this->readline->setInput('testing');
        $this->assertEquals(7, $this->readline->getCursorPosition());
        $this->assertEquals(7, $this->readline->getCursorCell());
    }

    public function testSettingMoveOffDoesNotAllowDirectionKeysToChangePosition()
    {
        $this->readline->setInput('test');
        $this->readline->setMove(false);
        $this->readline->moveCursorTo(2);

        $this->readline->onKeyLeft();
        $this->assertEquals(2, $this->readline->getCursorPosition());

        $this->readline->onKeyRight();
        $this->assertEquals(2, $this->readline->getCursorPosition());

        $this->readline->onKeyHome();
        $this->assertEquals(2, $this->readline->getCursorPosition());

        $this->readline->onKeyEnd();
        $this->assertEquals(2, $this->readline->getCursorPosition());
    }

    public function testMultiByteInput()
    {
        $this->readline->setInput('täst');
        $this->assertEquals('täst', $this->readline->getInput());
        $this->assertEquals(4, $this->readline->getCursorPosition());
        $this->assertEquals(4, $this->readline->getCursorCell());
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

    public function testDataEventWillBeEmittedForCompleteLine()
    {
        $this->readline->on('data', $this->expectCallableOnceWith('hello'));

        $this->input->emit('data', array("hello\n"));
    }

    public function testDataEventWillNotBeEmittedForIncompleteLineButWillStayInInputBuffer()
    {
        $this->readline->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("hello"));

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testDataEventWillBeEmittedForCompleteLineAndRemainingWillStayInInputBuffer()
    {
        $this->readline->on('data', $this->expectCallableOnceWith('hello'));

        $this->input->emit('data', array("hello\nworld"));

        $this->assertEquals('world', $this->readline->getInput());
    }

    public function testDataEventWillBeEmittedForEmptyLine()
    {
        $this->readline->on('data', $this->expectCallableOnceWith(''));

        $this->input->emit('data', array("\n"));
    }

    public function testWriteSimpleCharWritesOnce()
    {
        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "k"));

        $this->input->emit('data', array('k'));
    }

    public function testWriteMultiByteCharWritesOnce()
    {
        $this->output->expects($this->once())->method('write')->with($this->equalTo("\r\033[K" . "\xF0\x9D\x84\x9E"));

        // "𝄞" – U+1D11E MUSICAL SYMBOL G CLEF
        $this->input->emit('data', array("\xF0\x9D\x84\x9E"));
    }

    public function testKeysHomeMovesToFront()
    {
        $this->readline->setInput('test');
        $this->readline->onKeyHome();

        $this->assertEquals(0, $this->readline->getCursorPosition());

        return $this->readline;
    }

    /**
     * @depends testKeysHomeMovesToFront
     * @param Readline $readline
     */
    public function testKeysEndMovesToEnd(Readline $readline)
    {
        $readline->onKeyEnd();

        $this->assertEquals(4, $readline->getCursorPosition());

        return $readline;
    }

    /**
     * @depends testKeysEndMovesToEnd
     * @param Readline $readline
     */
    public function testKeysLeftMovesToLeft(Readline $readline)
    {
        $readline->onKeyLeft();

        $this->assertEquals(3, $readline->getCursorPosition());

        return $readline;
    }

    /**
     * @depends testKeysLeftMovesToLeft
     * @param Readline $readline
     */
    public function testKeysRightMovesToRight(Readline $readline)
    {
        $readline->onKeyRight();

        $this->assertEquals(4, $readline->getCursorPosition());
    }

    public function testKeysSimpleChars()
    {
        $this->input->emit('data', array('hi!'));

        $this->assertEquals('hi!', $this->readline->getInput());
        $this->assertEquals(3, $this->readline->getCursorPosition());
        $this->assertEquals(3, $this->readline->getCursorCell());

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
        $this->assertEquals(2, $readline->getCursorCell());
    }

    public function testKeysMultiByteInput()
    {
        $this->input->emit('data', array('hä'));

        $this->assertEquals('hä', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());
        $this->assertEquals(2, $this->readline->getCursorCell());

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
        $this->assertEquals(1, $this->readline->getCursorCell());
    }

    public function testKeysBackspaceFrontDoesNothing()
    {
        $this->readline->setInput('test');
        $this->readline->moveCursorTo(0);

        $this->readline->onKeyBackspace();

        $this->assertEquals('test', $this->readline->getInput());
        $this->assertEquals(0, $this->readline->getCursorPosition());
        $this->assertEquals(0, $this->readline->getCursorCell());
    }

    public function testKeysDeleteMiddle()
    {
        $this->readline->setInput('test');
        $this->readline->moveCursorTo(2);

        $this->readline->onKeyDelete();

        $this->assertEquals('tet', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());
        $this->assertEquals(2, $this->readline->getCursorCell());
    }

    public function testKeysDeleteEndDoesNothing()
    {
        $this->readline->setInput('test');

        $this->readline->onKeyDelete();

        $this->assertEquals('test', $this->readline->getInput());
        $this->assertEquals(4, $this->readline->getCursorPosition());
        $this->assertEquals(4, $this->readline->getCursorCell());
    }

    public function testKeysPrependCharacterInFrontOfMultiByte()
    {
        $this->readline->setInput('ü');
        $this->readline->moveCursorTo(0);

        $this->input->emit('data', array('h'));

        $this->assertEquals('hü', $this->readline->getInput());
        $this->assertEquals(1, $this->readline->getCursorPosition());
        $this->assertEquals(1, $this->readline->getCursorCell());
    }

    public function testKeysWriteMultiByteAfterMultiByte()
    {
        $this->readline->setInput('ü');

        $this->input->emit('data', array('ä'));

        $this->assertEquals('üä', $this->readline->getInput());
        $this->assertEquals(2, $this->readline->getCursorPosition());
        $this->assertEquals(2, $this->readline->getCursorCell());
    }

    public function testKeysPrependMultiByteInFrontOfMultiByte()
    {
        $this->readline->setInput('ü');
        $this->readline->moveCursorTo(0);

        $this->input->emit('data', array('ä'));

        $this->assertEquals('äü', $this->readline->getInput());
        $this->assertEquals(1, $this->readline->getCursorPosition());
        $this->assertEquals(1, $this->readline->getCursorCell());
    }

    public function testDoubleWidthCharsOccupyTwoCells()
    {
        $this->readline->setInput('現');

        $this->assertEquals(1, $this->readline->getCursorPosition());
        $this->assertEquals(2, $this->readline->getCursorCell());

        return $this->readline;
    }

    /**
     * @depends testDoubleWidthCharsOccupyTwoCells
     * @param Readline $readline
     */
    public function testDoubleWidthCharMoveToStart(Readline $readline)
    {
        $readline->moveCursorTo(0);

        $this->assertEquals(0, $readline->getCursorPosition());
        $this->assertEquals(0, $readline->getCursorCell());

        return $readline;
    }

    /**
     * @depends testDoubleWidthCharMoveToStart
     * @param Readline $readline
     */
    public function testDoubleWidthCharMovesTwoCellsForward(Readline $readline)
    {
        $readline->moveCursorBy(1);

        $this->assertEquals(1, $readline->getCursorPosition());
        $this->assertEquals(2, $readline->getCursorCell());

        return $readline;
    }

    /**
     * @depends testDoubleWidthCharMovesTwoCellsForward
     * @param Readline $readline
     */
    public function testDoubleWidthCharMovesTwoCellsBackward(Readline $readline)
    {
        $readline->moveCursorBy(-1);

        $this->assertEquals(0, $readline->getCursorPosition());
        $this->assertEquals(0, $readline->getCursorCell());
    }

    public function testCursorCellIsAlwaysZeroIfEchoIsOff()
    {
        $this->readline->setInput('test');
        $this->readline->setEcho(false);

        $this->assertEquals(4, $this->readline->getCursorPosition());
        $this->assertEquals(0, $this->readline->getCursorCell());
    }

    public function testCursorCellAccountsForDoubleWidthCharacters()
    {
        $this->readline->setInput('現現現現');
        $this->readline->moveCursorTo(3);

        $this->assertEquals(3, $this->readline->getCursorPosition());
        $this->assertEquals(6, $this->readline->getCursorCell());

        return $this->readline;
    }

    /**
     * @depends testCursorCellAccountsForDoubleWidthCharacters
     * @param Readline $readline
     */
    public function testCursorCellObeysCustomEchoAsterisk(Readline $readline)
    {
        $readline->setEcho('*');

        $this->assertEquals(3, $readline->getCursorPosition());
        $this->assertEquals(3, $readline->getCursorCell());
    }

    public function testEmitEmptyInputOnEnter()
    {
        $this->readline->on('data', $this->expectCallableOnceWith(''));
        $this->readline->onKeyEnter();
    }

    public function testEmitInputOnEnterAndClearsInput()
    {
        $this->readline->setInput('test');
        $this->readline->on('data', $this->expectCallableOnceWith('test'));
        $this->readline->onKeyEnter();

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testSetInputDuringEmitKeepsInput()
    {
        $readline = $this->readline;

        $readline->on('data', function ($line) use ($readline) {
            $readline->setInput('test');
        });
        $readline->onKeyEnter();

        $this->assertEquals('test', $readline->getInput());
    }

    public function testWillRedrawEmptyPromptOnEnter()
    {
        $this->readline->setPrompt('> ');

        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->onKeyEnter();

        $this->assertEquals("\n\r\033[K" . "> ", $buffer);
    }

    public function testWillRedrawEmptyPromptOnEnterWithData()
    {
        $this->readline->setPrompt('> ');
        $this->readline->setInput('test');

        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->onKeyEnter();

        $this->assertEquals("\n\r\033[K" . "> ", $buffer);
    }

    public function testWillNotRedrawPromptOnEnterWhenEchoIsOff()
    {
        $this->readline->setEcho(false);
        $this->readline->setPrompt('> ');

        $this->output->expects($this->never())->method('write');

        $this->readline->onKeyEnter();
    }

    public function testEmitErrorWillEmitErrorAndClose()
    {
        $this->readline->on('error', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->readline->isReadable());
    }

    public function testEmitEndWillEmitEndAndClose()
    {
        $this->readline->on('end', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('end');

        $this->assertFalse($this->readline->isReadable());
    }

    public function testEmitCloseWillEmitClose()
    {
        $this->readline->on('end', $this->expectCallableNever());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('close');

        $this->assertFalse($this->readline->isReadable());
    }

    public function testClosedStdinWillCloseReadline()
    {
        $this->input = $this->getMock('React\Stream\ReadableStreamInterface');
        $this->input->expects($this->once())->method('isReadable')->willReturn(false);

        $this->readline = new Readline($this->input, $this->output);

        $this->assertFalse($this->readline->isReadable());
    }

    public function testPauseWillBeForwarded()
    {
        $this->input = $this->getMock('React\Stream\ReadableStreamInterface');
        $this->input->expects($this->once())->method('pause');

        $this->readline = new Readline($this->input, $this->output);

        $this->readline->pause();
    }

    public function testResumeWillBeForwarded()
    {
        $this->input = $this->getMock('React\Stream\ReadableStreamInterface');
        $this->input->expects($this->once())->method('resume');

        $this->readline = new Readline($this->input, $this->output);

        $this->readline->resume();
    }

    public function testPipeWillReturnDest()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $this->readline->pipe($dest);

        $this->assertEquals($dest, $ret);
    }
}
