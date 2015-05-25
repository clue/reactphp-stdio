<?php

use Clue\React\Stdio\Readline\NullAutocomplete;

class NullAutocompleteTest extends TestCase
{
    public function testDoesNothing()
    {
        $autocomplete = new NullAutocomplete();

        $readline = $this->getMockBuilder('Clue\React\Stdio\Readline')->disableOriginalConstructor()->getMock();

        $autocomplete->go($readline);
    }
}
