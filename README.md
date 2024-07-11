# Blates

This is a modified version of [Plates](https://platesphp.com) which precompiles any Blade syntax to Plates or PHP syntax before passing it through the Plates renderer.

It simply adds a primary step to compile Blade before passing it into the unmodified Plates classes.

## Installation

Blates is available via Composer:

```
composer require indgy/blates
```

## Documentation

The majority of Blade syntax is supported with the notable exception of components/slots.

Full documentation on Plates can be found at [platesphp.com](https://platesphp.com/).

## Issues

This is still under development and some features will be missing until I need them.

If you discover any bugs or issues with behaviour or performance please create an issue, if you are able a pull request with a fix would be most helpful.

Please make sure to update tests as appropriate.

For major changes, please open an issue first to discuss what you would like to change.

## Credits

Everyone who made Laravel Blade what it is, and the gentlemen of The PHP League who created Plates.

## License

[BSD-3-Clause](https://choosealicense.com/licenses/bsd-3-clause/)
