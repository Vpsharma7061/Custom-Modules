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
    // Checkbox to enable CSV upload.
    $form['enable_upload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CSV Upload'),
      '#description' => $this->t('Check to enable CSV file upload.'),
      '#ajax' => [
        'callback' => '::toggleCsvUpload',
        'wrapper' => 'csv-file-wrapper',
        'event' => 'change',
      ],
    ];

    // CSV File upload field, hidden by default.
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV File'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#upload_location' => 'public://sites/default/files/',
      '#prefix' => '<div id="csv-file-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="enable_upload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * AJAX callback to toggle the visibility of the CSV file upload field.
   */
  public function toggleCsvUpload(array &$form, FormStateInterface $form_state) {
    return $form['csv_file'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load the uploaded file.
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

      // Redirect after batch processing.
      $form_state->setRedirectUrl(Url::fromRoute('csvform.csv_upload_form'));
    }
  }

}
