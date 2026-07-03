# Plano — Catálogo estável de códigos de erro (issue #7)

## Problema

Os códigos de erro (`MFA_REQUIRED`, `INVALID_CODE`, `WEAK_PASSWORD`, etc.) viviam
espalhados como strings soltas em dois middlewares, três controllers e o `match`
de `AuthSecurityServiceProvider::resolveExceptionDetails()`. Não havia uma fonte
única — um código podia mudar em um lugar e ficar divergente do restante sem
nenhum erro de compilação avisando.

## Decisão

Criar `Ae3\AuthSecurity\Enums\ErrorCode` (enum backed string) com todos os
códigos hoje emitidos pelo pacote, e usá-lo como fonte da verdade em todo lugar
que hoje literal string:

- `AuthSecurityServiceProvider::resolveExceptionDetails()` — retorna
  `ErrorCode` em vez de string solta.
- Middlewares (`EnsureMfaCompleted`, `EnsureMustRegisterFactorCompleted`,
  `EnsureAccountNotLocked`, `EnsurePasswordNotExpired`) e controllers
  (`MfaVerificationController::resend()`, `RecoveryCodeController::store()`) —
  usam `ErrorCode::X->value` em vez da string literal.

Isso segue o padrão já estabelecido no pacote para domínio fechado (ver
`FactorType`, `AssistedRecoveryReason`): PHP falha em tempo de desenvolvimento
(autocomplete/typo) em vez de produzir um código incorreto silenciosamente em
runtime.

O enum já está espelhado em `openapi.yaml` (`components/schemas/ErrorCode`,
issue #3) — a partir de agora ambos devem ser mantidos em sincronia
manualmente (não há geração automática de OpenAPI a partir de enums PHP neste
pacote).

## Tasks

Ver `ROADMAP.md`.
