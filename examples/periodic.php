<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('> ');

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
