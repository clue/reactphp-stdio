<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$readline = $stdio->getReadline();

$readline->setPrompt('> ');

// add some special key bindings
$readline->on('a', function () use ($readline) {
    $readline->addInput('ä');
});
$readline->on('o', function () use ($readline) {
    $readline->addInput('ö');
});
$readline->on('u', function () use ($readline) {
    $readline->addInput('ü');
});

$readline->on('?', function () use ($stdio) {
    $stdio->write('Do you need help?');
});

// bind CTRL+E
$readline->on("\x05", function () use ($stdio) {
    $stdio->write("ignore CTRL+E" . PHP_EOL);
});
// bind CTRL+H
$readline->on("\x08", function () use ($stdio) {
    $stdio->write('Use "?" if you need help.' . PHP_EOL);
});

$stdio->write('Welcome to this interactive demo' . PHP_EOL);

// end once the user enters a command
$stdio->on('data', function ($line) use ($stdio, $readline) {
    $line = rtrim($line, "\r\n");
    $stdio->end('you just said: ' . $line . ' (' . strlen($line) . ')' . PHP_EOL);
});

$loop->run();
