const fs = require("fs");
const path = require("path");
const { DatabaseSync } = require("node:sqlite");

const ROOT = path.join(__dirname, "..");
const ON_VERCEL = Boolean(process.env.VERCEL);
const DATA_DIR = ON_VERCEL ? "/tmp" : path.join(ROOT, "data");
const DB_PATH = path.join(DATA_DIR, "vertice.db");
const SCHEMA_PATH = path.join(ROOT, "database", "schema.sql");

if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

const db = new DatabaseSync(DB_PATH);
db.exec("PRAGMA foreign_keys = ON;");
db.exec(fs.readFileSync(SCHEMA_PATH, "utf8"));

function moneyBr(cents) {
  return (Number(cents) / 100).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

function audit(userId, action, entity = null, entityId = null, meta = null) {
  db.prepare(
    `INSERT INTO audit_logs (user_id, action, entity, entity_id, meta)
     VALUES (?, ?, ?, ?, ?)`
  ).run(
    userId || null,
    action,
    entity,
    entityId,
    meta ? JSON.stringify(meta) : null
  );
}

function ensureSeeded() {
  const row = db.prepare("SELECT COUNT(*) AS c FROM users").get();
  if (Number(row?.c || 0) > 0) return false;
  // Seed mínimo embutido (evita falha na Vercel sem npm run seed).
  require("./seed-data")(db);
  return true;
}

ensureSeeded();

module.exports = { db, DB_PATH, ROOT, ON_VERCEL, moneyBr, audit, ensureSeeded };
