<?php

namespace Drupal\graphmailer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Graph Mailer settings.
 */
class GraphMailerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'graphmailer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['graphmailer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('graphmailer.settings');

    $form['tenant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tenant ID'),
      '#default_value' => $config->get('tenant_id') ?? '',
      '#required' => TRUE,
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id') ?? '',
      '#required' => TRUE,
    ];

    // Password field for client secret: leave empty to keep existing.
    $form['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('Leave empty to keep the existing secret.'),
    ];

    $form['from_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Default "From" address'),
      '#default_value' => $config->get('from_address') ?? '',
      '#required' => TRUE,
      '#description' => $this->t('The mailbox UPN or alias to use as sender.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('graphmailer.settings');

    $config->set('tenant_id', $form_state->getValue('tenant_id'));
    $config->set('client_id', $form_state->getValue('client_id'));

    // Only update client_secret if the field is not empty.
    $secret = $form_state->getValue('client_secret');
    if (!empty($secret)) {
      $config->set('client_secret', $secret);
    }

    $config->set('from_address', $form_state->getValue('from_address'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
