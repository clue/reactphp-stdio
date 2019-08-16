<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$stdio->setPrompt('> ');

// add some special key bindings
$stdio->on('a', function () use ($stdio) {
    $stdio->addInput('ä');
});
$stdio->on('o', function () use ($stdio) {
    $stdio->addInput('ö');
});
$stdio->on('u', function () use ($stdio) {
    $stdio->addInput('ü');
});

$stdio->on('?', function () use ($stdio) {
    $stdio->write('Do you need help?');
});

// bind CTRL+E
$stdio->on("\x05", function () use ($stdio) {
    $stdio->write("ignore CTRL+E" . PHP_EOL);
});
// bind CTRL+H
$stdio->on("\x08", function () use ($stdio) {
    $stdio->write('Use "?" if you need help.' . PHP_EOL);
});

$stdio->write('Welcome to this interactive demo' . PHP_EOL);

// end once the user enters a command
$stdio->on('data', function ($line) use ($stdio) {
    $line = rtrim($line, "\r\n");
    $stdio->end('you just said: ' . $line . ' (' . strlen($line) . ')' . PHP_EOL);
});

$loop->run();
