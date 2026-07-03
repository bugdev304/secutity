# Postman — ae3/auth-security

Guia de uso da collection e do ambiente Postman do pacote.

## Arquivos

| Arquivo | Descrição |
|---|---|
| `auth-security.postman_collection.json` | Collection completa com todos os endpoints |
| `auth-security.postman_environment.json` | Ambiente local com variáveis pré-configuradas |

## Como importar

1. Abra o Postman
2. **File → Import** → selecione os dois arquivos JSON
3. Selecione o ambiente **ae3-auth-security (local)** no canto superior direito

## Configuração inicial

No ambiente, ajuste:

| Variável | Valor padrão | O que é |
|---|---|---|
| `base_url` | `http://127.0.0.1:8000` | URL da app sandbox |
| `prefix` | `api/v1` | Prefixo das rotas do pacote |
| `sandbox_email` | `tl@example.local` | E-mail do usuário de teste |
| `sandbox_password` | `TestForte!1` | Senha do usuário de teste |
| `user_id` | `1` | ID do usuário (para recuperação assistida) |

As variáveis `access_token`, `mfa_session_token`, `factor_id`, `recovery_id`, `recovery_token` e `totp_secret` são preenchidas automaticamente pelos scripts de test das requests.

## Fluxo de uso recomendado

### Fluxo completo de login com MFA

```
Folder 0 → POST /login
  → captura access_token

Folder 1 → POST /mfa/factors (email)
  → captura factor_id

Folder 2 → POST /mfa/factors/{factor_id}/challenge
  → OTP enviado (verifique o log da app)

Folder 2 → POST /mfa/verify { factor_id, factor_type: "email", code: "OTP" }
  → captura mfa_session_token

(A partir daqui use X-Mfa-Session-Token: {{mfa_session_token}} nos endpoints protegidos)
```

### Cadastro de TOTP

```
Folder 1 → POST /mfa/factors (authenticator_app)
  → captura factor_id e totp_secret
  → escaneie o QR code ou adicione o secret no seu app autenticador

Folder 1 → POST /mfa/factors/{factor_id}/confirm { code: "TOTP atual" }
  → fator confirmado
```

### Recuperação assistida (TEC-11)

```
[Como usuário]
Folder 4 → POST /mfa/assisted-recoveries { target_user_id, reason_category: "device_lost" }
  → captura recovery_id

[Como admin — troque o access_token]
Folder 4 → POST /mfa/assisted-recoveries/{recovery_id}/release
  → captura recovery_token (entregar ao usuário por canal seguro)

[Como usuário novamente]
Folder 4 → POST /mfa/assisted-recoveries/complete { token: recovery_token }
  → status = "completed"
  → UserState.must_register_factor = true
  → próximo acesso exige cadastro de novo fator
```

### Geração de códigos de recuperação

```
Folder 3 → GET /mfa/recovery-codes          → metadados (total/remaining)
Folder 3 → POST /mfa/recovery-codes         → 409 se há códigos ativos
Folder 3 → POST /mfa/recovery-codes { confirm_invalidation: true }
  → { codes: ["AAAA-BBBB-...", ...] }  ← armazene com segurança, não será exibido novamente
```

## Dicas

- Use o **Console** do Postman (`View → Show Postman Console`) para ver os scripts de test capturando variáveis.
- Para testar fluxos de admin vs. usuário, crie dois ambientes (ou duas abas, uma por access_token).
- Em desenvolvimento local, configure a app sandbox com `LOG_CHANNEL=stack` e verifique `storage/logs/laravel.log` para ver os OTPs enviados via stub `LogMessageSender`.
