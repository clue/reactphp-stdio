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

// autocomplete the following commands (at offset=0/1 only)
$readline->setAutocomplete(function ($_, $offset) {
    return $offset > 1 ? array() : array('exit', 'quit', 'help', 'echo', 'print', 'printf');
});

$stdio->write('Welcome to this interactive demo' . PHP_EOL);

// react to commands the user entered
$stdio->on('data', function ($line) use ($stdio, $readline) {
    $line = rtrim($line, "\r\n");

    // add all lines from input to history
    // skip empty line and duplicate of previous line
    $all = $readline->listHistory();
    if ($line !== '' && $line !== end($all)) {
        $readline->addHistory($line);
    }

    $stdio->write('you just said: ' . $line . ' (' . strlen($line) . ')' . PHP_EOL);

    if (in_array(trim($line), array('quit', 'exit'))) {
        $stdio->end();
    }
});

$loop->run();
