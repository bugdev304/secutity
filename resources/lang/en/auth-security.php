<?php

declare(strict_types=1);

return [

    'mfa_required' => 'Multi-factor authentication is required to access this resource.',
    'mfa_factor_registration_required' => 'You must register a new MFA factor before accessing this resource.',
    'password_expired' => 'Your password has expired. Please change it to continue.',
    'account_locked' => 'Your account has been temporarily restricted. Please contact support.',
    'account_throttled' => 'Too many failed attempts. Please wait before trying again.',

    'otp_expired' => 'The verification code has expired. Please request a new one.',
    'otp_invalid' => 'The verification code is invalid.',
    'otp_resend_limit' => 'You have exceeded the maximum number of code resend requests.',
    'otp_resend_too_soon' => 'Please wait before requesting a new code.',

    'recovery_code_invalid' => 'The recovery code is invalid or has already been used.',
    'last_factor_required' => 'Cannot remove the last MFA factor when it is required by your profile.',
    'factor_not_registered' => 'No MFA factor is registered for this account.',

    'password_policy_violation' => 'The password does not meet the security requirements.',
    'password_violation_min_length' => 'Password must be at least :min characters.',
    'password_violation_classes_required' => 'Password must contain at least :required of the 4 character classes (uppercase, lowercase, number, special).',
    'password_violation_history' => 'Password cannot be the same as a recent previous password.',

    'assisted_recovery_invalid_status' => 'This operation cannot be performed with the current recovery status.',
    'assisted_recovery_invalid_token' => 'The recovery token is invalid.',
    'assisted_recovery_expired' => 'The recovery token has expired.',
    'assisted_recovery_release' => 'Deliver this token to the user via a secure channel. It will not be shown again.',

    'policy_below_floor' => 'The policy configuration is below the minimum security floor.',

    'invalidation_required' => 'Existing recovery codes must be explicitly invalidated before generating new ones.',

];
