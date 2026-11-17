const { db, DB_PATH } = require("./db");
const seedData = require("./seed-data");

function clear() {
  const tables = [
    "audit_logs", "commissions", "commission_rules", "stock_moves", "products",
    "checkins", "class_bookings", "class_sessions", "classes", "teachers",
    "payments", "invoices", "subscriptions", "members", "plans", "expenses",
    "expense_categories", "role_permissions", "users", "permissions", "roles",
    "units", "settings",
  ];
  db.exec("PRAGMA foreign_keys = OFF;");
  for (const t of tables) {
    db.prepare(`DELETE FROM ${t}`).run();
  }
  try {
    db.prepare("DELETE FROM sqlite_sequence").run();
  } catch {
    // ok
  }
  db.exec("PRAGMA foreign_keys = ON;");
}

clear();
seedData(db, { quiet: false, dbPath: DB_PATH });
