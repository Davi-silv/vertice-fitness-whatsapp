const express = require("express");
const bcrypt = require("bcryptjs");
const { db, audit } = require("../db");
const { currentUser } = require("../middleware");

const router = express.Router();

router.get("/", (req, res) => {
  const user = currentUser(req);
  const units = db
    .prepare("SELECT id, name FROM units WHERE active = 1 ORDER BY name")
    .all();
  res.json({
    ok: true,
    node: true,
    csrf: req.session.id || "node",
    units,
    user: user
      ? {
          id: user.id,
          name: user.name,
          email: user.email,
          role: user.role_slug,
        }
      : null,
    adminUrl: "/admin/",
  });
});

router.post("/", (req, res) => {
  const action = String(req.body?.action || req.body?.form || "");
  try {
    if (action === "login" || action === "staff_login") {
      const email = String(req.body?.email || "").trim();
      const password = String(req.body?.password || "");
      if (!email || !password) {
        return res.status(422).json({ ok: false, error: "Informe e-mail e senha." });
      }
      const user = db
        .prepare(
          `SELECT u.*, r.slug AS role_slug
           FROM users u
           INNER JOIN roles r ON r.id = u.role_id
           WHERE lower(u.email) = lower(?) AND u.active = 1`
        )
        .get(email);
      if (!user || !bcrypt.compareSync(password, user.password_hash)) {
        return res.status(401).json({ ok: false, error: "E-mail ou senha inválidos." });
      }
      req.session.regenerate((err) => {
        if (err) {
          return res.status(500).json({ ok: false, error: "Falha na sessão." });
        }
        req.session.userId = user.id;
        if (user.unit_id) req.session.unitFilter = user.unit_id;
        audit(user.id, "login", "users", user.id);
        res.json({
          ok: true,
          redirect: "/admin/",
          user: { name: user.name },
        });
      });
      return;
    }

    if (action === "register" || action === "staff_register") {
      const name = String(req.body?.name || "").trim();
      const email = String(req.body?.email || "").trim().toLowerCase();
      const password = String(req.body?.password || "");
      const password2 = String(req.body?.password_confirm || "");
      let unitId = Number(req.body?.unit_id || 0);
      const units = db
        .prepare("SELECT id, name FROM units WHERE active = 1 ORDER BY name")
        .all();

      if (!name || !email || !password) {
        return res.status(422).json({ ok: false, error: "Preencha nome, e-mail e senha." });
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return res.status(422).json({ ok: false, error: "Informe um e-mail válido." });
      }
      if (password.length < 8) {
        return res.status(422).json({ ok: false, error: "A senha deve ter pelo menos 8 caracteres." });
      }
      if (password !== password2) {
        return res.status(422).json({ ok: false, error: "As senhas não coincidem." });
      }
      const exists = db.prepare("SELECT id FROM users WHERE lower(email) = lower(?)").get(email);
      if (exists) {
        return res.status(409).json({ ok: false, error: "Já existe uma conta com este e-mail." });
      }
      const role = db.prepare("SELECT id FROM roles WHERE slug = 'recepcao'").get();
      if (!role) {
        return res.status(503).json({ ok: false, error: "Execute: npm run seed" });
      }
      if (unitId <= 0 && units[0]) unitId = units[0].id;

      const info = db
        .prepare(
          `INSERT INTO users (unit_id, role_id, name, email, password_hash, active)
           VALUES (?, ?, ?, ?, ?, 1)`
        )
        .run(unitId || null, role.id, name, email, bcrypt.hashSync(password, 10));
      const newId = Number(info.lastInsertRowid);
      audit(newId, "user.register", "users", newId, { source: "api" });

      req.session.regenerate((err) => {
        if (err) {
          return res.json({
            ok: true,
            redirect: "/?login=1",
            message: "Conta criada. Faça login.",
          });
        }
        req.session.userId = newId;
        res.json({ ok: true, redirect: "/admin/", user: { name } });
      });
      return;
    }

    if (action === "logout") {
      const user = currentUser(req);
      if (user) audit(user.id, "logout", "users", user.id);
      req.session.destroy(() => {
        res.json({ ok: true, redirect: "/" });
      });
      return;
    }

    return res.status(400).json({ ok: false, error: "Ação inválida." });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: "Erro interno no login." });
  }
});

module.exports = router;
