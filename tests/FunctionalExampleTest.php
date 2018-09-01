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

    public function testPeriodicExampleWithClosedInputQuitsImmediately()
    {
        $output = $this->execExample('php 01-periodic.php <&-');

        if (strpos($output, 'said') !== false) {
            $this->markTestIncomplete('Your platform exhibits a closed STDIN bug, this may need some further debugging');
        }

        $this->assertNotContains('you just said:', $output);
    }

    public function testPeriodicExampleWithClosedInputAndOutputQuitsImmediatelyWithoutOutput()
    {
        $output = $this->execExample('php 01-periodic.php <&- >&- 2>&-');

        if (strpos($output, 'said') !== false) {
            $this->markTestIncomplete('Your platform exhibits a closed STDIN bug, this may need some further debugging');
        }

        $this->assertEquals('', $output);
    }

    public function testBindingsExampleWithPipedInputEndsBecauseInputEnds()
    {
        $output = $this->execExample('echo test | php 04-bindings.php');

        $this->assertContains('you just said: test (4)' . PHP_EOL, $output);
    }

    public function testBindingsExampleWithPipedInputEndsWithSpecialBindingsReplacedBecauseInputEnds()
    {
        $output = $this->execExample('echo hello | php 04-bindings.php');

        $this->assertContains('you just said: hellö (6)' . PHP_EOL, $output);
    }

    public function testStubShowStdinIsReadableByDefault()
    {
        $output = $this->execExample('php ../tests/stub/01-check-stdin.php');

        $this->assertContains('YES', $output);
    }

    public function testStubCanCloseStdinAndIsNotReadable()
    {
        $output = $this->execExample('php ../tests/stub/02-close-stdin.php');

        $this->assertContains('NO', $output);
    }

    public function testStubCanCloseStdoutAndIsNotWritable()
    {
        $output = $this->execExample('php ../tests/stub/03-close-stdout.php 2>&1');

        $this->assertEquals('', $output);
    }

    public function testStubCanEndWithoutOutput()
    {
        $output = $this->execExample('php ../tests/stub/04-end.php');

        $this->assertEquals('', $output);
    }

    public function testStubCanEndWithoutExtensions()
    {
        $output = $this->execExample('php -n ../tests/stub/04-end.php');

        $this->assertEquals('', $output);
    }

    public function testPeriodicExampleViaInteractiveModeQuitsImmediately()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Skipped interactive mode on HHVM');
        }

        $output = $this->execExample('echo "require(\"01-periodic.php\");" | php -a');

        // starts with either "Interactive mode enabled" or "Interactive shell"
        $this->assertStringStartsWith('Interactive ', $output);
        $this->assertNotContains('you just said:', $output);
    }

    private function execExample($command)
    {
        chdir(__DIR__ . '/../examples/');

        return shell_exec($command);
    }
}
