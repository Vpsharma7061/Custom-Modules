<?php

namespace Drupal\csvform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CsvImportSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['csvform.settings'];
  }

  public function getFormId() {
    return 'csv_import_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('csvform.settings');

    $form['node_import_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Node Import Limit'),
      '#default_value' => $config->get('node_import_limit') ?: 50,
      '#description' => $this->t('Set the maximum number of nodes to import from the CSV file.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('csvform.settings')
      ->set('node_import_limit', $form_state->getValue('node_import_limit'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
