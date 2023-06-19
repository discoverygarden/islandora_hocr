<?php

namespace Drupal\islandora_hocr\Plugin\Action;

use Drupal\islandora\Plugin\Action\AbstractGenerateDerivative;

/**
 * Generates a hOCR derivative event.
 *
 * @Action(
 *   id = "generate_hocr_derivative",
 *   label = @Translation("Generate hOCR from an image"),
 *   type = "node"
 * )
 */
class HocrDerivative extends AbstractGenerateDerivative {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['path'] = '[date:custom:Y]-[date:custom:m]/[node:nid]-hOCR.hocr';
    $config['source_term_uri'] = 'http://pcdm.org/use#OriginalFile';
    $config['derivative_term_uri'] = 'https://discoverygarden.ca/use#hocr';
    $config['mimetype'] = 'text/vnd.hocr+html';
    $config['queue'] = 'islandora-connector-ocr';
    $config['destination_media_type'] = 'file';
    $config['args'] = '-c tessedit_create_hocr=1';
    return $config;
  }

}
