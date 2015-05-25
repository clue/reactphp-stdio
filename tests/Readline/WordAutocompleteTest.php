<?php

use Clue\React\Stdio\Readline\WordAutocomplete;

class WordAutocompleteTest extends TestCase
{
    private $readline;

    public function setUp()
    {
        $this->readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
    }

    public function testWordEndDoesCompleteCorrectWordAndAppendsTrailingSpaceAndMovesCursor()
    {
        $autocomplete = new WordAutocomplete(array('first', 'second'));

        $this->readline->expects($this->once())->method('getInput')->will($this->returnValue('fir'));
        $this->readline->expects($this->once())->method('getCursorPosition')->will($this->returnValue(3));

        $this->readline->expects($this->once())->method('setInput')->with($this->equalTo('first '));
        $this->readline->expects($this->once())->method('moveCursorTo')->with($this->equalTo(6));

        $autocomplete->go($this->readline);
    }

    public function testCursorInMiddleOfWordDoesCompleteCorrectWordAndMovesCursorBehindCompleted()
    {
        $autocomplete = new WordAutocomplete(array('first', 'second'));

        $this->readline->expects($this->once())->method('getInput')->will($this->returnValue('fir'));
        $this->readline->expects($this->once())->method('getCursorPosition')->will($this->returnValue(2));

        $this->readline->expects($this->once())->method('setInput')->with($this->equalTo('firstr'));
        $this->readline->expects($this->once())->method('moveCursorTo')->with($this->equalTo(5));

        $autocomplete->go($this->readline);
    }

    public function testUnknownInputWillNotBeCompleted()
    {
        $autocomplete = new WordAutocomplete(array('first', 'second'));

        $this->readline->expects($this->once())->method('getInput')->will($this->returnValue('test'));
        $this->readline->expects($this->never())->method('setInput');

        $autocomplete->go($this->readline);
    }

    public function testEmptyInputWillNotBeCompleted()
    {
        $autocomplete = new WordAutocomplete(array('first', 'second'));

        $this->readline->expects($this->once())->method('getInput')->will($this->returnValue(''));
        $this->readline->expects($this->never())->method('setInput');

        $autocomplete->go($this->readline);
    }

    public function testWordInSentenceDoesCompleteSentence()
    {
        $autocomplete = new WordAutocomplete(array('first', 'second'));

        $this->readline->expects($this->once())->method('getInput')->will($this->returnValue('hello fir'));
        $this->readline->expects($this->once())->method('getCursorPosition')->will($this->returnValue(9));

        $this->readline->expects($this->once())->method('setInput')->with($this->equalTo('hello first '));
        $this->readline->expects($this->once())->method('moveCursorTo')->with($this->equalTo(12));

        $autocomplete->go($this->readline);
    }
}
