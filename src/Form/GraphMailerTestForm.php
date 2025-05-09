<?php

// modules/custom/graphmailer/src/Form/GraphMailerTestForm.php

namespace Drupal\graphmailer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\graphmailer\GraphMailer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to test sending mail via Microsoft Graph API.
 */
class GraphMailerTestForm extends FormBase {

  /**
   * The GraphMailer service.
   *
   * @var \Drupal\graphmailer\GraphMailer
   */
  protected GraphMailer $graphMailer;

  /**
   * Constructs a GraphMailerTestForm.
   *
   * @param \Drupal\graphmailer\GraphMailer $graph_mailer
   *   The GraphMailer service.
   */
  public function __construct(GraphMailer $graph_mailer) {
    $this->graphMailer = $graph_mailer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('graphmailer.mailer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'graphmailer_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Haal fallback-from uit config.
    $default_from = $this->configFactory()
      ->get('graphmailer.settings')
      ->get('from_address') ?: '';

    $form['from'] = [
      '#type'          => 'email',
      '#title'         => $this->t('Afzender e-mail'),
      '#default_value' => $default_from,
      '#required'      => TRUE,
      '#description'   => $this->t('Kies hier het e-mailadres dat als afzender moet worden gebruikt.'),
    ];

    $form['to'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Ontvangers (komma-gescheiden)'),
      '#required'    => TRUE,
      '#description' => $this->t('Eén of meer e-mailadressen, gescheiden door komma’s.'),
    ];

    $form['cc'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('CC (komma-gescheiden)'),
      '#description' => $this->t('Optioneel: voer CC-adressen in, gescheiden door komma’s.'),
    ];

    $form['bcc'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('BCC (komma-gescheiden)'),
      '#description' => $this->t('Optioneel: voer BCC-adressen in, gescheiden door komma’s.'),
    ];

    $form['subject'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Onderwerp'),
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type'     => 'textarea',
      '#title'    => $this->t('Bericht'),
      '#required' => TRUE,
    ];

    $form['attachments'] = [
      '#type'             => 'managed_file',
      '#title'            => $this->t('Bijlagen'),
      '#multiple'         => TRUE,
      '#upload_location'  => 'public://graphmailer/',
      '#upload_validators'=> [
        'file_validate_extensions' => ['pdf doc docx png jpg jpeg'],
        'file_validate_size'       => [3 * 1024 * 1024],
      ],
      '#description'      => $this->t('Maximaal 3 MB per bestand.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Verstuur testmail'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fromArr = [$form_state->getValue('from')];
    $toArr   = array_filter(array_map('trim', explode(',', $form_state->getValue('to'))));
    $ccArr   = array_filter(array_map('trim', explode(',', $form_state->getValue('cc'))));
    $bccArr  = array_filter(array_map('trim', explode(',', $form_state->getValue('bcc'))));

    // Verwerk bijlagen.
    $attachments = [];
    foreach ($form_state->getValue('attachments') as $fid) {
      if ($file = File::load($fid)) {
        $data = file_get_contents($file->getFileUri());
        $attachments[] = [
          'name'        => $file->getFilename(),
          'contentType' => $file->getMimeType(),
          'content'     => base64_encode($data),
        ];
        $file->setPermanent();
        $file->save();
      }
    }

    try {
      // Verstuur met dynamische afzender.
      $this->graphMailer->sendMail(
        $toArr,
        $ccArr,
        $bccArr,
        $form_state->getValue('subject'),
        $form_state->getValue('body'),
        $attachments,
        $form_state->getValue('from')
      );
      \Drupal::messenger()->addStatus($this->t('Testmail succesvol verzonden.'));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Kon testmail niet versturen: @msg', [
        '@msg' => $e->getMessage(),
      ]));
    }
  }

}
