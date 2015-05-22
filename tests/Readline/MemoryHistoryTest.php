<?php

use Clue\React\Stdio\Readline\MemoryHistory;
use Clue\React\Stdio\Readline\History;
class MemoryHistoryTest extends TestCase
{
    private $readline;
    private $history;

    public function setUp()
    {
        $this->readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $this->history = new MemoryHistory();
    }

    public function testCanAdd()
    {
        $this->history->addLine('a');
        $this->history->addLine('b');

        return $this->history;
    }

    /**
     * @depends testCanAdd
     * @param History $history
     */
    public function testMovingUpRestoresLastEntry(History $history)
    {
        $this->readline->expects($this->once())->method('setInput')->with($this->equalTo('b'));

        $history->moveUp($this->readline);

        return $history;
    }

    /**
     * @depends testMovingUpRestoresLastEntry
     * @param History $history
     */
    public function testMovingUpMovesToNextEntryWhichIsFirst(History $history)
    {
        $this->readline->expects($this->once())->method('setInput')->with($this->equalTo('a'));

        $history->moveUp($this->readline);

        return $history;
    }

    /**
     * @depends testMovingUpMovesToNextEntryWhichIsFirst
     * @param History $history
     */
    public function testMovingUpWhenAlreadyOnFirstDoesNothing(History $history)
    {
        $this->readline->expects($this->never())->method('setInput');

        $history->moveUp($this->readline);
    }

    public function testMovingDownDoesNothing()
    {
        $this->history->addLine('ignored');

        $this->readline->expects($this->never())->method('setInput');

        $this->history->moveDown($this->readline);
    }

    public function testMovingInEmptyHistoryDoesNothing()
    {
        $this->readline->expects($this->never())->method('setInput');

        $this->history->moveUp($this->readline);
        $this->history->moveDown($this->readline);
    }
}
