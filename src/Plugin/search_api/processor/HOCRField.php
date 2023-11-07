<?php

namespace Drupal\islandora_hocr\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\islandora_hocr\Plugin\search_api\processor\Property\HOCRFieldProperty;
use Drupal\media\Plugin\media\Source\File;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
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
class HOCRField extends ProcessorPluginBase {

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
      static::PROPERTY_NAME => new HOCRFieldProperty([
        'label' => $this->t('HOCR Field'),
        'description' => $this->t('HOCR from referenced media.'),
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
        'computed' => FALSE,
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

    $data = [
      'file' => [
        'value' => NULL,
      ],
      'uri' => [
        'value' => NULL,
      ],
      'content' => [
        'value' => NULL,
      ],
    ];
    $data['file']['callable'] = function () use ($entity, &$data) {
      $data['file']['value'] ??= $this->getFile($entity);
      return $data['file']['value'];
    };
    $data['uri']['callable'] = function () use (&$data) {
      $data['uri']['value'] ??= $data['file']['callable']() ? $data['file']['value']->getFileUri() : NULL;
      return $data['uri']['value'];
    };
    $data['content']['callable'] = function () use (&$data) {
      $data['content']['value'] ??= $data['uri']['callable']() ? file_get_contents($data['uri']['value']) : NULL;
      return $data['content']['value'];
    };

    $fields = $item->getFields();

    foreach ($data as $key => $info) {
      $spec_fields = $this->getFieldsHelper()
        ->filterForPropertyPath(
          $fields,
          $item->getDatasourceId(),
          static::PROPERTY_NAME . ":$key"
        );
      foreach ($spec_fields as $field) {
        if (!$field->getValues()) {
          // Lazily load content from entity, as the field might already be
          // populated.
          $field->addValue($info['callable']());
        }
      }
    }

  }

  /**
   * Find the target file for this node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to find a file containing HOCR.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file containing HOCR, or NULL.
   */
  protected function getFile(NodeInterface $node) : ?FileInterface {
    $media_storage = $this->entityTypeManager->getStorage('media');
    $query = $media_storage->getQuery();

    $query->condition('field_media_of', $node->id());
    $query->condition('field_media_use.entity:taxonomy_term.field_external_uri.uri', 'https://discoverygarden.ca/use#hocr');
    $query->accessCheck(FALSE);

    $media = $query->execute();

    foreach ($media as $medium) {
      /** @var \Drupal\media\MediaInterface $entity */
      $entity = $media_storage->load($medium);
      if (!$entity) {
        continue;
      }

      $source = $entity->getSource();

      if ($source instanceof File) {
        $fid = $source->getSourceFieldValue($entity);
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager->getStorage('file')->load($fid);

        return $file;
      }
    }

    // Failed to find anything applicable/visible.
    return NULL;
  }

}
