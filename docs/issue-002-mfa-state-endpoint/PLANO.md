# Plano — GET /mfa/state (issue #2)

## Problema

O frontend descobre o estado de autenticação reagindo a erros HTTP 403 espalhados
(`MFA_FACTOR_REGISTRATION_REQUIRED`, `MFA_REQUIRED`, `PASSWORD_EXPIRED`). Não existe
endpoint que informe o estado de uma vez.

## Solução

`GET /mfa/state` — autenticado, mas **sem** exigir `X-Mfa-Session-Token` (não passa pelo
middleware `auth-security.mfa`), devolvendo tudo que o front precisa numa única chamada.

## Reaproveitamento (sem duplicar lógica)

- `EnsureMfaCompleted::isMfaRequired()` é privada e duplicaria lógica se reescrita — extrair
  para `Services/MfaRequirementResolver`, injetado tanto pelo middleware quanto pela action nova.
- `mfa_satisfied` — mesma checagem de `MfaSessionService::getUserId()` que o middleware já faz.
- `password_expired` — `PasswordPolicyService::isExpired()`.
- `account_locked` — `LockoutService::isLocked()`.
- `must_register_factor` — `HasAuthSecurity::mustRegisterFactor()` (já existe no trait).
- `factors` — `ListUserFactorsAction::execute()` + `FactorResource` (já existe).
- `contacts` — mapeamento de `MfaContactController::index()` extraído pra `MfaContactResource`
  (JsonResource), reusado nos dois lugares.

## Tasks

### [ ] T1 — Extrair `MfaRequirementResolver` de `EnsureMfaCompleted`
Novo `src/Services/MfaRequirementResolver.php` com `isRequiredFor(Authenticatable $user, ?string $context): bool`.
Middleware passa a injetar e delegar, sem duplicar a lógica.

### [ ] T2 — Criar `MfaContactResource`
`src/Http/Resources/MfaContactResource.php` — extrai o array de `MfaContactController::index()`.

### [ ] T3 — Criar `ResolveMfaStateAction`
`src/Actions/Mfa/ResolveMfaStateAction.php` — monta o array de estado completo.

### [ ] T4 — Criar `MfaStateController` + rota
`GET /mfa/state`, fora do middleware `auth-security.mfa` (senão o próprio discovery
tomaria 403 antes de informar que precisa de MFA).

### [ ] T5 — Testes
Cobrir os 5 estados combinacionais principais: tudo ok, precisa cadastrar fator,
precisa verificar MFA, senha expirada, conta bloqueada.
