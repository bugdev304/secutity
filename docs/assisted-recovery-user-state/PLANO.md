# Fix: Assisted Recovery não atualiza user_state

## Problema

Quando uma recuperação assistida é **completada** ou **recusada**, a tabela `user_state` não é atualizada corretamente:

- `complete()` — seta `must_register_factor = true` mas não desbloqueia a conta (`account_locked_at` permanece preenchido)
- `refuse()` — não toca em `user_state` em nada
- **Bonus bug**: `AssistedRecoveryController::store()` usa `UserState::find(target_user_id)` buscando pela PK da tabela `user_state`, não pelo `user_id` do usuário

## Comportamento esperado

| Ação | user_state |
|------|-----------|
| `complete` | `must_register_factor = true`, `account_locked_at = null`, `account_unlocked_at = now()`, `account_unlocked_by_user_id = null` |
| `refuse` | `recovery_refused_at = now()` |

---

## Tasks

### [ ] Task 1 — Migration: adicionar `recovery_refused_at` em `user_state`

**Arquivo:** `database/migrations/2026_06_30_000001_add_recovery_refused_at_to_user_state_table.php`

Adicionar coluna `recovery_refused_at TIMESTAMP NULL` após `must_register_factor`.

---

### [ ] Task 2 — Model `UserState`: expor `recovery_refused_at`

**Arquivo:** `src/Models/UserState.php`

- Adicionar `recovery_refused_at` em `$fillable`
- Adicionar cast `'recovery_refused_at' => 'datetime'`

---

### [ ] Task 3 — `AssistedRecoveryService::complete()`: desbloquear conta

**Arquivo:** `src/Services/AssistedRecoveryService.php`

```php
UserState::updateOrCreate(
    ['user_id' => $recovery->target_user_id],
    [
        'must_register_factor'        => true,
        'account_locked_at'           => null,
        'account_unlocked_at'         => now(),
        'account_unlocked_by_user_id' => null,
    ],
);
```

---

### [ ] Task 4 — `AssistedRecoveryService::refuse()`: registrar recusa

**Arquivo:** `src/Services/AssistedRecoveryService.php`

```php
UserState::updateOrCreate(
    ['user_id' => $recovery->target_user_id],
    ['recovery_refused_at' => now()],
);
```

---

### [ ] Task 5 — `HasAuthSecurity`: helper `lastRecoveryRefusedAt()`

**Arquivo:** `src/Concerns/HasAuthSecurity.php`

```php
public function lastRecoveryRefusedAt(): ?\Illuminate\Support\Carbon
{
    return $this->authSecurityState?->recovery_refused_at;
}
```

---

### [ ] Task 6 — Testes

**Arquivo:** `tests/Services/AssistedRecoveryServiceTest.php`

- `test_complete_unlocks_account` — verifica `account_locked_at = null` e `account_unlocked_at != null`
- `test_refuse_sets_recovery_refused_at_on_user_state` — verifica `recovery_refused_at` preenchido

---

### [ ] Task 7 — Bugfix `AssistedRecoveryController::store()`

**Arquivo:** `src/Http/Controllers/AssistedRecoveryController.php`

```php
// De:
$targetUser = UserState::find($request->input('target_user_id')) ?? $request->user();

// Para:
$userModel = config('auth-security.user_model');
$targetUser = $userModel::find($request->input('target_user_id')) ?? $request->user();
```

---

## Ordem de execução

```
Task 1 → Task 2 → Task 3 → Task 4 → Task 5 → Task 6
Task 7 (independente, pode rodar a qualquer momento)
```
