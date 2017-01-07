<?php

use Clue\React\Stdio\Stdio;
use Clue\Arguments;
use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;

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

// register all available commands and their arguments
$router = new Router();
$router->add('exit | quit', function() use ($stdio) {
    $stdio->end();
});
$router->add('help', function () use ($stdio) {
    $stdio->writeln('Use TAB-completion or use "exit"');
});
$router->add('(echo | print) <words>...', function (array $args) use ($stdio) {
    $stdio->writeln(implode(' ', $args['words']));
});
$router->add('printf <format> <args>...', function (array $args) use ($stdio) {
    $stdio->writeln(vsprintf($args['format'],$args['args']));
});

// autocomplete the following commands (at offset=0/1 only)
$readline->setAutocomplete(function ($_, $offset) {
    return $offset > 1 ? array() : array('exit', 'quit', 'help', 'echo', 'print', 'printf');
});

$stdio->writeln('Welcome to this interactive demo');

// react to commands the user entered
$stdio->on('line', function ($line) use ($router, $stdio, $readline) {
    // add all lines from input to history
    // skip empty line and duplicate of previous line
    $all = $readline->listHistory();
    if (trim($line) !== '' && $line !== end($all)) {
        $readline->addHistory($line);
    }

    try {
        $args = Arguments\split($line);
    } catch (Arguments\UnclosedQuotesException $e) {
        $stdio->writeln('Error: Invalid command syntax (unclosed quotes)');
        return;
    }

    // skip empty lines
    if (!$args) {
        return;
    }

    try {
        $router->handleArgs($args);
    } catch (NoRouteFoundException $e) {
        $stdio->writeln('Error: Invalid command usage');
    }
});

$loop->run();
