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

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) {
      closeLogin();
    }
  });

  if (overlay.classList.contains("is-open")) {
    setTab(overlay.dataset.tab || "login");
  }
})();
