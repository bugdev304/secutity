# ae3/auth-security

> Pacote Laravel reutilizável para verificação em duas etapas (MFA), política de senha forte e bloqueio de conta por tentativas.
>
> ⚠️ **Esqueleto inicial** — esta pasta foi criada durante o planejamento e contém apenas a infraestrutura mínima para receber o desenvolvimento. O código do pacote ainda **não foi implementado** — será feito por uma sessão dedicada (Claude Sonnet) seguindo o backlog em `..\giz-seg-001-mfa-2026-06-24\TAREFAS-DESENVOLVIMENTO.md`.

## Status

| Componente | Estado |
|---|---|
| `composer.json` | ⬜ A criar (Fase 1) |
| `src/` (código PHP) | ⬜ A criar (Fases 2-12) |
| `database/migrations/` | ⬜ A criar (Fase 2) |
| `config/auth-security.php` | ⬜ A criar (Fase 1) |
| `resources/lang/` | ⬜ A criar (Fase 12) |
| `tests/` (PHPUnit) | ⬜ A criar (Fase 13) |
| `tests/Fixtures/` (fixtures dos contratos) | ⬜ A criar (Fase 1.9) |
| `docs/postman/` | 🟡 **Esqueleto presente** (este planejamento) |
| README completo | ⬜ A escrever (Fase 14) |
| CHANGELOG | ⬜ A iniciar (Fase 14) |

## Para o desenvolvedor que vai implementar

1. Leia o material de planejamento em `..\giz-seg-001-mfa-2026-06-24\`:
   - `CONTINUIDADE.md` — contexto completo de produto e técnico
   - `TAREFAS-DESENVOLVIMENTO.md` — backlog em 14 fases
   - `PROMPT-SONNET.md` — prompt de partida da sessão
   - `01-SEG-001-protecao-dados/` — feature da GIZ que consome o pacote (RFs/RNs/CAs/HTs)

2. Os arquivos de Postman em `docs/postman/` são esqueletos — ajuste-os conforme implementa as rotas, e ao final preencha os exemplos de resposta reais (Fases 12 e 14).

3. O ponto de entrada da implementação é a **Fase 1 (Setup do pacote)** — siga `laravel-package-development` skill.

## Estrutura prevista (após implementação completa)

```
ae3-auth-security/
├── composer.json
├── README.md
├── CHANGELOG.md
├── LICENSE
├── phpunit.xml
├── pint.json
├── src/
│   ├── AuthSecurityServiceProvider.php
│   ├── Concerns/HasAuthSecurity.php
│   ├── Contracts/
│   │   ├── MfaTenantResolver.php
│   │   ├── MfaRoleResolver.php
│   │   ├── MfaContextResolver.php
│   │   ├── MfaMessageSender.php
│   │   └── MfaAuditLogger.php
│   ├── Enums/
│   ├── Models/
│   ├── Actions/
│   ├── Services/
│   ├── Http/Controllers/
│   ├── Http/Middleware/
│   ├── Http/Requests/
│   ├── Http/Resources/
│   ├── Listeners/
│   ├── Events/
│   └── Exceptions/
├── database/migrations/
├── config/auth-security.php
├── resources/lang/{en,pt-BR}/auth-security.php
├── routes/api.php
├── tests/
│   ├── Fixtures/
│   ├── Unit/
│   └── Feature/
└── docs/
    └── postman/
        ├── README.md                                  ← presente (esqueleto)
        ├── auth-security.postman_collection.json      ← presente (esqueleto)
        └── auth-security.postman_environment.json     ← presente (esqueleto)
```
