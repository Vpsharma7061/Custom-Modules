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
  
    $form['heading'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Hello User! Check the Checkbox to Upload Your CSV file') . '</h2>',
      '#prefix' => '<div class="csv-upload-heading">',
      '#suffix' => '</div>',
    ];

   
   $form['enable_upload'] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Enable CSV Upload'),
    '#description' => $this->t('Check to enable CSV file upload.'),
  ];

  
  $form['csv_container'] = [
    '#type' => 'container',
    '#attributes' => ['id' => 'csv-file-wrapper'],
    '#states' => [
      'visible' => [
        ':input[name="enable_upload"]' => ['checked' => TRUE],
      ],
    ],
  ];



  
  $form['csv_container']['csv_file'] = [
    '#type' => 'managed_file',
    '#title' => $this->t('CSV File'),
    '#upload_validators' => [
      'file_validate_extensions' => ['csv'],
    ],
    '#upload_location' => 'public://sites/default/files/',
    '#required' => TRUE, 

  ];
    
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
    
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
      $file->setPermanent();
      $file->save();

      
      $this->enqueueCsvRows($file->getFileUri());
      \Drupal::messenger()->addMessage($this->t('CSV queued for processing in the background.'));
    }

    
    $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
  }

  /**
   * Enqueue CSV rows for processing.
   */
  protected function enqueueCsvRows($fileUri) {
    $queue = \Drupal::queue('csv_node_creation_worker');
    $handle = fopen($fileUri, 'r');

    
    $nodeLimit = \Drupal::config('csvform.settings')->get('node_import_limit') ?: 2; 
    $addedCount = 0;

    if ($handle !== FALSE) {
        
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $existingNodes = $nodeStorage->loadByProperties(['type' => 'csvform']);

      
        $existingNodeIdentifiers = [];
        foreach ($existingNodes as $node) {
        
            $existingNodeIdentifiers[] = [
                'name' => $node->get('field_name')->value,
                'email' => strtolower($node->get('field_email')->value),
            ];
        }

        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($row) === 4) {
              
                $currentData = [
                    'name' => trim($row[0]),
                    'email' => strtolower(trim($row[1])),
                    'address' => trim($row[2]),
                    'contact_no' => trim($row[3]),
                ];

          
                $isDuplicate = false;
                foreach ($existingNodeIdentifiers as $existingItem) {
                    if ($existingItem['name'] === $currentData['name'] &&
                        $existingItem['email'] === $currentData['email']) {
                        $isDuplicate = true;
                        break; 
                    }
                }

                if (!$isDuplicate) {
                    $queue->createItem($currentData);
                    $addedCount++;

                    
                    \Drupal::logger('csvform')->debug('New item added to the queue: @data', ['@data' => json_encode($currentData)]);

                  
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
    
  //   if ($form_state->getValue('enable_upload', FALSE)) {
  //     $file = $form_state->getValue('csv_file');

  //     if ($file) {
  //       $file = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
  //       $file->setPermanent();
  //       $file->save();
        
    
  //       $batch = new BatchBuilder();
  //       $batch->setTitle($this->t('Processing CSV...'))
  //         ->setInitMessage($this->t('Starting CSV processing.'))
  //         ->setProgressMessage($this->t('Processing CSV...'))
  //         ->setErrorMessage($this->t('CSV processing encountered an error.'))
  //         ->setFinishCallback([CsvBatchProcess::class, 'batchFinished']);

      
  //       $batch->addOperation([CsvBatchProcess::class, 'batchProcess'], [$file->getFileUri()]);

        
  //       batch_set($batch->toArray());
        
        
  //       \Drupal::messenger()->addMessage($this->t('File uploaded successfully.'));
  //     }
  //   } else {
      
  //     \Drupal::messenger()->addMessage($this->t('CSV upload was not enabled.'));
  //   }

    
  //   $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
  // }


}
