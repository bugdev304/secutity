# Fix: Revogar tokens ao trocar senha

## Problema

`ChangePasswordAction::execute()` troca a senha e atualiza `user_state.password_changed_at`,
mas não revoga os tokens Sanctum existentes. Sessões abertas em outros dispositivos
continuam válidas mesmo após a troca de senha.

## Solução

Após `$user->forceFill(...)->save()`, revogar todos os tokens do usuário com
`$user->tokens()->delete()` — mesmo padrão usado em `AssistedRecoveryService::revokeTokens()`.

Usar `method_exists` para não quebrar se o model da app hospedeira não implementar
`HasApiTokens`.

## Tasks

### [ ] T1 — `ChangePasswordAction`: revogar tokens após troca de senha
Editar `src/Actions/Password/ChangePasswordAction.php`.

### [ ] T2 — Teste: verificar revogação de tokens na troca de senha
Editar `tests/Feature/PasswordControllerTest.php`.
