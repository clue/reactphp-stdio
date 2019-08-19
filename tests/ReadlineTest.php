<?php

use Clue\React\Stdio\Readline;
use React\Stream\ThroughStream;
use Evenement\EventEmitter;

class ReadlineTest extends TestCase
{
    private $input;
    private $output;
    private $readline;

    public function setUp()
    {
        $this->input = new ThroughStream();
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

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

    public function testAddInputAfterSetting()
    {
        $this->readline->setInput('hello');

        $this->assertSame($this->readline, $this->readline->addInput(' world'));
        $this->assertEquals('hello world', $this->readline->getInput());
        $this->assertEquals(11, $this->readline->getCursorPosition());
        $this->assertEquals(11, $this->readline->getCursorCell());
    }

    public function testAddInputAfterSettingCurrentCursorPosition()
    {
        $this->readline->setInput('hello');
        $this->readline->moveCursorTo(2);

        $this->assertSame($this->readline, $this->readline->addInput('ha'));
        $this->assertEquals('hehallo', $this->readline->getInput());
        $this->assertEquals(4, $this->readline->getCursorPosition());
        $this->assertEquals(4, $this->readline->getCursorCell());
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

    public function testAddingEmptyInputDoesNotNeedToRedraw()
    {
        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->addInput(''));
    }

    public function testAddingInputWithoutEchoDoesNotNeedToRedraw()
    {
        $this->readline->setEcho(false);

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->addInput('test'));
    }

    public function testMovingCursorWithoutEchoDoesNotNeedToRedraw()
    {
        $this->readline->setEcho(false);
        $this->readline->setInput('test');

        $this->output->expects($this->never())->method('write');

        $this->assertSame($this->readline, $this->readline->moveCursorTo(0));
        $this->assertSame($this->readline, $this->readline->moveCursorBy(2));
    }

    public function testDataEventWillBeEmittedForCompleteLineWithNl()
    {
        $this->readline->on('data', $this->expectCallableOnceWith("hello\n"));

        $this->input->emit('data', array("hello\n"));
    }

    public function testDataEventWillBeEmittedWithNlAlsoForCompleteLineWithCr()
    {
        $this->readline->on('data', $this->expectCallableOnceWith("hello\n"));

        $this->input->emit('data', array("hello\r"));
    }

    public function testDataEventWillNotBeEmittedForIncompleteLineButWillStayInInputBuffer()
    {
        $this->readline->on('data', $this->expectCallableNever());

        $this->input->emit('data', array("hello"));

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testDataEventWillBeEmittedForCompleteLineAndRemainingWillStayInInputBuffer()
    {
        $this->readline->on('data', $this->expectCallableOnceWith("hello\n"));

        $this->input->emit('data', array("hello\nworld"));

        $this->assertEquals('world', $this->readline->getInput());
    }

    public function testDataEventWillBeEmittedForEmptyLine()
    {
        $this->readline->on('data', $this->expectCallableOnceWith("\n"));

        $this->input->emit('data', array("\n"));
    }

    public function testEndInputWithoutDataOnCtrlD()
    {
        $this->readline->on('data', $this->expectCallableNever());
        $this->readline->on('end', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("\x04"));
    }

    public function testEndInputWithIncompleteLineOnCtrlD()
    {
        $this->readline->on('data', $this->expectCallableOnceWith('hello'));
        $this->readline->on('end', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("hello\x04"));
    }

    public function testCloseWillEmitCloseEventAndCloseInputStream()
    {
        $this->input->on('close', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->readline->close();

        $this->assertEquals(array(), $this->readline->listeners('close'));
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

    public function testKeysHomeEmitsBellWhenAlreadyAtBeginningOfLine()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyHome();
    }

    public function testKeysHomeDoesNotEmitBellWhenAlreadyAtBeginningOfLineButBellIsDisabled()
    {
        $this->output->expects($this->never())->method('write');
        $this->readline->setBell(false);
        $this->readline->onKeyHome();
    }

    public function testKeysHomeEmitsBellWhenAlreadyAtBeginningOfLineAndBellIsEnabledAgain()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->setBell(false);
        $this->readline->setBell(true);
        $this->readline->onKeyHome();
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

    public function testKeysEndEmitsBellWhenAlreadyAtEndOfLine()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyEnd();
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

    public function testKeysLeftEmitsBellWhenAlreadyAtBeginningOfLine()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyLeft();
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

    public function testKeysRightEmitsBellWhenAlreadyAtEndOfLine()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyRight();
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

    public function testKeysBackspaceEmitsBellWhenAlreadyAtBeginningOfLine()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyBackspace();
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

    public function testKeysDeleteEmitsBellWhenAlreadyAtEndOfLine()
    {
        $this->readline->setInput('test');

        $this->output->expects($this->once())->method('write')->with("\x07");
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

    public function testAutocompleteReturnsSelf()
    {
        $this->assertSame($this->readline, $this->readline->setAutocomplete(function () { }));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAutocompleteThrowsIfNotCallable()
    {
        $this->assertSame($this->readline, $this->readline->setAutocomplete(123));
    }

    public function testAutocompleteKeyEmitsBellWhenAutocompleteIsNotSet()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledOnTab()
    {
        $this->readline->setAutocomplete($this->expectCallableOnce());

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillNotBeCalledAfterUnset()
    {
        $this->readline->setAutocomplete($this->expectCallableNever());
        $this->readline->setAutocomplete(null);

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithEmptyBuffer()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('', 0, 0));

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithCompleteWord()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('hello', 0, 5));

        $this->readline->setInput('hello');

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithWordPrefix()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('he', 0, 2));

        $this->readline->setInput('hello');
        $this->readline->moveCursorTo(2);

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithLastWord()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('world', 6, 11));

        $this->readline->setInput('hello world');

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithLastWordPrefix()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('wo', 6, 8));

        $this->readline->setInput('hello world');
        $this->readline->moveCursorTo(8);

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithLastWordPrefixUnicode()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('wö', 6, 8));

        $this->readline->setInput('hällö wörld');
        $this->readline->moveCursorTo(8);

        $this->readline->onKeyTab();
    }

    public function testAutocompleteWillBeCalledWithLastWordPrefixQuotedUnicode()
    {
        $this->readline->setAutocomplete($this->expectCallableOnceWith('wö', 9, 11));

        $this->readline->setInput('"hällö" "wörld"');
        $this->readline->moveCursorTo(11);

        $this->readline->onKeyTab();
    }

    public function testAutocompleteAddsSpaceAfterComplete()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('exit');

        $this->readline->onKeyTab();

        $this->assertEquals('exit ', $this->readline->getInput());
    }

    public function testAutocompleteAddsSpaceAfterSecondWordIsComplete()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('exit ex');

        $this->readline->onKeyTab();

        $this->assertEquals('exit exit ', $this->readline->getInput());
    }

    public function testAutocompleteAddsSpaceAfterCompleteWithClosingDoubleQuote()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('"exit');

        $this->readline->onKeyTab();

        $this->assertEquals('"exit" ', $this->readline->getInput());
    }

    public function testAutocompleteAddsSpaceAfterCompleteWithClosingSingleQuote()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('\'exit');

        $this->readline->onKeyTab();

        $this->assertEquals('\'exit\' ', $this->readline->getInput());
    }

    public function testAutocompleteAddsSpaceAfterSecondWordIsCompleteWithClosingDoubleQuote()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('exit "exit');

        $this->readline->onKeyTab();

        $this->assertEquals('exit "exit" ', $this->readline->getInput());
    }

    public function testAutocompleteStaysInQuotedStringAtEnd()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        // move cursor before closing quote
        $this->readline->setInput('exit "ex"');
        $this->readline->moveCursorBy(-1);

        $this->readline->onKeyTab();

        $this->assertEquals('exit "exit"', $this->readline->getInput());
        $this->assertEquals(10, $this->readline->getCursorPosition());
    }

    public function testAutocompleteStaysInQuotedStringInMiddle()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        // move cursor before closing quote
        $this->readline->setInput('exit "ex" exit');
        $this->readline->moveCursorTo(8);

        $this->readline->onKeyTab();

        $this->assertEquals('exit "exit" exit', $this->readline->getInput());
        $this->assertEquals(10, $this->readline->getCursorPosition());
    }

    public function testAutocompleteAddsClosingSingleQuoteAndSpaceWhenMatchingEmptyString()
    {
        $this->readline->setAutocomplete(function () { return array(''); });

        $this->readline->setInput('\'');

        $this->readline->onKeyTab();

        $this->assertEquals('\'\' ', $this->readline->getInput());
    }

    public function testAutocompleteAddsClosingDoubleQuoteAndSpaceWhenMatchingEmptyString()
    {
        $this->readline->setAutocomplete(function () { return array(''); });

        $this->readline->setInput('"');

        $this->readline->onKeyTab();

        $this->assertEquals('"" ', $this->readline->getInput());
    }

    public function testAutocompleteAddsSingleQuotesAndSpaceWhenMatchingEmptyString()
    {
        $this->readline->setAutocomplete(function () { return array(''); });

        $this->readline->onKeyTab();

        $this->assertEquals('\'\' ', $this->readline->getInput());
    }

    public function testAutocompletePicksFirstComplete()
    {
        $this->readline->setAutocomplete(function () { return array('exit'); });

        $this->readline->setInput('e');

        $this->readline->onKeyTab();

        $this->assertEquals('exit ', $this->readline->getInput());
    }

    public function testAutocompleteIgnoresNonMatchingAndEmitsBell()
    {
        $this->readline->setAutocomplete(function () { return array('quit'); });

        $this->readline->setInput('e');

        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyTab();

        $this->assertEquals('e', $this->readline->getInput());
    }

    public function testAutocompletePicksNoneWhenEmptyAndMultipleMatch()
    {
        $this->readline->setAutocomplete(function () { return array('first', 'second'); });

        $this->readline->onKeyTab();

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testAutocompletePicksOnlyEntryWhenEmpty()
    {
        $this->readline->setAutocomplete(function () { return array('first'); });

        $this->readline->onKeyTab();

        $this->assertEquals('first ', $this->readline->getInput());
    }

    public function testAutocompleteUsesCommonPrefixWhenMultipleMatch()
    {
        $this->readline->setAutocomplete(function () { return array('first', 'firm'); });

        $this->readline->onKeyTab();

        $this->assertEquals('fir', $this->readline->getInput());
    }

    public function testAutocompleteUsesCommonPrefixWithoutClosingQUotesWhenMultipleMatchAfterQuotes()
    {
        $this->readline->setAutocomplete(function () { return array('first', 'firm'); });

        $this->readline->setInput('"');

        $this->readline->onKeyTab();

        $this->assertEquals('"fir', $this->readline->getInput());
    }

    public function testAutocompleteUsesCommonPrefixBetweenQuotesWhenMultipleMatchBetweenQuotes()
    {
        $this->readline->setAutocomplete(function () { return array('first', 'firm'); });

        $this->readline->setInput('""');
        $this->readline->moveCursorBy(-1);

        $this->readline->onKeyTab();

        $this->assertEquals('"fir"', $this->readline->getInput());
    }

    public function testAutocompleteUsesExactMatchWhenDuplicateMatch()
    {
        $this->readline->setAutocomplete(function () { return array('first', 'first'); });

        $this->readline->onKeyTab();

        $this->assertEquals('first ', $this->readline->getInput());
    }

    public function testAutocompleteUsesCommonPrefixWhenMultipleMatchAndEnd()
    {
        $this->readline->setAutocomplete(function () { return array('counter', 'count'); });

        $this->readline->onKeyTab();

        $this->assertEquals('count', $this->readline->getInput());
    }

    public function testAutocompleteShowsAvailableOptionsWhenMultipleMatch()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return array('a', 'b'); });

        $this->readline->onKeyTab();

        $this->assertContains("\na  b\n", $buffer);
    }

    public function testAutocompleteShowsAvailableOptionsWhenMultipleMatchWithEmptyWord()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return array('', 'a'); });

        $this->readline->onKeyTab();

        $this->assertContains("\n  a\n", $buffer);
    }

    public function testAutocompleteShowsAvailableOptionsWhenMultipleMatchIncompleteWord()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return array('hello', 'hellu'); });

        $this->readline->setInput('hell');

        $this->readline->onKeyTab();

        $this->assertContains("\nhello  hellu\n", $buffer);
    }

    public function testAutocompleteShowsAvailableOptionsWhenMultipleMatchIncompleteWordWithUmlauts()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return array('hällö', 'hällü'); });

        $this->readline->setInput('häll');

        $this->readline->onKeyTab();

        $this->assertContains("\nhällö  hällü\n", $buffer);
    }

    public function testAutocompleteShowsAvailableOptionsWithoutDuplicatesWhenMultipleMatch()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return array('a', 'b', 'b', 'a'); });

        $this->readline->onKeyTab();

        $this->assertContains("\na  b\n", $buffer);
    }

    public function testAutocompleteShowsLimitedNumberOfAvailableOptionsWhenMultipleMatch()
    {
        $buffer = '';
        $this->output->expects($this->atLeastOnce())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $this->readline->setAutocomplete(function () { return range('a', 'z'); });

        $this->readline->onKeyTab();

        $this->assertContains("\na  b  c  d  e  f  g  (+19 others)\n", $buffer);
    }

    public function testBindCustomFunctionFromBase()
    {
        $base = new EventEmitter();
        $base->on('a', $this->expectCallableOnceWith('a'));

        $this->readline = new Readline($this->input, $this->output, $base);
        $this->input->emit('data', array('a'));
    }

    public function testBindCustomFunctionOverwritesInput()
    {
        $this->readline->on('a', $this->expectCallableOnceWith('a'));

        $this->input->emit('data', array("a"));

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testBindCustomFunctionOverwritesInputButKeepsRest()
    {
        $this->readline->on('e', $this->expectCallableOnceWith('e'));

        $this->input->emit('data', array("test"));

        $this->assertEquals('tst', $this->readline->getInput());
    }

    public function testBindCustomFunctionCanOverwriteInput()
    {
        $readline = $this->readline;
        $readline->on('a', function () use ($readline) {
            $readline->addInput('ä');
        });

        $this->input->emit('data', array("hallo"));

        $this->assertEquals('hällo', $this->readline->getInput());
    }

    public function testBindCustomFunctionCanOverwriteAutocompleteBehavior()
    {
        $this->readline->on("\t", $this->expectCallableOnceWith("\t"));
        $this->readline->setAutocomplete($this->expectCallableNever());

        $this->input->emit('data', array("\t"));
    }

    public function testBindCustomFunctionToNlOverwritesDataEvent()
    {
        $this->readline->on("\n", $this->expectCallableOnceWith("\n"));
        $this->readline->on('line', $this->expectCallableNever());

        $this->input->emit('data', array("hello\n"));
    }

    public function testBindCustomFunctionToNlFiresOnCr()
    {
        $this->readline->on("\n", $this->expectCallableOnceWith("\n"));
        $this->readline->on("\r", $this->expectCallableNever());
        $this->readline->on('line', $this->expectCallableNever());

        $this->input->emit('data', array("hello\r"));
    }

    public function testBindCustomFunctionFromBaseCanOverwriteAutocompleteBehavior()
    {
        $base = new EventEmitter();
        $base->on("\t", $this->expectCallableOnceWith("\t"));

        $this->readline = new Readline($this->input, $this->output, $base);
        $this->readline->setAutocomplete($this->expectCallableNever());

        $this->input->emit('data', array("\t"));
    }

    public function testEmitEmptyInputOnEnter()
    {
        $this->readline->on('data', $this->expectCallableOnceWith("\n"));
        $this->readline->onKeyEnter();
    }

    public function testEmitInputOnEnterAndClearsInput()
    {
        $this->readline->setInput('test');
        $this->readline->on('data', $this->expectCallableOnceWith("test\n"));
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
        $this->readline->on('data', $this->expectCallableNever());
        $this->readline->on('end', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('end');

        $this->assertFalse($this->readline->isReadable());
    }

    public function testEmitEndAfterDataWillEmitDataAndEndAndClose()
    {
        $this->readline->on('data', $this->expectCallableOnce('hello'));
        $this->readline->on('end', $this->expectCallableOnce());
        $this->readline->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array('hello'));
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
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('isReadable')->willReturn(false);

        $this->readline = new Readline($this->input, $this->output);

        $this->assertFalse($this->readline->isReadable());
    }

    public function testPauseWillBeForwarded()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('pause');

        $this->readline = new Readline($this->input, $this->output);

        $this->readline->pause();
    }

    public function testResumeWillBeForwarded()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->input->expects($this->once())->method('resume');

        $this->readline = new Readline($this->input, $this->output);

        $this->readline->resume();
    }

    public function testPipeWillReturnDest()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $this->readline->pipe($dest);

        $this->assertEquals($dest, $ret);
    }

    public function testHistoryStartsEmpty()
    {
        $this->assertEquals(array(), $this->readline->listHistory());
    }

    public function testHistoryAddReturnsSelf()
    {
        $this->assertSame($this->readline, $this->readline->addHistory('hello'));
    }

    public function testHistoryAddEndsUpInList()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');
        $this->readline->addHistory('c');

        $this->assertEquals(array('a', 'b', 'c'), $this->readline->listHistory());
    }

    public function testHistoryUpEmptyDoesNotChangeInputAndEmitsBell()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyUp();

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testHistoryUpCyclesToLast()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();

        $this->assertEquals('b', $this->readline->getInput());
    }

    public function testHistoryUpBeyondTopCyclesToFirst()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();
        $this->readline->onKeyUp();
        $this->readline->onKeyUp();

        $this->assertEquals('a', $this->readline->getInput());
    }

    public function testHistoryUpAndThenEnterRestoresCycleToBottom()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();

        $this->readline->onKeyEnter();

        $this->readline->onKeyUp();

        $this->assertEquals('b', $this->readline->getInput());
    }

    public function testHistoryDownNotCyclingDoesNotChangeInputAndEmitsBell()
    {
        $this->output->expects($this->once())->method('write')->with("\x07");
        $this->readline->onKeyDown();

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testHistoryDownAfterUpRestoresEmpty()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();
        $this->readline->onKeyDown();

        $this->assertEquals('', $this->readline->getInput());
    }

    public function testHistoryDownAfterUpToTopRestoresBottom()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();
        $this->readline->onKeyUp();
        $this->readline->onKeyDown();

        $this->assertEquals('b', $this->readline->getInput());
    }

    public function testHistoryDownAfterUpRestoresOriginal()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->setInput('hello');

        $this->readline->onKeyUp();
        $this->readline->onKeyDown();

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testHistoryDownBeyondAfterUpStillRestoresOriginal()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->setInput('hello');

        $this->readline->onKeyUp();
        $this->readline->onKeyDown();
        $this->readline->onKeyDown();

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testHistoryClearReturnsSelf()
    {
        $this->assertSame($this->readline, $this->readline->clearHistory());
    }

    public function testHistoryClearResetsToEmptyList()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->clearHistory();

        $this->assertEquals(array(), $this->readline->listHistory());
    }

    public function testHistoryClearWhileCyclingRestoresOriginalInput()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->setInput('hello');

        $this->readline->onKeyUp();

        $this->readline->clearHistory();

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testHistoryLimitReturnsSelf()
    {
        $this->assertSame($this->readline, $this->readline->limitHistory(100));
    }

    public function testHistoryLimitTruncatesCurrentListToLimit()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');
        $this->readline->addHistory('c');

        $this->readline->limitHistory(2);

        $this->assertCount(2, $this->readline->listHistory());
        $this->assertEquals(array('b', 'c'), $this->readline->listHistory());
    }

    public function testHistoryLimitToZeroEmptiesCurrentList()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');
        $this->readline->addHistory('c');

        $this->readline->limitHistory(0);

        $this->assertCount(0, $this->readline->listHistory());
    }

    public function testHistoryLimitTruncatesAddingBeyondLimit()
    {
        $this->readline->limitHistory(2);

        $this->readline->addHistory('a');
        $this->readline->addHistory('b');
        $this->readline->addHistory('c');

        $this->assertCount(2, $this->readline->listHistory());
        $this->assertEquals(array('b', 'c'), $this->readline->listHistory());
    }

    public function testHistoryLimitZeroAlwaysReturnsEmpty()
    {
        $this->readline->limitHistory(0);

        $this->readline->addHistory('a');
        $this->readline->addHistory('b');
        $this->readline->addHistory('c');

        $this->assertCount(0, $this->readline->listHistory());
    }

    public function testHistoryLimitUnlimitedDoesNotTruncate()
    {
        $this->readline->limitHistory(null);

        for ($i = 0; $i < 1000; ++$i) {
            $this->readline->addHistory('line' . $i);
        }

        $this->assertCount(1000, $this->readline->listHistory());
    }

    public function testHistoryLimitRestoresOriginalInputIfCurrentIsTruncated()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->setInput('hello');

        $this->readline->onKeyUp();

        $this->readline->limitHistory(0);

        $this->assertEquals('hello', $this->readline->getInput());
    }

    public function testHistoryLimitKeepsCurrentIfCurrentRemainsDespiteTruncation()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();

        $this->readline->limitHistory(1);

        $this->assertEquals('b', $this->readline->getInput());
    }

    public function testHistoryLimitOnlyInBetweenTruncatesToLastAndKeepsInput()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->onKeyUp();

        $this->readline->limitHistory(3);

        $this->assertEquals('b', $this->readline->getInput());

        $this->readline->addHistory('c');
        $this->readline->addHistory('d');

        $this->assertCount(3, $this->readline->listHistory());
        $this->assertEquals(array('b', 'c', 'd'), $this->readline->listHistory());

        $this->assertEquals('b', $this->readline->getInput());
    }

    public function testHistoryLimitRestoresOriginalIfCurrentIsTruncatedDueToAdding()
    {
        $this->readline->addHistory('a');
        $this->readline->addHistory('b');

        $this->readline->setInput('hello');

        $this->readline->onKeyUp();

        $this->readline->limitHistory(1);

        $this->readline->addHistory('c');
        $this->readline->addHistory('d');

        $this->assertEquals('hello', $this->readline->getInput());
    }
}
