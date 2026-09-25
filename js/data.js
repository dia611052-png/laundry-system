/* ============================================================
   FreshTrack — mock data layer.
   Everything here stands in for the PHP/MySQL backend described
   in the proposal. It persists to localStorage only so the demo
   behaves consistently between pages in this frontend-only build.
   ============================================================ */

const DB = (() => {
  const KEYS = { users: "ft_users", services: "ft_services", orders: "ft_orders" };

  const STATUS_FLOW = ["Pending", "Received", "Washing", "Drying", "Ready for Pickup", "Completed"];

  function seed() {
    if (!localStorage.getItem(KEYS.services)) {
      const services = [
        { id: "svc-wash-fold", name: "Wash & Fold", price: 65, unit: "per kg", eta: "Same day (6 hrs)",
          description: "Everyday laundry, washed, dried, and folded. Good for weekly loads." },
        { id: "svc-wash-iron", name: "Wash & Iron", price: 90, unit: "per kg", eta: "24 hrs",
          description: "Washed, dried, and pressed. For office wear and uniforms." },
        { id: "svc-dry-clean", name: "Dry Cleaning", price: 180, unit: "per piece", eta: "48 hrs",
          description: "Delicate fabrics, coats, and formal wear cleaned without water." },
        { id: "svc-comforter", name: "Comforter / Beddings", price: 250, unit: "per item", eta: "48 hrs",
          description: "Blankets, comforters, and large beddings. Requires bulk drying." },
        { id: "svc-express", name: "Express Wash", price: 120, unit: "per kg", eta: "3 hrs",
          description: "Rush service for same-day pickup needs." },
      ];
      localStorage.setItem(KEYS.services, JSON.stringify(services));
    }

    if (!localStorage.getItem(KEYS.users)) {
      const users = [
        { id: "u-admin", name: "System Administrator", email: "admin@freshtrack.test", password: "admin123", role: "admin" },
        { id: "u-staff1", name: "Marisol Reyes", email: "staff@freshtrack.test", password: "staff123", role: "staff" },
        { id: "u-cust1", name: "Juan Dela Cruz", email: "customer@freshtrack.test", password: "customer123", role: "customer" },
      ];
      localStorage.setItem(KEYS.users, JSON.stringify(users));
    }

    if (!localStorage.getItem(KEYS.orders)) {
      const now = Date.now();
      const day = 86400000;
      const orders = [
        { id: "FT-10021", customerId: "u-cust1", serviceId: "svc-wash-fold", qty: 4, notes: "Fabric softener please.",
          status: "Ready for Pickup", createdAt: now - 2 * day,
          history: [
            { status: "Pending", at: now - 2 * day },
            { status: "Received", at: now - 2 * day + 3600000 },
            { status: "Washing", at: now - 1.7 * day },
            { status: "Drying", at: now - 1.3 * day },
            { status: "Ready for Pickup", at: now - 0.5 * day },
          ] },
        { id: "FT-10022", customerId: "u-cust1", serviceId: "svc-dry-clean", qty: 2, notes: "One blazer, one coat.",
          status: "Washing", createdAt: now - 0.8 * day,
          history: [
            { status: "Pending", at: now - 0.8 * day },
            { status: "Received", at: now - 0.6 * day },
            { status: "Washing", at: now - 0.2 * day },
          ] },
        { id: "FT-10018", customerId: "u-cust1", serviceId: "svc-wash-iron", qty: 6, notes: "",
          status: "Completed", createdAt: now - 6 * day,
          history: [
            { status: "Pending", at: now - 6 * day },
            { status: "Received", at: now - 5.8 * day },
            { status: "Washing", at: now - 5.5 * day },
            { status: "Drying", at: now - 5.2 * day },
            { status: "Ready for Pickup", at: now - 4.8 * day },
            { status: "Completed", at: now - 4 * day },
          ] },
      ];
      localStorage.setItem(KEYS.orders, JSON.stringify(orders));
    }
  }

  const read = (key) => JSON.parse(localStorage.getItem(key) || "[]");
  const write = (key, val) => localStorage.setItem(key, JSON.stringify(val));

  return {
    STATUS_FLOW,
    seed,
    getUsers: () => read(KEYS.users),
    saveUsers: (u) => write(KEYS.users, u),
    getServices: () => read(KEYS.services),
    getOrders: () => read(KEYS.orders),
    saveOrders: (o) => write(KEYS.orders, o),

    findUserByEmail(email) {
      return this.getUsers().find(u => u.email.toLowerCase() === String(email).toLowerCase());
    },
    findService(id) {
      return this.getServices().find(s => s.id === id);
    },
    nextOrderId() {
      const orders = this.getOrders();
      const max = orders.reduce((m, o) => {
        const n = parseInt(o.id.replace("FT-", ""), 10);
        return isNaN(n) ? m : Math.max(m, n);
      }, 10020);
      return "FT-" + (max + 1);
    },
    addOrder(order) {
      const orders = this.getOrders();
      orders.unshift(order);
      this.saveOrders(orders);
    },
    updateOrderStatus(orderId, status) {
      const orders = this.getOrders();
      const o = orders.find(x => x.id === orderId);
      if (!o) return;
      o.status = status;
      o.history.push({ status, at: Date.now() });
      this.saveOrders(orders);
    },
  };
})();

DB.seed();
