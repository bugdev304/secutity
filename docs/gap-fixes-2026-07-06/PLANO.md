# Plano — Correção de gaps de robustez e conformidade (2026-07-06)

## Contexto

Revisão de robustez do pacote, independente de qualquer app consumidora
específica: cobre suporte a guards de sessão (não só token/API), integridade
de dados de fator MFA (duplicidade e canal), e retenção/eliminação de dados
pessoais sem finalidade de tratamento (LGPD Art. 15/16). Os gaps foram
identificados por inspeção de código e por uma pergunta sobre suporte a
múltiplos contatos (vários e-mails/telefones) na mesma `mfaContacts()`.

## Tasks

### T1 — `AuthSecurityServiceProvider::routes()`: middleware stateful fixo em `'api'`
Sempre montava as rotas do pacote com `['api', "auth:{$guard}"]`, mesmo quando
`$guard` era `'web'` (sessão). `api` não inicia sessão (`StartSession` não
entra no pipeline), então `auth('web')->user()` nunca resolvia o usuário
mesmo com cookie de sessão válido — qualquer app consumidora que autentica
por sessão (não Sanctum/Passport puro) ficava sem conseguir usar `routes()`.
**Fix**: deriva o grupo (`web`/`api`) do driver real do guard
(`config('auth.guards.{guard}.driver') === 'session' ? 'web' : 'api'`).
Arquivo: `src/AuthSecurityServiceProvider.php`

### T2 — Fator MFA duplicado: mesmo contato podia ser cadastrado 2x
Nada impedia cadastrar o mesmo e-mail/telefone duas vezes como fator
separado (sem `unique` na tabela, sem checagem na Action).
**Fix**: migration própria com `unique(user_id, type, identifier)` (NULL de
TOTP não colide entre si) + checagem em `EnrollOtpFactorAction` lançando
`DuplicateFactorException` (`ErrorCode::DUPLICATE_FACTOR`, 409) antes do
insert.
Arquivos: `database/migrations/2026_07_06_000001_add_unique_constraint_to_factors_table.php`,
`src/Exceptions/DuplicateFactorException.php`, `src/Actions/Mfa/EnrollOtpFactorAction.php`,
`src/Enums/ErrorCode.php`, `src/AuthSecurityServiceProvider.php`

### T3 — Retenção/eliminação de dados (LGPD Art. 15/16)
O pacote nunca elimina dados que perderam finalidade de tratamento: fatores
nunca confirmados (abandono de cadastro) e recuperações assistidas
finalizadas ficam retidos indefinidamente.
**Fix**: `config('auth-security.retention')` + `DataRetentionService` +
comando `php artisan auth-security:purge-expired-data` (não roda
automaticamente — a app decide se/quando agendar, já que retenção de
auditoria pode ter base legal própria, Art. 16-I).
Arquivos: `config/auth-security.php`, `src/Services/DataRetentionService.php`,
`src/Console/Commands/PurgeExpiredDataCommand.php`

### T4 — `contact_token` aceito para qualquer `type`, mesmo de canal diferente
`ContactTokenizer::resolve()` varre todos os contatos do usuário e devolve o
primeiro cujo token bate, sem checar se o canal desse contato é o mesmo do
`type` pedido na requisição. Um client podia mandar `type=sms` com o
`contact_token` do e-mail e criar um fator `sms` com `identifier` = endereço
de e-mail (pior ainda com `authenticator_app` na lista de contatos, cujo
`identifier` é `null`). Achado ao revisar como suportar múltiplos
e-mails/telefones + autenticador na mesma lista de `mfaContacts()`.
**Fix**: `FactorController::store()` agora valida
`$contact->channel->value === $factorType->value` antes de prosseguir.
Arquivo: `src/Http/Controllers/FactorController.php`

## Testes
Todas as tasks têm teste de regressão dedicado (`AuthSecurityServiceProviderTest`,
`DataRetentionServiceTest`, `PurgeExpiredDataCommandTest`, casos novos em
`FactorControllerTest`, `ErrorCodeTest` atualizado). Suíte completa: 172
testes, 301 assertions, `vendor/bin/pint --dirty` limpo.

Nenhuma mudança foi commitada — o usuário optou por revisar e commitar
manualmente.
