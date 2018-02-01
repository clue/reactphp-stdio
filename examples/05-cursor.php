<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$readline = $stdio->getReadline();

$value = 10;
$readline->on("\033[A", function () use (&$value, $readline) {
    $value++;
    $readline->setPrompt('Value: ' . $value);
});
$readline->on("\033[B", function () use (&$value, $readline) {
    --$value;
    $readline->setPrompt('Value: ' . $value);
});

// hijack enter to just print our current value
$readline->on("\n", function () use ($readline, $stdio, &$value) {
    $stdio->write("Your choice was $value\n");
});

// quit on "q"
$readline->on('q', function () use ($stdio) {
    $stdio->end();
});

// user can still type all keys, but we simply hide user input
$readline->setEcho(false);

// instead of showing user input, we just show a custom prompt
$readline->setPrompt('Value: ' . $value);

$stdio->write('Welcome to this cursor demo

Use cursor UP/DOWN to change value.

Use "q" to quit
');

$loop->run();
