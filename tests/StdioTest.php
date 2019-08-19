<?php

use Clue\React\Stdio\Stdio;
use Clue\React\Stdio\Readline;
use React\EventLoop\Factory;
use React\Stream\ThroughStream;

class StdioTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = Factory::create();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCtorDefaultArgs()
    {
        $stdio = new Stdio($this->loop);

        // Closing STDIN/STDOUT is not a good idea for reproducible tests
        // $stdio->close();
    }

    public function testCtorWithoutReadlineWillCreateNewReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $stdio = new Stdio($this->loop, $input, $output);

        $this->assertInstanceOf('Clue\React\Stdio\Readline', $stdio->getReadline());
    }

    public function testCtorReadlineArgWillBeReturnedBygetReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $this->assertSame($readline, $stdio->getReadline());
    }

    public function testWriteEmptyStringWillNotWriteToOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $output->expects($this->never())->method('write');

        $this->assertTrue($stdio->write(''));
    }

    public function testWriteWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write('test');

        $this->assertEquals("\r\033[K" . "test\n" . "> input", $buffer);
    }

    public function testWriteAgainWillMoveToPreviousLineWriteOutputAndRestoreReadlinePosition()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write('hello');

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write('world');

        $this->assertEquals("\033[A" . "\r\033[5C" . "world\n" . "\033[7C", $buffer);
    }

    public function testWriteAgainWithBackspaceWillMoveToPreviousLineWriteOutputAndRestoreReadlinePosition()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write('hello!');

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write("\x08 world!");

        $this->assertEquals("\033[A" . "\r\033[6C" . "\x08 world!\n" . "\033[7C", $buffer);
    }

    public function testWriteAgainWithNewlinesWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write("first" . "\n" . "sec");

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write("ond" . "\n" . "third");

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[3C" . "ond\nthird\n" . "> input", $buffer);
    }

    public function testWriteAfterReadlineInputWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write('incomplete');

        $readline->emit('data', array('test'));
        $readline->setInput('input');

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write("test\n");

        $this->assertEquals("\r\033[K" . "test\n" . "> input", $buffer);
    }

    public function testWriteTwoLinesWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->write("hello\n");
        $stdio->write("world\n");

        $this->assertEquals("\r\033[K" . "hello\n" . "> input" . "\r\033[K" . "world\n" . "> input", $buffer);
    }

    public function testPauseWillBeForwardedToInput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('pause');

        $stdio->pause();
    }

    public function testResumeWillBeForwardedToInput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('resume');

        $stdio->resume();
    }

    public function testReadableWillBeForwardedToInput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('isReadable')->willReturn(true);

        $this->assertTrue($stdio->isReadable());
    }

    public function testPipeWillReturnDestStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $stdio->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testWritableWillBeForwardedToOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $output->expects($this->once())->method('isWritable')->willReturn(true);

        $this->assertTrue($stdio->isWritable());
    }

    public function testCloseWillEmitCloseEventAndCloseInputAndOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('close', $this->expectCallableOnce());

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('close');

        $stdio->close();

        $this->assertEquals(array(), $stdio->listeners('close'));
    }

    public function testCloseTwiceWillEmitCloseEventAndCloseInputAndOutputOnlyOnce()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('close', $this->expectCallableOnce());

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('close');

        $stdio->close();
        $stdio->close();
    }

    public function testEndWillClearReadlineAndCloseInputAndEndOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline->expects($this->once())->method('setPrompt')->with('')->willReturnSelf();
        $readline->expects($this->once())->method('setInput')->with('')->willReturnSelf();

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('end');

        $stdio->end();
    }

    public function testEndWithDataWillWriteAndCloseInputAndEndOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $output->expects($this->atLeastOnce())->method('write');

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('end');

        $stdio->end('test');
    }

    public function testWriteAfterEndWillNotWriteToOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);
        $stdio->end();

        $output->expects($this->never())->method('write');

        $this->assertFalse($stdio->write('test'));
    }

    public function testEndTwiceWillCloseInputAndEndOutputOnce()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('end');

        $stdio->end();
        $stdio->end();
    }

    public function testDataEventWillBeForwarded()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('data', $this->expectCallableOnceWith("hello\n"));

        $readline->emit('data', array("hello\n"));
    }

    public function testDataEventWithoutNewlineWillBeForwardedAsIs()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('data', $this->expectCallableOnceWith("hello"));

        $readline->emit('data', array("hello"));
    }

    public function testEndEventWillBeForwarded()
    {
        $input = new ThroughStream();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('end', $this->expectCallableOnce());

        $input->emit('end');
    }

    public function testErrorEventFromInputWillBeForwarded()
    {
        $input = new ThroughStream();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('error', $this->expectCallableOnce());

        $input->emit('error', array(new \RuntimeException()));
    }

    public function testErrorEventFromOutputWillBeForwarded()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = new ThroughStream();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('error', $this->expectCallableOnce());

        $output->emit('error', array(new \RuntimeException()));
    }

    public function testPromptWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->setPrompt('> ');

        $this->assertEquals('> ', $stdio->getPrompt());
    }

    public function testSetAutocompleteWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $readline->expects($this->once())->method('setAutocomplete')->with(null);

        $stdio->setAutocomplete(null);
    }

    public function testSetEchoWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $readline->expects($this->once())->method('setEcho')->with(false);

        $stdio->setEcho(false);
    }

    public function testSetMoveWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $readline->expects($this->once())->method('setMove')->with(false);

        $stdio->setMove(false);
    }

    public function testInputWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->setInput('hello');
        $stdio->addInput('!');

        $this->assertEquals('hello!', $stdio->getInput());
    }

    public function testCursorWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->setInput('hello');
        $stdio->moveCursorTo(0);
        $stdio->moveCursorBy(1);

        $this->assertEquals(1, $stdio->getCursorPosition());
        $this->assertEquals(1, $stdio->getCursorCell());
    }

    public function testHistoryWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->limitHistory(2);
        $stdio->addHistory('hello');
        $stdio->addHistory('world');
        $stdio->addHistory('again');

        $this->assertEquals(array('world', 'again'), $stdio->listHistory());

        $stdio->clearHistory();
        $this->assertEquals(array(), $stdio->listHistory());
    }

    public function testSetBellWillBeForwardedToReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $readline->expects($this->once())->method('setBell')->with(false);

        $stdio->setBell(false);
    }
}
