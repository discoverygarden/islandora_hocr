<?php

namespace Drupal\islandora_hocr\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

class RequestHandlerAdjuster implements ConfigFactoryOverrideInterface {

  protected ConfigFactoryInterface $configFactory;

  protected CacheableMetadata $cacheableMetadata;

  /**
   * Constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    $this->configFactory = $config_factory;
    $this->cacheableMetadata = new CacheableMetadata();
  }

  public function loadOverrides($names) {
    $overrides = [];

    foreach ($this->configFactory->listAll() as $config_name) {
      if (strpos($config_name, 'search_api_solr.solr_request_handler') !== 0) {
        continue;
      }
      $config = $this->configFactory->getEditable($config_name);

      $request_handler = $config->get('request_handler');
      if ($request_handler['name'] !== '/select') {
        continue;
      }

      $comps = array_column($request_handler['arr'] ?? [], NULL, 'name');
      if (isset($comps['components']) && in_array('ocrHighlight', $comps['components'])) {
        // Already in the set of components; nothing to do.
        continue;
      }

      $components = array_merge(
        $comps['first-components']['str'] ?? [],
        $comps['components']['str'] ?? [
          ['VALUE' => 'query'],
          ['VALUE' => 'facet'],
          ['VALUE' => 'mlt'],
          ['VALUE' => 'highlight'],
          ['VALUE' => 'stats'],
          ['VALUE' => 'debug'],
          ['VALUE' => 'expand'],
        ],
        $comps['last-components']['str'] ?? []
      );

      $query_offset = array_search(['VALUE' => 'query'], $components, TRUE);
      array_splice($components, $query_offset + 1, 0, [
        ['VALUE' => 'ocrHighlight'],
      ]);

      $overrides[$config_name]['request_handler']['arr'] = array_merge(
        array_filter($request_handler['arr'] ?? [], function ($value) {
          return !in_array(
            $value['name'],
            ['first-components', 'last-components', 'components']
          );
        }),
        [
          [
            'name' => 'components',
            'str' => $components,
          ],
        ]
      );
      $this->cacheableMetadata->addCacheableDependency($config);
    }

    return $overrides;
  }

  public function getCacheSuffix() {
    return 'islandora_hocr_overrides';
  }

  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  public function getCacheableMetadata($name) {
    return $this->cacheableMetadata;
  }

}
