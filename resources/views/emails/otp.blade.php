<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Código de verificação') }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: system-ui, -apple-system, sans-serif; color: #18181b; }
        .wrapper { max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: #18181b; padding: 28px 36px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; color: #ffffff; letter-spacing: -.01em; }
        .body { padding: 36px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #3f3f46; }
        .code-box { background: #f4f4f5; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .code { font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #18181b; font-family: monospace; }
        .expiry { font-size: 13px; color: #71717a; margin-top: 8px; }
        .footer { padding: 20px 36px; border-top: 1px solid #f4f4f5; }
        .footer p { margin: 0; font-size: 12px; color: #a1a1aa; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p>Você solicitou um código de verificação. Use-o para concluir o acesso:</p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
                <div class="expiry">
                    Válido por {{ config('auth-security.mfa.otp_validity_minutes', 10) }} minutos
                </div>
            </div>
            <p>Se você não solicitou este código, ignore este e-mail. Nunca compartilhe este código com ninguém.</p>
        </div>
        <div class="footer">
            <p>Este é um e-mail automático. Não responda a esta mensagem.</p>
        </div>
    </div>
</body>
</html>
