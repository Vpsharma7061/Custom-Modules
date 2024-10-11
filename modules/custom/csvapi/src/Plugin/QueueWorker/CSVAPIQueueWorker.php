<?php

namespace Drupal\csvapi\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * Processes CSV files from the queue.
 *
 * @QueueWorker(
 *   id = "csvapi_queue",
 *   title = @Translation("CSV API Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CSVAPIQueueWorker extends QueueWorkerBase {

  /**
   * Processes the queue item.
   *
   * @param mixed $item
   *   The item to process, containing the file ID.
   */
  public function processItem($item) {
    $file = File::load($item['file_id']);

    if ($file) {
      $this->processCSV($file);
    } else {
      \Drupal::logger('csvapi')->error('Failed to load file with ID: @id', ['@id' => $item['file_id']]);
    }
  }

  /**
   * Processes the CSV file and creates nodes.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity to process.
   */
  protected function processCSV(File $file) {
    \Drupal::logger('csvapi')->info('Starting to process CSV file: @uri', ['@uri' => $file->getFileUri()]);

   
    $data = fopen($file->getFileUri(), 'r');
    if ($data === FALSE) {
      \Drupal::logger('csvapi')->error('Could not open file @uri for reading.', ['@uri' => $file->getFileUri()]);
      return;
    }

    while (($row = fgetcsv($data)) !== FALSE) {
      if (count($row) <= 4) {
        $node = Node::create([
          'type' => 'csvform',
          'title' => $row[0],
          'field_name' => $row[0],
          'field_email' => $row[1],
          'field_address' => $row[2],
          'field_contact_no' => $row[3],
          'status' => 1,
          'uid' => 1,
        ]);
        $node->save();

        \Drupal::logger('csvapi')->info('Node created: @title', ['@title' => $row[0]]);
      } else {
        \Drupal::logger('csvapi')->warning('Row skipped due to insufficient columns: @row', [
          '@row' => implode(', ', $row),
        ]);
      }
    }

    fclose($data);
  }
}
