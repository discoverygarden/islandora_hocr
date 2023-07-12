<?php

namespace Drupal\islandora_hocr\Plugin\search_api\processor\Property;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Processor\ProcessorPropertyInterface;

class HOCRFieldProperty extends ComplexDataDefinitionBase implements ProcessorPropertyInterface {

  use StringTranslationTrait;

  /**
   * {@inheritDoc}
   */
  public function getPropertyDefinitions() {
    if (empty($this->propertyDefinitions)) {
      $this->propertyDefinitions = [
        'uri' => new ProcessorProperty([
          'label' => $this->t('HOCR URI Field'),
          'description' => $this->t('URI of HOCR content from referenced media.'),
          'type' => 'string',
          'processor_id' => $this->getProcessorId(),
          'is_list' => FALSE,
          'computed' => FALSE,
        ]),
        'content' => new ProcessorProperty([
          'label' => $this->t('HOCR Content Field'),
          'description' => $this->t('HOCR content from referenced media.'),
          'type' => 'string',
          'processor_id' => $this->getProcessorId(),
          'is_list' => FALSE,
          'computed' => FALSE,
        ]),
      ];
    }

    return $this->propertyDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessorId() {
    return $this->definition['processor_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return !empty($this->definition['hidden']);
  }

  /**
   * {@inheritdoc}
   */
  public function isList() {
    return (bool) ($this->definition['is_list'] ?? parent::isList());
  }


}
