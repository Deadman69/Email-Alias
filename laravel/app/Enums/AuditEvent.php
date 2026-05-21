<?php

namespace App\Enums;

enum AuditEvent: string
{
    case AliasCreated  = 'alias.created';
    case AliasDeleted  = 'alias.deleted';
    case AliasExpired  = 'alias.expired';
    case AliasExtended = 'alias.extended';
    case AliasShared   = 'alias.shared';
    case AliasUnshared = 'alias.unshared';

    case EmailReceived = 'email.received';
    case EmailRead     = 'email.read';
    case EmailDeleted  = 'email.deleted';

    case UserLogin          = 'user.login';
    case UserLogout         = 'user.logout';
    case TwoFactorEnabled   = '2fa.enabled';
    case TwoFactorDisabled  = '2fa.disabled';

    case AdminAliasCreated = 'admin.alias.created';
    case AdminAliasDeleted = 'admin.alias.deleted';
    case AdminViewedEmail  = 'admin.email.viewed';
    case AdminUserUpdated  = 'admin.user.updated';

    public function label(): string
    {
        return match ($this) {
            self::AliasCreated      => 'Alias created',
            self::AliasDeleted      => 'Alias deleted',
            self::AliasExpired      => 'Alias expired',
            self::AliasExtended     => 'Alias extended',
            self::AliasShared       => 'Alias shared',
            self::AliasUnshared     => 'Alias unshared',
            self::EmailReceived     => 'Email received',
            self::EmailRead         => 'Email read',
            self::EmailDeleted      => 'Email deleted',
            self::UserLogin         => 'User login',
            self::UserLogout        => 'User logout',
            self::TwoFactorEnabled  => '2FA enabled',
            self::TwoFactorDisabled => '2FA disabled',
            self::AdminAliasCreated => 'Admin: alias created',
            self::AdminAliasDeleted => 'Admin: alias deleted',
            self::AdminViewedEmail  => 'Admin: email viewed',
            self::AdminUserUpdated  => 'Admin: user updated',
        };
    }
}
