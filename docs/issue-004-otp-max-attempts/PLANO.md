# Plano — otp_max_attempts aplicado no verify (issue #4)

## Problema

`OtpService::verify()` lançava `OtpInvalidException` a cada código errado sem
contar tentativas: `otp_max_attempts` nunca era aplicado e `remaining_attempts`
sempre retornava 0 (default do construtor da exceção).

## Decisão

Seguir o mesmo padrão já usado para `otp_resend_limit` (contador no cache, ao
lado do `resend_count`, na mesma chave `otp_meta:*` com TTL alinhado à validade
do OTP):

- Adicionar `attempts` ao array de meta do OTP.
- Em código inválido, incrementar `attempts` e calcular
  `remaining = max(0, otp_max_attempts - attempts)`.
- Repassar `remaining` para `OtpInvalidException`.
- Quando `remaining` chega a 0, invalidar o OTP (forget do hash e do meta) —
  força o usuário a solicitar um novo desafio em vez de continuar tentando um
  código já sabido inválido.
- No acerto, o `forget` do meta já existente zera o contador implicitamente.
- Adicionar `otp_max_attempts` em `config/auth-security.php` (README já
  documentava o valor, config estava divergente — item também coberto pela
  issue #5, mas resolvido aqui porque é pré-requisito direto deste fix).

## Tasks

Ver `ROADMAP.md`.
