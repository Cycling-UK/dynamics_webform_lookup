<?php

namespace Drupal\dynamics_webform_lookup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Dynamics Webform Lookup settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dynamics_webform_lookup_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dynamics_webform_lookup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dynamics_webform_lookup.settings');

    // 1. Environment Switch.
    $form['environment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Active Environment'),
      '#options' => [
        'dev' => $this->t('Dev'),
        'prod' => $this->t('Prod'),
      ],
      '#default_value' => $config->get('environment') ?: 'dev',
      '#description' => $this->t('Choose which API URL to use for searches.'),
    ];

    // 2. API Endpoint Configuration.
    $form['api_config'] = [
      '#type' => 'details',
      '#title' => $this->t('API Endpoint Configuration'),
      '#open' => TRUE,
    ];

    $form['api_config']['api_url_dev'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dev API URL'),
      '#default_value' => $config->get('api_url_dev'),
      '#maxlength' => 1024,
    ];

    $form['api_config']['api_url_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prod API URL'),
      '#default_value' => $config->get('api_url_prod'),
      '#maxlength' => 1024,
    ];

    // 3. Search Logic Configuration.
    $form['search_logic'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Requirements'),
      '#open' => TRUE,
    ];

    $form['search_logic']['require_both_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require both Org Number and Postcode'),
      '#description' => $this->t('If checked, users must provide both values if both fields exist on the form. If unchecked, either field will suffice. If only one field exists on a form, this setting is ignored.'),
      '#default_value' => $config->get('require_both_fields') ?? FALSE,
    ];

    // 4. Sensitive Credentials (Mapped to Key Module).
    $form['advanced_security'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Security (API Credentials)'),
      '#open' => FALSE,
      '#description' => $this->t('Select the Keys that bridge to your environment variables.'),
    ];

    $form['advanced_security']['api_key_id'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Dynamics API Key'),
      '#default_value' => $config->get('api_key_id'),
    ];

    $form['advanced_security']['api_secret_id'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Dynamics API Secret'),
      '#default_value' => $config->get('api_secret_id'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dynamics_webform_lookup.settings')
      ->set('environment', $form_state->getValue('environment'))
      ->set('api_url_dev', $form_state->getValue('api_url_dev'))
      ->set('api_url_prod', $form_state->getValue('api_url_prod'))
      ->set('require_both_fields', (bool) $form_state->getValue('require_both_fields'))
      ->set('api_key_id', $form_state->getValue('api_key_id'))
      ->set('api_secret_id', $form_state->getValue('api_secret_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}