<?php

namespace Drupal\csvapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class CSVAPIController.
 *
 * Handles CSV file uploads and processing.
 */
class CSVAPIController extends ControllerBase {

  /**
   * Handles file upload via the API.
   */
  public function upload(Request $request) {
    $uploaded_file = $request->files->get('file');

    if ($uploaded_file && $uploaded_file->isValid()) {
      $directory = 'public://';
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $destination = $directory . $uploaded_file->getClientOriginalName();
      $file_content = file_get_contents($uploaded_file->getRealPath());
      $file_system->saveData($file_content, $destination, FileSystemInterface::EXISTS_REPLACE);

      $file = File::create([
        'uri' => $destination,
        'status' => 1,
      ]);
      $file->save();

      $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());

      if ($file) {
        $response = [
          'message' => 'Your CSV file has been uploaded successfully.',
          'file_url' => $file_url,
        ];
        return new JsonResponse($response, 200);
      }
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
    $file_uri = 'public://' . basename($file_path);

    $file = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $file_uri]);

    if ($file) {
      $queue_item = ['file_id' => reset($file)->id()];
      $queue_service = \Drupal::service('queue')->get('csvapi_queue');
      $queue_service->createItem($queue_item);

      return new JsonResponse(['message' => 'File enqueued successfully for processing.'], 200);
    } else {
      \Drupal::logger('csvapi')->error('File could not be found at URI: @uri', ['@uri' => $file_uri]);
    }
    
    return new JsonResponse(['error' => 'Could not process file.'], 400);
  }
}
