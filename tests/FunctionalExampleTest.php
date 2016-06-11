<?php

class FunctionalExampleTest extends TestCase
{
    public function testPeriodicExampleWithPipedInputEndsBecauseInputEnds()
    {
        $output = $this->execExample('echo hello | php 01-periodic.php');

        $this->assertContains('you just said: hello\n', $output);
    }

    public function testPeriodicExampleWithNullInputQuitsImmediately()
    {
        $output = $this->execExample('php 01-periodic.php < /dev/null');

        $this->assertNotContains('you just said:', $output);
    }

    public function testPeriodicExampleWithNoInputQuitsImmediately()
    {
        $output = $this->execExample('true | php 01-periodic.php');

        $this->assertNotContains('you just said:', $output);
    }

    public function testPeriodicExampleWithSleepNoInputQuitsOnEnd()
    {
        $output = $this->execExample('sleep 0.1 | php 01-periodic.php');

        $this->assertNotContains('you just said:', $output);
    }

    private function execExample($command)
    {
        chdir(__DIR__ . '/../examples/');

        return shell_exec($command);
    }
}
