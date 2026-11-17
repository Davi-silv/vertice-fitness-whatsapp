const express = require("express");
const { db, moneyBr, audit } = require("../db");
const { requireAuth, requirePerm } = require("../middleware");

const router = express.Router();
router.use(requireAuth);

router.get("/me", (req, res) => {
  res.json({
    ok: true,
    user: {
      id: req.user.id,
      name: req.user.name,
      email: req.user.email,
      role: req.user.role_slug,
      roleName: req.user.role_name,
    },
  });
});

router.get("/dashboard", requirePerm("dashboard.read"), (req, res) => {
  const members = db.prepare("SELECT COUNT(*) AS c FROM members WHERE status = 'active'").get().c;
  const openInvoices = db.prepare("SELECT COUNT(*) AS c FROM invoices WHERE status = 'open'").get().c;
  const overdueInvoices = db
    .prepare(
      `SELECT COUNT(*) AS c FROM invoices
       WHERE status = 'open' AND due_date < date('now')`
    )
    .get().c;
  const revenue = db
    .prepare(
      `SELECT COALESCE(SUM(amount_cents),0) AS s FROM payments
       WHERE paid_at >= date('now','start of month')`
    )
    .get().s;
  const checkinsToday = db
    .prepare("SELECT COUNT(*) AS c FROM checkins WHERE date(checked_in_at) = date('now')")
    .get().c;
  const sessionsToday = db
    .prepare(
      `SELECT COUNT(*) AS c FROM class_sessions
       WHERE date(starts_at) = date('now')`
    )
    .get().c;
  const recentMembers = db
    .prepare(
      `SELECT m.id, m.name, m.status, u.name AS unit_name, m.created_at
       FROM members m LEFT JOIN units u ON u.id = m.unit_id
       ORDER BY m.id DESC LIMIT 8`
    )
    .all();
  const openList = db
    .prepare(
      `SELECT i.id, i.description, i.amount_cents, i.due_date, m.name AS member_name,
              CASE WHEN i.due_date < date('now') THEN 1 ELSE 0 END AS overdue
       FROM invoices i INNER JOIN members m ON m.id = i.member_id
       WHERE i.status = 'open' ORDER BY i.due_date LIMIT 8`
    )
    .all();
  const todaySessions = db
    .prepare(
      `SELECT cs.id, cs.starts_at, c.capacity,
              c.name AS class_name, t.name AS teacher_name,
              (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = cs.id AND b.status = 'booked') AS booked
       FROM class_sessions cs
       INNER JOIN classes c ON c.id = cs.class_id
       LEFT JOIN teachers t ON t.id = c.teacher_id
       WHERE date(cs.starts_at) = date('now')
       ORDER BY cs.starts_at LIMIT 6`
    )
    .all();

  res.json({
    ok: true,
    kpis: {
      members,
      openInvoices,
      overdueInvoices,
      revenueCents: revenue,
      revenue: moneyBr(revenue),
      checkinsToday,
      sessionsToday,
    },
    recentMembers,
    openInvoicesList: openList.map((i) => ({
      ...i,
      overdue: Boolean(i.overdue),
      amount: moneyBr(i.amount_cents),
    })),
    todaySessions,
  });
});

router.get("/members", requirePerm("members.read"), (req, res) => {
  const q = String(req.query.q || "").trim();
  let rows;
  if (q) {
    rows = db
      .prepare(
        `SELECT m.*, u.name AS unit_name FROM members m
         LEFT JOIN units u ON u.id = m.unit_id
         WHERE m.name LIKE ? OR IFNULL(m.email,'') LIKE ? OR IFNULL(m.phone,'') LIKE ?
         ORDER BY m.name`
      )
      .all(`%${q}%`, `%${q}%`, `%${q}%`);
  } else {
    rows = db
      .prepare(
        `SELECT m.*, u.name AS unit_name FROM members m
         LEFT JOIN units u ON u.id = m.unit_id ORDER BY m.name`
      )
      .all();
  }
  const units = db.prepare("SELECT id, name FROM units WHERE active = 1 ORDER BY name").all();
  const plans = db.prepare("SELECT id, name, price_cents FROM plans WHERE active = 1 ORDER BY price_cents").all();
  res.json({ ok: true, members: rows, units, plans });
});

router.post("/members", requirePerm("members.write"), (req, res) => {
  const { name, email, phone, unit_id, status, plan_id, notes } = req.body || {};
  if (!name || !unit_id) {
    return res.status(422).json({ ok: false, error: "Nome e unidade são obrigatórios." });
  }
  const info = db
    .prepare(
      `INSERT INTO members (unit_id, name, email, phone, status, notes)
       VALUES (?, ?, ?, ?, ?, ?)`
    )
    .run(
      Number(unit_id),
      String(name).trim(),
      email ? String(email).trim() : null,
      phone ? String(phone).trim() : null,
      status || "active",
      notes || null
    );
  const memberId = Number(info.lastInsertRowid);
  if (plan_id) {
    const plan = db.prepare("SELECT * FROM plans WHERE id = ?").get(Number(plan_id));
    if (plan) {
      const start = new Date().toISOString().slice(0, 10);
      const endDate = new Date();
      endDate.setDate(endDate.getDate() + (plan.billing_days || 30));
      const end = endDate.toISOString().slice(0, 10);
      const sub = db
        .prepare(
          `INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
           VALUES (?, ?, ?, ?, 'active', ?, ?)`
        )
        .run(memberId, plan.id, Number(unit_id), req.user.id, start, end);
      db.prepare(
        `INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
         VALUES (?, ?, ?, ?, ?, ?, 'open')`
      ).run(
        memberId,
        Number(sub.lastInsertRowid),
        Number(unit_id),
        `Mensalidade ${plan.name}`,
        plan.price_cents,
        end
      );
    }
  }
  audit(req.user.id, "member.create", "members", memberId);
  res.json({ ok: true, id: memberId });
});

router.get("/plans", requirePerm("plans.read"), (req, res) => {
  const plans = db.prepare("SELECT * FROM plans ORDER BY price_cents").all();
  const subs = db
    .prepare(
      `SELECT s.*, m.name AS member_name, p.name AS plan_name
       FROM subscriptions s
       INNER JOIN members m ON m.id = s.member_id
       INNER JOIN plans p ON p.id = s.plan_id
       WHERE s.status = 'active'
       ORDER BY s.id DESC LIMIT 50`
    )
    .all();
  res.json({
    ok: true,
    plans: plans.map((p) => ({ ...p, price: moneyBr(p.price_cents) })),
    subscriptions: subs,
  });
});

router.post("/plans", requirePerm("plans.write"), (req, res) => {
  const name = String(req.body?.name || "").trim();
  const slug = String(req.body?.slug || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "-");
  const price = Math.round(Number(String(req.body?.price || "0").replace(",", ".")) * 100);
  const description = String(req.body?.description || "").trim() || null;
  if (!name || !slug) {
    return res.status(422).json({ ok: false, error: "Nome e slug obrigatórios." });
  }
  const info = db
    .prepare(
      `INSERT INTO plans (name, slug, price_cents, description) VALUES (?, ?, ?, ?)`
    )
    .run(name, slug, price, description);
  audit(req.user.id, "plan.create", "plans", Number(info.lastInsertRowid));
  res.json({ ok: true, id: Number(info.lastInsertRowid) });
});

router.get("/checkin", requirePerm("checkins.write"), (req, res) => {
  const recent = db
    .prepare(
      `SELECT c.*, m.name AS member_name, u.name AS unit_name
       FROM checkins c
       INNER JOIN members m ON m.id = c.member_id
       INNER JOIN units u ON u.id = c.unit_id
       ORDER BY c.id DESC LIMIT 30`
    )
    .all();
  res.json({ ok: true, recent });
});

router.post("/checkin", requirePerm("checkins.write"), (req, res) => {
  const action = String(req.body?.action || "search");
  if (action === "search") {
    const q = String(req.body?.q || "").trim();
    if (!q) return res.status(422).json({ ok: false, error: "Informe a busca." });
    const members = db
      .prepare(
        `SELECT m.*, u.name AS unit_name FROM members m
         LEFT JOIN units u ON u.id = m.unit_id
         WHERE m.name LIKE ? OR IFNULL(m.phone,'') LIKE ? OR IFNULL(m.document,'') LIKE ? OR CAST(m.id AS TEXT) = ?
         ORDER BY m.name LIMIT 20`
      )
      .all(`%${q}%`, `%${q}%`, `%${q}%`, q);
    return res.json({ ok: true, members });
  }
  if (action === "checkin") {
    const memberId = Number(req.body?.member_id || 0);
    const member = db.prepare("SELECT * FROM members WHERE id = ?").get(memberId);
    if (!member) return res.status(404).json({ ok: false, error: "Aluno não encontrado." });
    const info = db
      .prepare(
        `INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, 'manual', ?)`
      )
      .run(member.id, member.unit_id, req.user.id);
    audit(req.user.id, "checkin", "checkins", Number(info.lastInsertRowid));
    return res.json({ ok: true, id: Number(info.lastInsertRowid), member: member.name });
  }
  res.status(400).json({ ok: false, error: "Ação inválida" });
});

router.get("/schedule", requirePerm("schedule.read"), (req, res) => {
  const sessions = db
    .prepare(
      `SELECT cs.*, c.name AS class_name, c.capacity, t.name AS teacher_name,
              (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = cs.id AND b.status = 'booked') AS booked
       FROM class_sessions cs
       INNER JOIN classes c ON c.id = cs.class_id
       LEFT JOIN teachers t ON t.id = c.teacher_id
       ORDER BY cs.starts_at LIMIT 40`
    )
    .all();
  const classes = db
    .prepare(
      `SELECT c.*, t.name AS teacher_name, u.name AS unit_name
       FROM classes c
       LEFT JOIN teachers t ON t.id = c.teacher_id
       LEFT JOIN units u ON u.id = c.unit_id
       WHERE c.active = 1 ORDER BY c.name`
    )
    .all();
  const members = db.prepare("SELECT id, name FROM members WHERE status = 'active' ORDER BY name").all();
  res.json({ ok: true, sessions, classes, members });
});

router.post("/schedule", requirePerm("schedule.write"), (req, res) => {
  const action = String(req.body?.action || "");
  if (action === "book") {
    const sessionId = Number(req.body?.session_id || 0);
    const memberId = Number(req.body?.member_id || 0);
    try {
      db.prepare(
        `INSERT INTO class_bookings (session_id, member_id, status) VALUES (?, ?, 'booked')`
      ).run(sessionId, memberId);
      audit(req.user.id, "booking.create", "class_bookings", sessionId, { memberId });
      return res.json({ ok: true });
    } catch {
      return res.status(409).json({ ok: false, error: "Aluno já reservado nesta aula." });
    }
  }
  if (action === "session") {
    const classId = Number(req.body?.class_id || 0);
    const starts = String(req.body?.starts_at || "").replace("T", " ");
    const cls = db.prepare("SELECT * FROM classes WHERE id = ?").get(classId);
    if (!cls || !starts) return res.status(422).json({ ok: false, error: "Dados inválidos." });
    const startDate = new Date(starts);
    const endDate = new Date(startDate.getTime() + (cls.duration_min || 60) * 60000);
    const toSql = (d) => d.toISOString().replace("T", " ").slice(0, 19);
    const info = db
      .prepare(`INSERT INTO class_sessions (class_id, starts_at, ends_at) VALUES (?, ?, ?)`)
      .run(classId, toSql(startDate), toSql(endDate));
    return res.json({ ok: true, id: Number(info.lastInsertRowid) });
  }
  res.status(400).json({ ok: false, error: "Ação inválida" });
});

router.get("/finance", requirePerm("finance.read"), (req, res) => {
  const open = db
    .prepare(
      `SELECT i.*, m.name AS member_name FROM invoices i
       INNER JOIN members m ON m.id = i.member_id
       WHERE i.status = 'open' ORDER BY i.due_date`
    )
    .all();
  const payments = db
    .prepare(
      `SELECT p.*, i.description, m.name AS member_name FROM payments p
       INNER JOIN invoices i ON i.id = p.invoice_id
       INNER JOIN members m ON m.id = i.member_id
       ORDER BY p.id DESC LIMIT 30`
    )
    .all();
  const expenses = db
    .prepare(
      `SELECT e.*, c.name AS category_name, u.name AS unit_name
       FROM expenses e
       LEFT JOIN expense_categories c ON c.id = e.category_id
       LEFT JOIN units u ON u.id = e.unit_id
       ORDER BY e.expense_date DESC LIMIT 30`
    )
    .all();
  const units = db.prepare("SELECT id, name FROM units WHERE active = 1").all();
  const categories = db.prepare("SELECT id, name FROM expense_categories ORDER BY name").all();
  res.json({
    ok: true,
    open: open.map((i) => ({ ...i, amount: moneyBr(i.amount_cents) })),
    payments: payments.map((p) => ({ ...p, amount: moneyBr(p.amount_cents) })),
    expenses: expenses.map((e) => ({ ...e, amount: moneyBr(e.amount_cents) })),
    units,
    categories,
  });
});

router.post("/finance", requirePerm("finance.write"), (req, res) => {
  const action = String(req.body?.action || "");
  if (action === "pay") {
    const invoiceId = Number(req.body?.invoice_id || 0);
    const method = ["pix", "card", "cash"].includes(req.body?.method) ? req.body.method : "pix";
    const inv = db.prepare("SELECT * FROM invoices WHERE id = ?").get(invoiceId);
    if (!inv || inv.status !== "open") {
      return res.status(404).json({ ok: false, error: "Fatura não encontrada." });
    }
    db.prepare(
      `INSERT INTO payments (invoice_id, amount_cents, method, paid_at, notes, created_by)
       VALUES (?, ?, ?, datetime('now'), ?, ?)`
    ).run(inv.id, inv.amount_cents, method, req.body?.notes || null, req.user.id);
    db.prepare("UPDATE invoices SET status = 'paid' WHERE id = ?").run(inv.id);
    audit(req.user.id, "invoice.pay", "invoices", inv.id, { method });
    return res.json({ ok: true });
  }
  if (action === "expense") {
    const unitId = Number(req.body?.unit_id || 0);
    const description = String(req.body?.description || "").trim();
    const amount = Math.round(Number(String(req.body?.amount || "0").replace(",", ".")) * 100);
    const expenseDate = String(req.body?.expense_date || new Date().toISOString().slice(0, 10));
    if (!unitId || !description || amount <= 0) {
      return res.status(422).json({ ok: false, error: "Dados da despesa inválidos." });
    }
    const info = db
      .prepare(
        `INSERT INTO expenses (unit_id, category_id, description, amount_cents, expense_date, created_by)
         VALUES (?, ?, ?, ?, ?, ?)`
      )
      .run(
        unitId,
        req.body?.category_id ? Number(req.body.category_id) : null,
        description,
        amount,
        expenseDate,
        req.user.id
      );
    return res.json({ ok: true, id: Number(info.lastInsertRowid) });
  }
  res.status(400).json({ ok: false, error: "Ação inválida" });
});

router.get("/teachers", requirePerm("teachers.read"), (req, res) => {
  const teachers = db
    .prepare(
      `SELECT t.*, u.name AS unit_name FROM teachers t
       LEFT JOIN units u ON u.id = t.unit_id ORDER BY t.name`
    )
    .all();
  res.json({ ok: true, teachers });
});

module.exports = router;
