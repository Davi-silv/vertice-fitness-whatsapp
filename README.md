# Vértice Fitness — Concierge WhatsApp

Landing page premium da **Vértice Fitness** com simulador de atendimento no WhatsApp: planos, horários, agendamento, cadastro e redirecionamento para Maps/WhatsApp.

## Stack

| Ambiente | Front | API do chat |
|----------|-------|-------------|
| Local | HTML / CSS / JS + `index.php` | `api.php` (PHP 8+) |
| Vercel | HTML / CSS / JS estático | `api/chat.js` (serverless Node) |

> A Vercel **não executa PHP**. Por isso o projeto inclui uma API equivalente em JavaScript para o deploy na nuvem.

## Estrutura

```
ashiley/
├── index.html          # Página estática (também usada na Vercel)
├── index.php           # Mesma página com session_start (local PHP)
├── api.php             # Concierge local (sessão PHP + leads em data/)
├── api/
│   └── chat.js         # Concierge serverless (Vercel)
├── assets/
│   ├── app.js          # UI do chat
│   ├── styles.css
│   ├── logo-icon.png
│   └── logo.png
├── data/               # leads.json gerado localmente (gitignored)
├── vercel.json
├── package.json
├── .env.example
└── docs/DEPLOY.md      # GitHub + Vercel passo a passo
```

## Pré-requisitos

- **Local PHP:** PHP 8.0+ (`php -v`)
- **Deploy Vercel:** Conta [Vercel](https://vercel.com) + [GitHub](https://github.com) + Node 18+ (CLI opcional)

## Rodar localmente (PHP)

```bash
cd ashiley
php -S 127.0.0.1:8080 -t .
```

Abra:

- http://127.0.0.1:8080/ — ou `/index.php`
- http://127.0.0.1:8080/index.html — front estático (API ainda via `api.php`)

Cadastros feitos pelo chat são salvos em `data/leads.json`.

## Rodar localmente (Vercel CLI)

```bash
npm i -g vercel
vercel dev
```

A CLI sobe o front e a função `/api/chat`.

## Funcionalidades do chat

1. Pergunta o nome  
2. Menu: planos, serviços, agendamento, horários, cadastro  
3. Como chegar (Google Maps)  
4. Falar com atendente (WhatsApp)  
5. Cadastro de aluno (nome + telefone)

## Variáveis de ambiente

Copie `.env.example` → `.env` (local) ou configure na Vercel:

| Variável | Descrição |
|----------|-----------|
| `WHATSAPP_NUMBER` | Número com DDI, só dígitos (ex.: `5522999315667`) |

## Publicar

Guia completo: **[docs/DEPLOY.md](docs/DEPLOY.md)**

Resumo rápido:

```bash
# 1) GitHub
git init
git add .
git commit -m "feat: landing Vértice Fitness com concierge WhatsApp"
gh repo create vertice-fitness-whatsapp --public --source=. --remote=origin --push

# 2) Vercel (site + Import do repositório)
# Framework Preset: Other | Build: (vazio) | Output: .
```

## Licença

Uso interno / demonstração. Ajuste textos, preços e contatos antes de produção.
