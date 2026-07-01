# Roadmap — Correção de gaps seguros

| Task | Descrição | Status |
|------|-----------|--------|
| T1 | `MfaVerificationController`: remover `maskIdentifier()` duplicado | ✅ |
| T2 | `PasswordController`: adicionar `password_changed_at` na resposta | ✅ |
| T3 | `OrganizationPolicyResource`: adicionar campo `source` | ✅ |
| T4 | Rota de desbloqueio de conta (`AccountController` + route) | ✅ |
| T5 | `VerifyMfaRequest`: excluir `recovery_code` dos tipos válidos | ✅ (já correto — `FactorType` não tem case `RecoveryCode`) |
| T6 | Aplicar throttle nas rotas críticas | ✅ |
| T7 | `refuse`: aceitar e salvar `reason_text` (migration + model + service + controller + resource) | ✅ |

## Legenda
| Símbolo | Significado |
|---------|-------------|
| ⬜ | Pendente |
| 🔄 | Em andamento |
| ✅ | Concluído |
