<?php

namespace Drupal\csvapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Queue\QueueFactory;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CSVAPIQueueController.
 *
 * Handles CSV file processing and queue item management.
 */
class CSVAPIQueueController extends ControllerBase {

  protected $queueFactory;

  public function __construct(QueueFactory $queue_factory) {
    $this->queueFactory = $queue_factory;
  }

  // Static method for dependency injection.
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue')
    );
  }

  /**
   * Process a single item from the queue using its item ID.
   */
  public function processSingleItem(Request $request) {
    // Get the item ID from the request body
    $data = json_decode($request->getContent(), TRUE);

    if (!isset($data['item_id'])) {
      return new JsonResponse(['error' => 'Item ID is required.'], 400);
    }

    $item_id = $data['item_id'];
    $queue = $this->queueFactory->get('csvapi_queue');

    // Load the item from the queue
    $connection = \Drupal::database();
    $results = [];
    $query = $connection->select('queue', 'q')
      ->fields('q', ['item_id', 'data'])
      ->condition('item_id', $item_id)
      ->condition('name', 'csvapi_queue')
      ->execute()
      ->fetch();

    if (!$query) {
      return new JsonResponse(['error' => 'Item not found in the queue.'], 404);
    }

    // Unserialize the queue item data
    $rowData = unserialize($query->data);

    // Process the queue item and create a node
    if ($this->createNodeFromQueueItem($rowData)) {
      // Remove the item from the queue after successful processing
      $queue->deleteItem((object) ['item_id' => $item_id]);
      $results[$item_id] = ['status' => 'success', 'message' => 'Node created successfully, item removed from the queue.'];
      return new JsonResponse(['results' => $results], 200);
    } else {
      return new JsonResponse(['error' => 'Failed to create node from queue item.'], 500);
    }
  }



 /**
   * Process multiple items from the queue using their item IDs.
   */
  public function processMultipleItems(Request $request) {
    // Log the entry point of the method
    \Drupal::logger('csvapi')->info('Entered processMultipleItems method.');

    // Get the item IDs from the request body
    $data = json_decode($request->getContent(), TRUE);
    \Drupal::logger('csvapi')->info('Request data received: @data', ['@data' => print_r($data, TRUE)]);

    if (!isset($data['item_ids']) || !is_array($data['item_ids'])) {
        \Drupal::logger('csvapi')->error('Item IDs are required and must be an array.');
        return new JsonResponse(['error' => 'Item IDs are required and must be an array.'], 400);
    }

    $item_ids = $data['item_ids'];
    \Drupal::logger('csvapi')->info('Processing item IDs: @item_ids', ['@item_ids' => implode(',', $item_ids)]);

    $queue = $this->queueFactory->get('csvapi_queue');
    $connection = \Drupal::database();
    $results = [];

    // Loop through each item ID to process them
    foreach ($item_ids as $item_id) {
        \Drupal::logger('csvapi')->info('Processing queue item with ID: @item_id', ['@item_id' => $item_id]);

        // Load the item from the queue
        $query = $connection->select('queue', 'q')
            ->fields('q', ['item_id', 'data'])
            ->condition('item_id', $item_id)
            ->condition('name', 'csvapi_queue')
            ->execute()
            ->fetch();

        // Check if the item was found in the queue
        if (!$query) {
            \Drupal::logger('csvapi')->error('Item not found in the queue for item ID: @item_id', ['@item_id' => $item_id]);
            $results[$item_id] = ['status' => 'error', 'message' => 'Item not found in the queue.'];
            continue;
        }

        \Drupal::logger('csvapi')->info('Queue item found for item ID: @item_id, data: @data', ['@item_id' => $item_id, '@data' => print_r($query->data, TRUE)]);

        // Unserialize the queue item data
        $rowData = unserialize($query->data);
        \Drupal::logger('csvapi')->info('Unserialized data for item ID: @item_id, row data: @rowData', ['@item_id' => $item_id, '@rowData' => print_r($rowData, TRUE)]);

        // Process the queue item and create a node
        if ($this->createNodeFromQueueItem($rowData)) {
            // Remove the item from the queue after successful processing
            $queue->deleteItem((object) ['item_id' => $item_id]);
            \Drupal::logger('csvapi')->info('Node created successfully for item ID: @item_id, item removed from queue.', ['@item_id' => $item_id]);
            $results[$item_id] = ['status' => 'success', 'message' => 'Node created successfully, item removed from the queue.'];
        } else {
            \Drupal::logger('csvapi')->error('Failed to create node from queue item for item ID: @item_id', ['@item_id' => $item_id]);
            $results[$item_id] = ['status' => 'error', 'message' => 'Failed to create node from queue item.'];
        }
    }

    \Drupal::logger('csvapi')->info('Processing complete, results: @results', ['@results' => print_r($results, TRUE)]);

    return new JsonResponse(['results' => $results], 200);
  }


  /**
   * Create a node from CSV row data.
   *
   * @param array $rowData
   *   The CSV row data.
   *
   * @return bool
   *   TRUE if the node is created successfully, FALSE otherwise.
   */
  protected function createNodeFromQueueItem($item) {
    // Log the entire item to see its structure
    \Drupal::logger('csvapi')->info('Queue item data: @data', ['@data' => print_r($item, TRUE)]);

    // Access the data correctly
    $rowData = isset($item['data']) ? $item['data'] : NULL;

    if ($rowData && count($rowData) >= 4) {
        try {
            $node = Node::create([
                'type' => 'csvform',
                'title' => $rowData[0], // Name
                'field_name' => $rowData[0], // Name
                'field_email' => $rowData[1], // Email
                'field_address' => $rowData[2], // Address
                'field_contact_no' => $rowData[3], // Contact Number
                'status' => 1,
                'uid' => 1,
            ]);
            $node->save();

            \Drupal::logger('csvapi')->info('Node created successfully with ID: @id', ['@id' => $node->id()]);
            return new JsonResponse(['message' => 'Node created successfully.', 'node_id' => $node->id()], 201);
        } catch (\Exception $e) {
            \Drupal::logger('csvapi')->error('Node creation failed: @message', ['@message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Node creation failed due to an error.'], 500);
        }
    } else {
        \Drupal::logger('csvapi')->error('Insufficient data to create node from queue item. Data received: @data', ['@data' => print_r($item, TRUE)]);
        return new JsonResponse(['error' => 'Failed to create node from queue item.'], 400);
    }
}


}