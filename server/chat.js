/**
 * Concierge WhatsApp — lógica portada para Express.
 */

const WHATSAPP_NUMBER = process.env.WHATSAPP_NUMBER || "5522999315667";
const MAPS_URL =
  "https://www.google.com/maps/search/?api=1&query=Vertice+Fitness+Academia+de+Luxo+Av.+Atlantica+1900+Copacabana+Rio+de+Janeiro";

const plans = [
  {
    id: "black",
    title: "Black",
    price: "R$ 449/mês",
    detail: "Musculação · bike indoor · aulas coletivas · app Vértice · avaliação inicial",
  },
  {
    id: "gold",
    title: "Gold",
    price: "R$ 789/mês",
    detail: "Tudo do Black + personal 4x/mês · spa recovery · nutricionista · bike reservada",
  },
  {
    id: "platinum",
    title: "Platinum",
    price: "R$ 1.290/mês",
    detail: "Tudo do Gold + personal ilimitado · concierge · lounge VIP · prioridade no cycling",
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

function handleBoot(s) {
  Object.assign(s, defaultState());
  return {
    messages: [
      {
        text: "Bem-vindo(a) à Vértice Fitness.\n\nPara começarmos, qual é o seu nome?",
        media: true,
      },
    ],
    focus: true,
  };
}

function menuMessages(name) {
  return [
    {
      text: `Perfeito, ${name}. Sou o concierge Vértice. Como posso ajudar?`,
      options: mainMenu,
    },
  ];
}

function handleText(text, s) {
  if (s.step === "askName") {
    s.userName = text.slice(0, 40);
    s.step = "menu";
    return { messages: menuMessages(s.userName) };
  }
  if (s.step === "cadastroName") {
    s.draftStudent.name = text.slice(0, 60);
    s.step = "cadastroPhone";
    return {
      messages: [{ text: "Ótimo. Agora informe um WhatsApp com DDD (somente números):" }],
      focus: true,
    };
  }
  if (s.step === "cadastroPhone") {
    const phone = text.replace(/\D/g, "");
    if (phone.length < 10) {
      return {
        messages: [{ text: "Número inválido. Digite o WhatsApp com DDD, por favor." }],
        focus: true,
      };
    }
    s.draftStudent.phone = phone;
    s.step = "menu";
    const wa = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(
      `Olá! Quero finalizar meu cadastro na Vértice.\nNome: ${s.draftStudent.name}\nWhatsApp: ${phone}`
    )}`;
    return {
      messages: [
        {
          text: `Cadastro prévio registrado para *${s.draftStudent.name}*.\n\nUm consultor confirma a matrícula no WhatsApp.`,
          options: [
            { id: "atendente", label: "Continuar no WhatsApp", accent: true },
            { id: "menu", label: "Voltar ao menu" },
          ],
        },
      ],
      openUrl: wa,
    };
  }
  return {
    messages: [
      {
        text: "Posso ajudar com planos, horários, agendamento ou cadastro. Escolha uma opção:",
        options: mainMenu,
      },
    ],
  };
}

function handleOption(id, s) {
  if (id === "menu") {
    s.step = "menu";
    return { messages: menuMessages(s.userName || "atleta") };
  }
  if (id === "planos") {
    return {
      messages: [
        {
          text: "Nossos planos VIP:",
          cards: plans.map((p) => ({
            title: `${p.title} — ${p.price}`,
            body: p.detail,
          })),
        },
        {
          text: "Quer agendar uma visita ou falar com um consultor?",
          options: [
            { id: "agendar", label: "Agendar treino/visita" },
            { id: "atendente", label: "Falar no WhatsApp", accent: true },
            { id: "menu", label: "Menu" },
          ],
        },
      ],
    };
  }
  if (id === "servicos") {
    return {
      messages: [
        {
          text: "Serviços da unidade Copacabana:",
          cards: services.map((x) => ({ title: x.title, body: x.price })),
        },
        { text: "Mais alguma coisa?", options: mainMenu },
      ],
    };
  }
  if (id === "horarios") {
    return {
      messages: [
        {
          text:
            "Horários Vértice Copacabana:\n\n• Seg–Sex: 05:30–23:00\n• Sábado: 07:00–20:00\n• Domingo: 08:00–14:00",
          options: mainMenu,
        },
      ],
    };
  }
  if (id === "agendar") {
    return {
      messages: [
        {
          text: "Escolha um horário disponível:",
          options: [
            ...scheduleSlots.map((x) => ({ id: `slot_${x.id}`, label: x.label })),
            { id: "menu", label: "Menu" },
          ],
        },
      ],
    };
  }
  if (id.startsWith("slot_")) {
    const slot = scheduleSlots.find((x) => `slot_${x.id}` === id);
    return {
      messages: [
        {
          text: `Reserva prévia: *${slot?.label || "horário"}*.\n\nConfirmamos no WhatsApp em instantes.`,
          options: [
            { id: "atendente", label: "Confirmar no WhatsApp", accent: true },
            { id: "menu", label: "Menu" },
          ],
        },
      ],
    };
  }
  if (id === "cadastro") {
    s.step = "cadastroName";
    s.draftStudent = { name: "", phone: "" };
    return {
      messages: [{ text: "Vamos iniciar seu cadastro. Qual o nome completo do aluno?" }],
      focus: true,
    };
  }
  if (id === "maps") {
    return {
      messages: [
        {
          text: "Unidade Copacabana — Av. Atlântica, 1900 · Torre Vértice, 3º andar.",
          options: [
            { id: "menu", label: "Menu" },
            { id: "atendente", label: "WhatsApp", accent: true },
          ],
        },
      ],
      openUrl: MAPS_URL,
    };
  }
  if (id === "atendente") {
    const wa = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(
      `Olá! Vim pelo concierge Vértice.${s.userName ? ` Meu nome é ${s.userName}.` : ""}`
    )}`;
    return {
      messages: [
        {
          text: "Abrindo WhatsApp com um atendente Vértice…",
          options: [{ id: "menu", label: "Voltar ao menu" }],
        },
      ],
      openUrl: wa,
    };
  }
  return handleText("", s);
}

function getChatState(req) {
  if (!req.session.chat) req.session.chat = defaultState();
  return req.session.chat;
}

function chatHandler(req, res) {
  const s = getChatState(req);
  const action = String(req.body?.action || "");
  try {
    let result;
    if (action === "boot") result = handleBoot(s);
    else if (action === "option") {
      const id = String(req.body?.id || "").trim();
      if (!id) throw new Error("Opção inválida");
      result = handleOption(id, s);
    } else if (action === "message") {
      const text = String(req.body?.text || "").trim();
      if (!text) throw new Error("Mensagem vazia");
      result = handleText(text, s);
    } else {
      throw new Error("Ação inválida");
    }
    res.json(result);
  } catch (err) {
    res.status(400).json({ error: err.message || "Erro" });
  }
}

module.exports = { chatHandler };
