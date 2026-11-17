# Vértice Fitness — Node.js

Landing VIP + concierge WhatsApp + painel de gestão da academia, em **JavaScript / Node.js** (Express + SQLite).

## Seções do sistema

| Módulo | O que faz |
|--------|-----------|
| Landing + Chat | Concierge (planos, horários, agendamento, cadastro, WhatsApp) |
| Login / Cadastro | Autenticação via `/api/auth` → sessão → `/admin/` |
| Dashboard | KPIs: alunos, faturas, receita, check-ins |
| Alunos | Cadastro, busca e vínculo de plano |
| Planos | Planos Black/Gold/Platinum e assinaturas |
| Check-in | Busca e registro de acesso |
| Agenda | Sessões de aula e reservas |
| Financeiro | Faturas, recebimentos e despesas |
| Professores | Equipe e comissões |

## Subir local

```bash
cd ashiley
npm install
npm run seed
npm run dev
```

Abra: **http://127.0.0.1:3100/**

### Credenciais

| Usuário | Senha | Cargo |
|---------|-------|-------|
| `teste@vertice.local` | `Teste@2026` | Administrador (teste) |
| `admin@vertice.local` | `Vertice@2026` | Administrador |

## Stack

- Node.js 24 · Express · express-session · better-sqlite3 · bcryptjs
- Front: HTML/CSS/JS (landing + admin SPA)
- Sem PHP — stack 100% JavaScript/Node.js

## API principal

- `GET/POST /api/auth` — sessão, login, registro, logout
- `POST /api/chat` — concierge WhatsApp
- `GET/POST /api/admin/*` — dashboard, alunos, planos, check-in, agenda, financeiro, professores

## Vercel

A landing estática + `/api/chat` serverless (`api/chat.js`) continuam para demo do concierge.  
O **painel completo** (login + SQLite) roda no Node local (`npm run dev`).
