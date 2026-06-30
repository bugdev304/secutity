# Roadmap — Assisted Recovery: atualização do user_state

## Status geral

| Task | Descrição | Status |
|------|-----------|--------|
| T1 | Migration: `recovery_refused_at` em `user_state` | ✅ Concluído |
| T2 | Model `UserState`: expor nova coluna | ✅ Concluído |
| T3 | Service `complete()`: desbloquear conta | ✅ Concluído |
| T4 | Service `refuse()`: registrar `recovery_refused_at` | ✅ Concluído |
| T5 | Concern `HasAuthSecurity`: helper `lastRecoveryRefusedAt()` | ✅ Concluído |
| T6 | Testes: cobrir T3 e T4 | ✅ Concluído |
| T7 | Bugfix `controller store()`: `UserState::find` → buscar usuário | ✅ Concluído |
| T8 | Revogar tokens Sanctum ao completar ou recusar recovery | ✅ Concluído |

---

## Dependências

```
T1
└── T2
    ├── T3
    ├── T4
    └── T5
        └── T6

T7 (independente)
```

---

## Progresso

### ✅ T1 — Migration: `recovery_refused_at`

- **Arquivo:** `database/migrations/2026_06_30_000001_add_recovery_refused_at_to_user_state_table.php`
- **O que faz:** Adiciona coluna `recovery_refused_at TIMESTAMP NULL` na tabela `user_state`

---

### ✅ T2 — Model `UserState`

- **Arquivo:** `src/Models/UserState.php`
- **O que faz:** Adiciona `recovery_refused_at` em `$fillable` e cast `datetime`
- **Depende de:** T1

---

### ✅ T3 — Service `complete()`: desbloquear conta

- **Arquivo:** `src/Services/AssistedRecoveryService.php`
- **O que faz:** No `updateOrCreate` do UserState, além de `must_register_factor = true`, seta `account_locked_at = null`, `account_unlocked_at = now()`, `account_unlocked_by_user_id = null`
- **Depende de:** T2

---

### ✅ T4 — Service `refuse()`: registrar recusa

- **Arquivo:** `src/Services/AssistedRecoveryService.php`
- **O que faz:** Após atualizar `assisted_recoveries`, chama `UserState::updateOrCreate(['user_id' => ...], ['recovery_refused_at' => now()])`
- **Depende de:** T2

---

### ✅ T5 — Concern `HasAuthSecurity`: helper

- **Arquivo:** `src/Concerns/HasAuthSecurity.php`
- **O que faz:** Adiciona `lastRecoveryRefusedAt(): ?\Illuminate\Support\Carbon`
- **Depende de:** T2

---

### ✅ T6 — Testes

- **Arquivo:** `tests/Services/AssistedRecoveryServiceTest.php`
- **O que faz:**
  - `test_complete_unlocks_account` — verifica `account_locked_at = null` e `account_unlocked_at != null`
  - `test_refuse_sets_recovery_refused_at_on_user_state` — verifica `recovery_refused_at` preenchido
- **Depende de:** T3, T4

---

### ✅ T7 — Bugfix `controller store()`

- **Arquivo:** `src/Http/Controllers/AssistedRecoveryController.php`
- **O que faz:** Substitui `UserState::find(target_user_id)` por `$userModel::find(target_user_id)`, corrigindo busca por PK errada
- **Independente**

---

## Legenda

| Símbolo | Significado |
|---------|-------------|
| ⬜ | Pendente |
| 🔄 | Em andamento |
| ✅ | Concluído |
