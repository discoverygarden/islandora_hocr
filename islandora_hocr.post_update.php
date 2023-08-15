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

/**
 * Ensure `text_files` is not null.
 *
 * When Drupal initializes it, it would give it null, despite being not
 * null. Giving it an empty map seems to do the trick.
 */
function islandora_hocr_post_update_ensure_field_type_has_empty_map() {
  $config = \Drupal::configFactory()->getEditable('search_api_solr.solr_field_type.islandora_hocr_und_7_0_0');
  $value = $config->get('text_files');
  if ($value === NULL) {
    $config->set('text_files', [])
      ->save(TRUE);
  }
}
