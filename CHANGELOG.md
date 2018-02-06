# Changelog

## 2.1.0 (2018-02-05)

*   Feature: Add support for binding custom functions to any key code
    (#70 by @clue)

    ```php
    $readline->on('?', function () use ($stdio) {
        $stdio->write('Do you need help?');
    });
    ```

*   Feature: Add `addInput()` helper method
    (#69 by @clue)

    ```php
    $readline->addInput('hello');
    $readline->addInput(' world');
    ```

## 2.0.0 (2018-01-24)

A major compatibility release to update this package to support all latest
ReactPHP components!

This update involves a minor BC break due to dropped support for legacy
versions. We've tried hard to avoid BC breaks where possible and minimize impact
otherwise. We expect that most consumers of this package will actually not be
affected by any BC breaks, see below for more details.

*   BC break: Remove all deprecated APIs (individual `Stdin`, `Stdout`, `line` etc.)
    (#64 and #68 by @clue)

    >   All of this affects only what is considered "advanced usage".
        If you're affected by this BC break, then it's recommended to first
        update to the intermediary v1.2.0 release, which provides alternatives
        to all deprecated APIs and then update to this version without causing a
        BC break.

*   Feature / BC break: Consistently emit incoming "data" event with trailing newline
    unless stream ends without trailing newline (such as when piping).
    (#65 by @clue)

*   Feature: Forward compatibility with upcoming Stream v1.0 and EventLoop v1.0
    and avoid blocking when `STDOUT` buffer is full.
    (#68 by @clue)

## 1.2.0 (2017-12-18)

*   Feature: Optionally use `ext-readline` to enable raw input mode if available.
    This extension is entirely optional, but it is more efficient and reliable
    than spawning the external `stty` command.
    (#63 by @clue)

*   Feature: Consistently return boolean success from `write()` and
    avoid sending unneeded control codes between writes
    (#60 by @clue)

*   Deprecated: Deprecate input helpers and output helpers and
    recommend using `Stdio` as a normal `DuplexStreamInterface` instead.
    (#59 and #62 by @clue)

    ```php
    // deprecated
    $stdio->on('line', function ($line) use ($stdio) {
        $stdio->writeln("input: $line");
    });

    // recommended alternative
    $stdio->on('data', function ($line) use ($stdio) {
        $stdio->write("input: $line");
    });
    ```

*   Improve test suite by adding forward compatibility with PHPUnit 6
    (#61 by @carusogabriel)

## 1.1.0 (2017-11-01)

*   Feature: Explicitly end stream on CTRL+D and emit final data on EOF without EOL
    (#56 by @clue)

*   Feature: Support running on non-TTY and closing STDIN and STDOUT streams
    (#57 by @clue)

*   Feature / Fix: Restore blocking mode before closing and restore TTY mode on unclean shutdown
    (#58 by @clue)

*   Improve documentation to detail why async console I/O is not supported on Microsoft Windows
    (#54 by @clue)

*   Improve test suite by adding PHPUnit to require-dev,
    fix HHVM build for now again and ignore future HHVM build errors and
    lock Travis distro so future defaults will not break the build
    (#46, #48 and #52 by @clue)

## 1.0.0 (2017-01-08)

*   First stable release, now following SemVer

> Contains no other changes, so it's actually fully compatible with the v0.5.0 release.

## 0.5.0 (2017-01-08)

*   Feature: Add history support
    (#40 by @clue)

*   Feature: Add autocomplete support
    (#41 by @clue)

*   Feature: Suggest using ext-mbstring, otherwise use regex fallback
    (#42 by @clue)

*   Remove / BC break: Remove undocumented and low quality skeletons/helpers for
    `Buffer`, `ProgressBar` and `Spinner` (mostly dead code)
    (#39, #43 by @clue)

*   First class support for PHP 5.3 through PHP 7 and HHVM
    (#44 by @clue)

*   Simplify and restructure examples
    (#45 by @clue)

## 0.4.0 (2016-09-27)

*   Feature / BC break: The `Stdio` is now a well-behaving duplex stream
    (#35 by @clue)

*   Feature / BC break: The `Readline` is now a well-behaving readable stream
    (#32 by @clue)

*   Feature: Add `Readline::getPrompt()` helper
    (#33 by @clue)

*   Feature / Fix: All unsupported special keys, key codes and byte sequences will now be ignored
    (#36, #30, #19, #38 by @clue)

*   Fix: Explicitly redraw prompt on empty input
    (#37 by @clue)

*   Fix: Writing data that contains multiple newlines will no longer end up garbled
    (#34, #35 by @clue)

## 0.3.1 (2015-11-26)

*   Fix: Support calling `Readline::setInput()` during `line` event
    (#28)

    ```php
    $stdio->on('line', function ($line) use ($stdio) {
        $stdio->getReadline()->setInput($line . '!');
    });
    ```

## 0.3.0 (2015-05-18)

*   Feature: Support multi-byte UTF-8 characters and account for cell width
    (#20)

*   Feature: Add support for HOME and END keys
    (#22)

*   Fix: Clear readline input and restore TTY on end
    (#21)

## 0.2.0 (2015-05-17)

*   Feature: Support echo replacements (asterisk for password prompts)
    (#11)

    ```php
    $stdio->getReadline()->setEcho('*');
    ```

*   Feature: Add accessors for text input buffer and current cursor position
    (#8 and #9)

    ```php
    $stdio->getReadline()->setInput('hello');
    $stdio->getReadline()->getCursorPosition();
    ```

*   Feature: All setters now return self to allow easy method chaining
    (#7)

    ```php
    $stdio->getReadline()->setPrompt('Password: ')->setEcho('*')->setInput('secret');
    ```

*   Feature: Only redraw() readline when necessary
    (#10 and #14)

## 0.1.0 (2014-09-08)

*   First tagged release

## 0.0.0 (2013-08-21)

*   Initial concept
