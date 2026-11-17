# Deploy — Vértice Fitness (Node.js)

## 1. Local (painel completo)

```bash
npm install
npm run seed
npm run dev
```

- Landing + login: http://127.0.0.1:3100/
- Painel: http://127.0.0.1:3100/admin/
- Usuário teste: `teste@vertice.local` / `Teste@2026`

## 2. GitHub

```bash
git add .
git commit -m "deploy"
git push origin main
```

Repositório: https://github.com/Davi-silv/vertice-fitness-whatsapp

## 3. Vercel (landing + concierge)

A Vercel hospeda a **landing estática** e a function `api/chat.js`.

O painel admin (SQLite + sessão) **não** roda na Vercel — use o Node local.

```bash
vercel --prod
```

Site: https://ashiley.vercel.app

### Rotas na Vercel

| Rota | Comportamento |
|------|----------------|
| `/` | Landing |
| `/api/chat` | Concierge WhatsApp |
| `/admin`, `/api/auth` | Redireciona para `gestao-local.html` (instruções Node) |

## 4. Variáveis

Opcional em `.env` / Vercel:

```
WHATSAPP_NUMBER=5522999315667
SESSION_SECRET=troque-em-producao
```
