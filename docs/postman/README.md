# Postman — testes manuais do `ae3/auth-security`

> Guia de uso da Postman Collection do pacote. Cobre os fluxos completos do MFA, gestão de fatores, códigos de recuperação, recuperação assistida, política da organização e senha.
>
> ⚠️ **Esqueleto inicial** — gerado durante o planejamento. Quando o pacote estiver implementado, o desenvolvedor completa as rotas com os payloads reais (referência: seção 13 do `CONTINUIDADE.md` do snapshot de planejamento).

---

## Pré-requisitos

- Postman ≥ 10 (ou Insomnia / Bruno — qualquer cliente que importe Collection v2.1).
- Uma app Laravel sandbox configurada com o pacote — passo-a-passo na seção **"Testando em uma app Laravel crua"** do `TAREFAS-DESENVOLVIMENTO.md`.
- App sandbox rodando em `http://127.0.0.1:8000` (ou outra URL — basta ajustar a variável de ambiente).

## Importação

1. **Collection:** `File → Import → auth-security.postman_collection.json`
2. **Environment:** `File → Import → auth-security.postman_environment.json`
3. No canto superior direito do Postman, selecionar o environment `auth-security (local)`.
4. Ajustar `base_url` se necessário (default: `http://127.0.0.1:8000`).

## Variáveis do ambiente

| Variável | O que é | Quando preencher |
|---|---|---|
| `base_url` | URL da app sandbox | Antes da primeira request |
| `access_token` | Token Sanctum após login | Capturado automaticamente por test script após `POST /login` |
| `mfa_session_token` | Token de sessão MFA após verify | Capturado após `POST /mfa/verify` bem-sucedido |
| `challenge_id` | ID do challenge ativo (envio de OTP/TOTP) | Capturado após `POST /mfa/factors/{id}/challenge` |
| `factor_id` | ID de um fator ativo | Capturado após `GET /mfa/factors` (primeiro item) ou `POST /mfa/factors/{id}/confirm` |
| `factor_id_pending` | ID de fator em cadastro (ainda não confirmado) | Capturado após `POST /mfa/factors` |
| `enrollment_challenge_id` | Challenge do cadastro do fator | Capturado após `POST /mfa/factors` |
| `recovery_id` | ID de uma recuperação assistida em andamento | Capturado após `POST /mfa/assisted-recoveries` |

> As capturas automáticas vivem em **test scripts** de cada request. O desenvolvedor preenche.

## Fluxo end-to-end (passo a passo)

### Etapa 1 — Login e cadastro do primeiro fator

1. **`POST /login`** (rota da app sandbox, fora do pacote) — informa CPF/email + senha.
   - O test script captura o token em `{{access_token}}`.
2. **`GET /v1/auth-security/mfa/factors`** — lista vazia esperada.
3. **`POST /v1/auth-security/mfa/factors`** com `type=email` e `identifier=tl@teste.local`.
   - Resposta: `factor_id_pending` + `enrollment_challenge_id`.
   - Em paralelo, abra `storage/logs/laravel.log` da sandbox — o `LoggingMessageSender` escreveu o OTP lá. Formato esperado: `[OTP] canal=email para=tl@teste.local código=XXXXXX`.
4. **`POST /v1/auth-security/mfa/factors/{{factor_id_pending}}/confirm`** com o código copiado do log.
   - Resposta `201` — fator confirmado e ativo.

### Etapa 2 — Cadastro de fator de aplicativo verificador

5. **`POST /v1/auth-security/mfa/factors`** com `type=authenticator_app`.
   - Resposta inclui `qr_code_svg` (base64) e `secret`. Renderize o SVG (Postman aceita visualizar) ou cole o `secret` no Google Authenticator manualmente.
6. **`POST /v1/auth-security/mfa/factors/{{factor_id_pending}}/confirm`** com o primeiro código gerado pelo app.

### Etapa 3 — Geração de códigos de recuperação

7. **`POST /v1/auth-security/mfa/recovery-codes`** (sem body — primeira geração).
   - Resposta: 8 códigos no formato `XXXX-XXXX-XXXX`. **Anote-os** — não aparecem de novo.

### Etapa 4 — Logout e login com MFA exigido

8. Limpar `{{access_token}}`.
9. **`POST /login`** novamente. Como o perfil exige MFA (sandbox configurada para isso), a resposta agora indica sessão parcial.
10. **`GET /v1/auth-security/mfa/factors`** — lista os fatores cadastrados (com identifier mascarado).
11. **`POST /v1/auth-security/mfa/factors/{{factor_id}}/challenge`** — escolhe um fator.
    - `email`/`sms`: OTP enviado (no log da sandbox).
    - `authenticator_app`: **nada é enviado** — usa o código atual do app.
    - `recovery_code`: **nada é enviado** — usa um código da lista.
12. **`POST /v1/auth-security/mfa/verify`** com o código → captura `{{mfa_session_token}}`.
13. Requests subsequentes à sandbox passam pelo middleware MFA e ficam liberadas.

### Etapa 5 — Cenários de erro

| Cenário | Request | Resposta esperada |
|---|---|---|
| Código inválido | `POST /mfa/verify` com código errado | `422 { code: "INVALID_CODE", remaining_attempts: N }` |
| Código expirado | aguardar 10+ min antes de chamar `/verify` | `422 { code: "INVALID_CODE" }` |
| Reenvio acima do limite | `POST /mfa/challenges/{id}/resend` 4x | `429 { code: "RESEND_RATE_LIMITED" }` |
| Reenvio em TOTP | `POST /mfa/challenges/{id}/resend` em challenge TOTP | `400 { code: "RESEND_NOT_ALLOWED" }` |
| Remover último fator (perfil exige) | `DELETE /mfa/factors/{id}` | `409 { code: "LAST_FACTOR_REQUIRED" }` |
| Regenerar sem confirmação | `POST /mfa/recovery-codes` sem `confirm_invalidation=true` quando já há lista | `409 { code: "INVALIDATION_REQUIRED" }` |
| Política abaixo do piso | `PUT /organization-policies` afrouxando célula do piso | `422 { code: "BELOW_FLOOR", conflicts: [...] }` |
| Senha fraca | `POST /password` com `new_password=123` | `422 { code: "WEAK_PASSWORD", violations: [...] }` |

### Etapa 6 — Recuperação assistida

1. Cenário: usuário perdeu todos os fatores.
2. **Logue como administrador da organização** (outro usuário da sandbox com role apropriada).
3. **`POST /v1/auth-security/mfa/assisted-recoveries`** com `target_user_id`, `reason_category="device_lost"`.
4. **`POST /v1/auth-security/mfa/assisted-recoveries/{{recovery_id}}/release`**.
   - O log da sandbox mostra o link único enviado ao usuário (`LoggingMessageSender` captura também notificações fora do OTP, conforme implementação).
5. Como usuário recuperado: **`POST /v1/auth-security/mfa/assisted-recoveries/complete`** com o `token` do link.
6. A sessão fica com `must_register_factor=true`. Qualquer rota da sandbox protegida pelo middleware do pacote retorna `403 { code: "MFA_FACTOR_REGISTRATION_REQUIRED" }` até o usuário cadastrar um novo fator.

## Códigos de erro do pacote

Espelhados em `CONTINUIDADE.md §13.9` do snapshot de planejamento. Resumo:

`MFA_REQUIRED` (403), `MFA_FACTOR_REGISTRATION_REQUIRED` (403), `PASSWORD_EXPIRED` (403), `ACCOUNT_LOCKED` (403), `INVALID_CODE` (422), `LAST_FACTOR_REQUIRED` (409), `INVALIDATION_REQUIRED` (409), `BELOW_FLOOR` (422), `WEAK_PASSWORD` (422), `RESEND_NOT_ALLOWED` (400), `RESEND_RATE_LIMITED` (429).

## Resolução de problemas

| Sintoma | Provável causa |
|---|---|
| `500 ConfigurationException` no boot da sandbox | Algum dos 5 contratos em `config/auth-security.php` está `null`. Apontar para os fixtures do pacote (`InMemoryTenantResolver`, etc.). |
| OTP não chega no log | `LoggingMessageSender` não está configurado em `config/auth-security.php` → `message_sender`. |
| `403 MFA_REQUIRED` em rota onde não deveria | O middleware `auth-security` está aplicado na rota mas o usuário ainda não tem `mfa_session_token`. Concluir o fluxo de verify primeiro. |
| Schema `auth_security` não encontrado | Faltou `vendor:publish --tag=auth-security-migrations` + `php artisan migrate`. |
| Cache parece "fantasma" | `php artisan cache:clear` entre rodadas; ou `php artisan migrate:fresh` para começar do zero. |
| `qr_code_svg` aparece como string ilegível | É base64 do SVG. Decodifique e cole em um arquivo `.svg`, ou Postman renderiza no preview. |

## Próximos passos para o desenvolvedor (quando este README for atualizado pelo dev)

- [ ] Substituir todas as referências a "esqueleto" e "será implementado" pelo conteúdo real.
- [ ] Validar que os caminhos dos endpoints na Collection batem com as rotas implementadas em `routes/api.php` do pacote.
- [ ] Adicionar exemplos reais de resposta em cada request (capturados em runtime).
- [ ] Confirmar que os test scripts de captura automática das variáveis estão funcionando.
- [ ] Documentar versão da Collection compatível com qual versão do pacote.
