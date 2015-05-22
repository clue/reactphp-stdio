<?php

use Clue\React\Stdio\Readline\NullHistory;
class NullHistoryTest extends TestCase
{
    public function testDoesNothing()
    {
        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();
        $readline->expects($this->never())->method('setInput');

        $history = new NullHistory();
        $history->addLine('a');
        $history->addLine('b');

        $history->moveUp($readline);
        $history->moveDown($readline);
    }
}
