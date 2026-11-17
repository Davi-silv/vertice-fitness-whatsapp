(() => {
  const overlay = document.getElementById("login-panel");
  if (!overlay) return;

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
  const forms = overlay.querySelectorAll("form.login-sheet__form");

  /** Hosting estático (Vercel etc.) não executa PHP — POST gera HTTP 405. */
  function isStaticAuthHost() {
    const hasCsrf = Boolean(overlay.querySelector('input[name="_csrf"]'));
    if (hasCsrf) return false;
    const host = location.hostname || "";
    if (/vercel\.app$/i.test(host) || /github\.io$/i.test(host)) return true;
    if (!location.port && !/\.php$/i.test(location.pathname || "")) return true;
    return !hasCsrf;
  }

  function showStaticHint() {
    if (!hint) return;
    hint.hidden = false;
    hint.scrollIntoView({ behavior: "smooth", block: "nearest" });
    hint.classList.add("is-alert");
  }

  function focusActiveForm() {
    const active = overlay.querySelector(".login-sheet__form.is-active input");
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

  function openLogin(tab) {
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("login-open");
    if (isStaticAuthHost()) showStaticHint();
    if (tab) setTab(tab);
    else setTab(overlay.dataset.tab || "login");
  }

  function closeLogin() {
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.classList.remove("login-open");
  }

  openers.forEach((btn) => btn.addEventListener("click", () => openLogin("login")));
  closers.forEach((btn) => btn.addEventListener("click", closeLogin));
  tabButtons.forEach((btn) => {
    btn.addEventListener("click", () => setTab(btn.getAttribute("data-auth-tab") || "login"));
  });

  forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!isStaticAuthHost()) return;
      event.preventDefault();
      event.stopPropagation();
      showStaticHint();
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) {
      closeLogin();
    }
  });

  if (isStaticAuthHost() && hint) {
    hint.hidden = false;
  }

  if (overlay.classList.contains("is-open")) {
    setTab(overlay.dataset.tab || "login");
  }
})();
