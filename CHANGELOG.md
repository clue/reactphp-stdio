# Changelog

## 0.2.0 (2015-05-17)

*   Feature: Support echo replacements (asterisk for password prompts)
    (#11)

    ```php
$stdio->getReadline()->setEcho('*');
```

*   Feature: Add accessors for text input buffer and current cursor position
    (#8 and #9)

    ```php
$stdio->getReadline()->setInput('hello);
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
