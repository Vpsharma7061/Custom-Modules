<?php

namespace Drupal\csvform\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;

/**
 * A CSV node creation worker.
 *
 * @QueueWorker(
 *   id = "csv_node_creation_worker",
 *   title = @Translation("CSV Node Creation Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CsvNodeCreationWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    \Drupal::logger('csvform')->info('Processing data: @data', ['@data' => print_r($data, TRUE)]);

    
    try {
      $node = Node::create([
        'type' => 'csvform',
        'title' => $data['name'],
        'field_name' => $data['name'],
        'field_email' => $data['email'],
        'field_address' => $data['address'],
        'field_contact_no' => $data['contact_no'],
        'status' => 1, 
        'uid' => 1, 
      ]);
      $node->save();
      \Drupal::logger('csvform')->info('Node created: @title', ['@title' => $data['name']]);
    } catch (\Exception $e) {
      \Drupal::logger('csvform')->error('Error creating node for @name: @message', [
        '@name' => $data['name'],
        '@message' => $e->getMessage(),
      ]);
    }
  }
}
