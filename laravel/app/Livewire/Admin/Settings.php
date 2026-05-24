<?php

namespace App\Livewire\Admin;

use App\Enums\AuditEvent;
use App\Models\AppToken;
use App\Models\Domain;
use App\Services\AuditLogger;
use App\Services\SettingService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Platform Settings')]
#[Layout('layouts.app')]
class Settings extends Component
{
    use WithFileUploads;
    // ── General ───────────────────────────────────────────────────────────────────
    public string $app_name              = '';
    public string $app_locale            = 'en';
    public bool   $version_check_enabled = true;
    public string $health_check_visibility = 'public';

    // ── Logo upload (not stored as a setting key — handled separately) ────────────
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $logoFile = null;

    public string $currentLogoPath = '';

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
    // SAML 2.0 — IdP
    public string $saml_idp_entity_id   = '';
    public string $saml_idp_sso_url     = '';
    public string $saml_idp_slo_url     = '';
    public string $saml_idp_certificate = '';
    // SAML 2.0 — SP
    public string $saml_sp_entity_id    = '';
    public string $saml_sp_x509cert     = '';
    public string $saml_sp_private_key  = '';  // leave blank to keep existing encrypted value
    // SAML 2.0 — Attribute mapping
    public string $saml_attr_email      = '';
    public string $saml_attr_name       = '';
    // General auth
    public bool   $local_auth_enabled   = true;
    public bool   $registration_enabled = false;
    public string $scim_bearer_token    = '';

    // ── Security ─────────────────────────────────────────────────────────────────
    public bool $two_factor_required = false;

    // ── Aliases ───────────────────────────────────────────────────────────────────
    public int    $alias_max_per_user     = 20;
    public bool   $alias_allow_permanent  = true;
    public bool   $alias_allow_custom     = true;
    public string $alias_default_type     = 'session';

    // ── Email (displayed in MB in the UI, stored in bytes) ────────────────────────
    public int  $alias_max_email_size_mb      = 10;
    public int  $alias_max_attachment_size_mb = 5;
    public int  $alias_max_mailbox_size_mb    = 0;   // 0 = unlimited
    public int  $alias_max_user_storage_mb    = 0;   // 0 = unlimited
    public int  $cleanup_retention_days       = 7;
    public int  $audit_log_retention_days     = 365;
    public bool $admin_can_read_emails        = false;

    // ── Domains ───────────────────────────────────────────────────────────────────

    #[Validate('required|string|max:253|regex:/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i')]
    public string $newDomain = '';

    public bool   $showConfirmDeleteDomain = false;
    /** 'keep' = null out domain_id on aliases | 'cascade' = delete associated aliases */
    public string $deleteDomainMode = 'keep';

    #[Locked]
    public string $pendingDeleteDomainId = '';

    /** Keyed by domain ULID — true = MX OK, false = no MX record found */
    public array $mxResults = [];

    // ── App Tokens ────────────────────────────────────────────────────────────────

    public string $newTokenName      = '';
    public string $newTokenAbilities = 'read:domains';   // comma-separated
    public string $newTokenExpiresAt = '';               // YYYY-MM-DD or empty

    /** Plain token shown once after creation — cleared on tab change or page reload */
    public string $plainToken        = '';
    public bool   $showPlainToken    = false;

    public bool   $showConfirmRevokeToken = false;

    #[Locked]
    public string $pendingRevokeTokenId = '';

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
        // SAML 2.0 — IdP
        $this->saml_idp_entity_id   = (string) $settings->get('saml_idp_entity_id', '');
        $this->saml_idp_sso_url     = (string) $settings->get('saml_idp_sso_url', '');
        $this->saml_idp_slo_url     = (string) $settings->get('saml_idp_slo_url', '');
        $this->saml_idp_certificate = (string) $settings->get('saml_idp_certificate', '');
        // SAML 2.0 — SP
        $this->saml_sp_entity_id    = (string) $settings->get('saml_sp_entity_id', '');
        $this->saml_sp_x509cert     = (string) $settings->get('saml_sp_x509cert', '');
        $this->saml_sp_private_key  = '';  // never expose in Livewire state
        // SAML 2.0 — Attribute mapping
        $this->saml_attr_email      = (string) $settings->get('saml_attr_email', '');
        $this->saml_attr_name       = (string) $settings->get('saml_attr_name', '');
        // Logo
        $this->currentLogoPath      = (string) $settings->get('app_logo_path', '');
        // SCIM
        $this->scim_bearer_token   = ''; // Never expose in Livewire state
        $this->local_auth_enabled  = (bool) $settings->get('local_auth_enabled', true);
        $this->registration_enabled = (bool) $settings->get('registration_enabled', false);

        $this->two_factor_required = (bool) $settings->get('two_factor_required', false);

        $this->alias_max_per_user    = (int) $settings->get('alias_max_per_user', 20);
        $this->alias_allow_permanent = (bool) $settings->get('alias_allow_permanent', true);
        $this->alias_allow_custom    = (bool) $settings->get('alias_allow_custom', true);
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

    /** Public URL of the currently saved logo, or null when none is set. */
    #[Computed]
    public function logoUrl(): ?string
    {
        if ($this->currentLogoPath && Storage::disk('public')->exists($this->currentLogoPath)) {
            return Storage::disk('public')->url($this->currentLogoPath);
        }

        return null;
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

    // ── Logo ──────────────────────────────────────────────────────────────────────

    /**
     * Upload and store a new logo image.
     * Only PNG, WebP, and JPEG are accepted — SVG is explicitly forbidden (XSS risk).
     */
    public function uploadLogo(SettingService $settings): void
    {
        $this->validate([
            'logoFile' => [
                'required',
                'file',
                'max:2048', // 2 MB
                'mimes:png,jpg,jpeg,webp',
            ],
        ]);

        // Delete the old logo if one exists.
        if ($this->currentLogoPath && Storage::disk('public')->exists($this->currentLogoPath)) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        $path = $this->logoFile->store('logos', 'public');

        $settings->set('app_logo_path', $path);
        $this->currentLogoPath = $path;
        $this->logoFile        = null;
        unset($this->logoUrl);

        Flux::toast(variant: 'success', text: __('Logo updated.'));
    }

    /**
     * Remove the custom logo and revert to the built-in icon.
     */
    public function removeLogo(SettingService $settings): void
    {
        if ($this->currentLogoPath && Storage::disk('public')->exists($this->currentLogoPath)) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        $settings->set('app_logo_path', '');
        $this->currentLogoPath = '';
        unset($this->logoUrl);

        Flux::toast(variant: 'success', text: __('Logo removed.'));
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
            'saml_sp_x509cert'              => 'nullable|string|max:8192',
            'saml_sp_private_key'           => 'nullable|string|max:8192',
            'saml_attr_email'               => 'nullable|string|max:500',
            'saml_attr_name'                => 'nullable|string|max:500',
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
            'saml_sp_x509cert'                 => $this->saml_sp_x509cert,
            'saml_attr_email'                  => $this->saml_attr_email,
            'saml_attr_name'                   => $this->saml_attr_name,
            'local_auth_enabled'               => $this->local_auth_enabled,
            'registration_enabled'             => $this->registration_enabled,
            'two_factor_required'              => $this->two_factor_required,
            'alias_max_per_user'               => $this->alias_max_per_user,
            'alias_allow_permanent'            => $this->alias_allow_permanent,
            'alias_allow_custom'               => $this->alias_allow_custom,
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

        if ($this->saml_sp_private_key !== '') {
            $data['saml_sp_private_key'] = $this->saml_sp_private_key;
            $this->saml_sp_private_key = '';
        }

        // Build a diff of what changed (hide secret values).
        $secretKeys = ['azure_client_secret', 'oidc_client_secret', 'scim_bearer_token', 'saml_sp_private_key'];
        $previous   = $settings->all();
        $changed    = [];

        foreach ($data as $key => $newValue) {
            $oldValue = $previous[$key] ?? null;
            // $settings->all() returns raw DB strings; Livewire props are typed.
            // Stringify both sides so "1" === true and "20" === 20.
            $normalized = fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) ($v ?? '');

            if ($normalized($oldValue) !== $normalized($newValue)) {
                $changed[$key] = in_array($key, $secretKeys, true)
                    ? '[secret changed]'
                    : ['from' => $oldValue, 'to' => $newValue];
            }
        }

        $settings->fill($data);

        $auditLogger->log(AuditEvent::SettingsSaved, null, [
            'actor'   => Auth::user()->email,
            'changed' => $changed ?: 'no changes',
        ]);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    // ── Domain computed + actions ─────────────────────────────────────────────────

    #[Computed]
    public function domains(): \Illuminate\Database\Eloquent\Collection
    {
        return Domain::orderByDesc('is_primary')->orderBy('name')->get();
    }

    public function addDomain(): void
    {
        $this->validateOnly('newDomain');

        $name = mb_strtolower(trim($this->newDomain));

        if (Domain::where('name', $name)->exists()) {
            $this->addError('newDomain', __('This domain is already registered.'));
            return;
        }

        // First domain automatically becomes primary.
        $isPrimary = Domain::count() === 0;

        Domain::create(['name' => $name, 'is_primary' => $isPrimary]);

        $this->reset('newDomain');
        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Domain added.'));
    }

    public function setPrimary(string $domainId): void
    {
        Domain::query()->update(['is_primary' => false]);
        Domain::findOrFail($domainId)->update(['is_primary' => true]);

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Primary domain updated.'));
    }

    public function checkMx(string $domainId): void
    {
        $domain = Domain::findOrFail($domainId);
        $this->mxResults[$domainId] = $domain->checkMx();
    }

    public function requestDeleteDomain(string $domainId): void
    {
        $this->pendingDeleteDomainId = $domainId;
        $this->deleteDomainMode      = 'keep';
        $this->showConfirmDeleteDomain = true;
    }

    public function deleteDomain(): void
    {
        if (! $this->pendingDeleteDomainId) {
            return;
        }

        $this->validate([
            'deleteDomainMode' => 'required|in:keep,cascade',
        ]);

        $domain = Domain::findOrFail($this->pendingDeleteDomainId);

        if ($this->deleteDomainMode === 'cascade') {
            // Hard-delete all aliases that belong to this domain.
            // The Alias booted() hook will cascade to emails + attachments.
            $domain->aliases()->each(fn ($alias) => $alias->forceDelete());
        }
        // 'keep': the DB FK is ON DELETE SET NULL — domain_id becomes null automatically.
        // The alias's `domain` string column still holds the original domain name
        // so the SMTP receiver can keep routing mail for those aliases.

        // Promote the next domain to primary when deleting the current primary.
        if ($domain->is_primary) {
            $next = Domain::where('id', '!=', $domain->id)->orderBy('name')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        $domain->delete();

        $this->pendingDeleteDomainId   = '';
        $this->showConfirmDeleteDomain = false;
        unset($this->domains);

        $msg = $this->deleteDomainMode === 'cascade'
            ? __('Domain and all associated aliases removed.')
            : __('Domain removed. Existing aliases were kept.');

        Flux::toast(variant: 'success', text: $msg);
    }

    // ── App Token computed + actions ──────────────────────────────────────────────

    #[Computed]
    public function appTokens(): \Illuminate\Database\Eloquent\Collection
    {
        return AppToken::orderByDesc('created_at')->get();
    }

    public function createAppToken(): void
    {
        $this->validate([
            'newTokenName'      => 'required|string|max:100',
            'newTokenAbilities' => 'nullable|string|max:500',
            'newTokenExpiresAt' => 'nullable|date|after:today',
        ]);

        $abilities = array_values(array_filter(
            array_map('trim', explode(',', $this->newTokenAbilities ?: ''))
        ));

        $expiresAt = $this->newTokenExpiresAt
            ? \Carbon\Carbon::parse($this->newTokenExpiresAt)->endOfDay()
            : null;

        ['token' => $appToken, 'plain' => $plain] = AppToken::make(
            $this->newTokenName,
            $abilities ?: null,
            $expiresAt,
        );

        $appToken->save();

        $this->plainToken     = $plain;
        $this->showPlainToken = true;

        $this->reset('newTokenName', 'newTokenAbilities', 'newTokenExpiresAt');
        $this->newTokenAbilities = 'read:domains';
        unset($this->appTokens);

        Flux::toast(variant: 'success', text: __('Token created — copy it now, it will not be shown again.'));
    }

    public function dismissPlainToken(): void
    {
        $this->plainToken     = '';
        $this->showPlainToken = false;
    }

    public function requestRevokeToken(string $tokenId): void
    {
        $this->pendingRevokeTokenId = $tokenId;
        $this->showConfirmRevokeToken = true;
    }

    public function revokeToken(): void
    {
        if (! $this->pendingRevokeTokenId) {
            return;
        }

        AppToken::findOrFail($this->pendingRevokeTokenId)->delete();

        $this->pendingRevokeTokenId   = '';
        $this->showConfirmRevokeToken = false;
        unset($this->appTokens);

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }
}
