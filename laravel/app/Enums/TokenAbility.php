<?php

namespace App\Enums;

enum TokenAbility: string
{
    case AliasesRead     = 'aliases:read';
    case AliasesCreate   = 'aliases:create';
    case AliasesDelete   = 'aliases:delete';
    case EmailsRead      = 'emails:read';
    case EmailsDelete    = 'emails:delete';
    case AttachmentsRead = 'attachments:read';

    // Admin-only abilities — only users with role >= admin can create tokens with these
    case AdminAliases = 'admin:aliases';
    case AdminUsers   = 'admin:users';
    case AdminLogs    = 'admin:logs';

    public function label(): string
    {
        return match ($this) {
            self::AliasesRead     => __('Read aliases'),
            self::AliasesCreate   => __('Create aliases'),
            self::AliasesDelete   => __('Delete aliases'),
            self::EmailsRead      => __('Read emails'),
            self::EmailsDelete    => __('Delete emails'),
            self::AttachmentsRead => __('Download attachments'),
            self::AdminAliases    => __('Admin: manage all aliases'),
            self::AdminUsers      => __('Admin: manage users'),
            self::AdminLogs       => __('Admin: export audit logs'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AliasesRead     => __('List and view aliases'),
            self::AliasesCreate   => __('Create new aliases'),
            self::AliasesDelete   => __('Delete own aliases'),
            self::EmailsRead      => __('Read emails in accessible aliases'),
            self::EmailsDelete    => __('Delete emails in own aliases'),
            self::AttachmentsRead => __('Download email attachments'),
            self::AdminAliases    => __('List and delete any alias on the platform'),
            self::AdminUsers      => __('List users and update their role or status'),
            self::AdminLogs       => __('Export the full audit log'),
        };
    }

    public function isAdminAbility(): bool
    {
        return str_starts_with($this->value, 'admin:');
    }

    /** @return list<self> */
    public static function userAbilities(): array
    {
        return array_values(array_filter(self::cases(), fn ($a) => ! $a->isAdminAbility()));
    }

    /** @return list<self> */
    public static function adminAbilities(): array
    {
        return array_values(array_filter(self::cases(), fn ($a) => $a->isAdminAbility()));
    }
}
