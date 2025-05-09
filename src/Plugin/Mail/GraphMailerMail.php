<?php

namespace Drupal\graphmailer\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\graphmailer\GraphMailer;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[\Drupal\Core\Mail\Attribute\Mail(
  id: 'graphmailer_mail',
  label: new TranslatableMarkup('Graph Mailer'),
  description: new TranslatableMarkup('Sends email using Microsoft Graph API.')
)]
class GraphMailerMail implements MailInterface, ContainerFactoryPluginInterface {

  protected GraphMailer $graphMailer;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    GraphMailer $graphMailer
  ) {
    $this->graphMailer = $graphMailer;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('graphmailer.mailer')
    );
  }

  public function format(array $message): array {
    // Laat formattering aan GraphMailer of Webform over
    return $message;
  }

  public function mail(array $message): bool {
    $params = $message['params'] ?? [];
    $to = (array) ($message['to'] ?? []);
    $cc = (array) ($message['cc'] ?? []);
    $bcc = (array) ($message['bcc'] ?? []);
    $subject = $message['subject'] ?? '';
    $body = implode("\n", (array) ($message['body'] ?? []));
    $headers = $message['headers'] ?? [];

    // 'From' gegevens bepalen
    $fromEmail = $params['from_mail'] ?? $headers['From'] ?? '';
    $fromName  = $params['from_name'] ?? '';

    // Combineer From-naam en -adres als nodig
    $fromRaw = ($fromName && $fromEmail)
      ? "{$fromName} <{$fromEmail}>"
      : $fromEmail;

    try {
      $this->graphMailer->sendMail(
        $to,
        $cc,
        $bcc,
        $subject,
        $body,
        [],        // attachments (leeg)
        $fromRaw   // optioneel afzenderadres met of zonder naam
      );
      return true;
    }
    catch (\Exception $e) {
      // Log eventuele fouten
      \Drupal::logger('graphmailer')->error('Mail sending failed: @message', ['@message' => $e->getMessage()]);
      return false;
    }
  }

}
