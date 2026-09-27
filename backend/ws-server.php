<?php
/**
 * FreshTrack live-updates server.
 *
 * Replaces the old client-side polling: connected browsers (admin
 * reports, staff dashboard, customer dashboard) get pushed fresh order
 * data over a WebSocket instead of re-fetching a PHP endpoint every few
 * seconds.
 *
 * Run it (from the backend/ folder):
 *   composer install        # one-time, pulls in Ratchet
 *   php ws-server.php
 *
 * Configure host/port/interval in config/websocket.php (or via the
 * WS_HOST / WS_PORT / WS_BROADCAST_INTERVAL / WS_PUBLIC_URL env vars —
 * see that file for deployment notes).
 */

require __DIR__ . '/vendor/autoload.php';
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

/**
 * Tracks connected clients and pushes each of them a role-appropriate
 * slice of order data whenever tick() runs.
 */
class OrderUpdatesServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface> */
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

        // Stash the client's requested view on the connection itself so
        // tick() knows what to send it later without re-parsing anything.
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

        // Send a snapshot immediately so the page has live data right
        // away instead of waiting for the next tick.
        $this->sendTo($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Clients are read-only listeners; nothing to do with inbound messages.
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

    /** Called on a timer; pushes fresh data to every connected client. */
    public function tick(): void
    {
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
            $conn->send(json_encode($this->buildPayload($conn->role, $conn->statusFilter, $conn->customerId)));
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
                   s.name AS service_name, u.name AS customer_name
            FROM orders o
            JOIN services s ON s.id = o.service_id
            JOIN users u ON u.id = o.customer_id';
        $conditions = [];
        $params = [];

        if ($role === 'staff') {
            // Staff dashboard doubles as an order history log: show every
            // status unless a specific filter was requested.
            if ($statusFilter && $statusFilter !== 'All' && in_array($statusFilter, $allStatuses, true)) {
                $conditions[] = 'o.status = ?';
                $params[] = $statusFilter;
            }
        } elseif ($role === 'customer' && $customerId) {
            // Customers only ever see their own in-progress orders.
            $conditions[] = 'o.customer_id = ?';
            $params[] = $customerId;
            $conditions[] = 'o.status NOT IN ("Completed", "Cancelled")';
        } else {
            // Admin (and any unrecognized role) only needs statusCounts.
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

    protected function log(string $message, bool $isError = false): void
    {
        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $isError ? '⚠ ' : '', $message);
        echo $line . PHP_EOL;
    }
}

$app = new OrderUpdatesServer($pdo);

$loop   = Loop::get();
$socket = new SocketServer(WS_HOST . ':' . WS_PORT, [], $loop);
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
