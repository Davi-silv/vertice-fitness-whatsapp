(() => {
  const view = document.getElementById("view");
  const title = document.getElementById("page-title");
  const userLabel = document.getElementById("user-label");
  const nav = document.getElementById("nav");
  let page = "dashboard";

  async function api(url, options = {}) {
    const res = await fetch(url, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(options.headers || {}),
      },
      ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) {
      window.location.href = "/?login=1";
      throw new Error("Não autenticado");
    }
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || "Falha na API");
    }
    return data;
  }

  function setActive() {
    nav.querySelectorAll("button").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.page === page);
    });
  }

  async function renderDashboard() {
    const data = await api("/api/admin/dashboard");
    title.textContent = "Dashboard";
    view.innerHTML = `
      <section class="kpis">
        <article class="kpi"><span>Alunos ativos</span><strong>${data.kpis.members}</strong></article>
        <article class="kpi"><span>Faturas abertas</span><strong>${data.kpis.openInvoices}</strong></article>
        <article class="kpi"><span>Receita do mês</span><strong>${data.kpis.revenue}</strong></article>
        <article class="kpi"><span>Check-ins hoje</span><strong>${data.kpis.checkinsToday}</strong></article>
      </section>
      <section class="grid-2">
        <div class="panel">
          <h2>Alunos recentes</h2>
          <table>
            <thead><tr><th>Nome</th><th>Unidade</th><th>Status</th></tr></thead>
            <tbody>
              ${data.recentMembers
                .map(
                  (m) =>
                    `<tr><td>${esc(m.name)}</td><td>${esc(m.unit_name || "—")}</td><td>${esc(m.status)}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Faturas em aberto</h2>
          <table>
            <thead><tr><th>Aluno</th><th>Valor</th><th>Venc.</th></tr></thead>
            <tbody>
              ${data.openInvoicesList
                .map(
                  (i) =>
                    `<tr><td>${esc(i.member_name)}</td><td>${esc(i.amount)}</td><td>${esc(i.due_date)}</td></tr>`
                )
                .join("") || `<tr><td colspan="3" class="muted">Nenhuma fatura aberta</td></tr>`}
            </tbody>
          </table>
        </div>
      </section>`;
  }

  async function renderMembers() {
    const data = await api("/api/admin/members");
    title.textContent = "Alunos";
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Lista</h2>
          <div class="row" style="margin-bottom:.8rem">
            <input id="member-q" placeholder="Buscar nome, e-mail, telefone…" style="flex:1;border:1px solid var(--line);background:#090909;color:var(--text);border-radius:10px;padding:.7rem .8rem;font:inherit" />
            <button type="button" class="soft" id="member-search">Buscar</button>
          </div>
          <table>
            <thead><tr><th>Nome</th><th>Contato</th><th>Unidade</th><th>Status</th></tr></thead>
            <tbody id="members-body">
              ${data.members
                .map(
                  (m) =>
                    `<tr><td>${esc(m.name)}</td><td>${esc(m.phone || m.email || "—")}</td><td>${esc(m.unit_name || "—")}</td><td>${esc(m.status)}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Novo aluno</h2>
          <form class="form" id="member-form">
            <label>Nome<input name="name" required /></label>
            <label>E-mail<input name="email" type="email" /></label>
            <label>Telefone<input name="phone" /></label>
            <label>Unidade
              <select name="unit_id" required>
                ${data.units.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join("")}
              </select>
            </label>
            <label>Plano inicial
              <select name="plan_id">
                <option value="">Sem plano</option>
                ${data.plans.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join("")}
              </select>
            </label>
            <button class="primary" type="submit">Salvar aluno</button>
            <div id="member-msg"></div>
          </form>
        </div>
      </section>`;

    document.getElementById("member-search").onclick = async () => {
      const q = document.getElementById("member-q").value.trim();
      const found = await api(`/api/admin/members?q=${encodeURIComponent(q)}`);
      document.getElementById("members-body").innerHTML = found.members
        .map(
          (m) =>
            `<tr><td>${esc(m.name)}</td><td>${esc(m.phone || m.email || "—")}</td><td>${esc(m.unit_name || "—")}</td><td>${esc(m.status)}</td></tr>`
        )
        .join("");
    };

    document.getElementById("member-form").onsubmit = async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      const payload = Object.fromEntries(fd.entries());
      const msg = document.getElementById("member-msg");
      try {
        await api("/api/admin/members", { method: "POST", body: JSON.stringify(payload) });
        msg.className = "ok";
        msg.textContent = "Aluno cadastrado.";
        renderMembers();
      } catch (err) {
        msg.className = "error";
        msg.textContent = err.message;
      }
    };
  }

  async function renderPlans() {
    const data = await api("/api/admin/plans");
    title.textContent = "Planos";
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Planos ativos</h2>
          <table>
            <thead><tr><th>Nome</th><th>Preço</th><th>Descrição</th></tr></thead>
            <tbody>
              ${data.plans
                .map(
                  (p) =>
                    `<tr><td>${esc(p.name)}</td><td>${esc(p.price)}</td><td>${esc(p.description || "—")}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
          <h2 style="margin-top:1.2rem">Assinaturas ativas</h2>
          <table>
            <thead><tr><th>Aluno</th><th>Plano</th><th>Início</th></tr></thead>
            <tbody>
              ${data.subscriptions
                .map(
                  (s) =>
                    `<tr><td>${esc(s.member_name)}</td><td>${esc(s.plan_name)}</td><td>${esc(s.starts_at)}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Novo plano</h2>
          <form class="form" id="plan-form">
            <label>Nome<input name="name" required /></label>
            <label>Slug<input name="slug" required placeholder="ex: diamond" /></label>
            <label>Preço (R$)<input name="price" required placeholder="449,00" /></label>
            <label>Descrição<textarea name="description" rows="3"></textarea></label>
            <button class="primary" type="submit">Criar plano</button>
            <div id="plan-msg"></div>
          </form>
        </div>
      </section>`;

    document.getElementById("plan-form").onsubmit = async (ev) => {
      ev.preventDefault();
      const payload = Object.fromEntries(new FormData(ev.target).entries());
      const msg = document.getElementById("plan-msg");
      try {
        await api("/api/admin/plans", { method: "POST", body: JSON.stringify(payload) });
        msg.className = "ok";
        msg.textContent = "Plano criado.";
        renderPlans();
      } catch (err) {
        msg.className = "error";
        msg.textContent = err.message;
      }
    };
  }

  async function renderCheckin() {
    const data = await api("/api/admin/checkin");
    title.textContent = "Check-in";
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Busca rápida</h2>
          <form class="form" id="checkin-search">
            <label>Nome, telefone, documento ou ID
              <input name="q" required placeholder="Ex.: Marina" />
            </label>
            <button class="primary" type="submit">Buscar</button>
          </form>
          <div id="checkin-results" style="margin-top:1rem"></div>
        </div>
        <div class="panel">
          <h2>Últimos acessos</h2>
          <table>
            <thead><tr><th>Aluno</th><th>Unidade</th><th>Quando</th></tr></thead>
            <tbody>
              ${data.recent
                .map(
                  (c) =>
                    `<tr><td>${esc(c.member_name)}</td><td>${esc(c.unit_name)}</td><td>${esc(c.checked_in_at)}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
      </section>`;

    document.getElementById("checkin-search").onsubmit = async (ev) => {
      ev.preventDefault();
      const q = new FormData(ev.target).get("q");
      const box = document.getElementById("checkin-results");
      try {
        const found = await api("/api/admin/checkin", {
          method: "POST",
          body: JSON.stringify({ action: "search", q }),
        });
        if (!found.members.length) {
          box.innerHTML = `<p class="muted">Nenhum aluno encontrado.</p>`;
          return;
        }
        box.innerHTML = found.members
          .map(
            (m) => `
            <div class="panel" style="margin-bottom:.6rem">
              <strong>${esc(m.name)}</strong>
              <p class="muted">${esc(m.unit_name || "")} · ${esc(m.phone || m.email || "")}</p>
              <button type="button" class="primary" data-checkin="${m.id}">Registrar check-in</button>
            </div>`
          )
          .join("");
        box.querySelectorAll("[data-checkin]").forEach((btn) => {
          btn.onclick = async () => {
            await api("/api/admin/checkin", {
              method: "POST",
              body: JSON.stringify({ action: "checkin", member_id: Number(btn.dataset.checkin) }),
            });
            renderCheckin();
          };
        });
      } catch (err) {
        box.innerHTML = `<div class="error">${esc(err.message)}</div>`;
      }
    };
  }

  async function renderSchedule() {
    const data = await api("/api/admin/schedule");
    title.textContent = "Agenda";
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Próximas aulas</h2>
          <table>
            <thead><tr><th>Aula</th><th>Início</th><th>Vagas</th><th></th></tr></thead>
            <tbody>
              ${data.sessions
                .map(
                  (s) => `
                  <tr>
                    <td>${esc(s.class_name)}<br><span class="muted">${esc(s.teacher_name || "")}</span></td>
                    <td>${esc(s.starts_at)}</td>
                    <td>${s.booked}/${s.capacity}</td>
                    <td>
                      <select data-session="${s.id}" class="book-member">
                        <option value="">Reservar aluno…</option>
                        ${data.members
                          .map((m) => `<option value="${m.id}">${esc(m.name)}</option>`)
                          .join("")}
                      </select>
                    </td>
                  </tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Nova sessão</h2>
          <form class="form" id="session-form">
            <label>Aula
              <select name="class_id" required>
                ${data.classes.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("")}
              </select>
            </label>
            <label>Início<input type="datetime-local" name="starts_at" required /></label>
            <button class="primary" type="submit">Criar sessão</button>
            <div id="session-msg"></div>
          </form>
        </div>
      </section>`;

    view.querySelectorAll(".book-member").forEach((sel) => {
      sel.onchange = async () => {
        if (!sel.value) return;
        try {
          await api("/api/admin/schedule", {
            method: "POST",
            body: JSON.stringify({
              action: "book",
              session_id: Number(sel.dataset.session),
              member_id: Number(sel.value),
            }),
          });
          renderSchedule();
        } catch (err) {
          alert(err.message);
          sel.value = "";
        }
      };
    });

    document.getElementById("session-form").onsubmit = async (ev) => {
      ev.preventDefault();
      const payload = Object.fromEntries(new FormData(ev.target).entries());
      payload.action = "session";
      const msg = document.getElementById("session-msg");
      try {
        await api("/api/admin/schedule", { method: "POST", body: JSON.stringify(payload) });
        msg.className = "ok";
        msg.textContent = "Sessão criada.";
        renderSchedule();
      } catch (err) {
        msg.className = "error";
        msg.textContent = err.message;
      }
    };
  }

  async function renderFinance() {
    const data = await api("/api/admin/finance");
    title.textContent = "Financeiro";
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Faturas abertas</h2>
          <table>
            <thead><tr><th>Aluno</th><th>Descrição</th><th>Valor</th><th></th></tr></thead>
            <tbody>
              ${data.open
                .map(
                  (i) => `
                  <tr>
                    <td>${esc(i.member_name)}</td>
                    <td>${esc(i.description)}</td>
                    <td>${esc(i.amount)}</td>
                    <td><button type="button" class="primary" data-pay="${i.id}">Receber</button></td>
                  </tr>`
                )
                .join("") || `<tr><td colspan="4" class="muted">Sem faturas abertas</td></tr>`}
            </tbody>
          </table>
          <h2 style="margin-top:1.2rem">Pagamentos recentes</h2>
          <table>
            <thead><tr><th>Aluno</th><th>Valor</th><th>Método</th></tr></thead>
            <tbody>
              ${data.payments
                .map(
                  (p) =>
                    `<tr><td>${esc(p.member_name)}</td><td>${esc(p.amount)}</td><td>${esc(p.method)}</td></tr>`
                )
                .join("")}
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Nova despesa</h2>
          <form class="form" id="expense-form">
            <label>Unidade
              <select name="unit_id" required>
                ${data.units.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join("")}
              </select>
            </label>
            <label>Categoria
              <select name="category_id">
                <option value="">—</option>
                ${data.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("")}
              </select>
            </label>
            <label>Descrição<input name="description" required /></label>
            <label>Valor (R$)<input name="amount" required placeholder="100,00" /></label>
            <label>Data<input type="date" name="expense_date" required /></label>
            <button class="primary" type="submit">Registrar despesa</button>
            <div id="expense-msg"></div>
          </form>
        </div>
      </section>`;

    view.querySelectorAll("[data-pay]").forEach((btn) => {
      btn.onclick = async () => {
        await api("/api/admin/finance", {
          method: "POST",
          body: JSON.stringify({ action: "pay", invoice_id: Number(btn.dataset.pay), method: "pix" }),
        });
        renderFinance();
      };
    });

    const expenseForm = document.getElementById("expense-form");
    expenseForm.expense_date.value = new Date().toISOString().slice(0, 10);
    expenseForm.onsubmit = async (ev) => {
      ev.preventDefault();
      const payload = Object.fromEntries(new FormData(ev.target).entries());
      payload.action = "expense";
      const msg = document.getElementById("expense-msg");
      try {
        await api("/api/admin/finance", { method: "POST", body: JSON.stringify(payload) });
        msg.className = "ok";
        msg.textContent = "Despesa registrada.";
        renderFinance();
      } catch (err) {
        msg.className = "error";
        msg.textContent = err.message;
      }
    };
  }

  async function renderTeachers() {
    const data = await api("/api/admin/teachers");
    title.textContent = "Professores";
    view.innerHTML = `
      <div class="panel">
        <h2>Equipe</h2>
        <table>
          <thead><tr><th>Nome</th><th>Especialidade</th><th>Unidade</th><th>Comissão</th></tr></thead>
          <tbody>
            ${data.teachers
              .map(
                (t) =>
                  `<tr><td>${esc(t.name)}</td><td>${esc(t.specialty || "—")}</td><td>${esc(t.unit_name || "—")}</td><td>${t.commission_percent}%</td></tr>`
              )
              .join("")}
          </tbody>
        </table>
      </div>`;
  }

  function esc(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  const pages = {
    dashboard: renderDashboard,
    members: renderMembers,
    plans: renderPlans,
    checkin: renderCheckin,
    schedule: renderSchedule,
    finance: renderFinance,
    teachers: renderTeachers,
  };

  async function go(next) {
    page = next;
    setActive();
    view.innerHTML = `<p class="muted">Carregando…</p>`;
    try {
      await pages[page]();
    } catch (err) {
      view.innerHTML = `<div class="error">${esc(err.message)}</div>`;
    }
  }

  nav.querySelectorAll("button").forEach((btn) => {
    btn.addEventListener("click", () => go(btn.dataset.page));
  });

  document.getElementById("logout").onclick = async () => {
    await api("/api/auth", {
      method: "POST",
      body: JSON.stringify({ action: "logout" }),
    });
    window.location.href = "/";
  };

  api("/api/admin/me")
    .then((data) => {
      userLabel.textContent = `${data.user.name} · ${data.user.roleName}`;
      go("dashboard");
    })
    .catch(() => {
      window.location.href = "/?login=1";
    });
})();
