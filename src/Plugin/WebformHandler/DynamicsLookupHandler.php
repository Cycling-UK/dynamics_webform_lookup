<?php

namespace Drupal\dynamics_webform_lookup\Plugin\WebformHandler;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Webform lookup handler for Dynamics.
 *
 * @WebformHandler(
 * id = "dynamics_lookup",
 * label = @Translation("Dynamics Lookup"),
 * category = @Translation("External Integration"),
 * description = @Translation("Search Dynamics via Power Automate with Key module security."),
 * )
 */
class DynamicsLookupHandler extends WebformHandlerBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->tempStoreFactory = $container->get('tempstore.private');
    $instance->configFactory = $container->get('config.factory');
    $instance->keyRepository = $container->get('key.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/jquery';

    $form['elements']['dynamics_messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'dynamics-messages-wrapper'],
      '#weight' => -100,
    ];

    $results_el = &$this->findElement($form['elements'], 'lookup_results');
    if ($results_el) {
      $results_el['#prefix'] = '<div id="lookup-results-wrapper">';
      $results_el['#suffix'] = '</div>';
      $results_el['#validated'] = TRUE;
      $results_el['#ajax'] = [
        'callback' => [$this, 'autofillAjaxCallback'],
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Populating fields...')],
      ];

      $tempstore = $this->tempStoreFactory->get('dynamics_lookup');
      if ($options = $tempstore->get('dynamics_options')) {
        $results_el['#options'] = $options;
      }
    }

    $container_el = &$this->findElement($form['elements'], 'dynamics_lookup_container');
    if ($container_el) {
      $container_el['dynamics_search_button'] = [
        '#type' => 'button',
        '#value' => $this->t('Find Organisation'),
        '#ajax' => [
          'callback' => [$this, 'searchAjaxCallback'],
          'progress' => ['type' => 'throbber'],
        ],
        '#limit_validation_errors' => [],
        '#weight' => 100,
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }
  }

  public function searchAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $tempstore = $this->tempStoreFactory->get('dynamics_lookup');
    $config = $this->configFactory->get('dynamics_webform_lookup.settings');
    
    $response->addCommand(new HtmlCommand('#dynamics-messages-wrapper', ''));
    $response->addCommand(new InvokeCommand('.dynamics-inline-error', 'remove'));

    // 1. Detect which fields are present on the form.
    $has_org_field = $form_state->hasValue('lookup_org_num');
    $has_postcode_field = $form_state->hasValue('lookup_postcode');
    $require_both = $config->get('require_both_fields') ?? FALSE;

    $org_num = $has_org_field ? trim((string) $form_state->getValue('lookup_org_num')) : '';
    $postcode = $has_postcode_field ? trim((string) $form_state->getValue('lookup_postcode')) : '';

    // 2. Conditional Validation Logic.
    $errors = [];
    if ($has_org_field && $has_postcode_field) {
      if ($require_both && (empty($org_num) || empty($postcode))) {
        $errors[] = $this->t('Both Organisation Number and Postcode are required for this search.');
      } elseif (empty($org_num) && empty($postcode)) {
        $errors[] = $this->t('Please enter either an organisation number or a postcode to search.');
      }
    } elseif ($has_postcode_field && empty($postcode)) {
      $errors[] = $this->t('Please enter a postcode to search.');
    } elseif ($has_org_field && empty($org_num)) {
      $errors[] = $this->t('Please enter an organisation number to search.');
    } elseif (!$has_org_field && !$has_postcode_field) {
      $errors[] = $this->t('Configuration error: No search fields found on this form.');
    }

    if (!empty($errors)) {
      $error_html = '<div class="dynamics-inline-error messages messages--error">' . implode(' ', $errors) . '</div>';
      $response->addCommand(new PrependCommand('#edit-dynamics-lookup-container', $error_html));
      return $response;
    }

    // 3. Credential Retrieval.
    $key_id = $config->get('api_key_id');
    $secret_id = $config->get('api_secret_id');
    $key_entity = $key_id ? $this->keyRepository->getKey($key_id) : NULL;
    $secret_entity = $secret_id ? $this->keyRepository->getKey($secret_id) : NULL;
    $api_key = $key_entity ? trim($key_entity->getKeyValue()) : '';
    $secret = $secret_entity ? trim($secret_entity->getKeyValue()) : '';

    if (empty($api_key) || empty($secret)) {
      \Drupal::logger('dynamics_debug')->error('Key lookup failed. Check if Key entities "@key" and "@secret" exist.', [
        '@key' => $key_id,
        '@secret' => $secret_id,
      ]);
      $response->addCommand(new HtmlCommand('#dynamics-messages-wrapper', '<div class="messages messages--error">Security configuration missing. Please check module settings.</div>'));
      return $response;
    }
    
    $env = $config->get('environment') ?: 'dev';
    $api_url = ($env === 'prod') ? $config->get('api_url_prod') : $config->get('api_url_dev');

    if (empty($api_url)) {
      $response->addCommand(new HtmlCommand('#dynamics-messages-wrapper', '<div class="messages messages--error">Configuration error: API URL missing.</div>'));
      return $response;
    }

    // 4. API Payload Construction.
    $payload = [
      'application_secret' => $secret,
      'search_term' => !empty($postcode) ? strtoupper($postcode) : strtoupper($org_num),
    ];
    if ($has_org_field) {
      $payload['cuk_registrationnumber'] = strtoupper($org_num);
    }
    if ($has_postcode_field) {
      $payload['address1_postalcode'] = strtoupper($postcode);
    }

    try {
      $api_res = $this->httpClient->post($api_url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'apikey' => $api_key,
          'apisecret' => $secret,
          'Request-Type' => 'account-lookup',
        ],
        'json' => $payload,
        'timeout' => 45,
      ]);

      $items = json_decode($api_res->getBody()->getContents(), TRUE) ?? [];
      $options = ['' => $this->t('- Select Result (@count found) -', ['@count' => count($items)])];
      $results_data = [];

      foreach ($items as $item) {
        $id = $item['accountid'] ?? substr(hash('sha256', json_encode($item)), 0, 8);
        $options[$id] = ($item['name'] ?? 'Unknown') . (!empty($item['address1_postalcode']) ? ' (' . $item['address1_postalcode'] . ')' : '');
        $results_data[$id] = $item;
      }

      $tempstore->set('dynamics_options', $options);
      $tempstore->set('dynamics_results_data', $results_data);
      
      $results_el = &$this->findElement($form['elements'], 'lookup_results');
      if ($results_el) {
        $results_el['#options'] = $options;
        $response->addCommand(new ReplaceCommand('#lookup-results-wrapper', $results_el));
      }
      $response->addCommand(new HtmlCommand('#dynamics-messages-wrapper', '<div class="messages messages--status">' . count($items) . ' result(s) found.</div>'));
    } catch (\Exception $e) {
      \Drupal::logger('dynamics_debug')->error('Dynamics Search Error: @msg', ['@msg' => $e->getMessage()]);
      $response->addCommand(new HtmlCommand('#dynamics-messages-wrapper', '<div class="messages messages--error">Search failed.</div>'));
    }
    return $response;
  }

  public function autofillAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $tempstore = $this->tempStoreFactory->get('dynamics_lookup');
    $selected_id = $form_state->getValue('lookup_results');
    $results_data = $tempstore->get('dynamics_results_data');

    if (!$results_data || empty($selected_id) || !isset($results_data[$selected_id])) {
      return $response;
    }

    $record = $results_data[$selected_id];
    $mapping = [
      'business_name'                => $record['name'] ?? '',
      'business_registered_name'     => $record['cuk_registeredname'] ?? $record['name'] ?? '',
      'address_line_1'               => $record['address1_line1'] ?? '',
      'address_line_2'               => $record['address1_line2'] ?? '',
      'address_town'                 => $record['address1_city'] ?? '',
      'address_postcode'             => $record['address1_postalcode'] ?? '',
      'county'                       => $record['address1_county'] ?? '',
    ];

    foreach ($mapping as $field => $value) {
      $selector = '[name="' . $field . '"], [name$="[' . $field . ']"]';
      $response->addCommand(new InvokeCommand($selector, 'val', [$value]));
      $response->addCommand(new InvokeCommand($selector, 'trigger', ['change']));
    }

    return $response;
  }

  protected function &findElement(array &$elements, $key) {
    if (isset($elements[$key])) return $elements[$key];
    foreach (Element::children($elements) as $child) {
      if (is_array($elements[$child])) {
        $found = &$this->findElement($elements[$child], $key);
        if ($found !== NULL) return $found;
      }
    }
    $null = NULL;
    return $null;
  }
}