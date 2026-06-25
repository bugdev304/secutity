# Fluxo de rotas — ae3/auth-security

Referência visual dos fluxos cobertos pela coleção Postman.  
Cada seção corresponde a uma pasta da coleção.

---

## 0. Sandbox auth — obter token Sanctum

```mermaid
sequenceDiagram
    actor U as Usuário
    participant A as App (Laravel)

    U->>A: POST /auth/login<br/>{ email, password }
    A-->>U: 200 { token, mfa_enrolled }

    note over U,A: Guardar token como {{sanctum_token}}<br/>Usar em todas as requisições seguintes
```

---

## 1. Fluxo completo de login com MFA

```mermaid
sequenceDiagram
    actor U as Usuário
    participant A as App
    participant P as Pacote

    U->>A: POST /auth/login { email, password }
    A-->>U: 200 { token, mfa_enrolled: true }

    U->>P: GET /mfa/factors
    note right of P: Auth: Bearer {{sanctum_token}}
    P-->>U: 200 { data: [{ id, type, masked_identifier }] }

    U->>P: POST /mfa/factors/{factor_id}/challenge
    alt OTP (email / SMS)
        P-->>U: 202 { status: "sent" }
        note over U: Código chega no e-mail/SMS
    else TOTP (app autenticador)
        P-->>U: 200 { status: "use_app" }
        note over U: Abrir app autenticador
    end

    U->>P: POST /mfa/verify { factor_id, code }
    P-->>U: 200 { mfa_session_token, expires_at }

    note over U,P: Guardar como {{mfa_session_token}}

    U->>A: GET /dashboard<br/>X-Mfa-Session-Token: {{mfa_session_token}}
    A-->>U: 200 { data: { message: "Acesso liberado" } }
```

---

## 1a. Cadastro de fator OTP (e-mail)

```mermaid
sequenceDiagram
    actor U as Usuário
    participant P as Pacote

    U->>P: GET /mfa/contacts
    P-->>U: 200 { data: [{ channel, identifier, label }] }
    note over U: Captura mfa_identifier e mfa_channel<br/>do primeiro contato

    U->>P: POST /mfa/factors<br/>{ type: "otp_email", identifier: "{{mfa_identifier}}", name: "E-mail pessoal" }
    P-->>U: 201 { data: { id, type, masked_identifier, confirmed_at: null } }
    note over U: Guardar factor_id<br/>OTP enviado ao identifier

    U->>P: POST /mfa/factors/{factor_id}/confirm<br/>{ code: "123456" }
    P-->>U: 200 { data: { id, confirmed_at: "..." } }
    note over U: Fator ativo — pronto para uso
```

---

## 1b. Cadastro de fator TOTP (app autenticador)

```mermaid
sequenceDiagram
    actor U as Usuário
    participant P as Pacote

    U->>P: POST /mfa/factors<br/>{ type: "authenticator_app", holder_name: "Meu celular" }
    P-->>U: 201 { data: { id, secret, otpauth_uri, qr_code_svg } }
    note over U: Escanear qr_code_svg<br/>no Google Authenticator / Authy

    U->>P: POST /mfa/factors/{factor_id}/confirm<br/>{ code: "123456" }
    P-->>U: 200 { data: { id, confirmed_at: "..." } }
    note over U: Fator ativo — pronto para uso
```

---

## 2. Verificação MFA (Challenge → Verify)

> Fluxo executado durante o login quando o usuário **já tem fator cadastrado**.

```mermaid
flowchart TD
    A([Usuário logado\ncom Sanctum token]) --> B[GET /mfa/factors]
    B --> C{Escolher fator}

    C -->|OTP email/SMS| D[POST /mfa/factors/{id}/challenge]
    D --> E[Aguarda código chegar]
    E --> F[POST /mfa/verify\ncode: XXXXXX]

    C -->|TOTP app| G[POST /mfa/factors/{id}/challenge]
    G --> H[Abre app autenticador]
    H --> F

    C -->|Fallback - outro fator| I[GET /mfa/factors/alternatives]
    I --> C

    F --> J{Código correto?}
    J -->|Sim| K([X-Mfa-Session-Token\nRetornado])
    J -->|Não| L{Tentativas restantes?}
    L -->|Sim| F
    L -->|Não| M([Conta bloqueada\n403 ACCOUNT_LOCKED])

    K --> N[Requisições com\nX-Mfa-Session-Token]
```

---

## 2a. Recuperação via recovery code (sem acesso ao fator)

```mermaid
sequenceDiagram
    actor U as Usuário
    participant P as Pacote

    note over U: Usuário não tem acesso ao fator MFA

    U->>P: POST /mfa/recovery-codes/verify<br/>{ code: "XXXX-XXXX-XXXX" }
    P-->>U: 200 { mfa_session_token, expires_at }

    note over U,P: Token equivale ao MFA verificado<br/>Recovery code é invalidado após uso
```

---

## 3. Geração de códigos de recuperação

```mermaid
sequenceDiagram
    actor U as Usuário
    participant P as Pacote

    U->>P: GET /mfa/recovery-codes
    P-->>U: 200 { data: { total, remaining } }

    alt Sem códigos ativos
        U->>P: POST /mfa/recovery-codes
        P-->>U: 200 { data: { codes: [...] } }
        note over U: Códigos exibidos UMA vez — salvar agora
    else Já existem códigos ativos
        U->>P: POST /mfa/recovery-codes
        P-->>U: 409 INVALIDATION_REQUIRED

        U->>P: POST /mfa/recovery-codes<br/>{ confirm_invalidation: true }
        P-->>U: 200 { data: { codes: [...] } }
        note over U: Códigos anteriores invalidados
    end
```

---

## 4. Recuperação assistida (admin libera acesso)

```mermaid
sequenceDiagram
    actor U as Usuário
    actor Admin
    participant P as Pacote

    note over U: Usuário perdeu acesso a todos os fatores

    U->>P: POST /mfa/assisted-recoveries<br/>{ target_user_id, reason_category }
    P-->>U: 201 { data: { id, status: "requested" } }

    note over Admin: Admin revisa a solicitação

    alt Admin aprova
        Admin->>P: POST /mfa/assisted-recoveries/{id}/release
        P-->>Admin: 200 { data: { recovery_token } }
        note over Admin: Enviar recovery_token ao usuário\npor canal seguro (fora do sistema)

        U->>P: POST /mfa/assisted-recoveries/complete<br/>{ token: "recovery_token" }
        P-->>U: 200 { data: { status: "completed" } }
        note over U,P: UserState.must_register_factor = true\nPróximo login exige cadastro de novo fator
    else Admin recusa
        Admin->>P: POST /mfa/assisted-recoveries/{id}/refuse
        P-->>Admin: 200 { data: { status: "refused" } }
    end
```

---

## 5 & 6. Políticas e senha

Fluxos simples — sem ramificações.

| Ação | Endpoint | Método |
|---|---|---|
| Consultar política | `GET /organization-policies?tenant_type=&tenant_id=` | Leitura |
| Criar/atualizar política | `PUT /organization-policies` | Escrita (admin) |
| Alterar senha | `POST /password { current_password, password, password_confirmation }` | Escrita |

---

## Sequência recomendada para testes no Postman

```
0. POST /auth/login                         → captura sanctum_token
                                              (cookie auto: se mfa_enrolled=false, pule 1→2)

1. GET  /mfa/contacts                       → captura mfa_identifier, mfa_channel
2. POST /mfa/factors                        → captura factor_id (tipo otp_email)
3. POST /mfa/factors/{factor_id}/confirm    → ativa o fator

   -- encerrar sessão e logar de novo --

4. GET  /mfa/factors                        → confirmar fator aparece listado
5. POST /mfa/factors/{factor_id}/challenge  → OTP enviado
6. POST /mfa/verify                         → captura mfa_session_token

7. GET  /dashboard (ou rota protegida)      → acesso com X-Mfa-Session-Token ✓
```
