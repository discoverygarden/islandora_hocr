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

## Configuration

### Solr

We expect to make use of the [Solr OCR Highlighting Plugin](https://dbmdz.github.io/solr-ocrhighlighting/). The particulars of its installation are ultimately up to the environment into which it is being installed.

We have a single environment variable to allow the path of the library on the Solr instance to be specified, such that we can add its path to the configset for Solr:

- `SOLR_HOCR_PLUGIN_PATH`: A path resolvable by Solr to the directory containing the OCR Highlighting Plugin JAR.

There are a couple of config entities included:
- the `islandora_hocr` field type to perform tokenization
- the "Select w/ HOCR highlighting" `/select_ocr` request handler.

### HOCR Indexing

To `node` entities, we have added the ability to index HOCR from related media, making use of the [Solr OCR Highlighting Plugin](https://dbmdz.github.io/solr-ocrhighlighting/0.8.3/)

As an example, you might add the `islandora_hocr_field:content` property to be indexed in Solr via the Search API Solr config, as `islandora_hocr_field`, as a `Fulltext ("islandora_hocr")` field.

Something of an aside, but the `islandora_hocr_field:uri` is presently prototypical: The Solr OCR Highlighting plugin has another character filter which handles processing paths into the contents of the files; however, in the context of things communicating via the network, such access might not always be possible, particular should access control enter in to the equation... as such, we presently expect the full page-level OCR document to be pushed for each page.

## Usage

Assuming indexing is configured as above, with a `islandora_hocr_field`, then you might programmatically perform a Search API query with something like:

```php
$index = \Drupal\search_api\Entity\Index::load('default_solr_index');
$query = $index->query();

// The search term(s).
$query->keys('bravo');
// Additional conditions, as desired.
$query->addCondition('type', 'islandora_object');
// Activate our highlighting behaviour.
$query->setOption('islandora_hocr_properties', [
  'islandora_hocr_field' => [],
]);

// Perform the query.
$results = $query->execute();

// Get the additionally-populated property info, so we can identify what fields from the highlighted results correspond to which property.
$info = $results->getQuery()->getOption('islandora_hocr_properties');
// This should be an associative array mapping language codes to Solr fields,
// which can then be found in the $highlights below.
$language_fields = $info['islandora_hocr_field']['language_fields'];

// When processing the results, the
foreach ($results as $result) {
  // Highlighting info can be acquired from the items. The format here is the
  // same as the format from https://dbmdz.github.io/solr-ocrhighlighting/0.8.3/query/#response-format
  // for the given item/document.
  $highlights = $result->getExtraData('islandora_hocr_highlights');
}
```

## Troubleshooting/Issues

Having problems or solved one? contact
[discoverygarden](http://support.discoverygarden.ca).

### Known issues

- [Solr Cloud Package](https://dbmdz.github.io/solr-ocrhighlighting/0.8.3/installation/#for-solrcloud-users-installation-as-a-solr-package) (in)compatibility: The path to the library could be omitted; however, the conditional inclusion of prefixes in the config entities is problematic.

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
