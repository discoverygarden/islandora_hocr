<?php

namespace Drupal\islandora_hocr\Entity;

use Drupal\search_api_solr\Entity\SolrFieldType;

class HOCRSolrFieldType extends SolrFieldType {

  public function getDynamicFields(?int $solr_major_version = NULL) {
    $dynamic_fields = parent::getDynamicFields($solr_major_version);

    foreach ($dynamic_fields as &$field) {
      $name =& $field['name'];
      if (strpos($name, 't') === 0) {
        $name = "h{$name}";
      }
    }
    unset($field);

    return $dynamic_fields;
  }

}
