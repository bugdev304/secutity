# Plano — Correção de gaps seguros (2026-07-01)

## Contexto

Análise comparativa entre `docs/giz-seg-001-mfa-2026-06-24/` e a implementação atual
identificou gaps que podem ser corrigidos sem quebrar o contrato de API existente.

## Tasks

### T1 — `MfaVerificationController`: remover `maskIdentifier()` duplicado
Usar `IdentifierMasker::mask()` nos dois pontos do controller onde o método inline é chamado.
Arquivo: `src/Http/Controllers/MfaVerificationController.php`

### T2 — `PasswordController`: adicionar `password_changed_at` na resposta
Ler o `UserState` após a troca e incluir `password_changed_at` no `data` da resposta.
Arquivo: `src/Http/Controllers/PasswordController.php`

### T3 — `OrganizationPolicyResource`: adicionar campo `source`
- `context !== null` → `"policy"` (regra específica por contexto)
- `context === null` → `"inherited"` (regra genérica herdável)
Arquivo: `src/Http/Resources/OrganizationPolicyResource.php`

### T4 — Rota de desbloqueio de conta
Criar `AccountController::unlock()` e rota `POST /accounts/{userId}/unlock`.
O controller resolve o usuário via `config('auth-security.user_model')::findOrFail()`.
Arquivo: `src/Http/Controllers/AccountController.php` + `src/Http/routes.php`

### T5 — `VerifyMfaRequest`: excluir `recovery_code` dos tipos válidos
O `factor_type` só deve aceitar `email`, `sms` e `authenticator_app`.
Recovery codes têm endpoint próprio (`POST /mfa/recovery-codes/verify`).
Arquivo: `src/Http/Requests/VerifyMfaRequest.php`

### T6 — Aplicar throttle nas rotas críticas
Adicionar `throttle:auth-security:*` nos grupos de rotas correspondentes.
Os rate limiters já estão definidos no service provider — falta aplicá-los.
Arquivo: `src/Http/routes.php`

### T7 — `refuse`: aceitar e salvar `reason_text`
- Migration: coluna `refused_reason_text` nullable em `assisted_recoveries`
- Model: adicionar ao `$fillable`
- Service: passar `reason_text` ao `refuse()`
- Controller: ler `reason_text` do body e repassar
- Resource: expor `refused_reason_text`
