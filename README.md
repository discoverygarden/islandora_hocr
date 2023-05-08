# islandora_hocr

# Islandora hOCR

## Introduction

Adds the hOCR derivative functionality. (WIP)

## Usage

Currently, this module contains a migration facilitating the creation of a media use term for use in common Islandora configurations. Enabling the module will expose the `islandora_hocr_media_uses` migration to generate a media use term of the URI `https://discoverygarden.ca/use#hocr`.

```shell
# Flow might be something like:
drush en islandora_hocr
drush migrate:import islandora_hocr_media_uses
```

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-8) for
further information.

## Troubleshooting/Issues

Having problems or solved one? contact
[discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
