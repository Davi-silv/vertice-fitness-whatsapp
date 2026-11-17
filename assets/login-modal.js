(() => {
  const overlay = document.getElementById("login-panel");
  if (!overlay) return;

  const AUTH_URL = "/api/auth";
  const openers = [
    document.getElementById("open-login"),
    document.getElementById("open-login-cta"),
  ].filter(Boolean);
  const closers = [
    document.getElementById("close-login"),
    document.getElementById("close-login-backdrop"),
  ].filter(Boolean);
  const tabButtons = overlay.querySelectorAll("[data-auth-tab]");
  const panels = overlay.querySelectorAll("[data-auth-panel]");
  const title = document.getElementById("login-title");
  const hint = document.getElementById("php-auth-hint");
  const formLogin = document.getElementById("form-login");
  const formRegister = document.getElementById("form-register");
  const forms = [formLogin, formRegister].filter(Boolean);

  let csrf = overlay.querySelector('input[name="_csrf"]')?.value || "";
  let adminUrl = "/admin/";
  let backendReady = Boolean(csrf);
  let booting = null;

  function ensureFeedback() {
    let el = overlay.querySelector("[data-auth-feedback]");
    if (!el) {
      el = document.createElement("div");
      el.setAttribute("data-auth-feedback", "1");
      el.hidden = true;
      const tabs = overlay.querySelector(".login-tabs");
      (tabs || overlay.querySelector(".login-sheet__lead"))?.after(el);
    }
    return el;
  }

  function showFeedback(message, type = "error") {
    const el = ensureFeedback();
    el.hidden = !message;
    el.className =
      type === "success" ? "login-sheet__success" : "login-sheet__error";
    el.textContent = message || "";
  }

  function showStaticHint(show) {
    if (!hint) return;
    hint.hidden = !show;
    hint.classList.toggle("is-alert", show);
  }

  function setCsrf(token) {
    csrf = token || "";
    overlay.querySelectorAll('input[name="_csrf"]').forEach((input) => {
      input.value = csrf;
    });
    forms.forEach((form) => {
      if (!form.querySelector('input[name="_csrf"]')) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "_csrf";
        input.value = csrf;
        form.prepend(input);
      }
    });
  }

  function fillUnits(units) {
    if (!formRegister || !Array.isArray(units) || !units.length) return;
    let select = formRegister.querySelector('select[name="unit_id"]');
    if (!select) {
      const label = document.createElement("label");
      label.innerHTML = "Unidade";
      select = document.createElement("select");
      select.name = "unit_id";
      select.required = true;
      label.appendChild(select);
      const emailLabel = formRegister.querySelector('input[name="email"]')?.closest("label");
      emailLabel?.after(label);
    }
    select.innerHTML = "";
    units.forEach((unit) => {
      const opt = document.createElement("option");
      opt.value = String(unit.id);
      opt.textContent = unit.name;
      select.appendChild(opt);
    });
  }

  async function bootBackend() {
    if (booting) return booting;
    booting = (async () => {
      try {
        const res = await fetch(AUTH_URL, {
          method: "GET",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const type = res.headers.get("content-type") || "";
        if (!res.ok || !type.includes("application/json")) {
          throw new Error("Backend PHP indisponível");
        }
        const data = await res.json();
        if (!data?.ok || !data.csrf) {
          throw new Error(data?.error || "Backend PHP indisponível");
        }
        setCsrf(data.csrf);
        fillUnits(data.units || []);
        if (data.adminUrl) adminUrl = data.adminUrl;
        backendReady = true;
        showStaticHint(false);
        if (data.user) {
          showFeedback(`Olá, ${data.user.name}. Redirecionando…`, "success");
          window.setTimeout(() => {
            window.location.href = data.adminUrl || adminUrl;
          }, 400);
        }
        return true;
      } catch (err) {
        backendReady = false;
        showStaticHint(true);
        showFeedback(
          "Backend offline. Rode: npm run seed && npm run dev",
          "error"
        );
        return false;
      } finally {
        booting = null;
      }
    })();
    return booting;
  }

  function focusActiveForm() {
    const active = overlay.querySelector(".login-sheet__form.is-active input:not([type=hidden])");
    active?.focus();
  }

  function setTab(tab) {
    const next = tab === "register" ? "register" : "login";
    overlay.dataset.tab = next;
    tabButtons.forEach((btn) => {
      const on = btn.getAttribute("data-auth-tab") === next;
      btn.classList.toggle("is-active", on);
      if (btn.getAttribute("role") === "tab") {
        btn.setAttribute("aria-selected", on ? "true" : "false");
      }
    });
    panels.forEach((panel) => {
      const on = panel.getAttribute("data-auth-panel") === next;
      panel.classList.toggle("is-active", on);
    });
    if (title) {
      title.textContent = next === "register" ? "Criar conta" : "Gestão Vértice";
    }
    focusActiveForm();
  }

  async function openLogin(tab) {
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("login-open");
    if (tab) setTab(tab);
    else setTab(overlay.dataset.tab || "login");
    await bootBackend();
  }

  function closeLogin() {
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.classList.remove("login-open");
  }

  async function submitAuth(form, event) {
    event.preventDefault();
    event.stopPropagation();
    showFeedback("");

    const ready = backendReady || (await bootBackend());
    if (!ready || !csrf) {
      showFeedback("Backend PHP não encontrado nesta URL.", "error");
      return;
    }

    const panel = form.getAttribute("data-auth-panel");
    const action = panel === "register" ? "register" : "login";
    const body = {
      action,
      _csrf: csrf,
      email: form.email?.value?.trim() || "",
      password: form.password?.value || "",
    };
    if (action === "register") {
      body.name = form.name?.value?.trim() || "";
      body.password_confirm = form.password_confirm?.value || "";
      body.unit_id = form.unit_id?.value || "";
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const res = await fetch(AUTH_URL, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify(body),
      });
      const type = res.headers.get("content-type") || "";
      const data = type.includes("application/json") ? await res.json() : null;
      if (!data) {
        throw new Error("Resposta inválida do backend PHP");
      }
      if (!res.ok || !data.ok) {
        showFeedback(data.error || "Não foi possível autenticar.", "error");
        if (res.status === 419) {
          backendReady = false;
          await bootBackend();
        }
        return;
      }
      showFeedback(data.message || "Login ok. Abrindo painel…", "success");
      window.location.href = data.redirect || adminUrl;
    } catch (err) {
      showFeedback(
        "Falha ao falar com o Node.js. Use: npm run dev (porta 3100)",
        "error"
      );
      showStaticHint(true);
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  openers.forEach((btn) => btn.addEventListener("click", () => openLogin("login")));
  closers.forEach((btn) => btn.addEventListener("click", closeLogin));
  tabButtons.forEach((btn) => {
    btn.addEventListener("click", () => setTab(btn.getAttribute("data-auth-tab") || "login"));
  });
  forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      submitAuth(form, event);
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) {
      closeLogin();
    }
  });

  if (hint) hint.hidden = true;

  const params = new URLSearchParams(window.location.search);
  if (overlay.classList.contains("is-open") || params.has("login")) {
    openLogin(params.get("tab") || overlay.dataset.tab || "login");
  } else if (csrf) {
    backendReady = true;
  }
})();
