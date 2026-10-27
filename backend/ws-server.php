<?php
/**
 * FreshTrack live-updates server.
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/env.php';
load_env(__DIR__ . '/.env');
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/websocket.php';
require __DIR__ . '/includes/constants.php';

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

class OrderUpdatesServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->clients = new \SplObjectStorage();
        $this->pdo = $pdo;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $query = [];
        parse_str($conn->httpRequest->getUri()->getQuery(), $query);

        $conn->role         = in_array($query['role'] ?? '', ['admin', 'staff', 'customer'], true)
            ? $query['role']
            : 'guest';
        $conn->statusFilter = $query['status'] ?? null;
        $conn->customerId   = isset($query['customer_id']) ? (int) $query['customer_id'] : null;

        $this->clients->attach($conn);

        $label = $conn->role;
        if ($conn->role === 'customer' && $conn->customerId) {
            $label .= " (customer #{$conn->customerId})";
        } elseif ($conn->role === 'staff' && $conn->statusFilter) {
            $label .= " (filter: {$conn->statusFilter})";
        }

        $this->log("Client #{$conn->resourceId} connected — {$label}. " . count($this->clients) . ' total.');

        $this->sendTo($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Clients are read-only listeners
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $this->log("Client #{$conn->resourceId} disconnected. " . count($this->clients) . ' remaining.');
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->log("Error on client #{$conn->resourceId}: {$e->getMessage()}", true);
        $conn->close();
    }

    public function tick(): void
    {
        // 1. KEEP ALIVE: Ping the database every tick to prevent Layerbase from timing it out
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->log("Database ping failed. Reconnecting...", true);
            $this->reconnect();
        }

        if (count($this->clients) === 0) {
            return;
        }
        foreach ($this->clients as $client) {
            $this->sendTo($client);
        }
    }

    protected function sendTo(ConnectionInterface $conn): void
    {
        try {
            // 2. If a query fails during sending, reconnect and try one more time
            try {
                $payload = $this->buildPayload($conn->role, $conn->statusFilter, $conn->customerId);
            } catch (PDOException $e) {
                $this->log("Query failed: " . $e->getMessage() . ". Reconnecting and retrying...", true);
                $this->reconnect();
                $payload = $this->buildPayload($conn->role, $conn->statusFilter, $conn->customerId);
            }
            
            $conn->send(json_encode($payload));
        } catch (\Throwable $e) {
            $this->log("Failed to send update to client #{$conn->resourceId}: {$e->getMessage()}", true);
        }
    }

    protected function buildPayload(string $role, ?string $statusFilter, ?int $customerId): array
    {
        $allStatuses = array_merge(STATUS_FLOW, ['Cancelled']);

        $statusCounts = [];
        foreach ($allStatuses as $status) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
            $stmt->execute([$status]);
            $statusCounts[$status] = (int) $stmt->fetchColumn();
        }

        $sql = '
            SELECT o.id, o.tracking_code, o.status, o.qty, o.created_at,
                   o.predicted_remaining_hours, o.predicted_progress_percent,
                   o.predicted_finish_at, o.predicted_message, o.predicted_at,
                   s.name AS service_name, u.name AS customer_name
            FROM orders o
            JOIN services s ON s.id = o.service_id
            JOIN users u ON u.id = o.customer_id';
        $conditions = [];
        $params = [];

        if ($role === 'staff') {
            if ($statusFilter && $statusFilter !== 'All' && in_array($statusFilter, $allStatuses, true)) {
                $conditions[] = 'o.status = ?';
                $params[] = $statusFilter;
            }
        } elseif ($role === 'customer' && $customerId) {
            $conditions[] = 'o.customer_id = ?';
            $params[] = $customerId;
            $conditions[] = 'o.status NOT IN ("Completed", "Cancelled")';
        } else {
            $conditions[] = '1 = 0';
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY o.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'type'         => 'orders_update',
            'timestamp'    => time(),
            'statusCounts' => $statusCounts,
            'activeOrders' => $stmt->fetchAll(),
        ];
    }

    /** Re-establishes the dead database connection */
    protected function reconnect(): void
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]
            );
            $this->log("Database reconnected successfully.");
        } catch (PDOException $e) {
            $this->log("Database reconnection failed: " . $e->getMessage(), true);
        }
    }

    protected function log(string $message, bool $isError = false): void
    {
        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $isError ? '⚠ ' : '', $message);
        echo $line . PHP_EOL;
    }
}

$app = new OrderUpdatesServer($pdo);

$loop   = Loop::get();
$port = getenv('PORT') ?: WS_PORT;
$socket = new SocketServer(WS_HOST . ':' . $port, [], $loop);
$server = new IoServer(new HttpServer(new WsServer($app)), $socket, $loop);

$loop->addPeriodicTimer(WS_BROADCAST_INTERVAL, fn () => $app->tick());

echo str_repeat('=', 56) . PHP_EOL;
echo ' FreshTrack — live updates WebSocket server' . PHP_EOL;
echo str_repeat('=', 56) . PHP_EOL;
echo ' Listening on : ws://' . WS_HOST . ':' . WS_PORT . PHP_EOL;
echo ' Public URL   : ' . WS_PUBLIC_URL . PHP_EOL;
echo ' Broadcast    : every ' . WS_BROADCAST_INTERVAL . 's' . PHP_EOL;
echo ' Started at   : ' . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 56) . PHP_EOL;
echo ' Waiting for connections... (Ctrl+C to stop)' . PHP_EOL . PHP_EOL;

$server->run();