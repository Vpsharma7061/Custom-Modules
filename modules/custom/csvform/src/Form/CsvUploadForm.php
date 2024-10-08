<?php

namespace Drupal\csvform\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\csvform\Batch\CsvBatchProcess;


/**
 * Class CsvUploadForm.
 */
class CsvUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'csv_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Heading of the Form.
    $form['heading'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Hello User! Check the Checkbox to Upload Your CSV file') . '</h2>',
      '#prefix' => '<div class="csv-upload-heading">',
      '#suffix' => '</div>',
    ];

    // CSV File upload field.
   // Checkbox to enable CSV upload.
   $form['enable_upload'] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Enable CSV Upload'),
    '#description' => $this->t('Check to enable CSV file upload.'),
  ];

  // Container field for the CSV file upload.
  $form['csv_container'] = [
    '#type' => 'container',
    '#attributes' => ['id' => 'csv-file-wrapper'],
    '#states' => [
      'visible' => [
        ':input[name="enable_upload"]' => ['checked' => TRUE],
      ],
    ],
  ];



  // CSV File upload field.
  $form['csv_container']['csv_file'] = [
    '#type' => 'managed_file',
    '#title' => $this->t('CSV File'),
    '#upload_validators' => [
      'file_validate_extensions' => ['csv'],
    ],
    '#upload_location' => 'public://sites/default/files/',
    '#required' => TRUE, // Make it required if necessary.

  ];
    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }


  // Queue Implemenation ####################################################################################




  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $form_state->getValue('csv_file');

    if ($file) {
      // Load the file and make it permanent.
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
      $file->setPermanent();
      $file->save();

      // Enqueue the CSV rows for processing using Queue.
      $this->enqueueCsvRows($file->getFileUri());
      \Drupal::messenger()->addMessage($this->t('CSV queued for processing in the background.'));
    }

    // Redirect after processing.
    $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
  }

  /**
   * Enqueue CSV rows for processing.
   */
  protected function enqueueCsvRows($fileUri) {
    $queue = \Drupal::queue('csv_node_creation_worker');
    $handle = fopen($fileUri, 'r');

    // Retrieve the node import limit from configuration.
    $nodeLimit = \Drupal::config('csvform.settings')->get('node_import_limit') ?: 2; // Default to 2 if not set
    $addedCount = 0;

    if ($handle !== FALSE) {
        // Fetch existing nodes to prevent duplicates.
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $existingNodes = $nodeStorage->loadByProperties(['type' => 'csvform']);

        // Create an array to hold existing node identifiers for quick lookup.
        $existingNodeIdentifiers = [];
        foreach ($existingNodes as $node) {
            // Use a composite key based on the name and email.
            $existingNodeIdentifiers[] = [
                'name' => $node->get('field_name')->value,
                'email' => strtolower($node->get('field_email')->value),
            ];
        }

        // Process the CSV rows.
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($row) === 4) {
                // Normalize current data.
                $currentData = [
                    'name' => trim($row[0]),
                    'email' => strtolower(trim($row[1])),
                    'address' => trim($row[2]),
                    'contact_no' => trim($row[3]),
                ];

                // Check for duplicates in existing nodes.
                $isDuplicate = false;
                foreach ($existingNodeIdentifiers as $existingItem) {
                    if ($existingItem['name'] === $currentData['name'] &&
                        $existingItem['email'] === $currentData['email']) {
                        $isDuplicate = true;
                        break; // Stop checking if we found a match.
                    }
                }

                // If no duplicates are found, add the current data to the queue.
                if (!$isDuplicate) {
                    $queue->createItem($currentData);
                    $addedCount++;

                    // Log the addition of new items.
                    \Drupal::logger('csvform')->debug('New item added to the queue: @data', ['@data' => json_encode($currentData)]);

                    // Stop if we reach the configured limit.
                    if ($addedCount >= $nodeLimit) {
                        break;
                    }
                } else {
                    \Drupal::logger('csvform')->debug('Duplicate found, skipping row: @data', ['@data' => json_encode($currentData)]);
                }
            } else {
                \Drupal::logger('csvform')->warning('CSV row does not have the expected number of columns: @row', ['@row' => json_encode($row)]);
            }
        }

        fclose($handle);
        \Drupal::logger('csvform')->debug('Finished processing the CSV file.');
    } else {
        \Drupal::logger('csvform')->error('Failed to open the CSV file: @fileUri', ['@fileUri' => $fileUri]);
    }
}





  

  




  // Batch Process Implementation #########################################################################


  //  /**
  //  * {@inheritdoc}
  //  */
  // public function submitForm(array &$form, FormStateInterface $form_state) {
  //   // Process the uploaded file if the checkbox is checked.
  //   if ($form_state->getValue('enable_upload', FALSE)) {
  //     $file = $form_state->getValue('csv_file');

  //     if ($file) {
  //       $file = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
  //       $file->setPermanent();
  //       $file->save();
        
  //       // Define a batch for processing.
  //       $batch = new BatchBuilder();
  //       $batch->setTitle($this->t('Processing CSV...'))
  //         ->setInitMessage($this->t('Starting CSV processing.'))
  //         ->setProgressMessage($this->t('Processing CSV...'))
  //         ->setErrorMessage($this->t('CSV processing encountered an error.'))
  //         ->setFinishCallback([CsvBatchProcess::class, 'batchFinished']);

  //       // Add batch operation to process the CSV.
  //       $batch->addOperation([CsvBatchProcess::class, 'batchProcess'], [$file->getFileUri()]);

  //       // Set the batch.
  //       batch_set($batch->toArray());
        
  //       // Display a message to confirm submission.
  //       \Drupal::messenger()->addMessage($this->t('File uploaded successfully.'));
  //     }
  //   } else {
  //     // Optionally handle when the checkbox is not checked.
  //     \Drupal::messenger()->addMessage($this->t('CSV upload was not enabled.'));
  //   }

  //   // Redirect after processing.
  //   $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
  // }


}
