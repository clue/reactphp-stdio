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

    public function testWriteWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "test\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteAgainWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[5C" . "world\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteAgainWithBackspaceWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[6C" . "\x08 world!\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteAgainWithNewlinesWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[3C" . "ond\nthird\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteAfterReadlineInputWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "test\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testOverwriteWillClearReadlineMoveToPreviousLineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "\033[A" . "\r\033[K" . "overwrite\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testOverwriteAfterNewlineWillClearReadlineAndWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "overwrite\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteLineWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "test\n" . "\r\033[K" . "> input", $buffer);
    }

    public function testWriteTwoLinesWillClearReadlineWriteOutputAndRestoreReadline()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $output = $this->getMock('React\Stream\WritableStreamInterface');

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

        $this->assertEquals("\r\033[K" . "hello\n" . "\r\033[K" . "> input" . "\r\033[K" . "world\n" . "\r\033[K" . "> input", $buffer);
    }
}
