<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$readline = $stdio->getReadline();

$readline->setPrompt('> ');

// limit history to HISTSIZE env
$limit = getenv('HISTSIZE');
if ($limit === '' || $limit < 0) {
    // empty string or negative value means unlimited
    $readline->limitHistory(null);
} elseif ($limit !== false) {
    // apply any other value if given
    $readline->limitHistory($limit);
}

// add all lines from input to history
$readline->on('data', function ($line) use ($readline) {
    $all = $readline->listHistory();

    // skip empty line and duplicate of previous line
    if (trim($line) !== '' && $line !== end($all)) {
        $readline->addHistory($line);
    }
});

// autocomplete the following commands (at offset=0 only)
$readline->setAutocomplete(function ($_, $offset) {
    return $offset ? array() : array('exit', 'quit', 'help', 'echo', 'print', 'printf');
});

$stdio->writeln('Will print periodic messages until you type "quit" or "exit"');

$stdio->on('line', function ($line) use ($stdio, $loop, &$timer) {
    $stdio->writeln('you just said: ' . $line . ' (' . strlen($line) . ')');

    if (in_array(trim($line), array('quit', 'exit'))) {
        $timer->cancel();
        $stdio->end();
    }
});

// add some periodic noise
$timer = $loop->addPeriodicTimer(2.0, function () use ($stdio) {
    $stdio->writeln('hello');
});

$loop->run();
