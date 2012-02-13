<?php

class SwiftPostmarkTransport implements Swift_Transport {

  protected $apiKey;
  protected $connection;

  protected $_eventDispatcher;

  protected function __construct(Swift_Events_EventDispatcher $eventDispatcher) {
    $this->_eventDispatcher = $eventDispatcher;
  }

  public function setApiKey($key) {
    $this->apiKey = $key;
  }

  /**
   * Test if this Transport mechanism has started.
   *
   * @return boolean
   */
  public function isStarted() {
    return (isset($this->apiKey) && isset($this->connection));
  }

  /**
   * Start this Transport mechanism.
   */
  public function start() {
    if (!isset($this->apiKey)) throw new CException('Postmark Api Key need to be set');
    if ($this->connection == null) {
      $this->connection = curl_init("https://api.postmarkapp.com/email");
      $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Postmark-Server-Token: ' . $this->apiKey
      );
      curl_setopt_array($this->connection, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => dirname(__FILE__) . '/cacert.pem',
      ));
    }
  }

  /**
   * Stop this Transport mechanism.
   */
  public function stop() {
    if ($this->connection != null) {
      curl_close($this->connection);
      unset($this->connection);
    }
  }

  /**
   * Send the given Message.
   *
   * Recipient/sender data will be retreived from the Message API.
   * The return value is the number of recipients who were accepted for delivery.
   *
   * @param Swift_Mime_Message $message
   * @param string[]           &$failedRecipients to collect failures by-reference
   *
   * @return string|bool
   */
  public function send(Swift_Mime_Message $message, &$failedRecipients = null) {
    if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
      $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
      if ($evt->bubbleCancelled()) {
        return false;
      }
    }
    $headers = array();
    foreach ($message->getHeaders() as $header) {
      $headers[] = $header->toString();
    }
    $data = array(
      "From" => implode(' ', array_keys($message->getFrom())),
      "To" => implode(' ', array_keys($message->getTo())),
      "Subject" => $message->getSubject(),
      "Headers" => $headers,
    );
    if (count($message->getBcc()) > 0) $data['Bcc'] = implode(' ', array_keys($message->getBcc()));
    if (count($message->getCc()) > 0) $data['Cc'] = implode(' ', array_keys($message->getBcc()));
    if ($message->getContentType() == "text/html") {
      $data['HtmlBody'] = $message->getBody();
      $bodyType = 'html';
    } else {
      $data["TextBody"] = $message->getBody();
      $bodyType = 'text';
    }
    foreach ($message->getChildren() as $part) {
      if ($part->getContentType() == "text/html" && $bodyType == "text") {
        $data['HtmlBody'] = $part->getBody();
      } else if ($part->getContentType() == "text/plain" && $bodyType == "html") {
        $data["TextBody"] = $part->getBody();
      } else {
        if ($part instanceof Swift_Mime_Attachment) {
          $data['Attachments'][] = array(
            'Name'=>$part->getFilename(),
            'Content'=>base64_encode($part->getBody()),
            'ContentType'=>$part->getContentType(),
          );
        } else {
          $data['Attachments'][] = array(
            'Name'=>$part->getId(),
            'Content'=>$part->getBody(),
            'ContentType'=>$part->getContentType(),
          );
        }
      }
    }
    Yii::trace(json_encode($data));
    curl_setopt($this->connection, CURLOPT_POSTFIELDS, json_encode($data));
    $return = curl_exec($this->connection);
    $curlError = curl_error($this->connection);
    $httpCode = curl_getinfo($this->connection, CURLINFO_HTTP_CODE);
    if ($httpCode < 200 || $httpCode >= 300) {
      Yii::log($httpCode . $return, CLogger::LEVEL_ERROR, 'mail.SwiftPostmark');
      Yii::log($curlError, CLogger::LEVEL_ERROR, 'mail.SwiftPostmark');
      if ($httpCode == 422) {
        $return = json_decode($return);
        throw new Exception($return->Message, $return->ErrorCode);
      } else {
        throw new Exception("Error while mailing. Postmark returned HTTP code {$httpCode} with message \"{$return}\"", $httpCode);
      }
    } else {
      /*if ($evt) {
        if ($sent == count($message->getFrom()) + count($message->getCc()) + count($message->getBcc())) {
          $evt->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
        } elseif ($sent > 0) {
          $evt->setResult(Swift_Events_SendEvent::RESULT_TENTATIVE);
        } else {
          $evt->setResult(Swift_Events_SendEvent::RESULT_FAILED);
        }
        $evt->setFailedRecipients($failedRecipients);
        $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
      }*/
      Yii::trace($return, 'mail.SwiftPostmark');
      $result = json_decode($return, true);
      return $result['MessageID'];
    }
  }

  /**
   * Register a plugin in the Transport.
   *
   * @param Swift_Events_EventListener $plugin
   */
  public function registerPlugin(Swift_Events_EventListener $plugin) {
    $this->_eventDispatcher->bindEventListener($plugin);
  }

  public static function newInstance()
  {
    return new self(new Swift_Events_SimpleEventDispatcher());
  }
}