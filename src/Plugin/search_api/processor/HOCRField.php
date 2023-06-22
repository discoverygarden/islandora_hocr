<?php

namespace Drupal\islandora_hocr\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\Plugin\media\Source\File;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Facilitate indexing of HOCR.
 *
 * @SearchApiProcessor(
 *   id = "islandora_hocr_field",
 *   label = @Translation("Islandora hOCR field"),
 *   description = @Translation("Add hOCR to the index."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class HOCRField extends ProcessorPluginBase implements ContainerFactoryPluginInterface {

  use PluginFormTrait;

  const PROPERTY_NAME = 'islandora_hocr_field';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    if (!$datasource || $datasource->getEntityTypeId() != 'node') {
      return [];
    }

    return [
      static::PROPERTY_NAME => new ProcessorProperty([
        'label' => $this->t('HOCR Field'),
        'description' => $this->t('HOCR content from referenced media.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ]),
    ];
  }

  /**
   * {@inheritDoc}
   *
   * Adapted from https://git.drupalcode.org/project/search_api/-/blob/8.x-1.x/src/Plugin/search_api/processor/EntityType.php#L47-67
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException $e) {
      return;
    }

    if (!($entity instanceof NodeInterface)) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath(
        $item->getFields(),
        $item->getDatasourceId(),
        static::PROPERTY_NAME
      );

    if ($value = $this->getContent($entity)) {
      foreach ($fields as $field) {
        $field->addValue($value);
      }
    }
  }

  /**
   * Acquire content for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to obtain content.
   *
   * @return false|string|void
   *   The content if it was file-backed, FALSE if we failed to read it, or the
   *   empty string if it was _not_ file-backed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getContent(NodeInterface $node) {
    $media_storage = $this->entityTypeManager->getStorage('media');
    $query = $media_storage->getQuery();

    $query->condition('field_media_of', $node->id());
    $query->condition('field_media_use.entity:taxonomy_term.field_external_uri.uri', 'https://discoverygarden.ca/use#hocr');

    $media = $query->execute();

    $medium = reset($media);
    if (!$medium) {
      return;
    }

    /** @var \Drupal\media\MediaInterface $entity */
    $entity = $media_storage->load($medium);
    if (!$entity) {
      return;
    }

    $source = $entity->getSource();

    if ($source instanceof File) {
      $fid = $source->getSourceFieldValue($entity);
      $uri = $this->entityTypeManager->getStorage('file')->load($fid)->getFileUri();
      return file_get_contents($uri);
    }

    // Unsure how to obtain media content, as it is not file-backed.
    return '';
  }

}
