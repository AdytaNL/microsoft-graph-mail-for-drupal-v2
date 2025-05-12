<?php

namespace Drupal\graphmailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for sending email through Microsoft Graph API.
 */
class GraphMailer {

  /**
   * The HTTP client to communicate with Graph API.
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The config factory.
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Logger channel.
   * @var \Psr\Log\LoggerInterface
   */
  protected \Psr\Log\LoggerInterface $logger;

  /**
   * Constructs the GraphMailer service.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->httpClient    = $http_client;
    $this->configFactory = $config_factory;
    $this->logger        = $logger_factory->get('graphmailer');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * Retrieves module settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getSettings(): \Drupal\Core\Config\ImmutableConfig {
    return $this->configFactory->get('graphmailer.settings');
  }

  /**
   * Obtains an access token from Azure AD.
   *
   * @return string
   *   The access token.
   *
   * @throws \Drupal\graphmailer\MailException
   */
  public function getAccessToken(): string {
    $config = $this->getSettings();
    $tenant = $config->get('tenant_id');
    $client = $config->get('client_id');
    $secret = $config->get('client_secret');
    $url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

    try {
      $response = $this->httpClient->request('POST', $url, [
        'form_params' => [
          'client_id'     => $client,
          'client_secret' => $secret,
          'scope'         => 'https://graph.microsoft.com/.default',
          'grant_type'    => 'client_credentials',
        ],
        'timeout' => 5,
      ]);
    }
    catch (ConnectException $e) {
      $this->logger->error('Connection error retrieving access token: @msg', ['@msg' => $e->getMessage()]);
      throw new MailException('Unable to connect to authentication server.');
    }
    catch (RequestException $e) {
      $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
      $this->logger->error('Error retrieving access token (HTTP @code): @msg', ['@code' => $status, '@msg' => $e->getMessage()]);
      throw new MailException("Authentication failed (status {$status}).");
    }

    $data = Json::decode((string) $response->getBody());
    if (empty($data['access_token'])) {
      $this->logger->error('No access_token in response: @body', ['@body' => (string) $response->getBody()]);
      throw new MailException('Invalid authentication response.');
    }

    return $data['access_token'];
  }

  /**
   * Sends an email via Graph API, with optional custom display name.
   *
   * @param string[] $to
   *   Recipient addresses.
   * @param string[] $cc
   *   CC addresses.
   * @param string[] $bcc
   *   BCC addresses.
   * @param string $subject
   *   Subject.
   * @param string $body
   *   Body HTML.
   * @param array $attachments
   *   Attachments array.
   * @param string|null $fromRaw
   *   Optional raw "from" (Name <email> or just email).
   *
   * @throws \Drupal\graphmailer\MailException
   */
  public function sendMail(
    array  $to,
    array  $cc,
    array  $bcc,
    string $subject,
    string $body,
    array  $attachments = [],
    ?string $fromRaw    = NULL
  ): void {
    $config      = $this->getSettings();
    $defaultFrom = $config->get('from_address');
    $raw         = $fromRaw ?: $defaultFrom;
    // Parse explicit name <email>
    $explicitFrom = null;
    if (preg_match('/^(.*?)<([^>]+)>$/', $raw, $m)) {
      $name    = trim($m[1]);
      $address = trim($m[2]);
      $explicitFrom = [
        'emailAddress' => [
          'name'    => $name,
          'address' => $address,
        ],
      ];
      $fromAddress = $address;
    }
    else {
      // Only address
      $fromAddress = trim($raw);	
    }

    // URL-encode for Graph path
    $urlAddress = rawurlencode($fromAddress);
    $url        = "https://graph.microsoft.com/v1.0/users/{$urlAddress}/sendMail";

    // Log the URL
    file_put_contents(
      '/tmp/graphmailer-debug.log',
      date('c') . " - GraphMailer URL: {$url}\n",
      FILE_APPEND
    );

    // Build message payload
    $message = [
      'subject'      => $subject,
      'body'         => ['contentType' => 'HTML', 'content' => $body],
      'toRecipients' => array_map(fn($addr) => ['emailAddress' => ['address' => $addr]], $to),
    ];
    if (!empty($cc)) {
      $message['ccRecipients'] = array_map(fn($addr) => ['emailAddress' => ['address' => $addr]], $cc);
    }
    if (!empty($bcc)) {
      $message['bccRecipients'] = array_map(fn($addr) => ['emailAddress' => ['address' => $addr]], $bcc);
    }
    if ($explicitFrom) {
      $message['from'] = $explicitFrom;
    }
    if (!empty($attachments)) {
      $message['attachments'] = array_map(fn($att) => [
        '@odata.type'  => '#microsoft.graph.fileAttachment',
        'name'         => $att['name'],
        'contentType'  => $att['contentType'],
        'contentBytes' => $att['content'],
      ], $attachments);
    }

    // Send via Graph
    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->getAccessToken(),
          'Content-Type'  => 'application/json',
        ],
        'json'    => ['message' => $message],
        'timeout' => 10,
      ]);
      $statusCode = $response->getStatusCode();
      if ($statusCode !== 202) {
        $this->logger->error('Unexpected status code: @code, body: @body', [
          '@code' => $statusCode,
          '@body' => (string) $response->getBody(),
        ]);
        throw new MailException(sprintf('Email not accepted (status %s).', $statusCode));
      }
    }
    catch (RequestException $e) {
      $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
      $error  = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
      $this->logger->error('Error sending mail (HTTP @code): @body', ['@code' => $status, '@body' => $error]);
      throw new MailException(sprintf('Send failed (status %s).', $status));
    }
  }

}
