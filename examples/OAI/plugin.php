
<?php
namespace Drupal\islandora_local\Plugin\OaiMetadataMap;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\rest_oai_pmh\Plugin\OaiMetadataMap\DublinCoreRdf;
/**
 * Default Metadata Map.
 *
 * @OaiMetadataMap(
 *  id = "digital_object",
 *  label = @Translation("OAI Digital Object"),
 *  metadata_format = "oai_qdc",
 *  template = {
 *    "type" = "module",
 *    "name" = "rest_oai_pmh",
 *    "directory" = "templates",
 *    "file" = "oai-default"
 *  }
 * )
 */
class DCOMap extends DublinCoreRdf {
  /**
   *
   */
  public function getMetadataFormat() {
    return [
      'metadataPrefix' => 'oai_qdc',
      'schema' => 'http://worldcat.org/xmlschemas/qdc/1.0/qdc-1.0.xsd',
      'metadataNamespace' => 'http://worldcat.org/xmlschemas/qdc-1.0/',
    ];
  }
  /**
   *
   */
  public function getMetadataWrapper() {
    return [
      'oai_qdc' => [
        '@xmlns:oai_qdc' => 'http://worldcat.org/xmlschemas/qdc-1.0/',
        '@xmlns:dcterms' => 'http://purl.org/dc/terms/',
        '@xmlns:dc' => 'http://purl.org/dc/elements/1.1/',
        '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        '@xsi:schemaLocation' => 'http://worldcat.org/xmlschemas/qdc-1.0/ http://worldcat.org/xmlschemas/qdc/1.0/qdc-1.0.xsd http://purl.org/net/oclcterms http://worldcat.org/xmlschemas/oclcterms/1.4/oclcterms-1.4.xsd',
      ],
    ];
  }
  /**
   * Method to transform the provided entity into the desired metadata record.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   the entity to transform.
   *
   * @return string
   *   rendered XML.
   */
  public function transformRecord(ContentEntityInterface $entity) {
    if (!\Drupal::moduleHandler()->moduleExists('rdf')) {
      \Drupal::logger('rest_oai_pmh')->warning(
        $this->t("Can't use RDF Mapping-based Dublin Core without the RDF module enabled!")
      );
      return '';
    }
    $render_array['metadata_prefix'] = 'oai_dc';
    $rdf_mapping = rdf_get_mapping($entity->getEntityTypeId(), $entity->bundle());
    $field_blacklist = [
      'field_restrictions',
      'field_digital_id',
      'field_digital_project',
      'field_physical_identifier',
      'field_topic_location',
      'field_staff_note',
      'field_conversion_specifications',
      'field_date_digitized',
      'field_transcription',
    ];
    foreach ($entity->getFields() as $field_id => $fieldItemList) {
      if (in_array($field_id, $field_blacklist) || !$fieldItemList->access() || $fieldItemList->isEmpty()) {
        continue;
      }
      $field_mapping = $rdf_mapping->getPreparedFieldMapping($field_id);
      $element = FALSE;
      if (!empty($field_mapping)) {
        // See if the field is mapped to a property in this schema
        // e.g oai_dc only will print Dublin Core tags.
        foreach ($field_mapping['properties'] as $property) {
          if (strpos($property, 'dc11:') === 0 || strpos($property, 'dc:') === 0) {
            // Fix the dc namespaces.
            $element = preg_replace(['/^dc:/', '/^dc11:/'], ['dcterms:', 'dc:'], $property);
          }
          if ($element) {
            break;
          }
        }
      }
      // If $element is set, we have a valid property.
      if ($element) {
        // Add all the values for this field so the twig template can print.
        foreach ($fieldItemList as $item) {
          $index = $item->mainPropertyName();
          if (!empty($field_mapping['datatype_callback'])) {
            $callback = $field_mapping['datatype_callback']['callable'];
            $arguments = isset($field_mapping['datatype_callback']['arguments']) ? $field_mapping['datatype_callback']['arguments'] : NULL;
            $data = $item->getValue();
            $value = call_user_func($callback, $data, $arguments);
          }
          elseif ($index == 'target_id' && !empty($item->entity)) {
            $value = $item->entity->label();
            // Local metadata quirks.
            // Include first authority link URI, if available.
            if ($item->entity->hasField('field_authority_link') && !$item->entity->field_authority_link->isEmpty()) {
              $value .= ', ' . $item->entity->field_authority_link->first()->uri;
            }
            // Include relation if an Archival Resource.
            if ($item->entity->bundle() == 'archival_resource') {
              $variables['elements']['dc:relation'][] = ($item->entity->field_finding_aid_link->isEmpty()) ? $item->entity->toUrl('canonical', ['absolute' => TRUE])->toString() : $item->entity->field_finding_aid_link->first()->uri;
            }
            // Use the URI for dcterms:isFormatOf.
            if ($item->entity->bundle() == 'archival_object') {
              $value = $item->entity->toUrl('canonical', ['absolute' => TRUE])->toString();
            }
          }
          else {
            $value = $item->getValue()[$index];
          }
          $render_array['elements'][$element][] = $value;
        }
      }
    }
    return parent::build($render_array);
  }
}
