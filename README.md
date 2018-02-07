# Chadicus Coding Standard

[![Latest Stable Version](https://poser.pugx.org/chadicus/coding-standard/v/stable)](https://packagist.org/packages/chadicus/coding-standard)
[![Latest Unstable Version](https://poser.pugx.org/chadicus/coding-standard/v/unstable)](https://packagist.org/packages/chadicus/coding-standard)
[![License](https://poser.pugx.org/chadicus/coding-standard/license)](https://packagist.org/packages/chadicus/coding-standard)

[![Total Downloads](https://poser.pugx.org/chadicus/coding-standard/downloads)](https://packagist.org/packages/chadicus/coding-standard)
[![Daily Downloads](https://poser.pugx.org/chadicus/coding-standard/d/daily)](https://packagist.org/packages/chadicus/coding-standard)
[![Monthly Downloads](https://poser.pugx.org/chadicus/coding-standard/d/monthly)](https://packagist.org/packages/chadicus/coding-standard)

A [PHP_CodeSniffer](http://www.squizlabs.com/php-codesniffer) coding standard.

See what "sniffs" are enforced [here](http://chadicus.github.io/coding-standard).

## Composer

This standard is meant to be used in a project using [Composer](http://getcomposer.org).  It can be added to your project's composer.json as follows:

```sh
composer require --dev chadicus/coding-standard
```

Then to use it, you can run the following (or add to your build process):

```bash
./vendor/bin/phpcs --standard=$(pwd)/vendor/chadicus/coding-standard/Chadicus YOUR_FILES_AND_DIRECTORIES
```

## Contact

Developers may be contacted at:

 * [Pull Requests](https://github.com/chadicus/coding-standard/pulls)
 * [Issues](https://github.com/chadicus/coding-standard/issues)
