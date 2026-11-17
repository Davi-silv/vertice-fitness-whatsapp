(() => {
  const overlay = document.getElementById("login-panel");
  if (!overlay) return;

  const AUTH_PATH = "/api/auth";
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
  const hint = document.getElementById("auth-hint");
  const formLogin = document.getElementById("form-login");
  const formRegister = document.getElementById("form-register");
  const forms = [formLogin, formRegister].filter(Boolean);
  const tabs = overlay.querySelector(".login-tabs");
  const lead = overlay.querySelector(".login-sheet__lead");

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
      (tabs || lead)?.after(el);
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

  function setStaticMode(on) {
    if (hint) {
      hint.hidden = !on;
      hint.classList.toggle("is-alert", on);
    }
    if (tabs) tabs.hidden = on;
    forms.forEach((form) => {
      form.hidden = on;
      form.querySelectorAll("input,button,select").forEach((el) => {
        if (el.classList.contains("login-sheet__link")) return;
        el.disabled = on;
      });
    });
    if (lead) {
      lead.textContent = on
        ? "Na Vercel só a landing e o chat ficam online. O login do painel roda no Node local."
        : "Entre com sua conta ou crie um acesso para o painel administrativo.";
    }
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
        if (!window.VerticeAPI) {
          throw new Error("Cliente de API não carregado");
        }
        await window.VerticeAPI.ensureApi();
        const res = await window.VerticeAPI.apiFetch(AUTH_PATH, {
          method: "GET",
          headers: { Accept: "application/json" },
        });
        const type = res.headers.get("content-type") || "";
        if (!res.ok || !type.includes("application/json")) {
          throw new Error("Backend Node.js indisponível");
        }
        const data = await res.json();
        if (!data?.ok || !data.csrf) {
          throw new Error(data?.error || "Backend Node.js indisponível");
        }
        setCsrf(data.csrf);
        fillUnits(data.units || []);
        const base = window.VerticeAPI.getBase();
        if (data.adminUrl) {
          adminUrl = data.adminUrl.startsWith("http")
            ? data.adminUrl
            : `${base}${data.adminUrl}`;
        } else {
          adminUrl = `${base}/admin/`;
        }
        backendReady = true;
        setStaticMode(false);
        showFeedback("");
        if (data.user) {
          showFeedback(`Olá, ${data.user.name}. Redirecionando…`, "success");
          window.setTimeout(() => {
            window.location.href = adminUrl;
          }, 400);
        }
        return true;
      } catch (err) {
        backendReady = false;
        setStaticMode(true);
        showFeedback(
          "Backend offline. No PC rode npm run dev e abra http://127.0.0.1:3100/",
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
      showFeedback("Backend Node.js não encontrado nesta URL.", "error");
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
      const res = await window.VerticeAPI.apiFetch(AUTH_PATH, {
        method: "POST",
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
        throw new Error("Resposta inválida do backend Node.js");
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
      const base = window.VerticeAPI.getBase();
      const redirect = data.redirect || "/admin/";
      window.location.href = redirect.startsWith("http")
        ? redirect
        : `${base}${redirect}`;
    } catch (err) {
      showFeedback(
        "Falha ao falar com o Node.js. Use: npm run dev (porta 3100)",
        "error"
      );
      setStaticMode(true);
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
