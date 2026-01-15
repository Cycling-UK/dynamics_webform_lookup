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

    // 1. Environment Switch (Primary UI)
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

    // 2. API URL Fields
    $form['api_url_dev'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dev API URL'),
      '#default_value' => $config->get('api_url_dev'),
      '#maxlength' => 1024,
    ];

    $form['api_url_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prod API URL'),
      '#default_value' => $config->get('api_url_prod'),
      '#maxlength' => 1024,
    ];

    // 3. Sensitive Credentials Protection
    // This creates a collapsed accordion that must be clicked to open.
    $form['advanced_security'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Security (API Credentials)'),
      '#open' => FALSE, // Keeps it closed by default
      '#description' => $this->t('Warning: Changing these values will affect the connection to Dynamics.'),
    ];

    $form['advanced_security']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['advanced_security']['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('api_secret'),
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
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}