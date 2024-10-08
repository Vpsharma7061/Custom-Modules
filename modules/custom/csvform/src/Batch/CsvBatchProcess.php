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
    // Initialize sandbox on the first run.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['seek'] = 0;
      $context['sandbox']['file_size'] = filesize($fileUri);
      $context['sandbox']['processed_rows'] = 0; // Tracks rows processed in previous runs.
      $context['sandbox']['skipped_rows'] = 0; // Initialize skipped rows count.
    }

    if (!isset($context['sandbox']['file_handle'])) {
      $context['sandbox']['file_handle'] = fopen($fileUri, 'r');
    }

    // Get the node import limit from the configuration.
    $config = \Drupal::config('csvform.settings');
    $nodeLimit = $config->get('node_import_limit') ?: 50;

    // Open the file.
    $handle = $context['sandbox']['file_handle'];
    $count = 0;

    while (($row = fgetcsv($handle)) !== FALSE) {
      // Skip rows with incorrect format (for example, fewer than expected columns).
      if (count($row) < 4) {
        $context['sandbox']['skipped_rows']++;
        continue; // Skip this row if it's not valid.
      }

     
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $existing_nodes = $node_storage->loadByProperties([
        'field_name' => $row[0],
        'field_email' => $row[1],
        'field_address' => $row[2],
        'field_contact_no' => $row[3],
      ]);

      if ($existing_nodes) {
        // Increment skipped rows if a node already exists.
        $context['sandbox']['skipped_rows']++;
        continue; // Skip this row as the node already exists.
      }

      // Create a new node of type 'csvform'.
      
        $node = Node::create([
          'type' => 'csvform',
          'title' => $row[0],
          'field_name' => $row[0],
          'field_email' => $row[1],
          'field_address' => $row[2],
          'field_contact_no' => $row[3],
          'status' => 1, // Ensure the status is set to published.
        ]);

        $node->save(); // Save the node.
        $context['results']['processed']++; // Count only successful creations.
      
    

      // Update the file pointer and count the successful row processed.
      $context['sandbox']['seek'] = ftell($handle); // Update file pointer.
      $count++;

      // Limit the number of rows processed according to the configuration.
      if ($count >= $nodeLimit) {
        break; // Stop if the limit is reached.
      }
    }

    fclose($handle);
    unset($context['sandbox']['file_handle']);
    $context['finished'] = 1; // Mark the batch as finished.

    // Report on skipped rows.
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
