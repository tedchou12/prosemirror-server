<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class prosemirror_server implements MessageComponentInterface {
  protected $clients;

  public function __construct() {
    $this->clients = new \SplObjectStorage;
  }

  public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {

  }

  public function onOpen(ConnectionInterface $conn) {
    $query = $conn->httpRequest->getUri()->getQuery();
    parse_str($query, $data);

    if ($data['doc_id']) {
      $doc_id = $data['doc_id'];
      $path   = 'data/' . $doc_id . '.txt';
      if (file_exists($path)) {
        // $data = file_get_contents($path);
        $doc = sprintf('data/%d.txt', $doc_id);
        $step = sprintf('data/%d.history.txt', $doc_id);
        $out = sprintf('data/%d.out.txt', $doc_id);
        exec(sprintf('./prosemirror_server-macos doc=%s step=%s out=%s', $doc, $step, $out));

        if (file_exists($out)) {
          $data = file_get_contents($out);
          $response = json_decode($data, true);
        }
      } else {
        $data = array('doc_json' => array('type'    => 'doc',
                                          'attrs'   => array('layout' => NULL,
                                                             'padding' => NULL,
                                                             'width' => NULL),
                                          'content' => array(
                                                          array('type' => 'paragraph',
                                                                'attrs' => array('align' => NULL,
                                                                                 'color' => NULL,
                                                                                 'id' => NULL,
                                                                                'indent' => NULL,
                                                                                'lineSpacing' => NULL,
                                                                                'paddingBottom' => NULL,
                                                                                'paddingTop' => NULL,
                                                                                'objectId' => NULL),
                                                                'content' => array(
                                                                                  array('type' => 'text',
                                                                                        'text' => ' '),
                                                                                  ),
                                                             ),
                                                           ),
                                          ),
                      'users' => 1,
                      'version' => 0);
        $response = $data;
      }

      $response = array('type' => 'init',
                        'data' => $response);
      $conn->send(json_encode($response));
    }

    $this->clients->attach($conn);
  }

  public function onMessage(ConnectionInterface $conn, $s_data) {
    $query = $conn->httpRequest->getUri()->getQuery();
    parse_str($query, $sq_data);

    if ($sq_data['doc_id']) {
      $path = 'data/' . $sq_data['doc_id'] . '.history.txt';
    }

    $data = json_decode($s_data, true);

    $version = 0;
    $versions = array();
    $lines = file($path);
    foreach ($lines as $line) {
      if ($line) {
        $line_json = json_decode($line, true);
        $versions[] = $line_json['version'];
      }
    }

    $data['version'] = max($versions) + 1;

    $fp = fopen($path, 'a');
    fwrite($fp, json_encode($data));
    fwrite($fp, "\n");
    fclose($fp);

    foreach ($this->clients as $client) {
      if ($client) {
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $tq_data);

        if ($tq_data['doc_id'] == $sq_data['doc_id'] && $client != $conn) {
          $data['clientIDs'] = array($data['clientID']);
          $response = array('type' => 'step',
                            'data' => $data);
          $client->send(json_encode($response));
        }
      }
    }
  }

  public function onClose(ConnectionInterface $conn) {
    $query = $conn->httpRequest->getUri()->getQuery();
    parse_str($query, $data);
    // $session_id = base64_decode($data['session_id']);
    // $user_id = $data['user_id'];
    // $account = $GLOBALS['sessions']->chat_auth($session_id);
    // $channel_id = $data['channel_id'];
    // if ($channel_id) {
    //   $GLOBALS['members']->update_read($channel_id);
    // }
    // if ($account == $user_id) {
    //   $conn->close();
    // }

    $this->clients->detach($conn);
  }

  public function onError(ConnectionInterface $conn, \Exception $e) {
    echo "An error has occurred: {$e->getMessage()}\n";

    $conn->close();
  }
}
