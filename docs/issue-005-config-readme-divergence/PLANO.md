# Plano — Divergência config/README/código (issue #5)

## Escopo

`otp_max_attempts` já foi adicionado ao config e aplicado no `OtpService` como
parte da issue #4 (pré-requisito direto). Resta desta issue:

1. `session_ttl_hours` ausente do `config/auth-security.php`, com
   `MfaSessionService` usando uma constante fixa (`SESSION_TTL_MINUTES = 480`)
   em vez de ler config.
2. Conferir as demais chaves de `mfa.*` do bloco de exemplo do README contra o
   config real.

## Decisões

- Adicionar `session_ttl_hours => 8` em `config('auth-security.mfa')`.
- `MfaSessionService::create()` passa a calcular o TTL em minutos a partir de
  `config('auth-security.mfa.session_ttl_hours', 8) * 60`, eliminando a
  constante fixa.
- README: `otp_resend_max_per_hour` no bloco de exemplo não corresponde a
  nenhuma chave real (`OtpService` usa `otp_resend_limit`, que é um limite por
  janela do OTP ativo, não "por hora") — corrigir o nome no exemplo.
- README: adicionar `session_ttl_hours` ao exemplo (já estava lá) e conferir
  que o valor bate com o novo default do config (8).

## Tasks

Ver `ROADMAP.md`.
