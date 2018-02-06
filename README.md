# clue/stdio-react [![Build Status](https://travis-ci.org/clue/php-stdio-react.svg?branch=master)](https://travis-ci.org/clue/php-stdio-react)

Async, event-driven and UTF-8 aware console input & output (STDIN, STDOUT) for
truly interactive CLI applications, built on top of [ReactPHP](https://reactphp.org).

You can use this library to build truly interactive and responsive command
line (CLI) applications, that immediately react when the user types in
a line or hits a certain key. Inspired by `ext-readline`, but supports UTF-8
and interleaved I/O (typing while output is being printed), history and
autocomplete support and takes care of proper TTY settings under the hood
without requiring any extensions or special installation.

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Stdio](#stdio)
    * [Output](#output)
    * [Input](#input)
  * [Readline](#readline)
    * [Prompt](#prompt)
    * [Echo](#echo)
    * [Input buffer](#input-buffer)
    * [Cursor](#cursor)
    * [History](#history)
    * [Autocomplete](#autocomplete)
    * [Keys](#keys)
* [Pitfalls](#pitfalls)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

## Quickstart example

Once [installed](#install), you can use the following code to present a prompt in a CLI program:

```php
$loop = React\EventLoop\Factory::create();
$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('Input > ');

$stdio->on('data', function ($line) use ($stdio) {
    $line = rtrim($line, "\r\n");
    $stdio->write('Your input: ' . $line . PHP_EOL);

    if ($line === 'quit') {
        $stdio->end();
    }
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Stdio

The `Stdio` is the main interface to this library.
It is responsible for orchestrating the input and output streams
by registering and forwarding the corresponding events.
It also registers everything with the main [EventLoop](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();
$stdio = new Stdio($loop);
```

See below for waiting for user input and writing output.
The `Stdio` class is a well-behaving duplex stream
(implementing ReactPHP's `DuplexStreamInterface`) that emits each complete
line as a `data` event, including the trailing newline.

#### Output

The `Stdio` is a well-behaving writable stream
implementing ReactPHP's `WritableStreamInterface`.

The `write($text)` method can be used to print the given text characters to console output.
This is useful if you need more control or want to output individual bytes or binary output:

```php
$stdio->write('hello');
$stdio->write(" world\n");
```

Because the `Stdio` is a well-behaving writable stream,
you can also `pipe()` any readable stream into this stream.

```php
$logger->pipe($stdio);
```

#### Input

The `Stdio` is a well-behaving readable stream
implementing ReactPHP's `ReadableStreamInterface`.

It will emit a `data` event for every line read from console input.
The event will contain the input buffer as-is, including the trailing newline.
You can register any number of event handlers like this:

```php
$stdio->on('data', function ($line) {
    if ($line === "start\n") {
        doSomething();
    }
});
```

Note that this class takes care of buffering incomplete lines and will only emit
complete lines.
This means that the line will usually end with the trailing newline character.
If the stream ends without a trailing newline character, it will not be present
in the `data` event.
As such, it's usually recommended to remove the trailing newline character
before processing command line input like this:

```php
$stdio->on('data', function ($line) {
    $line = rtrim($line, "\r\n");
    if ($line === "start") {
        doSomething();
    }
});
```

Similarly, if you copy and paste a larger chunk of text, it will properly emit
multiple complete lines with a separate `data` event for each line.

Because the `Stdio` is a well-behaving readable stream that will emit incoming
data as-is, you can also use this to `pipe()` this stream into other writable
streams.

```
$stdio->pipe($logger);
```

You can control various aspects of the console input through the [`Readline`](#readline),
so read on..

### Readline

The [`Readline`](#readline) class is responsible for reacting to user input and presenting a prompt to the user.
It does so by reading individual bytes from the input stream and writing the current *user input line* to the output stream.

The *user input line* consists of a *prompt*, following by the current *user input buffer*.
The `Readline` allows you to control various aspects of this *user input line*.

You can access the current instance through the [`Stdio`](#stdio):

```php
$readline = $stdio->getReadline();
```

See above for waiting for user input.

Alternatively, the `Readline` is also a well-behaving readable stream
(implementing ReactPHP's `ReadableStreamInterface`) that emits each complete
line as a `data` event, including the trailing newline.
This is considered advanced usage.

#### Prompt

The *prompt* will be written at the beginning of the *user input line*, right before the *user input buffer*.

The `setPrompt($prompt)` method can be used to change the input prompt.
The prompt will be printed to the *user input line* as-is, so you will likely want to end this with a space:

```php
$readline->setPrompt('Input: ');
```

The default input prompt is empty, i.e. the *user input line* contains only the actual *user input buffer*.
You can restore this behavior by passing an empty prompt:

```php
$readline->setPrompt('');
```

The `getPrompt()` method can be used to get the current input prompt.
It will return an empty string unless you've set anything else:

```php
assert($readline->getPrompt() === '');
```

#### Echo

The *echo mode* controls how the actual *user input buffer* will be presented in the *user input line*.

The `setEcho($echo)` method can be used to control the echo mode.
The default is to print the *user input buffer* as-is.

You can disable printing the *user input buffer*, e.g. for password prompts.
The user will still be able to type, but will not receive any indication of the current *user input buffer*.
Please note that this often leads to a bad user experience as users will not even see their cursor position.
Simply pass a boolean `false` like this:

```php
$readline->setEcho(false);
```

Alternatively, you can also *hide* the *user input buffer* by using a replacement character.
One replacement character will be printed for each character in the *user input buffer*.
This is useful for password prompts to give users an indicatation that their key presses are registered.
This often provides a better user experience and allows users to still control their cursor position. 
Simply pass a string replacement character likes this:

```php
$readline->setEcho('*');
```

To restore the original behavior where every character appears as-is, simply pass a boolean `true`:

```php
$readline->setEcho(true);
```

#### Input buffer

Everything the user types will be buffered in the current *user input buffer*.
Once the user hits enter, the *user input buffer* will be processed and cleared.

The `addInput($input)` method can be used to add text to the *user input
buffer* at the current cursor position.
The given text will be inserted just like the user would type in a text and as
such adjusts the current cursor position accordingly.
The user will be able to delete and/or rewrite the buffer at any time.
Changing the *user input buffer* can be useful for presenting a preset input to
the user (like the last password attempt).
Simply pass an input string like this:

```php
$readline->addInput('hello');
```

The `setInput($buffer)` method can be used to control the *user input buffer*.
The given text will be used to replace the entire current *user input buffer*
and as such adjusts the current cursor position to the end of the new buffer.
The user will be able to delete and/or rewrite the buffer at any time.
Changing the *user input buffer* can be useful for presenting a preset input to
the user (like the last password attempt).
Simply pass an input string like this:

```php
$readline->setInput('lastpass');
```

The `getInput()` method can be used to access the current *user input buffer*.
This can be useful if you want to append some input behind the current *user input buffer*.
You can simply access the buffer like this:

```php
$buffer = $readline->getInput();
```

#### Cursor

By default, users can control their (horizontal) cursor position by using their arrow keys on the keyboard.
Also, every character pressed on the keyboard advances the cursor position.

The `setMove($toggle)` method can be used to control whether users are allowed to use their arrow keys.
To disable the left and right arrow keys, simply pass a boolean `false` like this:

```php
$readline->setMove(false);
```

To restore the default behavior where the user can use the left and right arrow keys,
simply pass a boolean `true` like this:

```php
$readline->setMove(true);
```

The `getCursorPosition()` method can be used to access the current cursor position,
measured in number of characters.
This can be useful if you want to get a substring of the current *user input buffer*.
Simply invoke it like this:

```php
$position = $readline->getCursorPosition();
```

The `getCursorCell()` method can be used to get the current cursor position,
measured in number of monospace cells.
Most *normal* characters (plain ASCII and most multi-byte UTF-8 sequences) take a single monospace cell.
However, there are a number of characters that have no visual representation
(and do not take a cell at all) or characters that do not fit within a single
cell (like some Asian glyphs).
This method is mostly useful for calculating the visual cursor position on screen,
but you may also invoke it like this:

```php
$cell = $readline->getCursorCell();
```

The `moveCursorTo($position)` method can be used to set the current cursor position to the given absolute character position.
For example, to move the cursor to the beginning of the *user input buffer*, simply call:

```php
$readline->moveCursorTo(0);
```

The `moveCursorBy($offset)` method can be used to change the cursor position
by the given number of characters relative to the current position.
A positive number will move the cursor to the right - a negative number will move the cursor to the left.
For example, to move the cursor one character to the left, simply call:

```php
$readline->moveCursorBy(-1);
```

#### History

By default, users can access the history of previous commands by using their
UP and DOWN cursor keys on the keyboard.
The history will start with an empty state, thus this feature is effectively
disabled, as the UP and DOWN cursor keys have no function then.

Note that the history is not maintained automatically.
Any input the user submits by hitting enter will *not* be added to the history
automatically.
This may seem inconvenient at first, but it actually gives you more control over
what (and when) lines should be added to the history.
If you want to automatically add everything from the user input to the history,
you may want to use something like this:

```php
$stdio->on('data', function ($line) use ($readline) {
    $line = rtrim($line);
    $all = $readline->listHistory();

    // skip empty line and duplicate of previous line
    if ($line !== '' && $line !== end($all)) {
        $readline->addHistory($line);
    }
});
```

The `listHistory(): string[]` method can be used to
return an array with all lines in the history.
This will be an empty array until you add new entries via `addHistory()`.

```php
$list = $readline->listHistory();

assert(count($list) === 0);
```

The `addHistory(string $line): Readline` method can be used to
add a new line to the (bottom position of the) history list.
A following `listHistory()` call will return this line as the last element.

```php
$readline->addHistory('a');
$readline->addHistory('b');

$list = $readline->listHistory();
assert($list === array('a', 'b'));
```

The `clearHistory(): Readline` method can be used to
clear the complete history list.
A following `listHistory()` call will return an empty array until you add new
entries via `addHistory()` again.
Note that the history feature will effectively be disabled if the history is
empty, as the UP and DOWN cursor keys have no function then.

```php
$readline->clearHistory();

$list = $readline->listHistory();
assert(count($list) === 0);
```

The `limitHistory(?int $limit): Readline` method can be used to
set a limit of history lines to keep in memory.
By default, only the last 500 lines will be kept in memory and everything else
will be discarded.
You can use an integer value to limit this to the given number of entries or
use `null` for an unlimited number (not recommended, because everything is
kept in RAM).
If you set the limit to `0` (int zero), the history will effectively be
disabled, as no lines can be added to or returned from the history list.
If you're building a CLI application, you may also want to use something like
this to obey the `HISTSIZE` environment variable:

```php
$limit = getenv('HISTSIZE');
if ($limit === '' || $limit < 0) {
    // empty string or negative value means unlimited
    $readline->limitHistory(null);
} elseif ($limit !== false) {
    // apply any other value if given
    $readline->limitHistory($limit);
}
```

There is no such thing as a `readHistory()` or `writeHistory()` method
because filesystem operations are inherently blocking and thus beyond the scope
of this library.
Using your favorite filesystem API and an appropriate number of `addHistory()`
or a single `listHistory()` call respectively should be fairly straight
forward and is left up as an exercise for the reader of this documentation
(i.e. *you*).

#### Autocomplete

By default, users can use autocompletion by using their TAB keys on the keyboard.
The autocomplete function is not registered by default, thus this feature is
effectively disabled, as the TAB key has no function then.

The `setAutocomplete(?callable $autocomplete): Readline` method can be used to
register a new autocomplete handler.
In its most simple form, you won't need to assign any arguments and can simply
return an array of possible word matches from a callable like this:

```php
$readline->setAutocomplete(function () {
    return array(
        'exit',
        'echo',
        'help',
    );
});
```

If the user types `he [TAB]`, the first two matches will be skipped as they do
not match the current word prefix and the last one will be picked automatically,
so that the resulting input buffer is `hello `.

If the user types `e [TAB]`, then this will match multiple entries and the user
will be presented with a list of up to 8 available word completions to choose
from like this:

```php
> e [TAB]
exit  echo
> e
```

Unless otherwise specified, the matches will be performed against the current
word boundaries in the input buffer.
This means that if the user types `hello [SPACE] ex [TAB]`, then the resulting
input buffer is `hello exit `, which may or may not be what you need depending
on your particular use case.

In order to give you more control over this behavior, the autocomplete function
actually receives three arguments (similar to `ext-readline`'s
[`readline_completion_function()`](http://php.net/manual/en/function.readline-completion-function.php)):
The first argument will be the current incomplete word according to current
cursor position and word boundaries, while the second and third argument will be
the start and end offset of this word within the complete input buffer measured
in (Unicode) characters.
The above examples will be invoked as `$fn('he', 0, 2)`, `$fn('e', 0, 1)` and
`$fn('ex', 6, 8)` respectively.
You may want to use this as an `$offset` argument to check if the current word
is an argument or a root command and the `$word` argument to autocomplete
partial filename matches like this:

```php
$readline->setAutocomplete(function ($word, $offset) {
    if ($offset <= 1) {
        // autocomplete root commands at offset=0/1 only
        return array('cat', 'rm', 'stat');
    } else {
        // autocomplete all command arguments as glob pattern
        return glob($word . '*', GLOB_MARK);
    }
});
```

> Note that the user may also use quotes and/or leading whitespace around the
root command, for example `"hell [TAB]`, in which case the offset will be
advanced such as this will be invoked as `$fn('hell', 1, 4)`.
Unless you use a more sophisticated argument parser, a decent approximation may
be using `$offset <= 1` to check this is a root command. 

If you need even more control over autocompletion, you may also want to access
and/or manipulate the [input buffer](#input-buffer) and [cursor](#cursor)
directly like this:

```php
$readline->setAutocomplete(function () use ($readline) {
    if ($readline->getInput() === 'run') {
        $readline->setInput('run --test --value=42');
        $readline->moveCursorBy(-2);
    }

    // return empty array so normal autocompletion doesn't kick in
    return array();
});
```

You can use a `null` value to remove the autocomplete function again and thus
disable the autocomplete function:

```php
$readline->setAutocomplete(null);
```

#### Keys

The `Readline` class is responsible for reading user input from `STDIN` and
registering appropriate key events.
By default, `Readline` uses a hard-coded key mapping that resembles the one
usually found in common terminals.
This means that normal Unicode character keys ("a" and "b", but also "?", "ä",
"µ" etc.) will be processed as user input, while special control keys can be
used for [cursor movement](#cursor), [history](#history) and
[autocomplete](#autocomplete) functions.
Unknown special keys will be ignored and will not processed as part of the user
input by default.

Additionally, you can bind custom functions to any key code you want.
If a custom function is bound to a certain key code, the default behavior will
no longer trigger.
This allows you to register entirely new functions to keys or to overwrite any
of the existing behavior.

For example, you can use the following code to print some help text when the
user hits a certain key:

```php
$readline->on('?', function () use ($stdio) {
     $stdio->write('Here\'s some help: …' . PHP_EOL);
});
```

Similarly, this can be used to manipulate the user input and replace some of the
input when the user hits a certain key:

```php
$readline->on('ä', function () use ($readline) {
     $readline->addInput('a');
});
```

The `Readline` uses raw binary key codes as emitted by the terminal.
This means that you can use the normal UTF-8 character representation for normal
Unicode characters.
Special keys use binary control code sequences (refer to ANSI / VT100 control
codes for more details).
For example, the following code can be used to register a custom function to the
UP arrow cursor key:

```php
$readline->on("\033[A", function () use ($readline) {
     $readline->setInput(strtoupper($readline->getInput()));
});
```

## Pitfalls

The [`Readline`](#readline) has to redraw the current user
input line whenever output is written to the `STDOUT`.
Because of this, it is important to make sure any output is always
written like this instead of using `echo` statements:

```php
// echo 'hello world!' . PHP_EOL;
$stdio->write('hello world!' . PHP_EOL);
```

Depending on your program, it may or may not be reasonable to
replace all such occurences.
As an alternative, you may utilize output buffering that will
automatically forward all write events to the [`Stdio`](#stdio)
instance like this:

```php
ob_start(function ($chunk) use ($stdio) {
    // forward write event to Stdio instead
    $stdio->write($chunk);

    // discard data from normal output handling
    return '';
}, 1);
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](http://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/stdio-react:^2.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

Internally, it will use the `ext-mbstring` to count and measure string sizes.
If this extension is missing, then this library will use a slighty slower Regex
work-around that should otherwise work equally well.
Installing `ext-mbstring` is highly recommended.

Internally, it will use the `ext-readline` to enable raw terminal input mode.
If this extension is missing, then this library will manually set the required
TTY settings on start and will try to restore previous settings on exit.
Input line editing is handled entirely within this library and does not rely on
`ext-readline`.
Installing `ext-readline` is entirely optional.

Note that *Microsoft Windows is not supported*.
Due to platform inconsistencies, PHP does not provide support for reading from
standard console input without blocking.
Unfortunately, until the underlying PHP feature request is implemented (which
is unlikely to happen any time soon), there's little we can do in this library.
A work-around for this remains unknown.
Your only option would be to entirely
[disable interactive input for Microsoft Windows](https://github.com/clue/psocksd/commit/c2f2f90ffc8ebf8233839ba2f3553f2698930125).
However this package does work on [`Windows Subsystem for Linux`](https://en.wikipedia.org/wiki/Windows_Subsystem_for_Linux) 
(or WSL) without issues. We suggest [installing WSL](https://msdn.microsoft.com/en-us/commandline/wsl/install_guide) 
when you want to run this package on Windows.
See also [#18](https://github.com/clue/php-stdio-react/issues/18) for more details.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT

## More

* If you want to learn more about processing streams of data, refer to the documentation of
  the underlying [react/stream](https://github.com/reactphp/stream) component.
* If you build an interactive CLI tool that reads a command line from STDIN, you
  may want to use [clue/arguments](https://github.com/clue/php-arguments) in
  order to split this string up into its individual arguments and then use
  [clue/commander](https://github.com/clue/php-commander) to route to registered
  commands and their required arguments.
