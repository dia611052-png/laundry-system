/* ============================================================
   FreshTrack — live updates via WebSocket (ws-server.php / Ratchet).
   Ported from the old app.js. Only the three dashboards connect —
   marketing pages, login, and the booking form have nothing to update.
   ============================================================ */

/**
 * Opens a socket to the WS server and calls `onUpdate(data)` with the
 * parsed `{ type: 'orders_update', statusCounts, activeOrders }`
 * payload every time the server pushes one. Reconnects with backoff.
 */
async function connectLiveUpdates(role, extraParams, onUpdate) {
  let wsUrl;
  try {
    const cfg = await api.get('/config.php');
    wsUrl = cfg.wsUrl;
  } catch (err) {
    console.warn('Live updates unavailable — could not fetch WS config.', err);
    return;
  }
  if (!wsUrl) return;

  const params = new URLSearchParams({ role, ...extraParams });
  const fullUrl = wsUrl + '?' + params.toString();

  let socket = null;
  let reconnectDelay = 1000; // starts at 1s, backs off up to 15s while disconnected
  let reconnectTimer = null;
  let closedByPage = false;

  function connect() {
    socket = new WebSocket(fullUrl);

    socket.addEventListener('open', () => {
      console.log(`Live updates connected (${role})`);
      reconnectDelay = 1000; // reset backoff after a successful connection
    });

    socket.addEventListener('message', (event) => {
      let data;
      try {
        data = JSON.parse(event.data);
      } catch (err) {
        console.error('Live update: could not parse message', err);
        return;
      }
      if (data && data.type === 'orders_update') onUpdate(data);
    });

    socket.addEventListener('close', () => {
      if (closedByPage) return;
      console.warn(`Live updates disconnected — retrying in ${reconnectDelay / 1000}s`);
      reconnectTimer = setTimeout(connect, reconnectDelay);
      reconnectDelay = Math.min(reconnectDelay * 2, 15000);
    });

    socket.addEventListener('error', () => socket.close());
  }

  connect();

  window.addEventListener('beforeunload', () => {
    closedByPage = true;
    if (reconnectTimer) clearTimeout(reconnectTimer);
    if (socket) socket.close();
  });
}
