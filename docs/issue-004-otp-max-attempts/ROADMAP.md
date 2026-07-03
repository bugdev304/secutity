# Roadmap — otp_max_attempts aplicado no verify (issue #4)

| Task | Descrição | Status |
|------|-----------|--------|
| T1 | Config: adicionar `otp_max_attempts` em `config/auth-security.php` | ✅ |
| T2 | `OtpService::verify()`: contar tentativas falhas e calcular `remaining_attempts` real | ✅ |
| T3 | `OtpService::verify()`: invalidar OTP ao esgotar as tentativas | ✅ |
| T4 | Testes: contador de tentativas, remaining_attempts e invalidação ao esgotar | ✅ |

## Legenda
| Símbolo | Significado |
|---------|-------------|
| ⬜ | Pendente |
| 🔄 | Em andamento |
| ✅ | Concluído |
