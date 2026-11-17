(() => {
  const chatBody = document.getElementById("chat-body");
  const composer = document.getElementById("composer");
  const input = document.getElementById("user-input");
  const statusText = document.getElementById("status-text");
  const API_URL = "/api/chat";

  let optionsLocked = false;
  let busy = false;

  function nowTime() {
    return new Date().toLocaleTimeString("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function scrollToBottom() {
    requestAnimationFrame(() => {
      chatBody.scrollTop = chatBody.scrollHeight;
    });
  }

  function setStatus(text) {
    statusText.replaceChildren();
    const dot = document.createElement("i");
    dot.className = "status-dot";
    dot.setAttribute("aria-hidden", "true");
    statusText.append(dot, document.createTextNode(` ${text}`));
  }

  function addUserMessage(text) {
    const wrap = document.createElement("div");
    wrap.className = "msg msg--user";
    wrap.innerHTML = `
      <div class="msg__bubble"></div>
      <span class="msg__time"></span>
    `;
    wrap.querySelector(".msg__bubble").textContent = text;
    wrap.querySelector(".msg__time").textContent = nowTime();
    chatBody.appendChild(wrap);
    scrollToBottom();
  }

  function showTyping() {
    const el = document.createElement("div");
    el.className = "msg msg--bot";
    el.dataset.typing = "1";
    el.innerHTML = `<div class="typing" aria-label="Digitando"><span></span><span></span><span></span></div>`;
    chatBody.appendChild(el);
    scrollToBottom();
    return el;
  }

  function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  async function renderBotMessage(msg) {
    setStatus("digitando…");
    const typing = showTyping();
    const delay = Math.min(1600, 450 + String(msg.text || "").length * 12);
    await wait(delay);
    typing.remove();

    const wrap = document.createElement("div");
    wrap.className = "msg msg--bot";

    const bubble = document.createElement("div");
    bubble.className = "msg__bubble";

    if (msg.media) {
      const media = document.createElement("div");
      media.className = "msg__media";
      media.innerHTML = `<img src="assets/logo.png" alt="Vértice Fitness" />`;
      bubble.appendChild(media);
      const slogan = document.createElement("span");
      slogan.className = "msg__slogan";
      slogan.textContent = "SUPERE SEUS LIMITES";
      bubble.appendChild(slogan);
    }

    const body = document.createElement("div");
    body.textContent = msg.text || "";
    bubble.appendChild(body);
    wrap.appendChild(bubble);

    if (Array.isArray(msg.options) && msg.options.length) {
      const list = document.createElement("div");
      list.className = "options";
      msg.options.forEach((opt) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = `option-btn${opt.accent ? " option-btn--accent" : ""}`;
        btn.textContent = opt.label;
        btn.dataset.optionId = opt.id;
        btn.addEventListener("click", () => onOption(opt.id, opt.label, list));
        list.appendChild(btn);
      });
      wrap.appendChild(list);
      optionsLocked = false;
    }

    const time = document.createElement("span");
    time.className = "msg__time";
    time.textContent = nowTime();
    wrap.appendChild(time);

    chatBody.appendChild(wrap);
    setStatus("online · atendimento premium");
    scrollToBottom();
  }

  async function applyResponse(data) {
    const messages = data.messages || [];
    for (const msg of messages) {
      await renderBotMessage(msg);
    }
    if (data.openUrl) {
      window.setTimeout(() => {
        window.open(data.openUrl, "_blank", "noopener,noreferrer");
      }, 600);
    }
    if (data.focus) {
      input.focus();
    }
  }

  async function apiCall(payload) {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
      credentials: "same-origin",
    });
    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.error || "Falha na API");
    }
    return data;
  }

  function lockOptions(container) {
    optionsLocked = true;
    container.querySelectorAll("button").forEach((btn) => {
      btn.disabled = true;
    });
  }

  async function onOption(id, label, container) {
    if (busy || (optionsLocked && container)) return;
    if (container) lockOptions(container);
    if (label) addUserMessage(label);
    busy = true;
    input.disabled = true;
    try {
      const data = await apiCall({ action: "option", id });
      await applyResponse(data);
    } catch (err) {
      await renderBotMessage({
        text: "Desculpe, houve um problema no concierge. Tente novamente em instantes.",
      });
      console.error(err);
    } finally {
      busy = false;
      input.disabled = false;
    }
  }

  async function handleText(raw) {
    const text = raw.trim();
    if (!text || busy) return;

    addUserMessage(text);
    input.value = "";
    busy = true;
    input.disabled = true;

    try {
      const data = await apiCall({ action: "message", text });
      await applyResponse(data);
    } catch (err) {
      await renderBotMessage({
        text: "Desculpe, houve um problema no concierge. Tente novamente em instantes.",
      });
      console.error(err);
    } finally {
      busy = false;
      input.disabled = false;
      input.focus();
    }
  }

  composer.addEventListener("submit", (event) => {
    event.preventDefault();
    handleText(input.value);
  });

  function splitLetters(root = document) {
    root.querySelectorAll("[data-letters]").forEach((el) => {
      if (el.dataset.split === "1") return;
      const text = el.textContent;
      el.textContent = "";
      el.setAttribute("aria-label", text);
      [...text].forEach((ch) => {
        const span = document.createElement("span");
        span.className = ch === " " ? "char char--space" : "char";
        span.textContent = ch === " " ? "\u00A0" : ch;
        span.setAttribute("aria-hidden", "true");
        el.appendChild(span);
      });
      el.dataset.split = "1";
    });
  }

  document.querySelectorAll(".brand__rail > span:not([aria-hidden])").forEach((el) => {
    el.classList.add("letter-fx");
    el.dataset.letters = "";
  });

  splitLetters();

  const stage = document.querySelector("[data-parallax]");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (stage && !reduceMotion) {
    let raf = 0;
    const onMove = (event) => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        stage.style.setProperty("--px", (x * 10).toFixed(2));
        stage.style.setProperty("--py", (y * 8).toFixed(2));
      });
    };
    window.addEventListener("pointermove", onMove, { passive: true });
  }

  async function boot() {
    input.disabled = true;
    try {
      const data = await apiCall({ action: "boot" });
      await applyResponse(data);
    } catch (err) {
      await renderBotMessage({
        text: "Bem-vindo(a) à Vértice Fitness.\n\nPara começarmos, qual é o seu nome?",
        media: true,
      });
      console.error(err);
    }
    input.disabled = false;
    input.focus();
  }

  boot();
})();
