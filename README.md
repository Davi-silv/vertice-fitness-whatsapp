# Vértice Fitness — Concierge WhatsApp + Gestão

Landing page premium da **Vértice Fitness** com simulador de atendimento no WhatsApp, e painel administrativo em `/admin` (PHP + SQLite) para alunos, financeiro, agenda, estoque e comissões.

## Stack

| Ambiente | Front | API do chat | Gestão `/admin` |
|----------|-------|-------------|-----------------|
| Local | HTML / CSS / JS + `index.php` | `api.php` (PHP 8+) | PHP 8 + PDO + SQLite |
| Vercel | HTML / CSS / JS estático | `api/chat.js` (serverless Node) | **Não suportado** (sem PHP/SQLite persistente) |

> A Vercel **não executa PHP**. Landing e chat sobem na Vercel; o painel de gestão precisa de hosting PHP (local, VPS, cPanel, etc.).

## Estrutura

```
ashiley/
├── index.html / index.php   # Landing
├── api.php / api/chat.js    # Concierge WhatsApp
├── assets/                  # CSS/JS da landing
├── admin/                   # Painel de gestão
│   ├── index.php            # Dashboard
│   ├── login.php / logout.php
│   ├── pages/               # Módulos (alunos, financeiro, …)
│   ├── api/                 # Ações POST / CSV
│   ├── assets/              # CSS/JS do painel
│   └── includes/            # DB, auth, RBAC, layout
├── database/
│   ├── schema.sql
│   └── seed.php
├── data/                    # SQLite + uploads (gitignored)
└── docs/DEPLOY.md
```

## Pré-requisitos

- **Local PHP:** PHP 8.0+ com PDO SQLite (`php -v`)
- **Deploy Vercel:** só a landing + chat Node

## Rodar localmente (landing + admin)

```bash
cd ashiley
php database/seed.php          # cria data/vertice.db e usuário admin
php -S 127.0.0.1:8080 -t .
```

Abra:

- http://127.0.0.1:8080/ — landing
- http://127.0.0.1:8080/admin/login.php — gestão

### Credenciais seed

| Usuário | Senha | Cargo |
|---------|-------|-------|
| `admin@vertice.local` | `Vertice@2026` | Administrador |
| `financeiro@vertice.local` | `Vertice@2026` | Financeiro |
| `recepcao@vertice.local` | `Vertice@2026` | Recepção |
| `professor@vertice.local` | `Vertice@2026` | Professor |

Para recriar o banco demo: `php database/seed.php` (o script limpa e reinsere os dados)

## Módulos do painel

Dashboard, alunos, planos/assinaturas, financeiro (faturas, baixas, despesas), check-in, agenda, professores, estoque, comissões, relatórios CSV/PDF-print, unidades e usuários (RBAC).

## Variáveis de ambiente (landing)

Copie `.env.example` → `.env` (local) ou configure na Vercel:

| Variável | Descrição |
|----------|-----------|
| `WHATSAPP_NUMBER` | Número com DDI, só dígitos (ex.: `5522999315667`) |

## Publicar

- **Landing:** [docs/DEPLOY.md](docs/DEPLOY.md) (GitHub + Vercel)
- **Admin:** hosting PHP apontando a raiz do projeto (Apache/Nginx/`php -S`). O SQLite fica em `data/vertice.db` — garanta permissão de escrita em `data/` e `data/uploads/`.

## Licença

Uso interno / demonstração. Ajuste textos, preços e contatos antes de produção.
