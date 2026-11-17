/**
 * Concierge Vértice Fitness — serverless (Vercel).
 * Espelha a lógica de api.php; estado guardado em cookie HttpOnly.
 */

const WHATSAPP_NUMBER = process.env.WHATSAPP_NUMBER || "5522999315667";
const MAPS_URL =
  "https://www.google.com/maps/search/?api=1&query=Vertice+Fitness+Academia+de+Luxo+Av.+Atlantica+1900+Copacabana+Rio+de+Janeiro";
const UNIT_ADDRESS =
  "Av. Atlântica, 1900 — Copacabana, Rio de Janeiro · Torre Vértice, 3º andar";
const COOKIE_NAME = "vertice_state";

const plans = [
  {
    id: "black",
    title: "Black",
    price: "R$ 449/mês",
    detail:
      "Musculação · bike indoor · aulas coletivas · app Vértice · avaliação inicial",
  },
  {
    id: "gold",
    title: "Gold",
    price: "R$ 789/mês",
    detail:
      "Tudo do Black + personal 4x/mês · spa recovery · nutricionista · bike reservada",
  },
  {
    id: "platinum",
    title: "Platinum",
    price: "R$ 1.290/mês",
    detail:
      "Tudo do Gold + personal ilimitado · concierge · lounge VIP · prioridade no cycling",
  },
];

const services = [
  { title: "Musculação (piso premium Technogym)", price: "incluso nos planos" },
  { title: "Bike indoor / cycling studio", price: "incluso nos planos · avulsa R$ 95" },
  { title: "Functional & HIIT VIP", price: "incluso nos planos · avulsa R$ 110" },
  { title: "Personal Training privado", price: "a partir de R$ 280/sessão" },
  { title: "Studio Pilates Reformer", price: "R$ 190/aula" },
  { title: "Spa & Recovery (crio + massagem)", price: "R$ 320/sessão" },
  { title: "Assessoria nutricional premium", price: "R$ 650/mês" },
  { title: "Day Pass convidado", price: "R$ 180/dia" },
];

const scheduleSlots = [
  { id: "musculacao", label: "Amanhã · 07:00 — Musculação (orientação)" },
  { id: "bike", label: "Amanhã · 08:30 — Bike indoor" },
  { id: "tarde", label: "Amanhã · 18:30 — Functional VIP" },
  { id: "noite", label: "Quinta · 20:00 — Pilates Reformer" },
];

const mainMenu = [
  { id: "planos", label: "Planos e valores" },
  { id: "servicos", label: "Serviços da unidade" },
  { id: "agendar", label: "Agendar treino" },
  { id: "horarios", label: "Horários de funcionamento" },
  { id: "cadastro", label: "Cadastro de aluno" },
  { id: "maps", label: "Como chegar (Google Maps)", accent: true },
  { id: "atendente", label: "Falar com atendente (WhatsApp)", accent: true },
];

function defaultState() {
  return {
    step: "askName",
    userName: "",
    draftStudent: { name: "", phone: "" },
  };
}

function parseCookies(header = "") {
  return Object.fromEntries(
    header
      .split(";")
      .map((p) => p.trim())
      .filter(Boolean)
      .map((p) => {
        const i = p.indexOf("=");
        return i === -1 ? [p, ""] : [p.slice(0, i), decodeURIComponent(p.slice(i + 1))];
      })
  );
}

function readState(req) {
  try {
    const cookies = parseCookies(req.headers.cookie || "");
    if (!cookies[COOKIE_NAME]) return defaultState();
    const parsed = JSON.parse(Buffer.from(cookies[COOKIE_NAME], "base64url").toString("utf8"));
    if (!parsed || typeof parsed !== "object") return defaultState();
    return {
      step: parsed.step || "askName",
      userName: parsed.userName || "",
      draftStudent: {
        name: parsed.draftStudent?.name || "",
        phone: parsed.draftStudent?.phone || "",
      },
    };
  } catch {
    return defaultState();
  }
}

function stateCookie(state) {
  const value = Buffer.from(JSON.stringify(state), "utf8").toString("base64url");
  const secure = process.env.VERCEL ? "; Secure" : "";
  return `${COOKIE_NAME}=${value}; Path=/; HttpOnly; SameSite=Lax; Max-Age=86400${secure}`;
}

function reply(text, extra = {}) {
  return { text, ...extra };
}

function greetingName(s) {
  return s.userName ? `Olá, ${s.userName}` : "Olá";
}

function scheduleOptions() {
  return [
    ...scheduleSlots.map((s) => ({ id: s.id, label: s.label })),
    { id: "menu", label: "Voltar ao menu" },
  ];
}

function showMainMenu(s, intro) {
  s.step = "menu";
  return reply(
    intro || `${greetingName(s)}, como posso ajudar hoje?\nEscolha uma opção abaixo:`,
    { options: mainMenu }
  );
}

function handleBoot(s) {
  Object.assign(s, defaultState());
  return {
    messages: [
      reply(
        "Bem-vindo(a) à Vértice Fitness.\n\nPara começarmos, qual é o seu nome?",
        { media: true }
      ),
    ],
    step: s.step,
  };
}

function handleOption(id, s) {
  const messages = [];

  if (id === "planos") {
    const lines = plans
      .map((p) => `• ${p.title} — ${p.price}\n  ${p.detail}`)
      .join("\n\n");
    messages.push(
      reply(
        `Nossos planos Vértice Fitness:\n\n${lines}\n\nTaxa de adesão: R$ 190 (isentas no Platinum).`
      )
    );
    messages.push(showMainMenu(s, "Deseja ver outra opção?"));
    return { messages, step: s.step };
  }

  if (id === "servicos") {
    const lines = services.map((svc) => `• ${svc.title} — ${svc.price}`).join("\n");
    messages.push(
      reply(
        `Serviços da unidade Vértice Fitness:\n\n${lines}\n\nAmbientes climatizados, toalhas premium e vestiários com amenities.`
      )
    );
    messages.push(showMainMenu(s, "Posso ajudar com mais alguma coisa?"));
    return { messages, step: s.step };
  }

  if (id === "horarios") {
    messages.push(
      reply(
        "Horários Vértice Fitness Copacabana:\n\n• Seg a Sex — 05:30 às 23:00\n• Sábado — 07:00 às 20:00\n• Domingo e feriados — 08:00 às 14:00\n\nLounge VIP e spa seguem o mesmo expediente, com última entrada 45 min antes do fechamento."
      )
    );
    messages.push(showMainMenu(s, "Quer agendar um horário ou ver planos?"));
    return { messages, step: s.step };
  }

  if (id === "agendar") {
    s.step = "schedule";
    messages.push(
      reply("Perfeito. Qual horário prefere para o treino?", {
        options: scheduleOptions(),
      })
    );
    return { messages, step: s.step };
  }

  if (id === "cadastro") {
    s.step = "register";
    s.draftStudent = { name: s.userName || "", phone: "" };
    const text = s.draftStudent.name
      ? `Vamos cadastrar você, ${s.draftStudent.name}.\nConfirme ou digite o nome completo para a ficha:`
      : "Vamos iniciar seu cadastro.\nDigite o nome completo do aluno:";
    messages.push(reply(text));
    return { messages, step: s.step, focus: true };
  }

  if (id === "maps") {
    messages.push(
      reply(
        `Como chegar na Vértice Fitness:\n\n${UNIT_ADDRESS}\n\nEstacionamento valet no térreo. Vou abrir o Google Maps para você.`
      )
    );
    messages.push(showMainMenu(s, "Mapa aberto. Precisa de mais alguma coisa?"));
    return { messages, step: s.step, openUrl: MAPS_URL };
  }

  if (id === "atendente") {
    const name = s.userName || "interessado(a)";
    const msg = encodeURIComponent(
      `Olá, Vértice Fitness! Sou ${name} e gostaria de falar com um atendente.`
    );
    const url = `https://wa.me/${WHATSAPP_NUMBER}?text=${msg}`;
    messages.push(
      reply(
        "Claro. Vou te conectar com nossa equipe no WhatsApp agora.\nHorário de atendimento humano: 08:00 às 21:00."
      )
    );
    messages.push(showMainMenu(s, "Atendente acionado. Posso ajudar com mais algo por aqui?"));
    return { messages, step: s.step, openUrl: url };
  }

  if (id === "menu") {
    messages.push(showMainMenu(s));
    return { messages, step: s.step };
  }

  const slot = scheduleSlots.find((item) => item.id === id);
  if (slot) {
    const aluno = s.userName || "convidado";
    messages.push(
      reply(
        `Treino reservado com sucesso.\n\n${slot.label}\nAluno: ${aluno}\n\nVocê receberá a confirmação por WhatsApp. Chegue 10 min antes para o check-in biométrico.`
      )
    );
    messages.push(showMainMenu(s, "Agendamento concluído. O que mais deseja?"));
    return { messages, step: s.step };
  }

  messages.push(showMainMenu(s, "Não entendi. Escolha uma opção abaixo:"));
  return { messages, step: s.step };
}

function handleText(text, s) {
  const messages = [];

  if (s.step === "askName") {
    const first = text.split(/\s+/)[0] || text;
    let clean = first.replace(/[^\p{L}\p{N}-]/gu, "") || text.slice(0, 24);
    s.userName = clean.charAt(0).toUpperCase() + clean.slice(1);
    messages.push(
      reply(
        `${greetingName(s)}! Bem-vindo(a) à Vértice Fitness.\nAqui você consulta planos, agenda treinos, cadastra-se e fala com nosso concierge.`
      )
    );
    messages.push(showMainMenu(s));
    return { messages, step: s.step };
  }

  if (s.step === "register") {
    s.draftStudent.name = text;
    s.step = "registerPhone";
    messages.push(
      reply(
        `Obrigado, ${s.draftStudent.name}.\nAgora digite um WhatsApp com DDD (somente números):`
      )
    );
    return { messages, step: s.step };
  }

  if (s.step === "registerPhone") {
    const digits = text.replace(/\D/g, "");
    if (digits.length < 10) {
      messages.push(
        reply("Não reconheci o número. Envie com DDD, por exemplo: 21999998888")
      );
      return { messages, step: s.step };
    }
    s.draftStudent.phone = digits;
    console.log("[lead]", {
      name: s.draftStudent.name,
      phone: digits,
      at: new Date().toISOString(),
    });
    const phoneDisplay = digits.startsWith("55") ? digits : `55${digits}`;
    messages.push(
      reply(
        `Cadastro registrado com sucesso.\n\nAluno: ${s.draftStudent.name}\nWhatsApp: +${phoneDisplay}\nStatus: lista de espera / visita guiada\n\nNossa equipe entrará em contato em até 1 hora útil.`
      )
    );
    messages.push(showMainMenu(s, "Cadastro pronto. Deseja explorar planos ou agendar?"));
    return { messages, step: s.step };
  }

  if (["menu", "schedule", "idle"].includes(s.step)) {
    const lower = text.toLowerCase();
    if (/plano|valor|preço|preco/.test(lower)) return handleOption("planos", s);
    if (/serviço|servico|personal|spa|musculação|musculacao|bike|cycling/.test(lower)) {
      return handleOption("servicos", s);
    }
    if (/agenda|treino|marcar/.test(lower)) {
      s.step = "schedule";
      messages.push(
        reply("Claro. Escolha um horário:", { options: scheduleOptions() })
      );
      return { messages, step: s.step };
    }
    if (/hora|funcionamento|abre|fecha/.test(lower)) return handleOption("horarios", s);
    if (/mapa|chegar|endereço|endereco|local/.test(lower)) return handleOption("maps", s);
    if (/atendente|humano|falar|whatsapp/.test(lower)) return handleOption("atendente", s);
    if (/cadastro|matricula|aluno/.test(lower)) {
      s.step = "register";
      s.draftStudent = { name: s.userName || "", phone: "" };
      messages.push(
        reply(
          s.draftStudent.name
            ? `Vamos cadastrar você, ${s.draftStudent.name}. Digite o nome completo:`
            : "Digite o nome completo do aluno:"
        )
      );
      return { messages, step: s.step };
    }
    messages.push(
      showMainMenu(
        s,
        "Não entendi completamente. Use as opções abaixo para navegar com facilidade:"
      )
    );
    return { messages, step: s.step };
  }

  messages.push(showMainMenu(s));
  return { messages, step: s.step };
}

function json(res, status, body, cookie) {
  const headers = {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
  };
  if (cookie) headers["Set-Cookie"] = cookie;
  res.statusCode = status;
  Object.entries(headers).forEach(([k, v]) => res.setHeader(k, v));
  res.end(JSON.stringify(body));
}

module.exports = async function handler(req, res) {
  if (req.method === "OPTIONS") {
    res.statusCode = 204;
    res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type");
    res.end();
    return;
  }

  if (req.method !== "POST") {
    return json(res, 405, { error: "Método não permitido" });
  }

  let payload = req.body;
  if (typeof payload === "string") {
    try {
      payload = JSON.parse(payload || "{}");
    } catch {
      return json(res, 400, { error: "JSON inválido" });
    }
  }
  if (!payload || typeof payload !== "object") {
    payload = {};
  }

  const s = readState(req);
  const action = String(payload.action || "");

  try {
    let result;
    if (action === "boot") {
      result = handleBoot(s);
    } else if (action === "option") {
      const id = String(payload.id || "").trim();
      if (!id) throw new Error("Opção inválida");
      result = handleOption(id, s);
    } else if (action === "message") {
      const text = String(payload.text || "").trim();
      if (!text) throw new Error("Mensagem vazia");
      result = handleText(text, s);
    } else {
      throw new Error("Ação inválida");
    }
    return json(res, 200, result, stateCookie(s));
  } catch (err) {
    return json(res, 400, { error: err.message || "Erro" });
  }
};
