(() => {
  const view = document.getElementById("view");
  const title = document.getElementById("page-title");
  const subtitle = document.getElementById("page-sub");
  const userLabel = document.getElementById("user-label");
  const nav = document.getElementById("nav");
  const shell = document.querySelector(".shell");
  const sidebarBackdrop = document.getElementById("sidebar-backdrop");
  let page = "dashboard";

  const meta = {
    dashboard: {
      title: "Visão geral",
      sub: "Resumo do dia e prioridades da academia",
    },
    members: {
      title: "Alunos",
      sub: "Busque, cadastre e acompanhe a base",
    },
    plans: {
      title: "Planos",
      sub: "Catálogo comercial e assinaturas ativas",
    },
    checkin: {
      title: "Check-in",
      sub: "Registre acesso rápido na recepção",
    },
    schedule: {
      title: "Agenda",
      sub: "Aulas do dia, vagas e reservas",
    },
    finance: {
      title: "Financeiro",
      sub: "Receber faturas e registrar despesas",
    },
    teachers: {
      title: "Professores",
      sub: "Equipe e especialidades por unidade",
    },
  };

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

  function esc(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function fmtDate(value) {
    if (!value) return "—";
    const raw = String(value).replace(" ", "T");
    const d = new Date(raw.length <= 10 ? `${raw}T12:00:00` : raw);
    if (Number.isNaN(d.getTime())) return esc(value);
    return d.toLocaleDateString("pt-BR");
  }

  function fmtDateTime(value) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return esc(value);
    return d.toLocaleString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function fmtTime(value) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return esc(value);
    return d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
  }

  function statusBadge(status) {
    const s = String(status || "").toLowerCase();
    if (s === "active" || s === "ativo") return `<span class="badge badge--ok">Ativo</span>`;
    if (s === "inactive" || s === "inativo") return `<span class="badge badge--muted">Inativo</span>`;
    if (s === "open" || s === "aberto") return `<span class="badge badge--warn">Em aberto</span>`;
    return `<span class="badge badge--muted">${esc(status || "—")}</span>`;
  }

  function emptyState(titleText, hint) {
    return `<div class="empty"><strong>${esc(titleText)}</strong>${esc(hint || "")}</div>`;
  }

  function setActive() {
    nav.querySelectorAll("button[data-page]").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.page === page);
    });
  }

  function closeNav() {
    shell.classList.remove("is-nav-open");
    sidebarBackdrop.hidden = true;
  }

  function openNav() {
    shell.classList.add("is-nav-open");
    sidebarBackdrop.hidden = false;
  }

  function bindGo(root = view) {
    root.querySelectorAll("[data-go]").forEach((el) => {
      el.addEventListener("click", () => go(el.dataset.go));
    });
  }

  function memberRows(members) {
    if (!members.length) {
      return `<tr><td colspan="4">${emptyState("Nenhum aluno", "Tente outro termo ou cadastre um novo.")}</td></tr>`;
    }
    return members
      .map(
        (m) => `
        <tr>
          <td><strong>${esc(m.name)}</strong></td>
          <td>${esc(m.phone || m.email || "—")}</td>
          <td>${esc(m.unit_name || "—")}</td>
          <td>${statusBadge(m.status)}</td>
        </tr>`
      )
      .join("");
  }

  async function renderDashboard() {
    const data = await api("/api/admin/dashboard");
    const overdue = data.kpis.overdueInvoices || 0;
    const today = new Date().toLocaleDateString("pt-BR", {
      weekday: "long",
      day: "2-digit",
      month: "long",
    });

    view.innerHTML = `
      <p class="muted" style="margin:0">Hoje · ${esc(today)}</p>

      <section class="quick-actions">
        <button type="button" class="quick" data-go="checkin">
          <strong>Fazer check-in</strong>
          <span>Registrar acesso na recepção</span>
        </button>
        <button type="button" class="quick" data-go="members">
          <strong>Novo aluno</strong>
          <span>Cadastro com plano inicial</span>
        </button>
        <button type="button" class="quick" data-go="finance">
          <strong>Receber fatura</strong>
          <span>${overdue ? `${overdue} atrasada(s)` : "Ver cobranças abertas"}</span>
        </button>
        <button type="button" class="quick" data-go="schedule">
          <strong>Ver agenda</strong>
          <span>${data.kpis.sessionsToday || 0} aula(s) hoje</span>
        </button>
      </section>

      <section class="kpis">
        <article class="kpi" data-go="members">
          <div class="kpi__label"><span>Alunos ativos</span><span>→</span></div>
          <strong>${data.kpis.members}</strong>
          <span class="kpi__hint">Base operacional</span>
        </article>
        <article class="kpi ${overdue ? "is-alert" : ""}" data-go="finance">
          <div class="kpi__label">
            <span>Faturas abertas</span>
            <span>→</span>
          </div>
          <strong>${data.kpis.openInvoices}</strong>
          <span class="kpi__hint">${overdue ? `${overdue} vencida(s)` : "Em dia"}</span>
        </article>
        <article class="kpi" data-go="finance">
          <div class="kpi__label"><span>Receita do mês</span><span>→</span></div>
          <strong>${esc(data.kpis.revenue)}</strong>
          <span class="kpi__hint">Pagamentos recebidos</span>
        </article>
        <article class="kpi" data-go="checkin">
          <div class="kpi__label"><span>Check-ins hoje</span><span>→</span></div>
          <strong>${data.kpis.checkinsToday}</strong>
          <span class="kpi__hint">Fluxo de presença</span>
        </article>
      </section>

      <section class="grid-3">
        <div class="panel">
          <div class="panel__head">
            <h2>Cobranças prioritárias</h2>
            <button type="button" class="panel__link" data-go="finance">Abrir financeiro</button>
          </div>
          ${
            data.openInvoicesList.length
              ? `<div class="list-stack">
                  ${data.openInvoicesList
                    .map(
                      (i) => `
                    <div class="list-item">
                      <div class="list-item__main">
                        <strong>${esc(i.member_name)}</strong>
                        <span class="list-item__meta">${esc(i.description || "Mensalidade")} · vence ${fmtDate(i.due_date)}</span>
                      </div>
                      <div style="text-align:right;display:grid;gap:.25rem;justify-items:end">
                        <strong>${esc(i.amount)}</strong>
                        ${i.overdue ? `<span class="badge badge--danger">Atrasada</span>` : `<span class="badge badge--warn">Aberta</span>`}
                      </div>
                    </div>`
                    )
                    .join("")}
                </div>`
              : emptyState("Nada em aberto", "Quando houver faturas, elas aparecem aqui.")
          }
        </div>

        <div class="panel">
          <div class="panel__head">
            <h2>Aulas de hoje</h2>
            <button type="button" class="panel__link" data-go="schedule">Abrir agenda</button>
          </div>
          ${
            data.todaySessions.length
              ? `<div class="list-stack">
                  ${data.todaySessions
                    .map((s) => {
                      const pct = s.capacity ? Math.min(100, Math.round((s.booked / s.capacity) * 100)) : 0;
                      return `
                      <div class="list-item">
                        <div class="list-item__main">
                          <strong>${esc(s.class_name)}</strong>
                          <span class="list-item__meta">${fmtTime(s.starts_at)} · ${esc(s.teacher_name || "Sem professor")}</span>
                        </div>
                        <div class="capacity" title="${s.booked}/${s.capacity}">
                          <div class="capacity__bar"><div class="capacity__fill ${pct >= 100 ? "is-full" : ""}" style="width:${pct}%"></div></div>
                          <span class="list-item__meta">${s.booked}/${s.capacity}</span>
                        </div>
                      </div>`;
                    })
                    .join("")}
                </div>`
              : emptyState("Sem aulas hoje", "Crie uma sessão na agenda.")
          }
        </div>

        <div class="panel">
          <div class="panel__head">
            <h2>Alunos recentes</h2>
            <button type="button" class="panel__link" data-go="members">Ver todos</button>
          </div>
          ${
            data.recentMembers.length
              ? `<table>
                  <thead><tr><th>Nome</th><th>Unidade</th><th></th></tr></thead>
                  <tbody>
                    ${data.recentMembers
                      .map(
                        (m) => `
                      <tr>
                        <td>${esc(m.name)}</td>
                        <td>${esc(m.unit_name || "—")}</td>
                        <td>${statusBadge(m.status)}</td>
                      </tr>`
                      )
                      .join("")}
                  </tbody>
                </table>`
              : emptyState("Sem alunos", "Cadastre o primeiro aluno.")
          }
        </div>
      </section>`;

    bindGo();
  }

  async function renderMembers() {
    const data = await api("/api/admin/members");
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <div class="panel__head">
            <h2>Lista de alunos</h2>
            <span class="muted">${data.members.length} no total</span>
          </div>
          <div class="toolbar" style="margin-bottom:.85rem">
            <input id="member-q" class="field grow" placeholder="Buscar por nome, e-mail ou telefone…" />
            <button type="button" class="soft" id="member-search">Buscar</button>
          </div>
          <table>
            <thead><tr><th>Nome</th><th>Contato</th><th>Unidade</th><th>Status</th></tr></thead>
            <tbody id="members-body">${memberRows(data.members)}</tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Novo aluno</h2>
          <form class="form" id="member-form">
            <label>Nome<input name="name" required placeholder="Nome completo" /></label>
            <label>E-mail<input name="email" type="email" placeholder="opcional" /></label>
            <label>Telefone<input name="phone" placeholder="(11) 90000-0000" /></label>
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

    const search = async () => {
      const q = document.getElementById("member-q").value.trim();
      const found = await api(`/api/admin/members?q=${encodeURIComponent(q)}`);
      document.getElementById("members-body").innerHTML = memberRows(found.members);
    };

    document.getElementById("member-search").onclick = search;
    document.getElementById("member-q").onkeydown = (ev) => {
      if (ev.key === "Enter") {
        ev.preventDefault();
        search();
      }
    };

    document.getElementById("member-form").onsubmit = async (ev) => {
      ev.preventDefault();
      const payload = Object.fromEntries(new FormData(ev.target).entries());
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
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <div class="panel__head"><h2>Planos ativos</h2></div>
          <table>
            <thead><tr><th>Nome</th><th>Preço</th><th>Descrição</th></tr></thead>
            <tbody>
              ${
                data.plans.length
                  ? data.plans
                      .map(
                        (p) =>
                          `<tr><td><strong>${esc(p.name)}</strong></td><td>${esc(p.price)}</td><td class="muted">${esc(p.description || "—")}</td></tr>`
                      )
                      .join("")
                  : `<tr><td colspan="3">${emptyState("Nenhum plano", "Crie o primeiro à direita.")}</td></tr>`
              }
            </tbody>
          </table>
          <div class="panel__head" style="margin-top:1.2rem"><h2>Assinaturas ativas</h2></div>
          <table>
            <thead><tr><th>Aluno</th><th>Plano</th><th>Início</th></tr></thead>
            <tbody>
              ${
                data.subscriptions.length
                  ? data.subscriptions
                      .map(
                        (s) =>
                          `<tr><td>${esc(s.member_name)}</td><td>${esc(s.plan_name)}</td><td>${fmtDate(s.starts_at)}</td></tr>`
                      )
                      .join("")
                  : `<tr><td colspan="3">${emptyState("Sem assinaturas", "Ao vincular um plano a um aluno, aparece aqui.")}</td></tr>`
              }
            </tbody>
          </table>
        </div>
        <div class="panel">
          <h2>Novo plano</h2>
          <form class="form" id="plan-form">
            <label>Nome<input name="name" required placeholder="Ex.: Diamond" /></label>
            <label>Slug<input name="slug" required placeholder="ex: diamond" /></label>
            <label>Preço (R$)<input name="price" required placeholder="449,00" /></label>
            <label>Descrição<textarea name="description" rows="3" placeholder="Benefícios do plano"></textarea></label>
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
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <h2>Busca rápida</h2>
          <p class="muted" style="margin:0 0 .85rem;font-size:.85rem">Digite nome, telefone, documento ou ID e registre o acesso.</p>
          <form class="form" id="checkin-search">
            <label>Aluno
              <input name="q" required autofocus placeholder="Ex.: Marina" />
            </label>
            <button class="primary" type="submit">Buscar aluno</button>
          </form>
          <div id="checkin-results" style="margin-top:1rem"></div>
        </div>
        <div class="panel">
          <div class="panel__head"><h2>Últimos acessos</h2></div>
          <table>
            <thead><tr><th>Aluno</th><th>Unidade</th><th>Quando</th></tr></thead>
            <tbody>
              ${
                data.recent.length
                  ? data.recent
                      .map(
                        (c) =>
                          `<tr><td>${esc(c.member_name)}</td><td>${esc(c.unit_name)}</td><td>${fmtDateTime(c.checked_in_at)}</td></tr>`
                      )
                      .join("")
                  : `<tr><td colspan="3">${emptyState("Sem check-ins", "Os acessos recentes aparecem aqui.")}</td></tr>`
              }
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
          box.innerHTML = emptyState("Nenhum aluno encontrado", "Confira o nome ou telefone.");
          return;
        }
        box.innerHTML = `
          <div class="list-stack">
            ${found.members
              .map(
                (m) => `
              <div class="list-item">
                <div class="list-item__main">
                  <strong>${esc(m.name)}</strong>
                  <span class="list-item__meta">${esc(m.unit_name || "—")} · ${esc(m.phone || m.email || "")}</span>
                </div>
                <button type="button" class="primary" data-checkin="${m.id}">Check-in</button>
              </div>`
              )
              .join("")}
          </div>`;
        box.querySelectorAll("[data-checkin]").forEach((btn) => {
          btn.onclick = async () => {
            btn.disabled = true;
            btn.textContent = "…";
            try {
              await api("/api/admin/checkin", {
                method: "POST",
                body: JSON.stringify({ action: "checkin", member_id: Number(btn.dataset.checkin) }),
              });
              renderCheckin();
            } catch (err) {
              btn.disabled = false;
              btn.textContent = "Check-in";
              box.insertAdjacentHTML("afterbegin", `<div class="error">${esc(err.message)}</div>`);
            }
          };
        });
      } catch (err) {
        box.innerHTML = `<div class="error">${esc(err.message)}</div>`;
      }
    };
  }

  async function renderSchedule() {
    const data = await api("/api/admin/schedule");
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <div class="panel__head"><h2>Próximas aulas</h2></div>
          ${
            data.sessions.length
              ? `<div class="list-stack">
                  ${data.sessions
                    .map((s) => {
                      const pct = s.capacity ? Math.min(100, Math.round((s.booked / s.capacity) * 100)) : 0;
                      return `
                      <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="list-item__main" style="flex:1;min-width:160px">
                          <strong>${esc(s.class_name)}</strong>
                          <span class="list-item__meta">${fmtDateTime(s.starts_at)} · ${esc(s.teacher_name || "Sem professor")}</span>
                          <div class="capacity" style="margin-top:.45rem;max-width:140px">
                            <div class="capacity__bar"><div class="capacity__fill ${pct >= 100 ? "is-full" : ""}" style="width:${pct}%"></div></div>
                            <span class="list-item__meta">${s.booked}/${s.capacity}</span>
                          </div>
                        </div>
                        <select data-session="${s.id}" class="book-member field" style="max-width:220px">
                          <option value="">Reservar aluno…</option>
                          ${data.members.map((m) => `<option value="${m.id}">${esc(m.name)}</option>`).join("")}
                        </select>
                      </div>`;
                    })
                    .join("")}
                </div>`
              : emptyState("Nenhuma sessão", "Crie a primeira aula à direita.")
          }
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
    view.innerHTML = `
      <section class="grid-2">
        <div class="panel">
          <div class="panel__head"><h2>Faturas abertas</h2></div>
          ${
            data.open.length
              ? `<div class="list-stack">
                  ${data.open
                    .map((i) => {
                      const overdue = i.due_date && String(i.due_date) < new Date().toISOString().slice(0, 10);
                      return `
                      <div class="list-item">
                        <div class="list-item__main">
                          <strong>${esc(i.member_name)}</strong>
                          <span class="list-item__meta">${esc(i.description)} · ${esc(i.amount)} · vence ${fmtDate(i.due_date)}</span>
                        </div>
                        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                          ${overdue ? `<span class="badge badge--danger">Atrasada</span>` : `<span class="badge badge--warn">Aberta</span>`}
                          <button type="button" class="primary" data-pay="${i.id}">Receber Pix</button>
                        </div>
                      </div>`;
                    })
                    .join("")}
                </div>`
              : emptyState("Sem faturas abertas", "Tudo em dia por aqui.")
          }
          <div class="panel__head" style="margin-top:1.25rem"><h2>Pagamentos recentes</h2></div>
          <table>
            <thead><tr><th>Aluno</th><th>Valor</th><th>Método</th></tr></thead>
            <tbody>
              ${
                data.payments.length
                  ? data.payments
                      .map(
                        (p) =>
                          `<tr><td>${esc(p.member_name)}</td><td>${esc(p.amount)}</td><td>${esc(p.method)}</td></tr>`
                      )
                      .join("")
                  : `<tr><td colspan="3" class="muted">Nenhum pagamento ainda</td></tr>`
              }
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
            <label>Descrição<input name="description" required placeholder="Ex.: Material de limpeza" /></label>
            <label>Valor (R$)<input name="amount" required placeholder="100,00" /></label>
            <label>Data<input type="date" name="expense_date" required /></label>
            <button class="primary" type="submit">Registrar despesa</button>
            <div id="expense-msg"></div>
          </form>
        </div>
      </section>`;

    view.querySelectorAll("[data-pay]").forEach((btn) => {
      btn.onclick = async () => {
        btn.disabled = true;
        btn.textContent = "…";
        try {
          await api("/api/admin/finance", {
            method: "POST",
            body: JSON.stringify({ action: "pay", invoice_id: Number(btn.dataset.pay), method: "pix" }),
          });
          renderFinance();
        } catch (err) {
          alert(err.message);
          btn.disabled = false;
          btn.textContent = "Receber Pix";
        }
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
    view.innerHTML = `
      <div class="panel">
        <div class="panel__head">
          <h2>Equipe</h2>
          <span class="muted">${data.teachers.length} professor(es)</span>
        </div>
        ${
          data.teachers.length
            ? `<table>
                <thead><tr><th>Nome</th><th>Especialidade</th><th>Unidade</th><th>Comissão</th></tr></thead>
                <tbody>
                  ${data.teachers
                    .map(
                      (t) => `
                    <tr>
                      <td><strong>${esc(t.name)}</strong></td>
                      <td>${esc(t.specialty || "—")}</td>
                      <td>${esc(t.unit_name || "—")}</td>
                      <td>${t.commission_percent}%</td>
                    </tr>`
                    )
                    .join("")}
                </tbody>
              </table>`
            : emptyState("Sem professores", "Cadastre a equipe no banco/seed.")
        }
      </div>`;
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
    if (!pages[next]) return;
    page = next;
    setActive();
    closeNav();
    const info = meta[page] || { title: page, sub: "" };
    title.textContent = info.title;
    subtitle.textContent = info.sub;
    view.innerHTML = `<p class="skeleton">Carregando…</p>`;
    try {
      await pages[page]();
    } catch (err) {
      view.innerHTML = `<div class="error">${esc(err.message)}</div>`;
    }
  }

  nav.querySelectorAll("button[data-page]").forEach((btn) => {
    btn.addEventListener("click", () => go(btn.dataset.page));
  });

  document.getElementById("menu-toggle")?.addEventListener("click", openNav);
  document.getElementById("sidebar-close")?.addEventListener("click", closeNav);
  sidebarBackdrop?.addEventListener("click", closeNav);

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
