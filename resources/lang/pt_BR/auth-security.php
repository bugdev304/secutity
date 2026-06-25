<?php

declare(strict_types=1);

return [

    'mfa_required' => 'A verificação em duas etapas é obrigatória para acessar este recurso.',
    'mfa_factor_registration_required' => 'Você deve cadastrar um novo fator de verificação antes de acessar este recurso.',
    'password_expired' => 'Sua senha expirou. Por favor, redefina-a para continuar.',
    'account_locked' => 'Sua conta foi temporariamente restrita. Por favor, entre em contato com o suporte.',

    'otp_expired' => 'O código de verificação expirou. Solicite um novo código.',
    'otp_invalid' => 'O código de verificação é inválido.',
    'otp_resend_limit' => 'Você excedeu o número máximo de reenvios de código.',
    'otp_resend_too_soon' => 'Aguarde antes de solicitar um novo código.',

    'recovery_code_invalid' => 'O código de recuperação é inválido ou já foi utilizado.',
    'last_factor_required' => 'Não é possível remover o último fator de verificação quando ele é obrigatório para o seu perfil.',
    'factor_not_registered' => 'Nenhum fator de verificação está cadastrado para esta conta.',

    'password_policy_violation' => 'A senha não atende aos requisitos de segurança.',
    'password_violation_min_length' => 'A senha deve ter pelo menos :min caracteres.',
    'password_violation_classes_required' => 'A senha deve conter pelo menos :required das 4 classes de caracteres (maiúscula, minúscula, número, especial).',
    'password_violation_history' => 'A senha não pode ser igual a uma senha utilizada recentemente.',

    'assisted_recovery_invalid_status' => 'Esta operação não pode ser realizada com o status atual da recuperação.',
    'assisted_recovery_invalid_token' => 'O token de recuperação é inválido.',
    'assisted_recovery_expired' => 'O token de recuperação expirou.',

    'policy_below_floor' => 'A configuração de política está abaixo do piso mínimo de segurança.',

    'invalidation_required' => 'Os códigos de recuperação existentes devem ser explicitamente invalidados antes de gerar novos.',

];
