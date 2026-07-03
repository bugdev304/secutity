# Plano — Especificação OpenAPI 3.1 (issue #3)

## Objetivo

Publicar `openapi.yaml` (OpenAPI 3.1) cobrindo todos os endpoints do pacote, com
schemas de request/response e o catálogo de códigos de erro, para permitir geração
de client/tipos TypeScript pelo front (ex.: `openapi-typescript`).

## Decisões

- Arquivo publicado na raiz do repositório (`openapi.yaml`), caminho convencional
  para geradores de client.
- `servers` usa variável de path prefix (`auth-security` por padrão, configurável
  via `config('auth-security.route_prefix')`), já que o pacote é montado pelo app
  host sob o prefixo que ele escolher.
- Autenticação documentada como Sanctum bearer token (`Authorization: Bearer`).
- Cada endpoint documenta os `code` de erro específicos que pode retornar,
  referenciando um catálogo central em `components/schemas/ErrorCode` (enum) e
  `components/responses` reutilizáveis por status HTTP.
- Middlewares que retornam erro *antes* do controller (`EnsureMfaCompleted`,
  `EnsureAccountNotLocked`, `EnsurePasswordNotExpired`, `EnsureMustRegisterFactorCompleted`)
  também entram no catálogo de erros porque afetam qualquer rota autenticada do
  pacote.
- Fonte da verdade: FormRequests (`src/Http/Requests/*`), Resources
  (`src/Http/Resources/*`), Controllers (`src/Http/Controllers/*`) e o mapeamento
  de exceções em `AuthSecurityServiceProvider::resolveExceptionDetails()`.

## Tasks

Ver `ROADMAP.md`.
