<?php

namespace Drupal\islandora_hocr\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\PostExtractResultsEvent;
use Drupal\search_api_solr\Event\PostFieldMappingEvent;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\QueryType\Select\Query\Query;
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
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected FieldsHelperInterface $fieldsHelper;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructor.
   */
  public function __construct(
    string $library_path,
    FieldsHelperInterface $fields_helper,
    LanguageManagerInterface $language_manager
  ) {
    $this->libraryPath = $library_path;
    $this->fieldsHelper = $fields_helper;
    $this->languageManager = $language_manager;
  }

  /**
   * Static factory.
   *
   * @return self
   *   An instance of this class.
   */
  public static function create() : self {
    return new static(
      getenv('SOLR_HOCR_PLUGIN_PATH'),
      \Drupal::service('search_api.fields_helper'),
      \Drupal::languageManager()
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiSolrEvents::POST_CONFIG_FILES_GENERATION => 'addLibraryInfo',
      SearchApiSolrEvents::PRE_QUERY => 'preQuery',
      SearchApiSolrEvents::POST_EXTRACT_RESULTS => 'postExtractResults',
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

  /**
   * Pre-query event handler; add in OCR highlighting info, if requested.
   *
   * If the `islandora_hocr_properties` option is populated with an associative
   * array of properties mapping to an array (presently, initially expected to
   * be empty), we will identify the related fields and attempt to highlight
   * them.
   *
   * @param \Drupal\search_api_solr\Event\PreQueryEvent $event
   *   The event object.
   */
  public function preQuery(PreQueryEvent $event) : void {
    $sapi_query = $event->getSearchApiQuery();
    $s_query = $event->getSolariumQuery();

    if ($sapi_query->getProcessingLevel() < QueryInterface::PROCESSING_FULL) {
      return;
    }

    if (!($s_query instanceof Query)) {
      return;
    }

    $highlight_props = $sapi_query->getOption('islandora_hocr_properties', []);
    $highlight_fields = [];

    if (!$highlight_props) {
      return;
    }

    $index = $sapi_query->getIndex();
    $backend = $index->getServerInstance()->getBackend();
    if (!($backend instanceof SolrBackendInterface)) {
      return;
    }

    $language_fields = $backend->getSolrFieldNamesKeyedByLanguage(
      $sapi_query->getLanguages(),
      $index
    );

    foreach ($highlight_props as $prop => &$info) {
      $info['language_fields'] = $language_fields[$prop];
      foreach ($info['language_fields'] as $field) {
        $highlight_fields[$field] = TRUE;
      }
    }

    $sapi_query->setOption('islandora_hocr_properties', $highlight_props);
    $sapi_query->setOption('islandora_hocr_fields', $highlight_fields);

    $s_query->setHandler('select_ocr')
      ->addParam('hl', 'true')
      ->addParam('hl.ocr.fl', implode(',', array_keys($highlight_fields)))
      //
      ->addParam('hl.ocr.absoluteHighlights', 'on')
      // We expect OCR per page.
      ->addParam('hl.ocr.trackPages', 'off');
  }

  /**
   * Post-result extraction event handler; add highlighting info where relevant.
   *
   * @param \Drupal\search_api_solr\Event\PostExtractResultsEvent $event
   *   The event object.
   */
  public function postExtractResults(PostExtractResultsEvent $event) : void {
    $sapi_query = $event->getSearchApiQuery();
    $index = $sapi_query->getIndex();
    $backend = $index->getServerInstance()->getBackend();
    if (!($backend instanceof SolrBackendInterface)) {
      return;
    }

    $result_set = $event->getSearchApiResultSet();
    $response = $result_set->getExtraData('search_api_solr_response');
    $ocr_hl = $response['ocrHighlighting'] ?? FALSE;
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($result_set as $item) {
      // Adapted from https://git.drupalcode.org/project/search_api_solr/-/blob/a5d0557c58f7a13c7cc63d1709fbc3fa5dca39ae/src/Plugin/search_api/backend/SearchApiSolrBackend.php?page=4#L3105-3107
      $solr_id = Utility::hasIndexJustSolrDatasources($index) ?
        str_replace('solr_document/', '', $item->getId()) :
        implode('-', [
          $backend->getTargetedSiteHash($index),
          $backend->getTargetedIndexId($index),
          $item->getId(),
        ]);
      if (empty($ocr_hl[$solr_id])) {
        continue;
      }

      $item->setExtraData('islandora_hocr_highlights', $ocr_hl[$solr_id]);
    }
  }

}
