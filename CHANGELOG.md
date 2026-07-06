# Changelog

Todas as mudanças relevantes do pacote `ae3/auth-security` são documentadas aqui.
Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

---

## [Unreleased]

### Added

#### Infraestrutura (Fase 1–3)
- `composer.json` com dependências: `pragmarx/google2fa-qrcode ^4.0`, `bacon/bacon-qr-code ^3.1`, `spatie/laravel-data ^4.23`
- `AuthSecurityServiceProvider` com auto-discovery, `mergeConfigFrom`, `publishesMigrations`
- `config/auth-security.php` com todas as chaves documentadas
- `AuthSecurityModel` base (schema-aware para PostgreSQL, compatível com SQLite em testes)
- Trait `HasAuthSecurity` para o modelo de usuário da app consumidora
- Facade `AuthSecurity`
- 5 contratos: `MfaTenantResolver`, `MfaRoleResolver`, `MfaContextResolver`, `MfaMessageSender`, `MfaAuditLogger`
- Interface `TenantIdentity`

#### Migrations (Fase 2)
- `auth_security.user_states` — estado derivado do usuário (lockout, password_changed_at, must_register_factor)
- `auth_security.factors` — fatores MFA (email, sms, authenticator_app) com `confirmed_at` (null = pendente)
- `auth_security.recovery_codes` — códigos de recuperação com hash bcrypt e geração em lote
- `auth_security.password_history` — histórico de senhas para evitar reutilização
- `auth_security.assisted_recoveries` — ciclo de vida de recuperação assistida (Requested → Released → Completed/Refused)
- `auth_security.organization_policies` — políticas MFA por tenant/role/contexto com índices parciais PostgreSQL

#### Models & Enums (Fase 3–4)
- Models: `UserState`, `Factor` (scope `confirmed()`), `RecoveryCode`, `PasswordHistory`, `AssistedRecovery`, `OrganizationPolicy`
- Observers registrados via ServiceProvider: `FactorObserver`, `RecoveryCodeObserver`, `OrganizationPolicyObserver` (invalida cache), `AssistedRecoveryObserver`, `PasswordHistoryObserver`, `UserStateObserver`
- Enums backed string: `FactorType`, `AssistedRecoveryReason`, `AssistedRecoveryStatus`

#### Services (Fase 5–8)
- `OtpService` — geração e verificação de OTP (cache, fixed-window, tentativas, reenvio)
- `TotpService` — geração de secret, QR code SVG inline, verificação TOTP via Google2FA
- `RecoveryCodeService` — geração (10 códigos, hash bcrypt), verificação com `last_used_at`, must_register_factor
- `PasswordPolicyService` — validação (comprimento, classes, histórico), registro no histórico, verificação de expiração
- `LockoutService` — registro de falha, bloqueio automático, desbloqueio com auditoria
- `AssistedRecoveryService` — ciclo completo: request / release (token ephemeral) / complete (TEC-11) / refuse
- `FloorPolicyService` — piso de política (papéis que sempre exigem MFA)
- `MfaSessionService` — token de sessão MFA stateless via cache (header `X-Mfa-Session-Token`, TTL 8h)

#### Domain Actions (Fase 9–11)
- Factor: `EnrollOtpFactorAction`, `EnrollTotpFactorAction`, `ConfirmFactorEnrollmentAction`, `RemoveFactorAction`, `ListUserFactorsAction`
- Password: `ChangePasswordAction`
- AssistedRecovery: `RequestAssistedRecoveryAction`, `ReleaseAssistedRecoveryAction`, `CompleteAssistedRecoveryAction`, `RefuseAssistedRecoveryAction`
- Policy: `GetEffectivePolicyAction` (precedência: floor → contexto específico → genérico → false), `UpsertOrganizationPolicyAction`
- `ResolveMfaRequirementAction`

#### Events & Listeners (Fase 11)
- 6 eventos: `MfaFactorEnrolled`, `MfaFactorRemoved`, `RecoveryCodesGenerated`, `OtpFailureExceeded`, `AssistedRecoveryExecuted`, `PolicyConfigurationAttemptedBelowFloor`
- `DispatchAuditLogListener` — subscribe em todos os eventos, delega ao `MfaAuditLogger` (só registrado se o contrato está bound)

#### HTTP Layer (Fase 12)
- 6 controllers: `FactorController`, `MfaVerificationController`, `RecoveryCodeController`, `AssistedRecoveryController`, `OrganizationPolicyController`, `PasswordController`
- 9 FormRequests com validação completa
- 4 Resources JSON com envelope `{ data, meta }`: `FactorResource` (identificador mascarado), `RecoveryCodeMetaResource` (metadados apenas), `AssistedRecoveryResource`, `OrganizationPolicyResource`
- 4 middlewares: `EnsureAccountNotLocked`, `EnsurePasswordNotExpired`, `EnsureMfaCompleted`, `EnsureMustRegisterFactorCompleted`
- Arquivo de rotas `src/Http/routes.php` com todos os endpoints
- 4 rate limiters registrados via ServiceProvider
- Aliases de middleware registrados via ServiceProvider
- Exception rendering centralizado no ServiceProvider (match de exceção → código/status HTTP)
- `AuthSecurityServiceProvider::routes()` — método estático para registrar rotas no `routes/api.php` da app
- Arquivos de língua `en` e `pt_BR` com todos os códigos de erro

#### Testes (Fase 13)
- 80 testes unitários/serviços (Services suite) — 102 assertions
- 38 testes de integração HTTP (Feature suite) — 99 assertions
- Total: 118 testes, 201 assertions
- `DatabaseTestCase` + `FeatureTestCase` bases com SQLite in-memory e fake contracts
- Suporte de test: `TestUser`, `FakeMfaMessageSender`, `FakeMfaAuditLogger`, fakes de resolvers

### Fixed

- `AuthSecurityServiceProvider::routes()` deriva o grupo de middleware stateful (`web`/`api`) do driver real do guard (`config('auth.guards.{guard}.driver')`) em vez de sempre fixar `api` — guards de sessão (ex.: `web`) agora recebem `StartSession` corretamente
- `factors`: constraint `unique(user_id, type, identifier)` — impede cadastrar o mesmo contato (e-mail/telefone) duas vezes como fator; `EnrollOtpFactorAction` valida isso antes do insert e lança `DuplicateFactorException` (`DUPLICATE_FACTOR`, 409). Múltiplos contatos distintos (vários e-mails/telefones) continuam suportados normalmente — cada um é um fator próprio

### Security

- Códigos de recuperação armazenados com `Hash::make()` (bcrypt irreversível)
- Token de recuperação assistida: hash bcrypt no banco, plain text entregue 1x ao admin
- Segredos TOTP: cast `encrypted` do Eloquent (criptografado com `APP_KEY`)
- Identificadores de fatores: nunca sincronizados com o perfil do usuário após cadastro (RN-SEG15/16)
- Códigos OTP: nunca logados
- Rate limiting em todos os endpoints sensíveis

---

## Convenção de versões

Este pacote segue [Semantic Versioning](https://semver.org/lang/pt-BR/).
A versão `1.0.0` será tagueada quando a app consumidora homologar o pacote em staging.
