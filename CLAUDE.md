# CLAUDE.md — ae3-auth-security

## Documentação de planos e implementações

Sempre que montar um plano de implementação ou roadmap:

1. Criar `docs/<nome-da-funcionalidade>/PLANO.md` com descrição das tasks e decisões
2. Criar `docs/<nome-da-funcionalidade>/ROADMAP.md` com tabela de status das tasks
3. **Usar `TaskCreate` para registrar cada task na lista de tasks da sessão** — é assim que o progresso é acompanhado durante a execução
4. Antes de começar cada task: `TaskUpdate` → `in_progress`
5. Ao concluir cada task: `TaskUpdate` → `completed` + atualizar `[x]` no ROADMAP.md
