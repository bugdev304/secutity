# Roadmap — Bloqueio de conta com backoff escalonado (2026-07-06)

| Task | Descrição | Status |
|------|-----------|--------|
| T1 | Config: `attempts_per_stage` + `backoff_minutes` + `reset_after_minutes` (substitui `max_attempts`/`window_minutes`/`unlock_strategy`) | ✅ |
| T2 | `TemporarilyThrottledException` + `ErrorCode::ACCOUNT_THROTTLED` | ✅ |
| T3 | `LockoutService::recordFailedAttempt()` reescrito com estágios de backoff | ✅ |
| T4 | Mapeamento da exceção → 429/`ACCOUNT_THROTTLED`/`retry_after_seconds` | ✅ |
| T5 | Expor `throttled_until` em `GET /mfa/state` | ✅ |
| T6 | Traduções `account_throttled` (en/pt_BR) | ✅ |
| T7 | Documentação (README, `.env.example`, `openapi.yaml`, `CHANGELOG.md`) | ✅ |

## Legenda
| Símbolo | Significado |
|---------|-------------|
| ⬜ | Pendente |
| 🔄 | Em andamento |
| ✅ | Concluído |
