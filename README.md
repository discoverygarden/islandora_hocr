# Islandora hOCR

## Introduction

Adds the hOCR derivative functionality.

## Installation

Install as usual, see
[this](https://www.drupal.org/docs/extending-drupal/installing-modules) for
further information.

## Configuration
An action must be created and configured to generate an hOCR derivative. The
action must also be triggered by a context in order for the derivative to be
made. Refer to the [official Islandora docs][islandora-docs] for more information.

## Usage

This module contains a migration facilitating the creation of a media use term for use in common Islandora configurations. Enabling the module will expose the `islandora_hocr_media_uses` migration to generate a media use term of the URI `https://discoverygarden.ca/use#hocr`.

```shell
# Flow might be something like:
drush en islandora_hocr
drush migrate:import islandora_hocr_media_uses
```

## Troubleshooting/Issues

Having problems or solved one? contact
[discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)

[islandora-docs]: https://islandora.github.io/documentation/concepts/derivatives/
