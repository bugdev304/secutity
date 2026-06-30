# Fix: Anonimização de contacts e remoção de identifier no POST /mfa/factors

## Problema

1. `GET /mfa/contacts` expõe o `identifier` real (email/telefone), violando LGPD
2. `POST /mfa/factors` recebe `identifier` em plain text no body — mas se o front só tem
   o valor mascarado, não tem como enviar o real
3. `maskIdentifier` está acoplado ao `FactorResource`, sem reuso

## Solução

### Contact Token (HMAC-SHA256)
Gerar um token opaco e estável por contato: `HMAC-SHA256(app_key, channel + identifier)`.
- Estável: mesmo contato sempre gera o mesmo token
- Opaco: não é possível reverter para o identifier real
- Seguro: depende da app key

### Fluxo novo
```
GET /mfa/contacts →
[
  {
    "channel": "email",
    "masked_identifier": "pa***@gmail.com",
    "label": "E-mail",
    "contact_token": "a3f9c2..."
  }
]

POST /mfa/factors →
{ "contact_token": "a3f9c2...", "name": "Meu e-mail" }
// backend resolve o identifier real pelo token
```

---

## Tasks

### [ ] T1 — `src/Support/IdentifierMasker.php`
Extrair `maskIdentifier()` de `FactorResource` para classe de suporte reutilizável.

### [ ] T2 — `FactorResource`: usar `IdentifierMasker`
Substituir implementação inline pelo uso da nova classe.

### [ ] T3 — `src/Support/ContactTokenizer.php`
Nova classe com dois métodos:
- `generate(string $channel, string $identifier): string` → HMAC-SHA256 com app key
- `resolve(Authenticatable $user, string $token): ?MfaContact` → itera contatos do usuário e encontra o match

### [ ] T4 — `MfaContactController::index()`
- Usar `IdentifierMasker`
- Usar `ContactTokenizer::generate()`
- Retornar `masked_identifier` + `contact_token` (remover `identifier`)

### [ ] T5 — `EnrollFactorRequest`: trocar `identifier` por `contact_token`
Para fatores OTP (email/sms): aceitar `contact_token` em vez de `identifier`.
TOTP (`authenticator_app`) não muda — não usa contato.

### [ ] T6 — `FactorController::store()`: resolver identifier via ContactTokenizer
Para OTP: chamar `ContactTokenizer::resolve()` para obter o `MfaContact` real.
Lançar exceção se token inválido/não encontrado.

### [ ] T7 — Testes
- `MfaContactController`: verificar que `identifier` não aparece no response
- `FactorController`: verificar que `contact_token` resolve corretamente o identifier

---

## Decisões

- `contact_token` é computado com `hash_hmac('sha256', $channel.$identifier, config('app.key'))`
- TOTP não é alterado (não usa contato)
- `label` permanece no response (é um rótulo legível, não dado sensível)
