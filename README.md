# Vértice Fitness — Gestão de Academia e Concierge

<div align="center">

### Landing page, atendimento conversacional e painel administrativo em JavaScript

[![Node.js](https://img.shields.io/badge/Node.js-24-5FA04E?logo=nodedotjs&logoColor=white)](https://nodejs.org/)
[![Express](https://img.shields.io/badge/Express-4-000000?logo=express&logoColor=white)](https://expressjs.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/pt-BR/docs/Web/JavaScript)
[![SQLite](https://img.shields.io/badge/Banco-SQLite-003B57?logo=sqlite&logoColor=white)](https://sqlite.org/)

**[Código no GitHub](https://github.com/Davi-silv/vertice-fitness-whatsapp)** · **[Portfólio](https://portifolio-future.vercel.app/)**

</div>

---

## Visão geral

O **Vértice Fitness** reúne uma página de apresentação da academia, um concierge conversacional com integração ao fluxo de WhatsApp e um painel para rotinas administrativas.

O projeto é implementado com **JavaScript, Node.js e Express**, incluindo uma interface web em HTML, CSS e JavaScript e uma API para autenticação, chat e gestão.

## Funcionalidades documentadas

| Módulo | Recursos |
| --- | --- |
| Landing page e chat | Apresentação de planos, informações e concierge |
| Autenticação | Login, cadastro e controle de sessão |
| Dashboard | Indicadores de alunos, faturas, receitas e check-ins |
| Alunos | Cadastro, pesquisa e associação a planos |
| Planos | Modalidades e assinaturas |
| Check-in | Busca e registro de acessos |
| Agenda | Aulas, sessões e reservas |
| Financeiro | Faturas, recebimentos e despesas |
| Professores | Cadastro de equipe e acompanhamento de comissões |

## Tecnologias e arquitetura

- **Backend:** Node.js 24 e Express 4;
- **Sessões e segurança:** bibliotecas de cookies/sessões e `bcryptjs`;
- **Armazenamento:** SQLite na implementação documentada;
- **Frontend:** HTML, CSS e JavaScript;
- **Integração:** chamadas HTTP do frontend para a API Express.

O cliente `assets/api-client.js` verifica a disponibilidade da API, que oferece endpoints como:

| Endpoint | Finalidade |
| --- | --- |
| `GET /api/health` | Verificação da API |
| `/api/auth` | Operações de autenticação e sessão |
| `POST /api/chat` | Fluxo de concierge |
| `/api/admin/*` | Operações de gerenciamento |

A autorização e a persistência devem ser verificadas no ambiente de implantação antes de disponibilizar dados reais.

## Organização do repositório

```text
api/           # Entrada de API para hospedagem
assets/        # Recursos e cliente HTTP do frontend
database/      # Arquivos relacionados aos dados
docs/          # Documentação adicional
public/        # Recursos públicos
scripts/       # Scripts de desenvolvimento
server/        # Servidor e rotinas de backend
index.html     # Landing page
gestao-local.html
```

## Executar localmente

**Pré-requisitos:** Node.js 24 e npm.

```bash
git clone https://github.com/Davi-silv/vertice-fitness-whatsapp.git
cd vertice-fitness-whatsapp
npm install
npm run seed
npm run dev
```

Abra **http://127.0.0.1:3100/**.

| Comando | Descrição |
| --- | --- |
| `npm run seed` | Preparar dados iniciais de desenvolvimento |
| `npm run dev` | Executar o ambiente local |
| `npm start` | Iniciar o servidor |

> **Atenção:** use somente dados e credenciais fictícias em ambientes de demonstração. Não use credenciais de teste em produção.

## Implantação e persistência

O README original descreve implantação na Vercel usando Express por `api/index.js`.

**Limitação importante:** o SQLite em ambientes serverless pode usar armazenamento temporário, como `/tmp`, sem garantia de persistência durável após reinicializações. Para uso real com dados de clientes, é necessário planejar armazenamento persistente e revisar a configuração de sessões.

## Autoria e contato

**Davi Barbosa da Silva** — Desenvolvedor Front-End / Full Stack e fundador da Evolutiva Tech.

[GitHub](https://github.com/Davi-silv) · [Portfólio](https://portifolio-future.vercel.app/) · [LinkedIn](https://www.linkedin.com/in/davi-barbosa-silva/)

---

<sub>Projeto de desenvolvimento web para apresentação técnica. Verifique a configuração do ambiente antes do uso em produção.</sub>
