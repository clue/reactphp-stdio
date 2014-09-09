# clue/stdio-react [![Build Status](https://travis-ci.org/clue/php-stdio-react.svg?branch=master)](https://travis-ci.org/clue/php-stdio-react)

Async standard console input & output (STDIN, STDOUT) for React PHP

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to present a prompt in a CLI program:

```php
$stdio = new Stdio($loop);
$stdio->getReadline()->setPrompt('Input > ');

$stdio->on('line', function ($line) use ($stdio) {
    var_dump($line);
    
    if ($line === 'quit') {
        $stdio->end();
    }
});
```

See also the [examples](examples).

## Install

The recommended way to install this library is [through composer](packagist://getcomposer.org).
[New to composer?](packagist://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/stdio-react": "dev-master"
    }
}
```

## License

MIT
