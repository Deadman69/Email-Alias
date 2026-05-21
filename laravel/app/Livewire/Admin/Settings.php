<?php

namespace App\Livewire\Admin;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\SettingService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Platform Settings')]
#[Layout('layouts.app')]
class Settings extends Component
{
    // ── General ───────────────────────────────────────────────────────────────────
    public string $app_name              = '';
    public string $app_locale            = 'en';
    public bool   $version_check_enabled = true;
    public string $health_check_visibility = 'public';

    // ── Auth ─────────────────────────────────────────────────────────────────────
    public bool   $sso_enabled          = false;
    public string $sso_provider         = 'azure';
    // Azure AD
    public string $azure_client_id      = '';
    public string $azure_client_secret  = '';
    public string $azure_tenant_id      = '';
    // Generic OIDC (Keycloak, Okta, Auth0…)
    public string $oidc_client_id       = '';
    public string $oidc_client_secret   = '';
    public string $oidc_issuer_url      = '';
    // SAML 2.0
    public string $saml_idp_entity_id   = '';
    public string $saml_idp_sso_url     = '';
    public string $saml_idp_slo_url     = '';
    public string $saml_idp_certificate = '';
    public string $saml_sp_entity_id    = '';
    // General auth
    public bool   $local_auth_enabled   = true;
    public bool   $registration_enabled = false;
    public string $scim_bearer_token    = '';

    // ── Security ─────────────────────────────────────────────────────────────────
    public bool $two_factor_required = false;

    // ── Aliases ───────────────────────────────────────────────────────────────────
    public int    $alias_max_per_user     = 20;
    public bool   $alias_allow_permanent  = true;
    public string $alias_default_type     = 'session';

    // ── Email (displayed in MB in the UI, stored in bytes) ────────────────────────
    public int  $alias_max_email_size_mb      = 10;
    public int  $alias_max_attachment_size_mb = 5;
    public int  $alias_max_mailbox_size_mb    = 0;   // 0 = unlimited
    public int  $alias_max_user_storage_mb    = 0;   // 0 = unlimited
    public int  $cleanup_retention_days       = 7;
    public int  $audit_log_retention_days     = 365;
    public bool $admin_can_read_emails        = false;

    // ── Active tab ────────────────────────────────────────────────────────────────
    public string $activeTab = 'general';

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function mount(SettingService $settings): void
    {
        $this->app_name              = (string) $settings->get('app_name', 'EmailAlias');
        $this->app_locale            = (string) $settings->get('app_locale', 'en');
        $this->version_check_enabled    = (bool)   $settings->get('version_check_enabled', true);
        $this->health_check_visibility = (string) $settings->get('health_check_visibility', 'public');
        $this->sso_enabled         = (bool)   $settings->get('sso_enabled', false);
        $this->sso_provider        = (string) $settings->get('sso_provider', 'azure');
        // Azure AD — never expose secrets in Livewire state
        $this->azure_client_id     = (string) $settings->get('azure_client_id', '');
        $this->azure_client_secret = '';  // leave blank to keep existing value
        $this->azure_tenant_id     = (string) $settings->get('azure_tenant_id', '');
        // Generic OIDC
        $this->oidc_client_id      = (string) $settings->get('oidc_client_id', '');
        $this->oidc_client_secret  = '';  // leave blank to keep existing value
        $this->oidc_issuer_url     = (string) $settings->get('oidc_issuer_url', '');
        // SAML 2.0
        $this->saml_idp_entity_id   = (string) $settings->get('saml_idp_entity_id', '');
        $this->saml_idp_sso_url     = (string) $settings->get('saml_idp_sso_url', '');
        $this->saml_idp_slo_url     = (string) $settings->get('saml_idp_slo_url', '');
        $this->saml_idp_certificate = (string) $settings->get('saml_idp_certificate', '');
        $this->saml_sp_entity_id    = (string) $settings->get('saml_sp_entity_id', '');
        // SCIM
        $this->scim_bearer_token   = ''; // Never expose in Livewire state
        $this->local_auth_enabled  = (bool) $settings->get('local_auth_enabled', true);
        $this->registration_enabled = (bool) $settings->get('registration_enabled', false);

        $this->two_factor_required = (bool) $settings->get('two_factor_required', false);

        $this->alias_max_per_user    = (int) $settings->get('alias_max_per_user', 20);
        $this->alias_allow_permanent = (bool) $settings->get('alias_allow_permanent', true);
        $this->alias_default_type    = (string) $settings->get('alias_default_type', 'session');

        $this->alias_max_email_size_mb      = (int) round($settings->get('alias_max_email_size_bytes', 10485760) / 1024 / 1024);
        $this->alias_max_attachment_size_mb = (int) round($settings->get('alias_max_attachment_size_bytes', 5242880) / 1024 / 1024);
        $this->alias_max_mailbox_size_mb    = (int) round($settings->get('alias_max_mailbox_size_bytes', 0) / 1024 / 1024);
        $this->alias_max_user_storage_mb    = (int) round($settings->get('alias_max_user_storage_bytes', 0) / 1024 / 1024);
        $this->cleanup_retention_days       = (int) $settings->get('cleanup_retention_days', 7);
        $this->audit_log_retention_days     = (int) $settings->get('audit_log_retention_days', 365);
        $this->admin_can_read_emails        = (bool) $settings->get('admin_can_read_emails', false);
    }

    // ── Computed ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function appUrl(): string
    {
        return config('app.url', '');
    }

    #[Computed]
    public function appVersion(): string
    {
        return config('emailalias.version', '0.0.0');
    }

    // ── Updaters ──────────────────────────────────────────────────────────────

    /** Auto-reset default type when permanent aliases are disabled. */
    public function updatedAliasAllowPermanent(bool $value): void
    {
        if (! $value && $this->alias_default_type === 'permanent') {
            $this->alias_default_type = 'session';
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────────

    public function save(SettingService $settings, AuditLogger $auditLogger): void
    {
        $this->validate([
            'app_name'                      => 'required|string|max:100',
            'app_locale'                    => 'required|in:en,fr',
            'sso_provider'                  => 'required|in:azure,keycloak,saml',
            'azure_client_id'               => 'nullable|string|max:255',
            'azure_client_secret'           => 'nullable|string|max:500',
            'azure_tenant_id'               => 'nullable|string|max:255',
            'oidc_client_id'                => 'nullable|string|max:255',
            'oidc_client_secret'            => 'nullable|string|max:500',
            'oidc_issuer_url'               => 'nullable|url|max:500',
            'saml_idp_entity_id'            => 'nullable|string|max:500',
            'saml_idp_sso_url'              => 'nullable|url|max:500',
            'saml_idp_slo_url'              => 'nullable|url|max:500',
            'saml_idp_certificate'          => 'nullable|string|max:8192',
            'saml_sp_entity_id'             => 'nullable|string|max:500',
            'scim_bearer_token'             => 'nullable|string|min:32|max:500',
            'alias_max_per_user'            => 'required|integer|min:1|max:1000',
            'alias_default_type'            => ['required', \Illuminate\Validation\Rule::in(
                                                   $this->alias_allow_permanent
                                                       ? ['session', 'duration', 'permanent']
                                                       : ['session', 'duration']
                                               )],
            'health_check_visibility'       => 'required|in:public,auth,admin',
            'alias_max_email_size_mb'       => 'required|integer|min:1|max:100',
            'alias_max_attachment_size_mb'  => 'required|integer|min:1|max:50',
            'alias_max_mailbox_size_mb'     => 'required|integer|min:0|max:102400',
            'alias_max_user_storage_mb'     => 'required|integer|min:0|max:1048576',
            'cleanup_retention_days'        => 'required|integer|min:0|max:3650',
            'audit_log_retention_days'      => 'required|integer|min:0|max:3650',
        ]);

        // Both SSO and local auth cannot be disabled simultaneously.
        if (! $this->sso_enabled && ! $this->local_auth_enabled) {
            $this->addError('local_auth_enabled', __('At least one authentication method must be enabled.'));

            return;
        }

        $data = [
            'app_name'                         => $this->app_name,
            'app_locale'                       => $this->app_locale,
            'version_check_enabled'            => $this->version_check_enabled,
            'health_check_visibility'          => $this->health_check_visibility,
            'sso_enabled'                      => $this->sso_enabled,
            'sso_provider'                     => $this->sso_provider,
            'azure_client_id'                  => $this->azure_client_id,
            'azure_tenant_id'                  => $this->azure_tenant_id,
            'oidc_client_id'                   => $this->oidc_client_id,
            'oidc_issuer_url'                  => $this->oidc_issuer_url,
            'saml_idp_entity_id'               => $this->saml_idp_entity_id,
            'saml_idp_sso_url'                 => $this->saml_idp_sso_url,
            'saml_idp_slo_url'                 => $this->saml_idp_slo_url,
            'saml_idp_certificate'             => $this->saml_idp_certificate,
            'saml_sp_entity_id'                => $this->saml_sp_entity_id,
            'local_auth_enabled'               => $this->local_auth_enabled,
            'registration_enabled'             => $this->registration_enabled,
            'two_factor_required'              => $this->two_factor_required,
            'alias_max_per_user'               => $this->alias_max_per_user,
            'alias_allow_permanent'            => $this->alias_allow_permanent,
            'alias_default_type'               => $this->alias_default_type,
            'alias_max_email_size_bytes'       => $this->alias_max_email_size_mb * 1024 * 1024,
            'alias_max_attachment_size_bytes'  => $this->alias_max_attachment_size_mb * 1024 * 1024,
            'alias_max_mailbox_size_bytes'     => $this->alias_max_mailbox_size_mb * 1024 * 1024,
            'alias_max_user_storage_bytes'     => $this->alias_max_user_storage_mb * 1024 * 1024,
            'cleanup_retention_days'           => $this->cleanup_retention_days,
            'audit_log_retention_days'         => $this->audit_log_retention_days,
            'admin_can_read_emails'            => $this->admin_can_read_emails,
        ];

        // Only update secrets if the admin explicitly entered a new value.
        // An empty field means "keep the existing encrypted value".
        if ($this->azure_client_secret !== '') {
            $data['azure_client_secret'] = $this->azure_client_secret;
            $this->azure_client_secret = '';
        }

        if ($this->oidc_client_secret !== '') {
            $data['oidc_client_secret'] = $this->oidc_client_secret;
            $this->oidc_client_secret = '';
        }

        if ($this->scim_bearer_token !== '') {
            $data['scim_bearer_token'] = $this->scim_bearer_token;
            $this->scim_bearer_token = '';
        }

        $settings->fill($data);

        $auditLogger->log(AuditEvent::SettingsSaved, null, [
            'actor' => Auth::user()->email,
        ]);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}
