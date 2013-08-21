<?php

use Clue\Stdio\React\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('> ');

$stdio->writeln('Will print periodic messages until you type "quit" or "exit"');

$stdio->on('line', function ($line) use ($stdio, $loop) {
    $stdio->writeln('you just said: ' . $line . ' (' . strlen($line) . ')');

    if ($line === 'quit' || $line === 'exit') {
        $loop->stop();
    }
});

// add some periodic noise
$loop->addPeriodicTimer(2.0, function () use ($stdio) {
    $stdio->writeln('hello');
});

$loop->run();
