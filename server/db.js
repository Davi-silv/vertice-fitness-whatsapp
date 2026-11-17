const fs = require("fs");
const path = require("path");
const Database = require("better-sqlite3");

const ROOT = path.join(__dirname, "..");
const DATA_DIR = path.join(ROOT, "data");
const DB_PATH = path.join(DATA_DIR, "vertice.db");
const SCHEMA_PATH = path.join(ROOT, "database", "schema.sql");

if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

const db = new Database(DB_PATH);
db.pragma("journal_mode = WAL");
db.pragma("foreign_keys = ON");

const schema = fs.readFileSync(SCHEMA_PATH, "utf8");
db.exec(schema);

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

module.exports = { db, DB_PATH, ROOT, moneyBr, audit };
