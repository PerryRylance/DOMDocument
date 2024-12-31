# DOMDocument
An extension of PHP's DOMDocument library. This library implements many common jQuery functions, adds support for HTML5, CSS selectors, method chaining and performing operations on sets of matched results.

## IMPORTANT NOTICE - The future of this library
With the release of PHP 8.4's [new DOM features](https://www.php.net/manual/en/migration84.new-features.php#migration84.new-features.dom), the core features of this package are now supported natively, that is, HTML5 and CSS selectors.

Whilst this library is still compatible, if there are any future versions then I will be handing over this functionality to PHP's new DOM classes.

I have experimented with the new version and as far as I can tell there is now no means to register our own classes as extensions of the built in node class. The `HTMLDocument` is `final` and I cannot see any documented way to register node classes. With that said, the documentation hasn't been updated yet, neither has Intelephense seemingly.

I will wait until the documentation has been updated and then decide whether this package will be deprecated or not. I may also take this library in a different direction such as providing a single jQuery like function to manipulate documents without touching or extending the built in implementation.

Please watch this space!

## Requirements
- PHP >= 8.2.0
- Composer

## Installation
I recommend installing this package via Composer.

`composer require perry-rylance/dom-document`

## Usage
Instantiate DOMDocument and enjoy the jQuery-like functions now at your disposal.

As of version 2.0.0, this library does not add jQuery-like methods to DOMElement, in order to avoid future collisions with the HTML living standard spec. Rather, these jQuery-like methods are implemented on `DOMObject` in the same manner jQuery objects work.

## Documentation
Read the libraries [documentation](https://perryrylance.github.io/DOMDocument/).

The requirements to generate documentation are as follows:

- `phpDocumentor` must be [installed](https://docs.phpdoc.org/guide/getting-started/installing.html#installation).

To generate documentation, use the following command.

`php <path/to/your/phpDocumentor.phar> -t ./docs --ignore vendor/`

## Testing & Development
As of 3.0.0, tests are run via Docker to facilitate cross PHP version testing.

All tests can be run by running `tests.sh`.

To run individual tests from your native CLI:

`docker-compose run php82 php /app/vendor/bin/phpunit tests --filter=testCastToString`

## Migrating from 2.* to 3.*
Version 2.* is not compatible with PHP 8.3, version 3.0.0 addresses this.

### Breaking changes
- The static method `contains` has moved from `DOMElement` to `DOMObject`, because as of 8.3 `contains` is implemented as a non-static method on `DOMElement`.
- The methods `load` and `loadHTML` now take integer flags as options and return boolean. This is to satisfy function signatures being the same as PHP's native `DOMDocument` in future versions.

## Migrating from 2.0.* to 2.1.*

### Breaking changes
- `first()` and `last()` now return a `DOMObject` instead of a `DOMElement`.

## Migrating from 1.0.* to 2.*
If you are considering switching from 1.0.* to >= 2.0.0 please note there are some considerations to be aware of.

### Moved methods
Many, many methods which were previously implemented on `DOMElement` are now on `DOMObject`. This helps avoid collisions with PHP's native `DOMElement` as more convenience methods are added there, like in the recent RFC sprint.

### Avoid querySelector
The method `querySelector` is still implemented and publicly visible for internal reasons, however, I'd recommend avoiding using this method.

Any code which did expect a single `DOMElement` from `querySelector` may now create fatal errors.

`DOMDocument::querySelector` now returns a `DOMObject` in an effort to make _almost_ all common methods still available and keep old code stable, however, some methods (such as `isBefore`) are not implemented by `DOMObject`. These methods are intended for internal use. Documentation will be updated to reflect this.

### Static contains
`DOMElement::contains` is now static, in line with jQuery's design pattern. This may create warnings for users migrating from older versions.

### PHP requirement
This library has _dropped support_ for PHP < 8.2.0 in version >= 3.0.0.

### Enhanced manipulation
Many methods, such as `append`, `prepend`, `before`, `after`, `wrap`, and many others, now support passing in an entire array or `DOMObject` as an argument.

This can be used to append content to multiple elements, where there is more than one element in the set.

If the target set contains only one element, then the same elements passed into these functions will be added to the DOM tree.

If the target set contains multiple elements, then the input elements will be cloned - please note that manipulating the input set after performing these operations may not affect the DOM tree, because the new content is cloned from the input set.

## Support
Please feel free to open issues here or submit pull requests.
