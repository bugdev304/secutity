# Plano — Bloqueio de conta com backoff escalonado (2026-07-06)

## Contexto

O bloqueio de conta (TEC-04) tinha um único limiar: N tentativas falhas numa janela fixa →
bloqueio definitivo (só desbloqueio administrativo). Pedido do usuário: um esquema em estágios,
no molde do backoff de jobs do Laravel (array de durações, escalando a cada novo estágio), com
bloqueio definitivo só ao esgotar o array, e reset total do contador após um período longo de
inatividade.

Decisão: **substituir totalmente** o esquema anterior (não manter compatibilidade com
`max_attempts`/`window_minutes`) — mudança de contrato aceita explicitamente pelo usuário.

## Tasks

### T1 — Config: `lockout.attempts_per_stage` + `lockout.backoff_minutes` + `lockout.reset_after_minutes`
Substitui `max_attempts`/`window_minutes`/`unlock_strategy` (este último nunca foi lido em
nenhum lugar do código — removido). `backoff_minutes` é env-tuneável via lista separada por
vírgula (`AUTH_SECURITY_LOCKOUT_BACKOFF_MINUTES=1,3` → `explode(',')` + `array_map('intval', ...)`);
vazio/ausente cai no default `[1, 3]`.
Arquivo: `config/auth-security.php`

### T2 — `TemporarilyThrottledException` + `ErrorCode::ACCOUNT_THROTTLED`
Bloqueio de estágio (temporário, expira só) precisa ser distinguível de `AccountLockedException`
(definitivo, só admin) — códigos de erro e status HTTP diferentes (429 vs 423/403), pro front
tratar cada caso de forma diferente (contagem regressiva vs. tela de suporte).
Arquivos: `src/Exceptions/TemporarilyThrottledException.php`, `src/Enums/ErrorCode.php`

### T3 — `LockoutService::recordFailedAttempt()` reescrito
Contador cumulativo (`attemptsKey`, TTL = `reset_after_minutes`, renovado a cada falha — sliding).
A cada `attempts_per_stage` falhas, resolve o estágio (`floor(count/attempts_per_stage) - 1`):
dentro do array `backoff_minutes` → bloqueia temporariamente (`throttleKey`, TTL = duração do
estágio); fora do array → bloqueia definitivamente (fluxo existente, inalterado).
Tentativa durante bloqueio ativo relança a mesma exceção com o mesmo `retry_after`, sem avançar
o contador.
`resetAttempts()` agora limpa as duas chaves de cache (contador + throttle ativo).
Novo método público `throttledUntil()` para inspeção externa.
Arquivo: `src/Services/LockoutService.php`

### T4 — Mapeamento da exceção no ServiceProvider
`TemporarilyThrottledException` → `429 Too Many Requests`, `ACCOUNT_THROTTLED`,
`retry_after_seconds` (mesmo padrão de `OtpResendTooSoonException`).
Arquivo: `src/AuthSecurityServiceProvider.php`

### T5 — Expor `throttled_until` em `GET /mfa/state`
Evita o front precisar reagir ao 429 da tentativa de login em si — mesmo racional do
`account_locked` já exposto ali.
Arquivos: `src/Actions/Mfa/ResolveMfaStateAction.php`, `src/Http/Controllers/MfaStateController.php`

### T6 — Traduções `account_throttled` (en/pt_BR)
Arquivos: `resources/lang/{en,pt_BR}/auth-security.php`

### T7 — Documentação
README (config, fluxo de login, tabelas de código de erro ×2), `.env.example`, `openapi.yaml`
(enum `ErrorCode`, descrição de campos extras), `CHANGELOG.md` (`### Changed`, sinalizado como
breaking change).

## Testes
`LockoutServiceTest` reescrito por completo (17 testes) cobrindo: 1ª falha não bloqueia, estágio 0
bloqueia temporário, tentativa dentro do bloqueio relança o mesmo `retry_after`, estágio 1 com
backoff maior, esgotar os estágios bloqueia definitivo, reset limpa contador + throttle, unlock
administrativo limpa tudo. Suíte completa: 176 testes, 310 assertions, Pint limpo.

Nenhuma mudança foi commitada — o usuário revisa e commita manualmente.
