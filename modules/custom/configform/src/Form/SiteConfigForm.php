<?php

namespace Drupal\configform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use function file_create_url; // Importing the function here

class SiteConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['configform.settings'];
  }

  /**
   * Build the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('configform.settings');

    // Text Field 1
    $form['text_field_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Field 1'),
      '#default_value' => $config->get('text_field_1') ?: '',
    ];

    // Text Field 2 (overridden by settings.php)
    $form['text_field_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Field 2 (Overridden by settings.php)'),
      '#default_value' => \Drupal::config('configform.settings')->get('text_field_2'),
      '#disabled' => TRUE,
    ];

    // Public file upload.
    $form['public_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload a file to the public directory'),
      '#upload_location' => 'public://uploads/',
      '#default_value' => $config->get('public_file') ? [$config->get('public_file')] : [],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg pdf'],
      ],
    ];

    // Private file upload.
    $form['private_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload a file to the private directory'),
      '#upload_location' => 'private://uploads/',
      '#default_value' => $config->get('private_file') ? [$config->get('private_file')] : [],
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg pdf'],
      ],
    ];

    // URL Field for public file
    $form['public_file_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL for Public File'),
      '#default_value' => $config->get('public_file_url') ?: '',
    ];

    // URL Field for private file
    $form['private_file_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL for Private File'),
      '#default_value' => $config->get('private_file_url') ?: '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler to save configurations.
   */
  /**
 * Submit handler to save configurations.
 */
/**
 * Submit handler to save configurations.
 */
public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('configform.settings');

    // Handle the file uploads for the public file.
    $public_file = $form_state->getValue('public_file');
    if (!empty($public_file)) {
        $file = File::load($public_file[0]);
        $file->setPermanent();
        $file->save();

        // Use the correct service to generate the public file URL.
        $public_file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        $config->set('public_file', $file->id());
        $config->set('public_file_url', $public_file_url);
    }

    // Handle the file uploads for the private file.
    $private_file = $form_state->getValue('private_file');
    if (!empty($private_file)) {
        $file = File::load($private_file[0]);
        $file->setPermanent();
        $file->save();

        // Use the correct service to generate the private file URL.
        $private_file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        $config->set('private_file', $file->id());
        $config->set('private_file_url', $private_file_url);
    }

    // Save the other form values.
    $config->set('text_field_1', $form_state->getValue('text_field_1'));
    $config->save();

    parent::submitForm($form, $form_state);
}


}
