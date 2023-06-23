<?php

namespace Drupal\islandora_hocr\EventSubscriber;

use Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\PostFieldMappingEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Highlighting library event subscriber.
 */
class HighlightingSolrConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * Path to the library to be added to the Solr config.
   *
   * @var string
   */
  protected string $libraryPath;

  /**
   * Constructor.
   */
  public function __construct(
    string $library_path
  ) {
    $this->libraryPath = $library_path;
  }

  /**
   * Static factory.
   *
   * @return self
   *   An instance of this class.
   */
  public static function create() : self {
    return new static(
      getenv('SOLR_HOCR_PLUGIN_PATH')
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiSolrEvents::POST_CONFIG_FILES_GENERATION => 'addLibraryInfo',
      SearchApiSolrEvents::POST_FIELD_MAPPING => 'remapField',
    ];
  }

  /**
   * Event responder; add library path to the Solr config.
   *
   * @param \Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent $event
   *   The event to which we are responding.
   */
  public function addLibraryInfo(PostConfigFilesGenerationEvent $event) : void {
    if (!isset($this->libraryPath)) {
      return;
    }

    $files = $event->getConfigFiles();

    if (!isset($files['solrconfig_extra.xml'])) {
      throw new \LogicException('Missing "solrconfig_extra.xml".');
    }
    $files['solrconfig_extra.xml'] .= <<<EOXML
<lib dir="{$this->libraryPath}" regex=".*\\.jar" />

EOXML;

    $event->setConfigFiles($files);
  }

}
