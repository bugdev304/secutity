# Roadmap — MFA Contacts: Anonimização e contact_token

## Status geral

| Task | Descrição | Status |
|------|-----------|--------|
| T1 | `IdentifierMasker`: extrair lógica de mascaramento | ✅ Concluído |
| T2 | `FactorResource`: usar `IdentifierMasker` | ✅ Concluído |
| T3 | `ContactTokenizer`: gerar e resolver contact tokens | ✅ Concluído |
| T4 | `MfaContactController`: retornar `masked_identifier` + `contact_token` | ✅ Concluído |
| T5 | `EnrollFactorRequest`: trocar `identifier` por `contact_token` | ✅ Concluído |
| T6 | `FactorController::store()`: resolver identifier via `ContactTokenizer` | ✅ Concluído |
| T7 | Testes | ✅ Concluído |

## Dependências

```
T1 → T2
T3 → T4
T1 → T4
T3 → T6
T5 → T6
T6 → T7
```

## Legenda

| Símbolo | Significado |
|---------|-------------|
| ⬜ | Pendente |
| 🔄 | Em andamento |
| ✅ | Concluído |
