<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketServer implements MessageComponentInterface {
    protected $clients = [];

    public function onOpen(ConnectionInterface $conn) {
        echo "A client connected\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $decodedMessage = json_decode($msg, true);

        if (isset($decodedMessage['type'])) {
            // Register client ID
            if ($decodedMessage['type'] === 'clientId') {
                $clientId = $decodedMessage['clientId'];
                $this->clients[$clientId] = $from;

                echo "Client registered: $clientId\n";

                // Acknowledge client ID registration
                $from->send(json_encode([
                    'type' => 'clientIdAck',
                    'message' => 'Client ID received and registered successfully'
                ]));
            }

            // Handle private messages
            if ($decodedMessage['type'] === 'private') {
                $fromId = $decodedMessage['from'];
                $toId = $decodedMessage['to'];
                $message = $decodedMessage['message'];

                if (isset($this->clients[$toId])) {
                    $this->clients[$toId]->send(json_encode([
                        'type' => 'private',
                        'from' => $fromId,
                        'message' => $message
                    ]));

                    echo "Private message sent from $fromId to $toId: $message\n";
                } else {
                    // Recipient not found
                    $from->send(json_encode([
                        'type' => 'error',
                        'message' => "Recipient $toId not found!"
                    ]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        echo "A client disconnected\n";

        foreach ($this->clients as $clientId => $clientConn) {
            if ($clientConn === $conn) {
                unset($this->clients[$clientId]);
                break;
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start the WebSocket server
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new WebSocketServer()
        )
    ),
    7799
);

echo "WebSocket server is running on ws://localhost:7799\n";
$server->run();
