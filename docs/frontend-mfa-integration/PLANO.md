# Plano — Integração do frontend com `ae3/auth-security`

## Contexto

O backend expõe um fluxo de MFA com dois estados de bloqueio distintos (`MFA_FACTOR_REGISTRATION_REQUIRED`
e `MFA_REQUIRED`), além de outros erros de domínio (conta bloqueada, senha expirada, etc). O frontend
precisa de uma camada única que intercepta esses códigos e decide o que mostrar, sem espalhar `if/else`
de código de erro pelas telas de negócio.

## Objetivo

Definir a arquitetura do lado do frontend para:
1. Interceptar erros HTTP do pacote de forma centralizada.
2. Modelar os estados de autenticação como uma máquina de estados simples.
3. Implementar as telas mínimas necessárias pra cada estado.
4. Persistir e injetar o `X-Mfa-Session-Token` corretamente.

## Material de apoio

[`tutorial-interativo.html`](tutorial-interativo.html) — walkthrough passo a passo (abra direto no
navegador, sem servidor) simulando cada tela do fluxo lado a lado com a chamada HTTP real feita
naquele momento, e o que é responsabilidade do backend vs. do frontend em cada etapa. Útil como
primeira leitura antes de começar as tasks abaixo.

## Contrato de erros (para o interceptor)

Todo erro do pacote retorna `{ message, code, ...extra }`. Tabela completa observada no código:

| `code` | HTTP | Quando | Ação do front |
|---|---|---|---|
| `MFA_FACTOR_REGISTRATION_REQUIRED` | 403 | Usuário sem fator confirmado (conta nova ou pós-recuperação) | Redireciona pra tela de cadastro de fator |
| `MFA_REQUIRED` | 403 | Usuário tem fator, mas sem `X-Mfa-Session-Token` válido nesta sessão | Redireciona pra tela de desafio MFA |
| `ACCOUNT_LOCKED` | 423 | Conta bloqueada por tentativas de login | Tela "conta bloqueada" — oferece recuperação assistida |
| `PASSWORD_EXPIRED` | 403 | Senha expirou (política de expiração) | Redireciona pra troca de senha obrigatória |
| `INVALID_CODE` | 422 | Código OTP/TOTP/recovery errado ou expirado | Mostra erro inline no campo de código |
| `RESEND_RATE_LIMITED` | 429 | Reenvio de OTP solicitado antes do intervalo/limite | Desabilita botão "reenviar" com contagem regressiva (`retry_after_seconds` quando presente) |
| `WEAK_PASSWORD` | 422 | Senha nova não atende à política | Mostra `violations[]` inline no formulário |
| `INVALID_IDENTIFIER` | 422 | `contact_token` inválido/não pertence ao usuário | Erro genérico — não deveria ocorrer em uso normal (indica token adulterado) |
| `LAST_FACTOR_REQUIRED` | 409 | Tentou remover o último fator ativo | Bloqueia remoção, mostra aviso "cadastre outro fator antes" |
| `INVALID_STATUS` | 409 | Ação de recuperação assistida em status incompatível | Recarrega estado da recuperação, mostra "essa solicitação já foi processada" |
| `INVALID_TOKEN` / `TOKEN_EXPIRED` | 422 | Token de recuperação assistida errado/expirado | Pede novo token ao administrador |
| `RESEND_NOT_ALLOWED` | 400 | Reenvio solicitado pra fator TOTP/recovery (não suportado) | Não deveria aparecer na UI — botão de reenvio só existe pra OTP |
| `INVALIDATION_REQUIRED` | 409 | Gerar novos códigos de recuperação quando já existem não usados | Modal de confirmação: "isso invalida os códigos atuais, continuar?" → reenvia com `confirm_invalidation: true` |

## Máquina de estados de autenticação

```
AUTENTICADO (token Sanctum válido)
   │
   ├─ 403 MFA_FACTOR_REGISTRATION_REQUIRED ──► ESTADO: precisa_cadastrar_fator
   │                                                  │
   │                                                  ▼ (após confirmar fator)
   ├─ 403 MFA_REQUIRED ───────────────────────► ESTADO: precisa_verificar_mfa
   │                                                  │
   │                                                  ▼ (após POST /mfa/verify)
   └─ (com X-Mfa-Session-Token válido) ───────► ESTADO: liberado
```

Importante: depois de cadastrar/confirmar um fator (saindo de `precisa_cadastrar_fator`), o usuário
**ainda cai em `precisa_verificar_mfa`** na próxima ação protegida — ter fator não é o mesmo que já ter
passado pelo desafio na sessão atual.

## Armazenamento do `X-Mfa-Session-Token`

**O backend nunca envia isso como header de resposta — só existe no corpo (body) JSON.** O nome
`X-Mfa-Session-Token` é exclusivamente o header que o **front** deve enviar nas requisições
seguintes; o servidor só o lê (nunca o escreve).

- `POST /mfa/verify` e `POST /mfa/recovery-codes/verify`, quando o código é válido, retornam no
  **body**: `{ data: { mfa_session_token, expires_at } }`. **A chave é `mfa_session_token`, não
  `token`** — erro comum de digitar errado ao extrair da resposta.
- Front extrai `data.mfa_session_token` do body e guarda em memória/storage seguro (nunca em
  `localStorage` puro se puder evitar — preferir storage de sessão ou state manager) junto do
  `expires_at`.
- A partir daí, **o front** monta o header `X-Mfa-Session-Token: <valor guardado>` manualmente em
  toda chamada seguinte às rotas do pacote (via interceptor de request — T1), exceto nas próprias
  rotas de cadastro/verificação de fator e login.
- Ao receber `403 MFA_REQUIRED` mesmo com token presente, descarta o token local (expirou/inválido) e
  reinicia o fluxo de desafio.

### Erros comuns que reproduzem "verify deu sucesso mas volta pra tela de código"

Sintoma: `POST /mfa/verify` retorna `200`, mas a próxima requisição protegida volta com `403
MFA_REQUIRED` de novo, como se o verify não tivesse acontecido. Duas causas, nesta ordem de
probabilidade:

1. **Front não está mandando o header na chamada seguinte.** Confirme no DevTools (aba Network):
   - A resposta do `/mfa/verify` realmente tem `data.mfa_session_token` preenchido?
   - A **próxima** requisição tem o header `X-Mfa-Session-Token` com esse mesmo valor?
   Se a extração pegou a chave errada (`data.token`, por exemplo) ou o storage não persistiu antes
   do redirect, o header sai vazio/undefined e a chamada seguinte falha de novo.

2. **Cache driver do backend não persiste entre requisições.** `MfaSessionService` grava o
   token→user_id via `Cache::store(config('auth-security.cache.driver'))` — se isso cair no driver
   `array` (comum em `.env` de dev/teste esquecido), o cache é resetado a cada request e o token
   nunca é encontrado, **mesmo que o front mande o header certinho**. Isso é responsabilidade de
   quem configura a app consumidora, não do front, mas costuma ser confundido com bug de frontend
   — vale descartar essa hipótese antes de sair depurando o cliente HTTP.

3. **Token guardado só em memória (variável JS, estado do Redux/Vuex/Pinia/Context) some num
   reload de página.** Se o usuário digitar a URL na barra do navegador (ou der F5), qualquer
   estado que não esteja em `sessionStorage`/`localStorage`/cookie é perdido — inclusive o
   `mfa_session_token`. Sintoma clássico: a navegação "principal" parece funcionar (porque o app
   refaz o fluxo de desafio do zero), mas partes da UI que disparam requisições próprias em
   paralelo (sidebar, widgets, contadores) voltam com `403 MFA_REQUIRED` e quebram silenciosamente,
   porque essas chamadas saem sem o header que só existia em memória. Ver checklist da T1 abaixo.

4. **Estado de MFA fica "preso" depois que a policy do servidor muda.** O código `MFA_REQUIRED` (ou
   `MFA_FACTOR_REGISTRATION_REQUIRED`) só é emitido no momento em que uma chamada falha; a partir daí
   o front grava esse veredito localmente (T2) e passa a decidir sozinho, sem nunca perguntar de novo,
   se deve continuar bloqueando a navegação. Se a policy do lado do servidor for alterada **depois**
   disso — ex.: um papel/role é incluído e depois removido da lista que exige MFA, ou qualquer outra
   mudança de configuração que dispense o usuário do desafio —, o front nunca fica sabendo: continua
   redirecionando pra tela de MFA indefinidamente, mesmo que o servidor já tenha parado de exigir,
   até um logout/login completo (que reconstrói o estado do zero) ou até o usuário forçar um desafio
   que nem é mais necessário.

   Sintoma: administrador reverte uma configuração de MFA, usuário confirma no backend/logs que a
   policy já não exige mais nada pra ele, mas o app continua jogando pra tela de MFA a cada
   navegação, sem nenhum erro novo no Network — porque nenhuma chamada nova ao servidor está
   acontecendo, o front está decidindo só com o que já tem guardado.

   **Correção**: tratar o estado local de MFA como um *cache* do último veredito do servidor, nunca
   como fonte de verdade permanente. Antes de usar esse estado pra bloquear uma navegação (ou, no
   mínimo, periodicamente / a cada boot da aplicação), revalide contra o backend com uma chamada
   leve e idempotente a qualquer endpoint autenticado que já passe pelo mesmo gate de MFA — não
   precisa ser um endpoint dedicado a isso. Dois desfechos possíveis:
   - Sucesso (sem `MFA_REQUIRED`/`MFA_FACTOR_REGISTRATION_REQUIRED` no corpo): a policy não exige
     mais nada pra esse usuário — descarta o estado local na hora e libera a navegação normal, sem
     exigir logout/login.
   - Falha com o mesmo código de antes: mantém o estado, deixa o interceptor central (T1) tratar como
     já trata hoje.

   Use um TTL curto (10–30s) nessa revalidação, para não transformar toda navegação num round-trip
   extra — o objetivo é não deixar o usuário preso por minutos/horas depois que a policy mudou, não
   garantir consistência em tempo real request a request (o próprio backend já costuma ter um cache
   de policy de poucos minutos, então revalidar mais rápido que isso não traz ganho real).

## Tasks

### T1 — Cliente HTTP + interceptor de erros
Camada única de fetch/axios que:
- Injeta `Authorization: Bearer <token sanctum>` e `X-Mfa-Session-Token` (quando existir).
- Em qualquer resposta de erro do pacote, extrai `{ message, code }` e despacha pro auth state
  machine (T2), nunca deixa o código de tela individual tratar isso.
- **Nenhum componente faz requisição HTTP fora dessa camada — sidebar, widgets, contadores de
  notificação, tudo.** Se algum pedaço da UI usa `fetch`/`axios` direto sem passar pelo client
  compartilhado, ele não recebe o header injetado nem é coberto pelo interceptor — vai quebrar
  silenciosamente na primeira vez que o token expirar ou sumir (ver causa nº3 dos "Erros comuns"
  acima). Auditar isso é parte do critério de pronto desta task, não só do código novo.
- **Debounce na transição de estado**: quando várias requisições em paralelo falham com o mesmo
  código (`MFA_REQUIRED`, por exemplo — comum no carregamento inicial de uma página com múltiplos
  painéis), o interceptor deve disparar a transição pro auth state machine **uma única vez**, não
  uma vez por requisição. Sem isso, várias respostas 403 quase simultâneas competem por redirecionar
  a aplicação, e o resultado é inconsistente (redirect piscando, corrida entre telas).
- **Checklist de validação obrigatório antes de considerar essa task pronta**: fazer login → confirmar
  fator → verificar MFA → chamar QUALQUER rota protegida seguinte e confirmar (via DevTools) que o
  header `X-Mfa-Session-Token` foi enviado com o valor certo. Repetir o teste com **F5 na página**
  (não só navegação client-side) pra garantir que o token sobrevive a um reload. Ver seção "Erros
  comuns" acima.

### T2 — Auth state machine
Estado global (`unauthenticated | needs_factor_registration | needs_mfa_challenge | authenticated | account_locked | password_expired`)
com transições disparadas pelos códigos de erro da tabela acima. Cada tela de negócio só lê o estado
atual — não sabe nada sobre códigos HTTP.

Esse estado é **cache do último veredito do servidor, não fonte de verdade permanente** — a policy que
determina se MFA é exigido pode mudar depois que o estado já foi gravado (ver "Erros comuns" item 4).
Antes de usar o estado gravado pra bloquear uma navegação, revalide contra o backend (TTL curto,
10–30s) e descarte o estado local se a revalidação vier sem o código de erro que o gerou.

### T3 — Tela: cadastro de fator (`needs_factor_registration`)
- `GET /mfa/contacts` → lista de contatos com `contact_token`.
- Formulário: escolher canal (email/sms via contato, ou "aplicativo autenticador").
- OTP: `POST /mfa/factors { type, contact_token, name? }` → tela de confirmação de código.
- TOTP: `POST /mfa/factors { type: "authenticator_app", holder_name, name? }` → mostra QR
  (`qr_code_svg`) e input pra código gerado no app.
- `POST /mfa/factors/{factor_id}/confirm { code }` → sucesso transiciona pra `needs_mfa_challenge`.

### T4 — Tela: desafio MFA (`needs_mfa_challenge`)
- `GET /mfa/factors` → lista de fatores confirmados (permitir escolher se houver mais de um).
- `POST /mfa/factors/{factor_id}/challenge` → dispara OTP ou mostra instrução TOTP.
  - Resposta OTP inclui `expires_at` e `resend_available_at` — usar pra countdown do botão reenviar.
- Botão reenviar: `POST /mfa/factors/{factor_id}/challenge/resend` (só habilitado pra tipos OTP).
- `POST /mfa/verify { factor_id, factor_type, code }` → guarda `mfa_session_token`, transiciona pra
  `authenticated`.
- Link "usar outro fator": `GET /mfa/factors/alternatives?exclude_factor_id=`.
- Link "perdi acesso": leva ao fluxo de recovery codes ou recuperação assistida (T6).

### T5 — Tela: gestão de fatores (área logada, não bloqueante)
- Listar (`GET /mfa/factors`), remover (`DELETE /mfa/factors/{id}` — tratar `LAST_FACTOR_REQUIRED`),
  adicionar novo fator (reusa fluxo de T3 sem o bloqueio de rota).
- Gestão de códigos de recuperação: `GET /mfa/recovery-codes` (metadados), `POST /mfa/recovery-codes`
  (gerar nova leva — tratar `INVALIDATION_REQUIRED` com confirmação).

### T6 — Fluxo de recuperação (perda de acesso ao fator)
- Opção A — código de recuperação: `POST /mfa/recovery-codes/verify { code }` → mesmo retorno de sessão
  do `/mfa/verify`. **Atenção**: consumir um código força `must_register_factor=true` no próximo
  acesso — front deve já esperar cair em `needs_factor_registration` depois.
- Opção B — recuperação assistida (depende de um admin): `POST /mfa/assisted-recoveries` (usuário
  solicita) → aguarda liberação → `POST /mfa/assisted-recoveries/complete { token }` (usuário completa
  com token recebido fora da banda) → também força re-registro de fator.

### T7 — Tela: conta bloqueada / senha expirada
- `ACCOUNT_LOCKED`: tela informativa + CTA pra abrir recuperação assistida (T6, Opção B) — não há
  autodesbloqueio pelo próprio usuário.
- `PASSWORD_EXPIRED`: formulário de troca obrigatória — `POST /password { password, new_password,
  new_password_confirmation }`. Resposta inclui `password_changed_at`.

### T8 — Testes E2E dos fluxos críticos
- Cadastro completo de fator (OTP e TOTP) até liberar acesso.
- Desafio MFA com reenvio e expiração de código.
- Recuperação via código, validando que força re-registro depois.
- Conta bloqueada → recuperação assistida → conclusão → re-registro de fator.
