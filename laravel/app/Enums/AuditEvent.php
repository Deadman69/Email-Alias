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

    case EmailReceived    = 'email.received';
    case EmailRead        = 'email.read';
    case EmailDeleted     = 'email.deleted';
    case EmailDownloaded  = 'email.downloaded';

    case EmailMailboxQuotaExceeded = 'email.mailbox.quota.exceeded';
    case EmailUserQuotaExceeded = 'email.user.quota.exceeded';
    case EmailMailboxRateLimit = 'email.mailbox.rate-limit';

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

    case DomainCreated    = 'domain.created';
    case DomainPrimaryChanged = 'domain.primary';
    case DomainDeleted    = 'domain.deleted';

    case AppTokenCreated  = 'app.token.created';
    case AppTokenRevoked  = 'app.token.revoked';

    // ── Bulk actions ──────────────────────────────────────────────────────────
    case EmailsBulkRead   = 'email.bulk_read';

    public function label(): string
    {
        return match ($this) {
            self::AliasCreated       => __('Alias created'),
            self::AliasDeleted       => __('Alias deleted'),
            self::AliasExpired       => __('Alias expired'),
            self::AliasExtended      => __('Alias extended'),
            self::AliasShared        => __('Alias shared'),
            self::AliasUnshared      => __('Alias unshared'),
            self::EmailReceived      => __('Email received'),
            self::EmailRead          => __('Email read'),
            self::EmailDeleted       => __('Email deleted'),
            self::EmailDownloaded    => __('Email downloaded'),
            self::EmailMailboxQuotaExceeded => __('Email dropped (Mailbox storage exceeded)'),
            self::EmailUserQuotaExceeded => __('Email dropped (User storage exceeded)'),
            self::EmailMailboxRateLimit => __('Email dropped (Rate limit)'),
            self::EmailsBulkRead     => __('Emails bulk read'),
            self::UserLogin          => __('User login'),
            self::UserLogout         => __('User logout'),
            self::TwoFactorEnabled   => __('2FA enabled'),
            self::TwoFactorDisabled  => __('2FA disabled'),
            self::SsoAccountLinked   => __('SSO account linked'),
            self::ProfileUpdated     => __('Profile updated'),
            self::PasswordChanged    => __('Password changed'),
            self::SettingsSaved      => __('Platform settings saved'),
            self::DomainCreated      => __('Domain created'),
            self::DomainPrimaryChanged => __('Domain defined as primary'),
            self::DomainDeleted      => __('Domain deleted'),
            self::AppTokenCreated    => __('Application token created'),
            self::AppTokenRevoked    => __('Application token revoked'),
            self::AdminAliasCreated  => __('Admin: alias created'),
            self::AdminAliasDeleted  => __('Admin: alias deleted'),
            self::AdminViewedEmail   => __('Admin: email viewed'),
            self::AdminUserUpdated   => __('Admin: user updated'),
            self::AdminUserDeleted   => __('Admin: user deleted'),
            self::ApiTokenCreated    => __('API token created'),
            self::ApiTokenRevoked    => __('API token revoked'),
            self::ApiAliasCreated    => __('API: alias created'),
            self::ApiAliasDeleted    => __('API: alias deleted'),
            self::ApiEmailRead       => __('API: email read'),
            self::ApiEmailDeleted    => __('API: email deleted'),
            self::ApiAdminUserUpdated => __('API admin: user updated'),
            self::WebhookDelivered   => __('Webhook delivered'),
            self::WebhookFailed      => __('Webhook failed'),
        };
    }
}
