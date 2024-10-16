<?php


namespace Drupal\csvapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CSVAPIController.
 *
 * Handles CSV file uploads and processing.
 */
class CSVAPIController extends ControllerBase {
  
  protected $fileSystem;
  protected $fileUrlGenerator;

  public function __construct(FileSystemInterface $file_system, FileUrlGenerator $file_url_generator) {
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
  }

  // Static method for dependency injection.
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Handles file upload via the API.
   */
  public function upload(Request $request) {
    $uploaded_file = $request->files->get('file');

    if ($uploaded_file && $uploaded_file->isValid()) {
        
        $file_extension = $uploaded_file->getClientOriginalExtension();
        
        if (strtolower($file_extension) !== 'csv') {
            return new JsonResponse(['error' => 'The uploaded file is not a CSV file.'], 400);
        }

        $directory = 'public://';
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
        $destination = $directory . $uploaded_file->getClientOriginalName();
        $file_content = file_get_contents($uploaded_file->getRealPath());
        $this->fileSystem->saveData($file_content, $destination, FileSystemInterface::EXISTS_REPLACE);

        $file = File::create([
            'uri' => $destination,
            'status' => 1,
        ]);
        $file->save();

        $file_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());

      
        $response = [
            'message' => 'Your CSV file has been uploaded successfully.',
            'file_url' => $file_url,  
            'status' => 'success',     
        ];
        return new JsonResponse($response, 200);
    }

    
    return new JsonResponse(['error' => 'Invalid file upload.'], 400);
}








  /**
   * Handles the CSV file processing via the API.
   */
  public function process(Request $request) {
    $raw_content = $request->getContent();
    \Drupal::logger('csvapi')->info('Received raw request data: @content', ['@content' => $raw_content]);

    $data = json_decode($raw_content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
        \Drupal::logger('csvapi')->error('JSON decode error: @error', ['@error' => json_last_error_msg()]);
        return new JsonResponse(['error' => 'Invalid JSON format.'], 400);
    }

    $file_url = $data['file_url'] ?? NULL;

    if (empty($file_url)) {
        \Drupal::logger('csvapi')->error('File URL is required.');
        return new JsonResponse(['error' => 'File URL is required.'], 400);
    }

    $file_path = parse_url($file_url, PHP_URL_PATH);
    \Drupal::logger('csvapi')->info('Parsed file path: @path', ['@path' => $file_path]);

    $file_uri = 'public://' . basename($file_path);
    $file = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $file_uri]);

    if ($file) {
        // Load the CSV file content
        $csv_data = array_map('str_getcsv', file($file_uri));
        
        // Enqueue each row of the CSV file
        $queue_service = \Drupal::service('queue')->get('csvapi_queue');

        foreach ($csv_data as $row) {
            // Enqueue the item with the raw row data
            $queue_service->createItem(['data' => $row]); // Store each row directly
        }

        return new JsonResponse(['message' => 'CSV file items enqueued successfully for processing.'], 200);
    } else {
        \Drupal::logger('csvapi')->error('File could not be found at URI: @uri', ['@uri' => $file_uri]);
    }

    return new JsonResponse(['error' => 'Could not process file.'], 400);
}



public function fetchQueueItems() {
    // Get the database connection
    $connection = \Drupal::database();

    // Query the queue table for all items
    $query = $connection->select('queue', 'q')
        ->fields('q', ['item_id', 'data']) // Adjust fields according to your queue structure
        ->condition('name', 'csvapi_queue') // Ensure this matches your queue name
        ->execute();

    // Initialize an array to hold queue items
    $queue_items = [];

    // Loop through each item retrieved from the queue
    foreach ($query as $record) {
        // Assuming 'data' is serialized, you might need to unserialize it
        $queue_items[] = [
            'id' => $record->item_id,  // The ID of the queue item
            'data' => unserialize($record->data), // Deserialize if it's serialized
        ];
    }

    // Check if there are any items in the queue
    if (empty($queue_items)) {
        // Return a friendly message if the queue is empty
        return new JsonResponse(['message' => 'The queue is currently empty , All items have been processed Successfully!'], 200);
    }

    // Return the queue items in JSON format
    return new JsonResponse($queue_items, 200);
}




}
