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
    // Initialize sandbox.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['seek'] = 0;
      $context['sandbox']['file_size'] = filesize($fileUri);
    }

    // Open the file.
    $handle = fopen($fileUri, 'r');
    if ($handle === FALSE) {
      throw new \Exception('Unable to open CSV file.');
    }

    fseek($handle, $context['sandbox']['seek']);
    $limit = 50;
    $count = 0;

    while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
      // Ensure CSV format is correct (4 columns).
      if (count($row) !== 4) {
        $context['results']['skipped']++;
        continue;
      }

      // Create a new node of type 'csvform'.
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

      $context['sandbox']['seek'] = ftell($handle);  // Update file pointer.
      $context['results']['processed']++;
      $count++;

      // Limit the number of rows processed in one batch operation.
      if ($count >= $limit) {
        break;
      }
    }

    fclose($handle);

    // Update progress.
    $context['finished'] = $context['sandbox']['seek'] / $context['sandbox']['file_size'];
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Batch completed. Processed %count rows.', ['%count' => $results['processed']]));
    }
    else {
      \Drupal::messenger()->addMessage(t('Batch did not complete.'), 'error');
    }
  }
}
