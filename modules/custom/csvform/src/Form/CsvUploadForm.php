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
    
    

    // Heading of the Form
    $form['heading'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Hello User! Check the Checkbox to Upload Your CSV file') . '</h2>',
      '#prefix' => '<div class="csv-upload-heading">',
      '#suffix' => '</div>',
    ];


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

  /**
   * AJAX callback to toggle the visibility of the CSV container.
   */
  public function toggleCsvUpload(array &$form, FormStateInterface $form_state) {
    // This function returns the part of the form that should be updated via AJAX.
    return $form['csv_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process the uploaded file if the checkbox is checked.
    if ($form_state->getValue('enable_upload', FALSE)) {
      $file = $form_state->getValue('csv_file');

      if ($file) {
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
        $file->setPermanent();
        $file->save();
        
        // Define a batch for processing.
        $batch = new BatchBuilder();
        $batch->setTitle($this->t('Processing CSV...'))
          ->setInitMessage($this->t('Starting CSV processing.'))
          ->setProgressMessage($this->t('Processing CSV...'))
          ->setErrorMessage($this->t('CSV processing encountered an error.'))
          ->setFinishCallback([CsvBatchProcess::class, 'batchFinished']);

        // Add batch operation to process the CSV.
        $batch->addOperation([CsvBatchProcess::class, 'batchProcess'], [$file->getFileUri()]);

        // Set the batch.
        batch_set($batch->toArray());
        
        // Display a message to confirm submission.
        \Drupal::messenger()->addMessage($this->t('File uploaded successfully.'));
      }
    } else {
      // Optionally handle when the checkbox is not checked.
      \Drupal::messenger()->addMessage($this->t('CSV upload was not enabled.'));
    }

    // Redirect after processing.
    $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
  }
}
