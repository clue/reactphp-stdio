<?php

namespace Clue\Tests\React\Stdio;

class FunctionalExampleTest extends TestCase
{
    public function testPeriodicExampleWithPipedInputEndsBecauseInputEnds()
    {
        $output = $this->execExample('echo hello | php 01-periodic.php');

        $this->assertContainsString('you just said: hello\n', $output);
    }

    public function testPeriodicExampleWithNullInputQuitsImmediately()
    {
        $output = $this->execExample('php 01-periodic.php < /dev/null');

        $this->assertNotContainsString('you just said:', $output);
    }

    public function testPeriodicExampleWithNoInputQuitsImmediately()
    {
        $output = $this->execExample('true | php 01-periodic.php');

        $this->assertNotContainsString('you just said:', $output);
    }

    public function testPeriodicExampleWithSleepNoInputQuitsOnEnd()
    {
        $output = $this->execExample('sleep 0.1 | php 01-periodic.php');

        $this->assertNotContainsString('you just said:', $output);
    }

    public function testPeriodicExampleWithClosedInputQuitsImmediately()
    {
        if (getenv('CI') === 'true' && (defined('HHVM_VERSION') || PHP_VERSION_ID >= 70000)) {
            $this->markTestSkipped('Test fails for Github CI with PHP >= 7.0 and HHVM');
        }

        $output = $this->execExample('php 01-periodic.php <&-');

        if (strpos($output, 'said') !== false) {
            $this->markTestIncomplete('Your platform exhibits a closed STDIN bug, this may need some further debugging');
        }

        $this->assertNotContainsString('you just said:', $output);
    }

    public function testPeriodicExampleWithClosedInputAndOutputQuitsImmediatelyWithoutOutput()
    {
        if (getenv('CI') === 'true' && (defined('HHVM_VERSION') || PHP_VERSION_ID >= 70000)) {
            $this->markTestSkipped('Test fails for Github CI with PHP >= 7.0 and HHVM');
        }

        $output = $this->execExample('php 01-periodic.php <&- >&- 2>&-');

        if (strpos($output, 'said') !== false) {
            $this->markTestIncomplete('Your platform exhibits a closed STDIN bug, this may need some further debugging');
        }

        $this->assertEquals('', $output);
    }

    public function testBindingsExampleWithPipedInputEndsBecauseInputEnds()
    {
        $output = $this->execExample('echo test | php 04-bindings.php');

        $this->assertContainsString('you just said: test (4)' . PHP_EOL, $output);
    }

    public function testBindingsExampleWithPipedInputEndsWithSpecialBindingsReplacedBecauseInputEnds()
    {
        $output = $this->execExample('echo hello | php 04-bindings.php');

        $this->assertContainsString('you just said: hellö (6)' . PHP_EOL, $output);
    }

    public function testStubShowStdinIsReadableByDefault()
    {
        $output = $this->execExample('php ../tests/stub/01-check-stdin.php');

        $this->assertContainsString('YES', $output);
    }

    public function testStubCanCloseStdinAndIsNotReadable()
    {
        $output = $this->execExample('php ../tests/stub/02-close-stdin.php');

        $this->assertContainsString('NO', $output);
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

    public function testStubCanEndWithoutReadlineFunctions()
    {
        $output = $this->execExample('php -d disable_functions=readline_callback_handler_install,readline_callback_handler_remove ../tests/stub/04-end.php');

        $this->assertEquals('', $output);
    }

    public function testPeriodicExampleViaInteractiveModeQuitsImmediately()
    {
        if (getenv('CI') === 'true' && PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('Test fails for Github CI with PHP >= 7.0');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Skipped interactive mode on HHVM');
        }

        $output = $this->execExample('echo "require(\"01-periodic.php\");" | php -a');

        // starts with either "Interactive mode enabled" or "Interactive shell"
        $this->assertStringStartsWith('Interactive ', $output);
        $this->assertNotContainsString('you just said:', $output);
    }

    private function execExample($command)
    {
        chdir(__DIR__ . '/../examples/');

        return shell_exec($command);
    }
}
