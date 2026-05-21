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
    case AdminUserDeleted  = 'admin.user.deleted';

    case ApiTokenCreated = 'api.token.created';
    case ApiTokenRevoked = 'api.token.revoked';

    // Via API — distinguished from web actions for audit clarity
    case ApiAliasCreated = 'api.alias.created';
    case ApiAliasDeleted = 'api.alias.deleted';
    case ApiEmailRead    = 'api.email.read';
    case ApiEmailDeleted = 'api.email.deleted';
    case ApiAdminUserUpdated = 'api.admin.user.updated';

    case WebhookDelivered = 'webhook.delivered';
    case WebhookFailed    = 'webhook.failed';

    // ── Auth / profile events ──────────────────────────────────────────────────
    case SsoAccountLinked = 'sso.account.linked';
    case ProfileUpdated   = 'profile.updated';
    case PasswordChanged  = 'password.changed';
    case SettingsSaved    = 'settings.saved';

    // ── Bulk actions ──────────────────────────────────────────────────────────
    case EmailsBulkRead   = 'email.bulk_read';

    public function label(): string
    {
        return match ($this) {
            self::AliasCreated       => 'Alias created',
            self::AliasDeleted       => 'Alias deleted',
            self::AliasExpired       => 'Alias expired',
            self::AliasExtended      => 'Alias extended',
            self::AliasShared        => 'Alias shared',
            self::AliasUnshared      => 'Alias unshared',
            self::EmailReceived      => 'Email received',
            self::EmailRead          => 'Email read',
            self::EmailDeleted       => 'Email deleted',
            self::EmailsBulkRead     => 'Emails bulk read',
            self::UserLogin          => 'User login',
            self::UserLogout         => 'User logout',
            self::TwoFactorEnabled   => '2FA enabled',
            self::TwoFactorDisabled  => '2FA disabled',
            self::SsoAccountLinked   => 'SSO account linked',
            self::ProfileUpdated     => 'Profile updated',
            self::PasswordChanged    => 'Password changed',
            self::SettingsSaved      => 'Platform settings saved',
            self::AdminAliasCreated  => 'Admin: alias created',
            self::AdminAliasDeleted  => 'Admin: alias deleted',
            self::AdminViewedEmail   => 'Admin: email viewed',
            self::AdminUserUpdated   => 'Admin: user updated',
            self::AdminUserDeleted   => 'Admin: user deleted',
            self::ApiTokenCreated    => 'API token created',
            self::ApiTokenRevoked    => 'API token revoked',
            self::ApiAliasCreated    => 'API: alias created',
            self::ApiAliasDeleted    => 'API: alias deleted',
            self::ApiEmailRead       => 'API: email read',
            self::ApiEmailDeleted    => 'API: email deleted',
            self::ApiAdminUserUpdated => 'API admin: user updated',
            self::WebhookDelivered   => 'Webhook delivered',
            self::WebhookFailed      => 'Webhook failed',
        };
    }
}
