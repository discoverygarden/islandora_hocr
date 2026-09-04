<?php

namespace Drupal\islandora_hocr\EventSubscriber;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent;
use Drupal\search_api_solr\Event\PostExtractResultsEvent;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\QueryType\Select\Query\Query;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Highlighting library event subscriber.
 */
class HighlightingSolrConfigEventSubscriber implements EventSubscriberInterface, ContainerInjectionInterface {

  private const ENV_PLUGIN_PATH = 'SOLR_HOCR_PLUGIN_PATH';
  private const ENV_SNIPPETS = 'ISLANDORA_HOCR_SNIPPETS';

  /**
   * Constructor.
   */
  public function __construct(
    protected ?string $libraryPath,
    protected FieldsHelperInterface $fieldsHelper,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) : static {
    return new static(
      getenv(static::ENV_PLUGIN_PATH) ?: NULL,
      $container->get('search_api.fields_helper'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    // XXX: Guard against search_api_solr not being installed: this is called
    // at container-compile time, which can happen before search_api_solr's
    // namespace is registered. Our container is rebuilt once it is installed,
    // at which point the events register as normal.
    if (class_exists(SearchApiSolrEvents::class)) {
      $events += [
        SearchApiSolrEvents::POST_CONFIG_FILES_GENERATION => 'addLibraryInfo',
        SearchApiSolrEvents::PRE_QUERY => 'preQuery',
        SearchApiSolrEvents::POST_EXTRACT_RESULTS => 'postExtractResults',
      ];
    }

    return $events;
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
    // XXX: Lucene version is approximately equal to Solr version; should
    // suffice for comparison, here.
    if (version_compare($event->getLuceneMatchVersion(), '9.0.0', '>=') && !empty(getenv(static::ENV_PLUGIN_PATH))) {
      // phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
      @trigger_error(strtr('The specification of <lib/> elements (as for which :var is used) is no longer recommended as of Solr 9. See README.md.', [
        ':var' => static::ENV_PLUGIN_PATH,
      ]), E_USER_DEPRECATED);
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
    unset($info);
    $sapi_query->setOption('islandora_hocr_properties', $highlight_props);
    $sapi_query->setOption('islandora_hocr_fields', $highlight_fields);
    $s_query->setHandler('select_ocr')
      ->addParam('hl', 'true')
      ->addParam('hl.ocr.fl', implode(',', array_keys($highlight_fields)))
      // Deal with absolute image coordinates.
      ->addParam('hl.ocr.absoluteHighlights', 'on')
      // We expect OCR per page.
      ->addParam('hl.ocr.trackPages', 'off')
      // Set the default number of snippets.
      ->addParam('hl.snippets', getenv(static::ENV_SNIPPETS) ?: '20');
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
