# Deploy — GitHub + Vercel

Este guia sobe o projeto no **GitHub** e publica na **Vercel** com o chat funcionando via função serverless (`api/chat.js`).

---

## 1. Preparar o repositório Git

Na pasta do projeto:

```bash
cd /caminho/para/ashiley

git init
git add .
git status   # confira: não deve aparecer data/leads.json nem .env
git commit -m "feat: landing Vértice Fitness com concierge WhatsApp"
```

Arquivos importantes que **devem** ir para o Git:

- `index.html`, `index.php`, `api.php`
- `api/chat.js`
- `assets/*`
- `vercel.json`, `package.json`, `README.md`, `.gitignore`

---

## 2. Criar o repositório no GitHub e enviar

### Opção A — GitHub CLI (`gh`)

```bash
gh auth login
gh repo create vertice-fitness-whatsapp --public --source=. --remote=origin --push
```

### Opção B — Site do GitHub

1. Em [github.com/new](https://github.com/new), crie um repositório **vazio** (sem README).
2. No terminal:

```bash
git branch -M main
git remote add origin https://github.com/SEU_USUARIO/vertice-fitness-whatsapp.git
git push -u origin main
```

Troque `SEU_USUARIO` pelo seu usuário/organização.

---

## 3. Publicar na Vercel

### Pelo painel (recomendado)

1. Acesse [vercel.com/new](https://vercel.com/new) e faça login com GitHub.
2. **Import** o repositório `vertice-fitness-whatsapp`.
3. Configure o projeto:

| Campo | Valor |
|-------|--------|
| Framework Preset | **Other** |
| Build Command | *(deixe vazio)* |
| Output Directory | *(deixe vazio / `.`)* |
| Install Command | *(deixe vazio)* |
| Root Directory | `.` |

4. Em **Environment Variables**, adicione (opcional):

| Name | Value |
|------|--------|
| `WHATSAPP_NUMBER` | `5522999315667` |

5. Clique em **Deploy**.

Ao terminar, a Vercel mostra uma URL do tipo:

`https://vertice-fitness-whatsapp.vercel.app`

### Pela CLI

```bash
npm i -g vercel
vercel login
vercel          # preview
vercel --prod   # produção
```

Na primeira execução, aceite vincular ao repositório GitHub se perguntar.

---

## 4. Como a Vercel serve este projeto

| Rota | Origem |
|------|--------|
| `/` ou `/index.html` | Arquivo estático |
| `/assets/*` | CSS, JS, logos |
| `/api/chat` | Função serverless Node (`api/chat.js`) |
| `/api.php` | Rewrite → `/api/chat` (ver `vercel.json`) |

O front detecta o host `*.vercel.app` e chama `/api/chat` automaticamente (`assets/app.js`).

### Diferenças vs. local PHP

| Recurso | Local (`api.php`) | Vercel (`api/chat.js`) |
|---------|-------------------|------------------------|
| Sessão do chat | `$_SESSION` | Cookie `HttpOnly` |
| Cadastros (leads) | `data/leads.json` | Log no painel Vercel (sem disco persistente) |
| PHP | Sim | Não |

Para leads em produção na Vercel, integre depois um banco (Supabase, PlanetScale, etc.) ou um webhook (n8n, Make, Sheets).

---

## 5. Checklist pós-deploy

- [ ] Abrir a URL da Vercel e ver a landing (logo + mockup do celular)
- [ ] Digitar o nome no chat e receber o menu
- [ ] Testar **Planos e valores**
- [ ] Testar **Como chegar** (abre Maps)
- [ ] Testar **Falar com atendente** (abre WhatsApp)
- [ ] Confirmar que logos carregam (`/assets/logo-icon.png`, `/assets/logo.png`)

Se o chat falhar com erro de rede:

1. Abra DevTools → Network e veja se `POST /api/chat` retorna 200.
2. Confirme que `api/chat.js` está no repositório e foi publicado.
3. Em Vercel → Project → Deployments → Functions, verifique se `/api/chat` aparece.

---

## 6. Domínio customizado (opcional)

1. Vercel → Project → **Settings** → **Domains**
2. Adicione `www.seudominio.com` (ou apex)
3. Aponte o DNS conforme o painel (A / CNAME)

---

## 7. Atualizar o site depois

```bash
git add .
git commit -m "chore: atualiza copy / ajustes"
git push origin main
```

A Vercel faz **deploy automático** a cada push na branch de produção (em geral `main`).

---

## 8. Problemas comuns

### “404 This page could not be found”
Você provavelmente abriu a URL do portfólio Next.js (ex.: porta 3100), não a da Vercel nem o `php -S` local. Use a URL gerada no deploy.

### Chat não responde no HTML estático local
Com `php -S`, use a pasta do projeto como root para `api.php` existir. Ou rode `vercel dev`.

### Cookie / estado “esquece” o nome
Aceite cookies no navegador; o estado na Vercel vive no cookie `vertice_state`.

### Build falhou na Vercel
Este projeto **não precisa** de build. Framework = Other, Build Command vazio.

---

## 9. Painel de gestão (`/admin`) — hosting PHP

O painel **não roda na Vercel** (não há runtime PHP nem disco SQLite persistente). Use a Vercel só para a landing; hospede `/admin` em PHP 8+ (VPS, cPanel, Railway com PHP, etc.).

### Local / VPS simples

```bash
cd ashiley
php database/seed.php
php -S 0.0.0.0:8080 -t .
# Painel: http://SEU_HOST:8080/admin/login.php
```

### Apache / Nginx

- Document root = pasta do projeto (`ashiley/`)
- PHP 8+ com extensão `pdo_sqlite`
- Diretórios graváveis: `data/` e `data/uploads/`
- Credenciais iniciais: ver `README.md` (seed)

### Segurança em produção

- Troque a senha do `admin@vertice.local` imediatamente
- Restrinja acesso a `/admin` (VPN, IP allowlist ou HTTPS + senha forte)
- Faça backup periódico de `data/vertice.db`
- Não publique `data/vertice.db` no Git (já está no `.gitignore`)
