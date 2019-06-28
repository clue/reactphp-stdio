<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$value = 10;
$stdio->on("\033[A", function () use (&$value, $stdio) {
    $value++;
    $stdio->setPrompt('Value: ' . $value);
});
$stdio->on("\033[B", function () use (&$value, $stdio) {
    --$value;
    $stdio->setPrompt('Value: ' . $value);
});

// hijack enter to just print our current value
$stdio->on("\n", function () use ($stdio, &$value) {
    $stdio->write("Your choice was $value\n");
});

// quit on "q"
$stdio->on('q', function () use ($stdio) {
    $stdio->end();
});

// user can still type all keys, but we simply hide user input
$stdio->setEcho(false);

// instead of showing user input, we just show a custom prompt
$stdio->setPrompt('Value: ' . $value);

$stdio->write('Welcome to this cursor demo

Use cursor UP/DOWN to change value.

Use "q" to quit
');

$loop->run();
