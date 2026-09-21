# Roteiro do projeto — Landing Page SOU + BLU

Este documento explica como o projeto funciona de ponta a ponta: o que cada
arquivo faz, como o cadastro de parceiro percorre o sistema, como configurar,
rodar e publicar.

> Observação: o `.htaccess` bloqueia o download de arquivos `.md`, então este
> roteiro não fica acessível pelo navegador quando publicado.

---

## 1. Visão geral

Landing page institucional do Grupo BLU / SOU + BLU. O objetivo é captar
**parceiros** (correspondentes/promotoras) por um formulário. Depois do
cadastro, a equipe interna revisa, aprova, e o sistema cria automaticamente o
acesso do parceiro na plataforma **Sou+Blu** e envia o login por e-mail.

Stack:

| Camada       | Tecnologia                                             |
| ------------ | ------------------------------------------------------ |
| Front-end    | HTML + CSS + JavaScript puro (sem framework/build)     |
| Back-end     | PHP 8 (endpoints em `api/`), pensado para a Locaweb    |
| Banco        | MySQL (via PDO)                                        |
| Integrações  | FonteData (consulta de CNPJ) e API REST da Sou+Blu     |
| Hospedagem   | Locaweb / cPanel (Apache, por isso o `.htaccess`)      |

---

## 2. Estrutura de arquivos

```
landingPageBlu/
├── index.html              Página pública (hero, soluções, FAQ, contato, formulário)
├── script.js               Comportamento da página + envio do formulário
├── css/style.css           Estilos
├── imagens/                Logos e favicon
├── termos.html             Termos e Condições de Uso
├── politicas.html          Políticas
├── politica-privacidade.html  Política de Privacidade
│
├── api/
│   ├── parceiros.php       Endpoint principal (POST público, GET/PATCH admin)
│   ├── consulta-cnpj.php   Consulta o CNPJ na FonteData (server-side)
│   └── lib/
│       ├── soublu.php      Cria usuário + parceiro na API da Sou+Blu
│       └── mailer.php      Envia o e-mail de aprovação
│
├── adminLP/
│   └── parceiros-admin.html  Painel interno (kanban dos cadastros)
│
├── config.php              Helpers: .env, conexão PDO, JSON, checagem de admin
├── schema.sql              Criação da tabela `parceiros`
├── .env / .env.example     Segredos e configuração (o .env NÃO é versionado)
├── .htaccess               Bloqueia acesso direto a arquivos sensíveis
├── .gitignore              Ignora .env, php-local.ini, node_modules
├── php-local.ini           php.ini só para rodar local (não versionar)
└── .prettierrc.json        Formatação do código
```

---

## 3. Front-end (página pública)

### `index.html`
Seções, na ordem: **hero** com carrossel (`#inicio`), faixa de números,
barra de instituições parceiras, serviço, **soluções** (`#solucoes`),
**como funciona** (`#como-funciona`), **dúvidas** (`#duvidas`, acordeão),
**segurança** (`#seguranca`), **contato** (`#contato`) e o formulário
**Seja parceiro** (`#seja-parceiro`).

### `script.js`
Blocos independentes, cada um só roda se os elementos existirem:

1. **`CONFIG`** (topo do arquivo) — único lugar a editar para trocar o
   número de WhatsApp (`whatsapp`, só dígitos com DDI+DDD) e a URL de vídeo
   institucional (`videoUrl`, apenas `youtube.com/embed/ID`).
2. **Carrossel do hero** — troca de slide a cada 6 s; pausa com mouse/foco,
   aba oculta ou botão "Pausar"; respeita `prefers-reduced-motion`.
3. **Menu mobile** — botão hambúrguer, fecha com `Esc` ou ao clicar num link.
4. **WhatsApp** — se o número em `CONFIG` for válido, mostra o botão de
   WhatsApp e esconde o telefone.
5. **Vídeo** — só aceita URL `https` de embed do YouTube (validação por
   hostname e caminho) e substitui a marca da seção "Sobre".
6. **Máscaras** — CPF/CNPJ (muda sozinho conforme o tamanho) e telefones.
7. **Formulário de parceiro** — ver seção 5.
8. **Animações de scroll** — `IntersectionObserver`; sem JS o conteúdo já
   aparece; não esconde o que já está visível.
9. **Rodapé legal** — no mobile o texto fica recolhido ("Ler mais").

Acessibilidade está presente em todo o código: `aria-*`, `inert` nos slides
inativos, foco tratado, `prefers-reduced-motion`.

---

## 4. Back-end (`api/` e `config.php`)

### `config.php`
- `env($key, $default)` — lê o `.env` uma única vez (linhas `CHAVE=valor`,
  ignora comentários).
- `db()` — conexão PDO/MySQL única (`utf8mb4`, exceções ligadas).
- `jsonInput()` / `jsonResponse()` — lê o corpo JSON e responde JSON.
- `requireAdmin()` — exige o header `X-Admin-Token` igual a `ADMIN_TOKEN`,
  comparado com `hash_equals` (resistente a timing attack). Sem token
  configurado, ninguém entra.

### `api/parceiros.php`
| Método  | Acesso  | O que faz                                                        |
| ------- | ------- | ---------------------------------------------------------------- |
| `POST`  | Público | Grava um novo cadastro com status `pendente`.                    |
| `GET`   | Admin   | Lista todos os cadastros (mais novos primeiro) + `loginUrl`.     |
| `PATCH` | Admin   | Muda o status (`pendente`/`aprovado`/`recusado`) ou reenvia e-mail (`action: "resend-email"`). |

Ao mudar para **`aprovado`**, chama `syncParceiroToSoublu()`:
- Se já existe `soublu_user_id`, não recria (idempotente).
- Se falha, grava o motivo em `soublu_error` e **a aprovação continua valendo**
  — o erro aparece no cartão do painel para ajuste manual.

Todas as consultas usam *prepared statements* (sem SQL injection).

### `api/consulta-cnpj.php`
Só `POST`. Valida 14 dígitos, chama a FonteData com a chave do `.env`
(nunca vai ao navegador), grava o resultado JSON na própria linha do parceiro
(`fontedata_result`, `fontedata_consultado_em`) e, opcionalmente, envia a um
webhook interno (`INTERNAL_NOTIFY_WEBHOOK_URL`) ou registra no log de erros do
PHP. **A resposta HTTP nunca contém os dados da consulta** — é sempre
`{"ok": true}`.

### `api/lib/soublu.php`
`soubluCreateParceiro()` replica o fluxo do sistema principal:
1. Normaliza o e-mail e verifica se já existe usuário com ele (se sim, aborta
   com mensagem clara).
2. Gera ID, matrícula (`F` + 5 dígitos) e **senha temporária de 10 caracteres**
   (sem caracteres ambíguos como `0/O`, `1/l`), com `random_int`.
3. Cria o **usuário** (`role: parceiro`) na tabela `users`.
4. Monta razão social e endereço a partir do resultado da FonteData (se houver)
   e cria o registro em `partners`.
5. Dispara o e-mail de aprovação.

### `api/lib/mailer.php`
`sendApprovalEmail()` usa o `mail()` nativo do PHP (funciona na Locaweb sem
SMTP). Assunto codificado em UTF-8, remetente vem de `MAIL_FROM`. Nunca lança
exceção — falha de envio vai para o log e a aprovação segue.

---

## 5. Fluxo completo do cadastro

```
Visitante                Navegador (script.js)        Servidor (PHP)              Externo
   │                            │                          │                        │
   │ preenche formulário ──────►│ valida obrigatórios      │                        │
   │                            │ + aceite dos termos      │                        │
   │                            │ POST /api/parceiros.php ►│ INSERT (pendente)      │
   │                            │◄──────── 201 ok ─────────│                        │
   │                            │ se CNPJ (14 dígitos):    │                        │
   │                            │ POST /api/consulta-cnpj ►│ ── consulta ──────────►│ FonteData
   │                            │                          │◄─ dados ───────────────│
   │                            │                          │ UPDATE fontedata_result│
   │◄ "Cadastro salvo!" ────────│                          │                        │

Equipe interna (adminLP/parceiros-admin.html)
   │ informa ADMIN_TOKEN ─► GET /api/parceiros.php ─► vê cartões por status
   │ move cartão para "Aprovado" ─► PATCH status=aprovado
   │                                   ├─► cria user + partner na Sou+Blu ──► API Sou+Blu
   │                                   └─► e-mail com login + senha temporária ─► parceiro
```

Pontos importantes:
- O site **só confirma o cadastro depois que o banco gravou** (timeout de 15 s;
  em erro, os dados permanecem no formulário).
- A consulta de CNPJ é *fire-and-forget* (`keepalive`): se falhar, não afeta o
  cadastro.
- Campos obrigatórios: nome, CPF/CNPJ, telefone comercial, e-mail comercial e
  aceite dos termos. Os demais são opcionais.

---

## 6. Painel administrativo (`adminLP/parceiros-admin.html`)

- Pede o `ADMIN_TOKEN` e o guarda em `sessionStorage` (some ao fechar a aba).
- Mostra os cadastros como cartões por status; ao abrir um cartão, exibe os
  dados do formulário, o resultado da FonteData (razão social, sócios,
  endereços, telefones, e-mails) e o bloco **Acesso Sou+Blu**.
- Mover para **Aprovado** cria o acesso na Sou+Blu; o painel mostra login e
  senha temporária, e há botão **Reenviar e-mail**.
- Erros de sincronização aparecem no cartão com o motivo.

> Atenção: a senha temporária fica armazenada em texto puro na coluna
> `soublu_temp_password` (necessário para o "reenviar e-mail"). Considere
> limpá-la depois do primeiro acesso.

---

## 7. Banco de dados (`schema.sql`)

Tabela única `parceiros`:

- **Cadastro:** `id`, `created_at`, `name`, `document`, `commercial_phone`,
  `whatsapp_phone`, `commercial_email`, `financial_email`, `city`,
  `contact_time`, `works_commercial`, `has_address`, `credit_experience`,
  `message`.
- **Workflow:** `status` (padrão `pendente`), `status_updated_at`.
- **FonteData:** `fontedata_result` (JSON em texto), `fontedata_consultado_em`.
- **Sou+Blu:** `soublu_user_id`, `soublu_temp_password`, `soublu_synced_at`,
  `soublu_error`.

Rode o arquivo **uma vez** no phpMyAdmin (`CREATE TABLE IF NOT EXISTS`, então é
seguro repetir).

---

## 8. Configuração (`.env`)

Copie `.env.example` para `.env` e preencha:

| Variável                      | Para quê                                                    |
| ----------------------------- | ----------------------------------------------------------- |
| `FONTEDATA_API_KEY`           | Chave da FonteData. Vazia = consulta de CNPJ é ignorada.    |
| `INTERNAL_NOTIFY_WEBHOOK_URL` | (Opcional) webhook Slack/Discord/Make/Zapier.               |
| `DB_HOST` `DB_NAME` `DB_USER` `DB_PASS` | Banco MySQL criado no cPanel.                     |
| `ADMIN_TOKEN`                 | "Senha" do painel. Use um valor longo e aleatório.          |
| `SOUBLU_API_BASE_URL` `SOUBLU_API_KEY` | API REST da Sou+Blu (criação automática do acesso).|
| `MAIL_FROM`                   | Remetente do e-mail (use um endereço do seu domínio).       |
| `SOUBLU_LOGIN_URL`            | Link de login que vai no e-mail.                            |

---

## 9. Segurança — o que já está feito

- Chaves e senhas só no `.env` (fora do Git, bloqueado pelo `.htaccess`).
- `.htaccess` nega `.env*`, `.git*`, `config.php`, `.sql`, `.ini`, `.md`,
  `.log`, `.bak`. Os `require` do PHP leem do disco e não são afetados.
- `GET`/`PATCH` protegidos por token comparado com `hash_equals`.
- SQL sempre parametrizado.
- A chave da FonteData nunca chega ao navegador, e os dados da consulta não
  voltam na resposta HTTP.
- Vídeo incorporado só aceita YouTube via `https`.

### Pontos de atenção (sugestões)
1. **`.env` versionado?** Confirme com `git ls-files .env` que ele não está no
   repositório (o `.gitignore` o ignora, mas só vale se nunca foi commitado).
2. **`POST /api/parceiros.php` é público** e não tem limite de requisições nem
   captcha — vale um rate limit ou reCAPTCHA/Turnstile contra spam.
3. **`consulta-cnpj.php` é público** e gasta créditos da FonteData por
   chamada; qualquer um pode chamá-lo com CNPJs aleatórios. Considere exigir
   que o `id` exista e ainda não tenha sido consultado.
4. **`id` vem do cliente** (`Date.now()`), então pode colidir ou ser forjado.
   Melhor gerar no servidor.
5. **Senha temporária em texto puro** no banco e no painel (ver seção 6).
6. **`parceiros-admin.html` está em `/adminLP/`**: o caminho é previsível.
   O acesso real é protegido pelo token, mas pode-se adicionar proteção por
   senha do servidor (`.htpasswd`) nessa pasta.

---

## 10. Como rodar localmente

Pré-requisito: PHP 8.3 (o `php-local.ini` já habilita `curl`, `mbstring`,
`openssl`, `pdo_mysql`, `mysqli` — ajuste `extension_dir` para o seu PC).

```powershell
cd C:\BLU\lp2\landingPageBlu
copy .env.example .env      # e preencha (ao menos DB_* e ADMIN_TOKEN)
php -c php-local.ini -S localhost:8000
```

- Site: <http://localhost:8000/>
- Painel: <http://localhost:8000/adminLP/parceiros-admin.html>

Precisa de um MySQL local com o `schema.sql` aplicado. Note que o servidor
embutido do PHP **ignora o `.htaccess`**, então localmente os arquivos
sensíveis ficam acessíveis — isso só vale para desenvolvimento.

Sem `FONTEDATA_API_KEY`, o cadastro funciona normalmente e a consulta de CNPJ
é apenas ignorada. Sem `SOUBLU_*`, a aprovação funciona, mas o acesso não é
criado (o erro fica em `soublu_error`).

---

## 11. Como publicar (Locaweb / cPanel)

1. No cPanel, crie o banco MySQL e um usuário com todas as permissões.
2. No phpMyAdmin, execute o `schema.sql`.
3. Envie os arquivos para a pasta pública do domínio (**sem** `php-local.ini`
   e sem `.git`, se possível).
4. Crie o `.env` no servidor com os valores reais.
5. Teste: envie um cadastro pelo site → abra o painel → aprove → confira o
   e-mail e o registro na Sou+Blu.
6. Em caso de problema, consulte o **Error Log** (Locaweb > Estatísticas).

---

## 12. Tarefas comuns

| Quero…                              | Onde mexer                                              |
| ----------------------------------- | ------------------------------------------------------- |
| Trocar o WhatsApp                   | `script.js` → `CONFIG.whatsapp`                         |
| Colocar vídeo institucional         | `script.js` → `CONFIG.videoUrl`                         |
| Alterar textos/seções               | `index.html`                                            |
| Alterar visual                      | `css/style.css`                                         |
| Adicionar campo no formulário       | `index.html` + `script.js` (objeto `record`) + `schema.sql` (coluna) + `api/parceiros.php` (INSERT e `recordFromRow`) |
| Mudar o texto do e-mail             | `api/lib/mailer.php`                                    |
| Mudar dados enviados à Sou+Blu      | `api/lib/soublu.php` (`$userPayload`, `$partnerPayload`)|
| Trocar a senha do painel            | `ADMIN_TOKEN` no `.env`                                 |
