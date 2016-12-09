<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$readline = $stdio->getReadline();

$readline->setPrompt('> ');

// add all lines from input to history
$readline->on('data', function ($line) use ($readline) {
    $all = $readline->listHistory();

    // skip empty line and duplicate of previous line
    if (trim($line) !== '' && $line !== end($all)) {
        $readline->addHistory($line);
    }
});

$stdio->writeln('Will print periodic messages until you type "quit" or "exit"');

$stdio->on('line', function ($line) use ($stdio, $loop, &$timer) {
    $stdio->writeln('you just said: ' . $line . ' (' . strlen($line) . ')');

    if ($line === 'quit' || $line === 'exit') {
        $timer->cancel();
        $stdio->end();
    }
});

// add some periodic noise
$timer = $loop->addPeriodicTimer(2.0, function () use ($stdio) {
    $stdio->writeln('hello');
});

$loop->run();
