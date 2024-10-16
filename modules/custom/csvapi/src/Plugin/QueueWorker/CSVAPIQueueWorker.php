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
   *   The item to process, which now contains the CSV row data.
   */
  public function processItem($item) {
    // Assuming $item is an associative array containing a 'data' key with the row data
    if (isset($item['data'])) {
      $this->createNodeFromCSVRow($item['data']);
    } else {
      \Drupal::logger('csvapi')->error('Queue item does not contain data.');
    }
  }

  /**
   * Creates a node from a CSV row.
   *
   * @param array $row
   *   The CSV row data.
   */
  protected function createNodeFromCSVRow(array $row) {
    // Ensure there are enough fields in the row
    if (count($row) >= 4) {
      $node = Node::create([
        'type' => 'csvform',
        'title' => $row[0], // Assuming title is the first column
        'field_name' => $row[0],
        'field_email' => $row[1],
        'field_address' => $row[2],
        'field_contact_no' => $row[3],
        'status' => 1,
        'uid' => 1, // You may want to set this to the currently logged-in user
      ]);
      $node->save();

      \Drupal::logger('csvapi')->info('Node created: @title', ['@title' => $row[0]]);
    } else {
      \Drupal::logger('csvapi')->warning('Row skipped due to insufficient columns: @row', [
        '@row' => implode(', ', $row),
      ]);
    }
  }
}
