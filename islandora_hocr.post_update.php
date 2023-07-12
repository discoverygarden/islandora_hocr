<?php

/**
 * @file
 * Post-update hooks.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Install initial request handler and field type if they do not yet exist.
 */
function islandora_hocr_post_update_install_initial_entities() {
  $ids = [
    'search_api_solr.solr_field_type.islandora_hocr_und_7_0_0',
    'search_api_solr.solr_request_handler.request_handler_select_islandora_hocr_7_0_0',
  ];

  $config_dir = \Drupal::service('extension.list.module')->getPath('islandora_hocr') . '/config/install';

  foreach ($ids as $id) {
    $data = Yaml::parseFile("{$config_dir}/{$id}.yml");
    $config = \Drupal::configFactory()->getEditable($id);
    if ($config->isNew()) {
      $config->initWithData($data)->save(TRUE);
    }
  }

}
