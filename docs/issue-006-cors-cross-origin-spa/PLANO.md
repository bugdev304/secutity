# Plano — Documentar CORS para SPA de origem separada (issue #6)

## Objetivo

Issue puramente documental: adicionar uma seção ao README cobrindo os requisitos
de CORS para SPAs hospedadas em origem diferente da API, já que o pacote depende
do header customizado `X-Mfa-Session-Token` (enviado pelo client, nunca devolvido
como header de resposta).

## Conteúdo da seção

- `Access-Control-Allow-Headers` precisa incluir `X-Mfa-Session-Token` (e
  `Authorization` para o Bearer token do Sanctum), senão o preflight falha
  silenciosamente e o browser bloqueia a requisição real sem erro visível no
  código da app.
- O token de sessão MFA trafega apenas no **body** da resposta de
  `POST /mfa/verify` / `POST /mfa/recovery-codes/verify` e no **header da
  requisição** nas chamadas seguintes — nunca como header de resposta, então
  `Access-Control-Expose-Headers` não é necessário para ele.
- Nota sobre `supports_credentials`: só é relevante se a app consumidora usar
  guard baseado em cookie de sessão (Sanctum SPA) em vez de Bearer token puro;
  nesse caso `Access-Control-Allow-Credentials: true` e `withCredentials`/
  `credentials: 'include'` no client são obrigatórios.
- Exemplo de config `config/cors.php` (Laravel) com os headers corretos.

## Tasks

Ver `ROADMAP.md`.
