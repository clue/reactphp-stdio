<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$stdio->writeln('Will print periodic messages until you submit anything');

// add some periodic noise
$timer = $loop->addPeriodicTimer(0.5, function () use ($stdio) {
    $stdio->writeln(date('Y-m-d H:i:s') . ' hello');
});

// react to commands the user entered
$stdio->on('line', function ($line) use ($stdio, $timer) {
    $stdio->writeln('you just said: ' . $line . ' (' . strlen($line) . ')');

    $timer->cancel();
    $stdio->end();
});

$loop->run();
