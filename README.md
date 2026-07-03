# ae3/auth-security

Pacote Laravel reutilizável que unifica **MFA** (OTP por e-mail/SMS e TOTP), **política de senha forte** e **bloqueio de conta por tentativas**. Projetado para ser consumido via Composer path repository ou Packagist privado.

- PHP 8.2+ / Laravel 10–13
- Stateless API (sem sessão PHP) — token de sessão MFA via header `X-Mfa-Session-Token`
- Armazenamento em PostgreSQL com schema próprio (`auth_security.*`)
- Contratos de extensão: `MfaMessageSender`, `MfaAuditLogger`, `MfaTenantResolver`, `MfaRoleResolver`, `MfaContextResolver`

---

## Sumário

1. [Instalação](#instalação)
2. [Publicar artefatos](#publicar-artefatos)
3. [Configuração](#configuração)
4. [Bootstrap da aplicação](#bootstrap-da-aplicação)
5. [Contratos obrigatórios](#contratos-obrigatórios)
6. [Rotas](#rotas)
7. [Middlewares](#middlewares)
8. [Fluxos de uso](#fluxos-de-uso)
9. [Eventos](#eventos)
10. [Guia de sandbox](#guia-de-sandbox)

---

## Instalação

```bash
# Via path repository (GIZ / dev local)
# Em composer.json da app consumidora:
# "repositories": [{ "type": "path", "url": "../ae3-auth-security" }]

composer require ae3/auth-security
```

O pacote registra automaticamente o `AuthSecurityServiceProvider` via auto-discovery.

---

## Publicar artefatos

```bash
# Configuração
php artisan vendor:publish --tag=auth-security-config

# Migrations (requer publishesMigrations — Laravel 11+)
php artisan vendor:publish --tag=auth-security-migrations

# Arquivos de lingua (opcional — sobrescrever traduções)
php artisan vendor:publish --tag=auth-security-lang
```

Após publicar as migrations:

```bash
php artisan migrate
```

As tabelas são criadas no schema `auth_security` (PostgreSQL) conforme `config('auth-security.schema')`.

---

## Configuração

```php
// config/auth-security.php (após vendor:publish)

return [
    'schema' => env('AUTH_SECURITY_SCHEMA', 'auth_security'),

    'user_model' => env('AUTH_SECURITY_USER_MODEL', \App\Models\User::class),

    // Contratos — implementações da app consumidora
    'tenant_resolver'  => \App\MfaResolvers\TenantResolver::class,
    'role_resolver'    => \App\MfaResolvers\RoleResolver::class,
    'context_resolver' => \App\MfaResolvers\ContextResolver::class,
    'message_sender'   => \App\MfaResolvers\MessageSender::class,
    'audit_logger'     => \App\MfaResolvers\AuditLogger::class,

    'require_contracts' => true, // false apenas em testes

    'cache' => [
        'driver'              => null, // null = cache default da app
        'key_prefix'          => 'auth_security:',
        'policy_ttl_minutes'  => 5,
    ],

    'mfa' => [
        'otp_length'               => 6,
        'otp_validity_minutes'     => 10,
        'otp_max_attempts'         => 5,
        'otp_resend_interval_seconds' => 30,
        'otp_resend_max_per_hour'  => 5,
        'session_ttl_hours'        => 8,
        'recovery_codes_count'     => 10,
    ],

    'lockout' => [
        'max_attempts'   => 5,
        'window_minutes' => 10,
    ],

    'password_policy' => [
        'min_length'       => 12,
        'classes_required' => 3,   // 0-4 (upper, lower, number, special)
        'history_size'     => 5,   // últimas N senhas não podem ser reutilizadas
        'expiration_days'  => 90,  // 0 = sem expiração
    ],

    'floor_policy' => [
        'roles_required' => [], // papéis que sempre exigem MFA independente de política
    ],

    'assisted_recovery' => [
        'token_expires_hours' => 24,
    ],

    'routes' => [
        'prefix' => 'auth-security',
    ],
];
```

---

## Bootstrap da aplicação

### 1. Registrar as rotas

Em `routes/api.php`:

```php
use Ae3\AuthSecurity\AuthSecurityServiceProvider;

AuthSecurityServiceProvider::routes(
    prefix: 'v1',           // prefixo adicional — rotas ficam em /v1/mfa/*, /v1/organization-policies, etc.
    middleware: [],         // middlewares extras além de ['api', "auth:{$guard}"]
    guard: 'sanctum',       // guard de autenticação — 'api' para Passport, ou null pra não aplicar nenhum
);
```

`prefix` e `guard` também podem ser fixados uma vez em `config('auth-security.routes')`, sem precisar
passar em toda chamada.

### 2. Registrar os middlewares (opcional — já registrados via alias)

O pacote registra automaticamente os aliases:

| Alias | Classe |
|---|---|
| `auth-security.not-locked` | `EnsureAccountNotLocked` |
| `auth-security.password-not-expired` | `EnsurePasswordNotExpired` |
| `auth-security.mfa` | `EnsureMfaCompleted` |
| `auth-security.must-register-factor` | `EnsureMustRegisterFactorCompleted` |

Exemplo em `bootstrap/app.php` (Laravel 11):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('api', [
        \Ae3\AuthSecurity\Http\Middleware\EnsureAccountNotLocked::class,
        \Ae3\AuthSecurity\Http\Middleware\EnsurePasswordNotExpired::class,
    ]);
})
```

### 3. Adicionar trait ao modelo de usuário

```php
use Ae3\AuthSecurity\Concerns\HasAuthSecurity;

class User extends Authenticatable
{
    use HasAuthSecurity;
    // ...
}
```

### 4. Integrar o bloqueio de conta ao seu fluxo de login

**O pacote não intercepta o login sozinho** — ele não sabe quando uma tentativa de autenticação
aconteceu, porque login (verificação de senha) é responsabilidade da sua aplicação, não deste
pacote. Você precisa chamar as duas pontas manualmente, no seu próprio controller/action de login:

```php
use Ae3\AuthSecurity\Actions\Account\RecordFailedLoginAction;
use Ae3\AuthSecurity\Services\LockoutService;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function store(
        LoginRequest $request,
        RecordFailedLoginAction $recordFailedLogin,
        LockoutService $lockoutService,
    ) {
        $user = User::where('email', $request->input('email'))->firstOrFail();

        if (! Hash::check($request->input('password'), $user->password)) {
            // Lança AccountLockedException se atingir o limiar configurado em
            // config('auth-security.lockout') — deixe isso subir pro seu exception handler.
            $recordFailedLogin->execute($user);

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // Login bem-sucedido: zera o contador de tentativas falhas.
        $lockoutService->resetAttempts($user);

        // ... emitir o token Sanctum/Passport normalmente
    }
}
```

**Duas chamadas, dois momentos:**

| Momento | O que chamar | Efeito |
|---|---|---|
| Senha incorreta | `RecordFailedLoginAction::execute($user)` | Incrementa o contador; bloqueia e lança `AccountLockedException` ao atingir `lockout.max_attempts` |
| Login bem-sucedido | `LockoutService::resetAttempts($user)` | Zera o contador — sem isso, tentativas antigas continuam somando em janelas futuras |

Sem a segunda chamada, o contador de tentativas falhas nunca é resetado por sucesso — só expira
sozinho depois de `lockout.window_minutes`, o que pode gerar bloqueios inesperados mesmo após
logins corretos no meio do caminho.

---

## Contratos

Todos os 5 contratos possuem implementação padrão (no-op). O pacote funciona sem nenhuma configuração adicional — mas com comportamento limitado para cada contrato não configurado.

| Contrato | Chave config | Default quando null | Impacto se ignorado |
|---|---|---|---|
| `MfaMessageSender` | `message_sender` | `NullMfaMessageSender` | OTP logado como `warning`, nunca entregue — **use apenas em sandbox** |
| `MfaAuditLogger` | `audit_logger` | `NullMfaAuditLogger` | Eventos de segurança descartados silenciosamente |
| `MfaTenantResolver` | `tenant_resolver` | `NullMfaTenantResolver` | Todos os usuários sem tenant — políticas de organização inativas |
| `MfaRoleResolver` | `role_resolver` | `NullMfaRoleResolver` | Nenhum papel resolve, MFA nunca obrigatório via RBAC |
| `MfaContextResolver` | `context_resolver` | `NullMfaContextResolver` | Sem contexto de acesso — políticas por contexto inativas |

**Regra geral**: `MfaMessageSender` é imprescindível em produção (OTP deve ser entregue). Os demais podem ficar como default em apps single-tenant sem RBAC.

### Implementando os contratos

Registre em `config/auth-security.php`:

```php
'message_sender'   => App\Mfa\MyMessageSender::class,
'audit_logger'     => App\Mfa\MyAuditLogger::class,
'tenant_resolver'  => App\Mfa\MyTenantResolver::class,
'role_resolver'    => App\Mfa\MyRoleResolver::class,
'context_resolver' => App\Mfa\MyContextResolver::class,
```

Ou via `AppServiceProvider::register()`:

```php
$this->app->singleton(MfaMessageSender::class, MyMessageSender::class);
```

### MfaMessageSender

Responsável por **entregar o OTP** ao usuário (e-mail, SMS, WhatsApp, push).

```php
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Enums\MfaChannel;

class MyMessageSender implements MfaMessageSender
{
    public function sendOtp(MfaChannel $channel, string $identifier, string $code): void
    {
        match ($channel) {
            MfaChannel::EMAIL => Mail::to($identifier)->send(new OtpMail($code)),
            MfaChannel::SMS   => SmsService::send($identifier, "Seu código: {$code}"),
            MfaChannel::AUTHENTICATOR_APP => throw new \LogicException('TOTP não usa envio de OTP.'),
        };
    }
}
```

### MfaAuditLogger

Responsável por **persistir eventos de segurança** (enrollment, verify, lockout, recovery).

```php
use Ae3\AuthSecurity\Contracts\MfaAuditLogger;

class MyAuditLogger implements MfaAuditLogger
{
    public function logEvent(string $event, array $payload): void
    {
        AuditLog::create(['event' => $event, 'payload' => $payload]);
    }
}
```

### MfaTenantResolver

Resolve o **tenant** de um usuário. Necessário para políticas de organização e RBAC multi-tenant.

```php
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;

class MyTenantResolver implements MfaTenantResolver
{
    public function tenantOf(Authenticatable $user): ?TenantIdentity
    {
        return $user->organization; // deve implementar TenantIdentity
    }
}
```

### MfaRoleResolver

Resolve os **papéis** de um usuário e determina se um papel exige MFA para um tenant/contexto.

```php
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;

class MyRoleResolver implements MfaRoleResolver
{
    public function rolesOf(Authenticatable $user): array
    {
        return $user->roles->pluck('name')->toArray();
    }

    public function requiresMfa(TenantIdentity $tenant, string $role, ?string $context = null): bool
    {
        return app(GetEffectivePolicyAction::class)->execute(
            $tenant->getTenantType(), $tenant->getTenantKey(),
            $role, 0, $context,
        );
    }
}
```

### MfaContextResolver

Resolve o **contexto de acesso** do request (ex.: `web_admin`, `citizen`). Permite políticas MFA diferenciadas por canal.

```php
use Ae3\AuthSecurity\Contracts\MfaContextResolver;

class MyContextResolver implements MfaContextResolver
{
    public function contextOf(Request $request): ?string
    {
        return $request->header('X-Access-Context'); // ex: 'web_admin', 'citizen'
    }
}
```

---

## Rotas

Todos os endpoints requerem autenticação Sanctum (`auth:sanctum`).

### Fatores MFA

| Método | URI | Ação |
|---|---|---|
| `GET` | `{prefix}/mfa/contacts` | Listar contatos disponíveis para cadastro (requer `MfaContactProvider` no User) |
| `GET` | `{prefix}/mfa/factors` | Listar fatores confirmados do usuário |
| `POST` | `{prefix}/mfa/factors` | Iniciar cadastro de fator (OTP ou TOTP) |
| `POST` | `{prefix}/mfa/factors/{factor}/confirm` | Confirmar cadastro de fator com código |
| `DELETE` | `{prefix}/mfa/factors/{factor}` | Remover fator |
| `GET` | `{prefix}/mfa/factors/alternatives` | Listar fatores alternativos (para fallback) |

### Verificação MFA

| Método | URI | Ação |
|---|---|---|
| `POST` | `{prefix}/mfa/factors/{factor}/challenge` | Solicitar código (OTP) ou instrução (TOTP) |
| `POST` | `{prefix}/mfa/factors/{factor}/challenge/resend` | Reenviar OTP |
| `POST` | `{prefix}/mfa/verify` | Verificar código — retorna `X-Mfa-Session-Token` |
| `POST` | `{prefix}/mfa/recovery-codes/verify` | Verificar código de recuperação |

### Códigos de recuperação

| Método | URI | Ação |
|---|---|---|
| `GET` | `{prefix}/mfa/recovery-codes` | Metadados (total/remaining) — nunca os códigos |
| `POST` | `{prefix}/mfa/recovery-codes` | Gerar nova leva (retorna códigos em texto plano — única vez) |

### Recuperação assistida

| Método | URI | Ação |
|---|---|---|
| `POST` | `{prefix}/mfa/assisted-recoveries` | Solicitar recuperação (usuário) |
| `POST` | `{prefix}/mfa/assisted-recoveries/{recovery}/release` | Liberar token de recuperação (admin) |
| `POST` | `{prefix}/mfa/assisted-recoveries/complete` | Completar recuperação com token (usuário) |
| `POST` | `{prefix}/mfa/assisted-recoveries/{recovery}/refuse` | Recusar solicitação (admin) |

### Políticas de organização

| Método | URI | Ação |
|---|---|---|
| `GET` | `{prefix}/organization-policies` | Listar políticas de um tenant |
| `PUT` | `{prefix}/organization-policies` | Criar ou atualizar política |

### Senha

| Método | URI | Ação |
|---|---|---|
| `POST` | `{prefix}/password` | Alterar senha com validação de política |

**`POST /password` já valida a política automaticamente** (via `ChangePasswordRequest`, que usa
`PasswordPolicyRule` internamente). Mas **esse é o único lugar do pacote onde isso acontece** — se
sua app tem outros pontos que definem senha (cadastro de usuário, criação de conta pelo admin,
importação em massa, etc.), você precisa aplicar `PasswordPolicyRule` manualmente nesses
formulários. O pacote não intercepta criação de usuário, porque isso não é responsabilidade dele.

```php
use Ae3\AuthSecurity\Rules\PasswordPolicyRule;

class RegisterUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            // Sem usuário ainda (cadastro novo) — passe null, o histórico é pulado automaticamente.
            'password' => ['required', 'string', 'confirmed', new PasswordPolicyRule()],
        ];
    }
}
```

Se o formulário for de troca de senha de um usuário **já existente** (fora do endpoint `/password`
do pacote — ex.: reset de senha via link de e-mail), passe o usuário pro construtor pra também
verificar o histórico:

```php
'new_password' => ['required', 'string', 'confirmed', new PasswordPolicyRule($user)],
```

---

## Middlewares

### `auth-security.not-locked`

Retorna `403 ACCOUNT_LOCKED` se a conta estiver bloqueada por tentativas.

### `auth-security.password-not-expired`

Retorna `403 PASSWORD_EXPIRED` se a senha expirou conforme `expiration_days`.

### `auth-security.mfa`

Exige o header `X-Mfa-Session-Token` válido (criado ao verificar MFA). Retorna `403 MFA_REQUIRED` se ausente ou expirado.

### `auth-security.must-register-factor`

Retorna `403 MFA_FACTOR_REGISTRATION_REQUIRED` se `UserState.must_register_factor = true`. Ativado automaticamente após recuperação assistida concluída (TEC-11).

---

## Fluxos de uso

### Fluxo de login com MFA

```
1. POST /login (app)           → access_token Sanctum
2. GET  /mfa/factors           → lista fatores disponíveis
3. POST /mfa/factors/{id}/challenge → dispara OTP ou retorna instrução TOTP
4. POST /mfa/verify            → verifica código → { mfa_session_token, expires_at }
5. Requisições protegidas com X-Mfa-Session-Token header
```

### Cadastro de fator OTP

```
1. POST /mfa/factors { type: "email", identifier: "user@email.com" }
   → factor criado (pending), OTP enviado
2. POST /mfa/factors/{id}/confirm { code: "123456" }
   → factor confirmado (confirmed_at preenchido)
```

### Cadastro de fator TOTP

```
1. POST /mfa/factors { type: "authenticator_app", holder_name: "Nome" }
   → { factor_id, secret, otpauth_uri, qr_code_svg }
   (usuário escaneia QR no app autenticador)
2. POST /mfa/factors/{id}/confirm { code: "123456" }
   → factor confirmado
```

### Recuperação assistida (TEC-11)

```
Usuário:
1. POST /mfa/assisted-recoveries { target_user_id, reason_category }
   → recovery { status: "requested" }

Admin:
2. POST /mfa/assisted-recoveries/{id}/release
   → { recovery_token } — entregar ao usuário por canal seguro

Usuário:
3. POST /mfa/assisted-recoveries/complete { token }
   → recovery { status: "completed" }
   → UserState.must_register_factor = true
   → Próximo login exige cadastro de novo fator antes de acessar recursos
```

### Geração de códigos de recuperação

```
1. GET  /mfa/recovery-codes                           → metadados (total/remaining)
2. POST /mfa/recovery-codes                           → 409 INVALIDATION_REQUIRED (se há ativos)
3. POST /mfa/recovery-codes { confirm_invalidation: true } → { codes: [...] }
   (códigos mostrados apenas 1 vez — armazenar com segurança)
```

---

## Eventos

O pacote dispara 6 eventos que o `DispatchAuditLogListener` encaminha ao `MfaAuditLogger`:

| Evento | Quando |
|---|---|
| `MfaFactorEnrolled` | Fator confirmado |
| `MfaFactorRemoved` | Fator removido |
| `RecoveryCodesGenerated` | Nova leva de recovery codes gerada |
| `OtpFailureExceeded` | Tentativas OTP esgotadas |
| `AssistedRecoveryExecuted` | Recuperação assistida concluída |
| `PolicyConfigurationAttemptedBelowFloor` | Tentativa de política abaixo do piso bloqueada |

Para observar eventos adicionais, registre listeners na app consumidora normalmente via `EventServiceProvider`.

---

## Responsabilidades da app consumidora

O pacote cuida do ciclo de vida dos fatores, OTP, TOTP, recovery e políticas. As responsabilidades abaixo ficam fora do escopo do pacote e devem ser implementadas na app.

### Mascaramento do identifier

O `FactorResource` já retorna `masked_identifier` (nunca o valor cru): e-mails mostram os 2 primeiros caracteres (`wo****@company.com`), telefones mostram os 4 últimos (`*******9999`). **Não exponha `identifier` diretamente** — use sempre o campo mascarado do Resource.

### Invalidar fator quando o contato muda no perfil

O `identifier` de cada fator é gravado no momento do enrollment e não é atualizado automaticamente quando o usuário altera e-mail ou telefone no perfil. Se o contato mudar, o OTP continuará sendo enviado para o endereço antigo.

A app deve observar mudanças nos campos de contato do `User` e remover os fatores correspondentes, forçando re-enrollment:

```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('email')) {
            $user->factors()
                ->where('type', FactorType::OtpEmail->value)
                ->each(fn (Factor $factor) => app(RemoveFactorAction::class)->execute(
                    $user, $factor, mfaRequired: false,
                ));
        }

        if ($user->wasChanged('phone')) {
            $user->factors()
                ->where('type', FactorType::OtpSms->value)
                ->each(fn (Factor $factor) => app(RemoveFactorAction::class)->execute(
                    $user, $factor, mfaRequired: false,
                ));
        }
    }
}
```

> O `RemoveFactorAction` com `mfaRequired: false` remove o fator sem verificar se é o último — adequado para remoção administrativa. Se a app exige que o usuário sempre tenha pelo menos um fator, adicione a lógica de guarda antes de remover.

### Contatos disponíveis para cadastro de fator

O pacote expõe `GET /mfa/contacts` que retorna os contatos do usuário disponíveis para cadastro de fator. Para que a rota retorne dados, o Model do usuário deve implementar `MfaContactProvider`:

```php
use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Enums\MfaChannel;

class User extends Authenticatable implements MfaContactProvider
{
    public function mfaContacts(): array
    {
        return [
            new MfaContact(channel: MfaChannel::EMAIL, identifier: $this->email,         label: 'E-mail principal'),
            new MfaContact(channel: MfaChannel::SMS,    identifier: $this->phone,         label: 'Celular'),
            new MfaContact(channel: MfaChannel::SMS,    identifier: $this->backup_phone,  label: 'Celular de backup'),
        ];
    }
}
```

Resposta da rota:

```json
{
  "data": [
    { "channel": "email", "masked_identifier": "pa***@example.com", "label": "E-mail principal", "contact_token": "9f2a1c...e31" },
    { "channel": "sms",   "masked_identifier": "*******9999",        "label": "Celular",          "contact_token": "7bd0f4...a02" }
  ],
  "meta": {}
}
```

Se o User não implementar a interface, a rota retorna `data: []` sem erro. **O `identifier` real nunca é
exposto** — mesmo com o usuário autenticado escolhendo seu próprio contato, a resposta traz só
`masked_identifier` (via `IdentifierMasker`) e um `contact_token` opaco (HMAC de `channel + identifier`,
gerado por `ContactTokenizer`). É esse token, não o identifier, que o front deve enviar de volta.

Fluxo recomendado:

```
1. GET  /mfa/contacts                 → lista contatos mascarados + contact_token
2. Usuário escolhe qual contact_token usar
3. POST /mfa/factors                  → { type, contact_token, name }
4. POST /mfa/factors/{factor}/confirm → { code }
```

O backend resolve o `contact_token` de volta pro identifier real internamente
(`ContactTokenizer::resolve()`) — o front nunca manda nem vê o e-mail/telefone em texto plano
nessa etapa.

O campo `name` (livre) permite ao usuário identificar cada fator na listagem — útil quando há múltiplos fatores do mesmo tipo.

---

## Guia de sandbox

Para testar localmente com a GIZ (ou qualquer app consumidora):

### 1. Path repository

```json
// composer.json da app consumidora
{
    "repositories": [
        { "type": "path", "url": "../ae3-auth-security" }
    ]
}
```

```bash
composer require ae3/auth-security:@dev
```

### 2. Variáveis de ambiente

```dotenv
AUTH_SECURITY_SCHEMA=auth_security
```

### 3. Implementar contratos mínimos (dev)

Crie implementações stub em `app/MfaStubs/` para testes locais:

```php
// config/auth-security.php
'message_sender' => \App\MfaStubs\LogMessageSender::class, // loga OTP em laravel.log
'audit_logger'   => \App\MfaStubs\LogAuditLogger::class,
```

```php
// app/MfaStubs/LogMessageSender.php
class LogMessageSender implements MfaMessageSender {
    public function sendOtp(MfaChannel $channel, string $identifier, string $code): void {
        Log::info("OTP [{$channel->value}] para {$identifier}: {$code}");
    }
}
```

### 4. Importar a collection Postman

1. Abra o Postman
2. Importe `docs/postman/auth-security.postman_collection.json`
3. Importe `docs/postman/auth-security.postman_environment.json`
4. Configure `base_url` e `access_token` no ambiente
5. Execute o folder em sequência: Login → Fatores → Challenge → Verify → rotas protegidas

### Respostas de erro padronizadas

Todos os erros retornam `{ message, code, ...extras }`:

| Code | Status | Situação |
|---|---|---|
| `INVALID_CODE` | 422 | OTP/TOTP/recovery code inválido ou expirado |
| `INVALID_IDENTIFIER` | 422 | Identificador não pertence aos contatos do usuário |
| `RESEND_RATE_LIMITED` | 429 | Reenvio muito frequente |
| `WEAK_PASSWORD` | 422 | Senha viola política — `violations[]` |
| `BELOW_FLOOR` | 422 | Política abaixo do piso — `conflicts[]` |
| `LAST_FACTOR_REQUIRED` | 409 | Tentativa de remover único fator com MFA obrigatório |
| `INVALID_STATUS` | 409 | Operação incompatível com status da recuperação |
| `INVALID_TOKEN` | 422 | Token de recuperação incorreto |
| `TOKEN_EXPIRED` | 422 | Token de recuperação expirado |
| `INVALIDATION_REQUIRED` | 409 | Códigos de recuperação ativos — enviar `confirm_invalidation: true` |
| `ACCOUNT_LOCKED` | 403 | Conta bloqueada por tentativas |
| `PASSWORD_EXPIRED` | 403 | Senha expirada |
| `MFA_REQUIRED` | 403 | Sessão MFA ausente ou expirada |
| `MFA_FACTOR_REGISTRATION_REQUIRED` | 403 | Deve cadastrar novo fator |
