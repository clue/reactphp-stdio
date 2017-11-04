<?php

use React\EventLoop\Factory;
use Clue\React\Stdio\Stdio;
use Clue\React\Stdio\Readline;
use React\Stream\ReadableStream;
use React\Stream\WritableStream;

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
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $this->assertSame($input, $stdio->getInput());
        $this->assertSame($output, $stdio->getOutput());
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

        $stdio->writeln('test');

        $this->assertEquals("\r\033[K" . "test\n" . "> input", $buffer);
    }

    public function testOverwriteWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write('first');

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->overwrite('overwrite');

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[K" . "overwrite\n" . "> input", $buffer);
    }

    public function testOverwriteAfterNewlineWillClearReadlineAndWriteOutputAndRestoreReadline()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);
        $readline->setPrompt('> ');
        $readline->setInput('input');

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->write("first\n");

        $buffer = '';
        $output->expects($this->any())->method('write')->will($this->returnCallback(function ($data) use (&$buffer) {
            $buffer .= $data;
        }));

        $stdio->overwrite('overwrite');

        $this->assertEquals("\r\033[K" . "overwrite\n" . "> input", $buffer);
    }

    public function testWriteLineWillClearReadlineWriteOutputAndRestoreReadline()
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

        $stdio->writeln('test');

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

        $stdio->writeln('hello');
        $stdio->writeln('world');

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

    public function testCloseWillCloseInputAndOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('close');

        $stdio->close();
    }

    public function testCloseTwiceWillCloseInputAndOutputOnlyOnce()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $input->expects($this->once())->method('close');
        $output->expects($this->once())->method('close');

        $stdio->close();
        $stdio->close();
    }

    public function testEndWillCloseInputAndEndOutput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

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
        $stdio->on('line', $this->expectCallableOnceWith('hello'));

        $readline->emit('data', array('hello'));
    }

    public function testEndEventWillBeForwarded()
    {
        $input = new ReadableStream();
        $output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('end', $this->expectCallableOnce());

        $input->emit('end');
    }

    public function testErrorEventFromInputWillBeForwarded()
    {
        $input = new ReadableStream();
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
        $output = new WritableStream();

        //$readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline = new Readline($input, $output);

        $stdio = new Stdio($this->loop, $input, $output, $readline);

        $stdio->on('error', $this->expectCallableOnce());

        $output->emit('error', array(new \RuntimeException()));
    }
}
