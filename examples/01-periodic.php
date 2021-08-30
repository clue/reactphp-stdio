<?php

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$stdio = new Clue\React\Stdio\Stdio();

$stdio->write('Will print periodic messages until you submit anything' . PHP_EOL);

// add some periodic noise
$timer = Loop::addPeriodicTimer(0.5, function () use ($stdio) {
    $stdio->write(date('Y-m-d H:i:s') . ' hello' . PHP_EOL);
});

// react to commands the user entered
$stdio->on('data', function ($line) use ($stdio, $timer) {
    $stdio->write('you just said: ' . addcslashes($line, "\0..\37") . ' (' . strlen($line) . ')' . PHP_EOL);

    Loop::cancelTimer($timer);
    $stdio->end();
});

// cancel periodic timer if STDIN closed
$stdio->on('end', function () use ($stdio, $timer) {
    Loop::cancelTimer($timer);
    $stdio->end();
});

// input already closed on program start, exit immediately
if (!$stdio->isReadable()) {
    Loop::cancelTimer($timer);
    $stdio->end();
}
