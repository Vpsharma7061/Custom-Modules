<?php

namespace Drupal\csvform\Batch;

use Drupal\node\Entity\Node;

/**
 * Class CsvBatchProcess.
 */
class CsvBatchProcess {

  /**
   * Batch processing callback.
   */
  public static function batchProcess(string $fileUri, array &$context) {
  
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['seek'] = 0;
      $context['sandbox']['file_size'] = filesize($fileUri);
      $context['sandbox']['processed_rows'] = 0; 
      $context['sandbox']['skipped_rows'] = 0; 
    }

    if (!isset($context['sandbox']['file_handle'])) {
      $context['sandbox']['file_handle'] = fopen($fileUri, 'r');
    }

    
    $config = \Drupal::config('csvform.settings');
    $nodeLimit = $config->get('node_import_limit') ?: 2;

    
    $handle = $context['sandbox']['file_handle'];
    $count = 0;

    while (($row = fgetcsv($handle)) !== FALSE) {
      
      if (count($row) < 4) {
        $context['sandbox']['skipped_rows']++;
        continue; 
      }

     
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $existing_nodes = $node_storage->loadByProperties([
        'field_name' => $row[0],
        'field_email' => $row[1],
        'field_address' => $row[2],
        'field_contact_no' => $row[3],
      ]);

      if ($existing_nodes) {
        $context['sandbox']['skipped_rows']++;
        continue; 
      }

      
        $node = Node::create([
          'type' => 'csvform',
          'title' => $row[0],
          'field_name' => $row[0],
          'field_email' => $row[1],
          'field_address' => $row[2],
          'field_contact_no' => $row[3],
          'status' => 1, 
        ]);

        $node->save(); 
        $context['results']['processed']++; 
      
    

      $context['sandbox']['seek'] = ftell($handle); 
      $count++;

      
      if ($count >= $nodeLimit) {
        break; 
      }
    }

    fclose($handle);
    unset($context['sandbox']['file_handle']);
    $context['finished'] = 1; 

    
    $context['results']['skipped'] = $context['sandbox']['skipped_rows'];
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Batch completed. Processed %count rows.', ['%count' => $results['processed']]));
      if (isset($results['skipped'])) {
        \Drupal::messenger()->addMessage(t('Skipped %count rows due to existing nodes.', ['%count' => $results['skipped']]));
      }
    } else {
      \Drupal::messenger()->addMessage(t('Batch did not complete.'), 'error');
    }
  }
}
