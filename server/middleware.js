const { db } = require("./db");

function currentUser(req) {
  const id = req.session?.userId;
  if (!id) return null;
  return (
    db
      .prepare(
        `SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.active = 1`
      )
      .get(id) || null
  );
}

function requireAuth(req, res, next) {
  const user = currentUser(req);
  if (!user) {
    return res.status(401).json({ ok: false, error: "Não autenticado" });
  }
  req.user = user;
  next();
}

function userPermissions(user) {
  if (!user) return new Set();
  const rows = db
    .prepare(
      `SELECT p.slug
       FROM role_permissions rp
       INNER JOIN permissions p ON p.id = rp.permission_id
       WHERE rp.role_id = ?`
    )
    .all(user.role_id);
  return new Set(rows.map((r) => r.slug));
}

function requirePerm(slug) {
  return (req, res, next) => {
    const user = req.user || currentUser(req);
    if (!user) return res.status(401).json({ ok: false, error: "Não autenticado" });
    const perms = userPermissions(user);
    if (!perms.has("*") && !perms.has(slug)) {
      return res.status(403).json({ ok: false, error: "Sem permissão" });
    }
    req.user = user;
    next();
  };
}

module.exports = { currentUser, requireAuth, requirePerm, userPermissions };
