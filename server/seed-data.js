const bcrypt = require("bcryptjs");

/**
 * Popula o banco. Usado pelo seed CLI e pelo auto-seed na Vercel.
 */
module.exports = function seedData(db, { quiet = false, dbPath = "" } = {}) {
  const roleIds = {};
  for (const [slug, name] of [
    ["admin", "Administrador"],
    ["financeiro", "Financeiro"],
    ["recepcao", "Recepção"],
    ["professor", "Professor"],
  ]) {
    const info = db.prepare("INSERT INTO roles (slug, name) VALUES (?, ?)").run(slug, name);
    roleIds[slug] = Number(info.lastInsertRowid);
  }

  const permIds = {};
  const permissions = [
    ["*", "Acesso total"],
    ["dashboard.read", "Ver dashboard"],
    ["members.read", "Ver alunos"],
    ["members.write", "Editar alunos"],
    ["plans.read", "Ver planos"],
    ["plans.write", "Editar planos"],
    ["finance.read", "Ver financeiro"],
    ["finance.write", "Editar financeiro"],
    ["checkins.write", "Fazer check-in"],
    ["schedule.read", "Ver agenda"],
    ["schedule.write", "Editar agenda"],
    ["teachers.read", "Ver professores"],
    ["teachers.write", "Editar professores"],
    ["users.write", "Gerenciar usuários"],
  ];
  for (const [slug, name] of permissions) {
    const info = db.prepare("INSERT INTO permissions (slug, name) VALUES (?, ?)").run(slug, name);
    permIds[slug] = Number(info.lastInsertRowid);
  }

  const map = {
    admin: ["*"],
    financeiro: ["dashboard.read", "finance.read", "finance.write", "members.read", "plans.read"],
    recepcao: [
      "dashboard.read", "members.read", "members.write", "plans.read", "plans.write",
      "finance.read", "checkins.write", "schedule.read", "schedule.write",
    ],
    professor: ["dashboard.read", "schedule.read", "checkins.write", "members.read", "teachers.read"],
  };
  const link = db.prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
  for (const [role, perms] of Object.entries(map)) {
    for (const p of perms) link.run(roleIds[role], permIds[p]);
  }

  const unit1 = Number(
    db.prepare("INSERT INTO units (name, slug, address, phone) VALUES (?, ?, ?, ?)").run(
      "Vértice Copacabana",
      "copacabana",
      "Av. Atlântica, 1900 — Copacabana, Rio de Janeiro",
      "21999998888"
    ).lastInsertRowid
  );
  db.prepare("INSERT INTO units (name, slug, address, phone) VALUES (?, ?, ?, ?)").run(
    "Vértice Ipanema",
    "ipanema",
    "Rua Visconde de Pirajá, 500 — Ipanema, Rio de Janeiro",
    "21988887777"
  );

  const passAdmin = bcrypt.hashSync("Vertice@2026", 10);
  const passTeste = bcrypt.hashSync("Teste@2026", 10);
  const insUser = db.prepare(
    "INSERT INTO users (unit_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)"
  );
  const adminId = Number(
    insUser.run(unit1, roleIds.admin, "Admin Vértice", "admin@vertice.local", passAdmin).lastInsertRowid
  );
  insUser.run(unit1, roleIds.admin, "Usuário Teste", "teste@vertice.local", passTeste);
  insUser.run(unit1, roleIds.financeiro, "Ana Financeiro", "financeiro@vertice.local", passAdmin);
  insUser.run(unit1, roleIds.recepcao, "Bruno Recepção", "recepcao@vertice.local", passAdmin);
  const profUserId = Number(
    insUser.run(unit1, roleIds.professor, "Carla Professor", "professor@vertice.local", passAdmin)
      .lastInsertRowid
  );

  const planIds = {};
  const insPlan = db.prepare(
    "INSERT INTO plans (name, slug, price_cents, description) VALUES (?, ?, ?, ?)"
  );
  for (const [name, slug, price, desc] of [
    ["Black", "black", 44900, "Musculação, bike, aulas coletivas, app"],
    ["Gold", "gold", 78900, "Black + personal 4x, spa, nutri"],
    ["Platinum", "platinum", 129000, "Gold + personal ilimitado, concierge VIP"],
  ]) {
    planIds[slug] = Number(insPlan.run(name, slug, price, desc).lastInsertRowid);
  }

  const catIds = {};
  for (const cat of ["Aluguel", "Folha", "Utilities", "Marketing", "Manutenção", "Suprimentos"]) {
    const info = db.prepare("INSERT INTO expense_categories (name) VALUES (?)").run(cat);
    catIds[cat] = Number(info.lastInsertRowid);
  }

  const teacherId = Number(
    db.prepare(
      "INSERT INTO teachers (user_id, unit_id, name, phone, specialty, commission_percent) VALUES (?, ?, ?, ?, ?, ?)"
    ).run(profUserId, unit1, "Carla Professor", "21977776666", "Functional / Bike", 10).lastInsertRowid
  );

  const members = [
    ["Marina Souza", "marina@email.com", "21911112222", "active"],
    ["Pedro Lima", "pedro@email.com", "21933334444", "active"],
    ["Julia Rocha", "julia@email.com", "21955556666", "active"],
  ];
  const memberIds = [];
  const insMember = db.prepare(
    "INSERT INTO members (unit_id, name, email, phone, status) VALUES (?, ?, ?, ?, ?)"
  );
  for (const [name, email, phone, status] of members) {
    memberIds.push(Number(insMember.run(unit1, name, email, phone, status).lastInsertRowid));
  }

  const today = new Date();
  const iso = (d) => d.toISOString().slice(0, 10);
  const start = iso(today);
  const end = iso(new Date(today.getFullYear(), today.getMonth() + 1, today.getDate()));

  const sub1 = Number(
    db.prepare(
      `INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
       VALUES (?, ?, ?, ?, 'active', ?, ?)`
    ).run(memberIds[0], planIds.gold, unit1, adminId, start, end).lastInsertRowid
  );
  db.prepare(
    `INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
     VALUES (?, ?, ?, ?, 'active', ?, ?)`
  ).run(memberIds[1], planIds.black, unit1, adminId, start, end);
  db.prepare(
    `INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
     VALUES (?, ?, ?, ?, 'active', ?, ?)`
  ).run(memberIds[2], planIds.platinum, unit1, adminId, start, end);

  const inv1 = Number(
    db.prepare(
      `INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
       VALUES (?, ?, ?, ?, ?, ?, 'paid')`
    ).run(memberIds[0], sub1, unit1, "Mensalidade Gold", 78900, start).lastInsertRowid
  );
  db.prepare(
    `INSERT INTO payments (invoice_id, amount_cents, method, paid_at, created_by)
     VALUES (?, ?, 'pix', datetime('now','-1 day'), ?)`
  ).run(inv1, 78900, adminId);

  db.prepare(
    `INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
     VALUES (?, ?, ?, ?, ?, ?, 'open')`
  ).run(memberIds[1], null, unit1, "Mensalidade Black", 44900, end);

  db.prepare(
    `INSERT INTO expenses (unit_id, category_id, description, amount_cents, expense_date, created_by)
     VALUES (?, ?, 'Aluguel unidade Copacabana', 2500000, ?, ?)`
  ).run(unit1, catIds.Aluguel, start, adminId);

  const classId = Number(
    db.prepare(
      "INSERT INTO classes (unit_id, teacher_id, name, capacity, duration_min) VALUES (?, ?, ?, ?, ?)"
    ).run(unit1, teacherId, "Bike Indoor VIP", 20, 45).lastInsertRowid
  );
  const starts = new Date();
  starts.setDate(starts.getDate() + 1);
  starts.setHours(8, 30, 0, 0);
  const endsAt = new Date(starts.getTime() + 45 * 60000);
  const toSql = (d) => d.toISOString().replace("T", " ").slice(0, 19);
  const sessionId = Number(
    db.prepare(
      "INSERT INTO class_sessions (class_id, starts_at, ends_at) VALUES (?, ?, ?)"
    ).run(classId, toSql(starts), toSql(endsAt)).lastInsertRowid
  );
  db.prepare("INSERT INTO class_bookings (session_id, member_id) VALUES (?, ?)").run(
    sessionId,
    memberIds[0]
  );

  db.prepare(
    "INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, 'manual', ?)"
  ).run(memberIds[0], unit1, adminId);
  db.prepare(
    "INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, 'manual', ?)"
  ).run(memberIds[1], unit1, adminId);

  db.prepare("INSERT INTO settings (key, value) VALUES (?, ?)").run("academy_name", "Vértice Fitness");
  db.prepare(
    `INSERT INTO audit_logs (user_id, action, entity, entity_id, meta) VALUES (?, ?, ?, ?, ?)`
  ).run(adminId, "seed.run", "system", null, JSON.stringify({ db: dbPath }));

  if (!quiet) {
    console.log("OK seed Node.js");
    console.log("Login teste: teste@vertice.local / Teste@2026");
    console.log("Login admin: admin@vertice.local / Vertice@2026");
    if (dbPath) console.log("DB:", dbPath);
  }
};
