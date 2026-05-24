<div class="max-w-3xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('Platform Settings') }}</flux:heading>
        <flux:subheading>{{ __('These settings override .env values in real time. No server restart required.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Tab navigation --}}
        <div class="flex flex-wrap gap-2 border-b border-zinc-200 pb-4 dark:border-zinc-700">
            <flux:button
                type="button"
                wire:click="$set('activeTab', 'general')"
                :variant="$activeTab === 'general' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('General') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'auth')"
                :variant="$activeTab === 'auth' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('Authentication') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'security')"
                :variant="$activeTab === 'security' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('Security') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'aliases')"
                :variant="$activeTab === 'aliases' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('Aliases') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'email')"
                :variant="$activeTab === 'email' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('Email') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'domains')"
                :variant="$activeTab === 'domains' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('Domains') }}
            </flux:button>

            <flux:button
                type="button"
                wire:click="$set('activeTab', 'app-tokens')"
                :variant="$activeTab === 'app-tokens' ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('API Tokens') }}
            </flux:button>
        </div>

        {{-- General --}}
        @if ($activeTab === 'general')
            <div class="space-y-4 pt-4">
                <flux:field>
                    <flux:label>{{ __('Application name') }}</flux:label>
                    <flux:input wire:model="app_name" placeholder="EmailAlias" />
                    <flux:error name="app_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Application URL') }}</flux:label>
                    <flux:input value="{{ $this->appUrl }}" disabled />
                    <flux:description>{{ __('Set via APP_URL in .env — cannot be changed here.') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default language') }}</flux:label>
                    <flux:select wire:model="app_locale" class="max-w-xs">
                        <flux:select.option value="en">{{ __('English') }}</flux:select.option>
                        <flux:select.option value="fr">{{ __('French') }}</flux:select.option>
                    </flux:select>
                    <flux:description>{{ __('Users can override this in their profile settings.') }}</flux:description>
                    <flux:error name="app_locale" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('App version') }}</flux:label>
                    <flux:input value="{{ $this->appVersion }}" disabled class="max-w-xs font-mono" />
                    <flux:description>{{ __('Defined in the VERSION file. Update by deploying a new release.') }}</flux:description>
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>{{ __('Check for updates automatically') }}</flux:label>
                    <flux:switch wire:model="version_check_enabled" />
                    <flux:description>{{ __('Periodically checks GitHub for a newer release and displays a badge in the admin panel.') }}</flux:description>
                </flux:field>

                {{-- Logo upload ──────────────────────────────────────────── --}}
                <flux:separator text="{{ __('Application logo') }}" />

                <div class="space-y-3">
                    {{-- Current logo preview --}}
                    @if ($this->logoUrl)
                        <div class="flex items-center gap-4">
                            <img src="{{ $this->logoUrl }}" alt="{{ __('Current logo') }}" class="h-12 w-auto rounded-lg border border-zinc-200 object-contain dark:border-zinc-700">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="removeLogo"
                                class="text-red-500 hover:text-red-600"
                            >
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @else
                        <flux:text class="text-sm text-zinc-400">{{ __('No custom logo — using the built-in icon.') }}</flux:text>
                    @endif

                    {{-- Upload form (separate submit, not part of main settings save) --}}
                    <form wire:submit="uploadLogo" class="flex items-start gap-3" enctype="multipart/form-data">
                        <div class="flex-1">
                            <flux:input type="file" wire:model="logoFile" accept=".png,.jpg,.jpeg,.webp" />
                            <flux:error name="logoFile" />
                            <flux:description>{{ __('PNG, JPG or WebP — max 2 MB. SVG is not accepted.') }}</flux:description>
                        </div>
                        <flux:button type="submit" variant="filled" size="sm">{{ __('Upload') }}</flux:button>
                    </form>
                </div>

                <flux:field>
                    <flux:label>{{ __('Health check visibility') }}</flux:label>
                    <flux:select wire:model="health_check_visibility" class="max-w-xs">
                        <flux:select.option value="public">{{ __('Public — no authentication required') }}</flux:select.option>
                        <flux:select.option value="auth">{{ __('Authenticated users only') }}</flux:select.option>
                        <flux:select.option value="admin">{{ __('Admins only') }}</flux:select.option>
                    </flux:select>
                    <flux:description>{{ __('Controls who can access /health and /api/v1/health endpoints.') }}</flux:description>
                    <flux:error name="health_check_visibility" />
                </flux:field>
            </div>
        @endif

        {{-- Authentication --}}
        @if ($activeTab === 'auth')
            <div class="space-y-6 pt-4">

                <flux:separator text="{{ __('Local authentication') }}" />

                <flux:field variant="inline">
                    <flux:label>{{ __('Enable local login (email + password)') }}</flux:label>
                    <flux:switch wire:model="local_auth_enabled" />
                    <flux:description>{{ __('Disable only if SSO is the sole authentication method.') }}</flux:description>
                </flux:field>
                <flux:error name="local_auth_enabled" />

                <flux:field variant="inline">
                    <flux:label>{{ __('Allow user self-registration') }}</flux:label>
                    <flux:switch wire:model="registration_enabled" />
                    <flux:description>{{ __('If disabled, only admins can create accounts.') }}</flux:description>
                </flux:field>

                <flux:separator text="{{ __('SSO') }}" />

                <flux:field variant="inline">
                    <flux:label>{{ __('Enable SSO') }}</flux:label>
                    <flux:switch wire:model.live="sso_enabled" />
                </flux:field>

                <div @class(['space-y-4', 'opacity-40 pointer-events-none' => ! $sso_enabled])>

                    <flux:field>
                        <flux:label>{{ __('SSO provider') }}</flux:label>
                        <flux:select wire:model.live="sso_provider" class="max-w-xs">
                            <flux:select.option value="azure">{{ __('Azure AD') }}</flux:select.option>
                            <flux:select.option value="keycloak">{{ __('Keycloak / Generic OIDC') }}</flux:select.option>
                            <flux:select.option value="saml">{{ __('SAML 2.0') }}</flux:select.option>
                        </flux:select>
                        <flux:description>{{ __('The identity provider users will authenticate against.') }}</flux:description>
                        <flux:error name="sso_provider" />
                    </flux:field>

                    {{-- Azure AD ──────────────────────────────────────────────── --}}
                    @if ($sso_provider === 'azure')
                        <flux:field>
                            <flux:label>{{ __('Client ID') }}</flux:label>
                            <flux:input wire:model="azure_client_id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                            <flux:error name="azure_client_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Client Secret') }}</flux:label>
                            <flux:input wire:model="azure_client_secret" type="password" placeholder="{{ __('Leave blank to keep current value') }}" />
                            <flux:description>{{ __('Stored encrypted in the database.') }}</flux:description>
                            <flux:error name="azure_client_secret" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Tenant ID') }}</flux:label>
                            <flux:input wire:model="azure_tenant_id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                            <flux:description>{{ __('Use "common" for multi-tenant apps.') }}</flux:description>
                            <flux:error name="azure_tenant_id" />
                        </flux:field>

                        <flux:callout variant="info" icon="information-circle">
                            <flux:callout.heading>{{ __('Azure redirect URI') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Register this URI in your Azure App Registration:') }}
                                <code class="font-mono text-sm">{{ $this->appUrl }}/auth/sso/callback</code>
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    {{-- Generic OIDC (Keycloak, Okta, Auth0, Dex…) ───────────── --}}
                    @if ($sso_provider === 'keycloak')
                        <flux:field>
                            <flux:label>{{ __('Issuer URL') }}</flux:label>
                            <flux:input wire:model="oidc_issuer_url" placeholder="https://keycloak.example.com/realms/myrealm" />
                            <flux:description>{{ __('Discovery document is fetched automatically from {issuer}/.well-known/openid-configuration.') }}</flux:description>
                            <flux:error name="oidc_issuer_url" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Client ID') }}</flux:label>
                            <flux:input wire:model="oidc_client_id" />
                            <flux:error name="oidc_client_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Client Secret') }}</flux:label>
                            <flux:input wire:model="oidc_client_secret" type="password" placeholder="{{ __('Leave blank to keep current value') }}" />
                            <flux:description>{{ __('Stored encrypted in the database.') }}</flux:description>
                            <flux:error name="oidc_client_secret" />
                        </flux:field>

                        <flux:callout variant="info" icon="information-circle">
                            <flux:callout.heading>{{ __('OIDC redirect URI') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Register this redirect URI in your OIDC client:') }}
                                <code class="font-mono text-sm">{{ $this->appUrl }}/auth/sso/callback</code>
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    {{-- SAML 2.0 ──────────────────────────────────────────────── --}}
                    @if ($sso_provider === 'saml')
                        <flux:callout variant="success" icon="check-circle">
                            <flux:callout.heading>{{ __('SAML 2.0 is ready') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Your SP metadata is available at') }}
                                <code class="font-mono text-xs">{{ $this->appUrl }}/auth/saml/metadata</code>.
                                {{ __('Share this URL with your Identity Provider.') }}
                            </flux:callout.text>
                        </flux:callout>

                        <flux:separator text="{{ __('Identity Provider (IdP)') }}" />

                        <flux:field>
                            <flux:label>{{ __('IdP Entity ID') }}</flux:label>
                            <flux:input wire:model="saml_idp_entity_id" placeholder="https://idp.example.com/metadata" />
                            <flux:error name="saml_idp_entity_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('IdP SSO URL') }}</flux:label>
                            <flux:input wire:model="saml_idp_sso_url" placeholder="https://idp.example.com/sso/saml" />
                            <flux:error name="saml_idp_sso_url" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('IdP SLO URL') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional') }})</span></flux:label>
                            <flux:input wire:model="saml_idp_slo_url" placeholder="https://idp.example.com/slo/saml" />
                            <flux:error name="saml_idp_slo_url" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('IdP X.509 certificate') }}</flux:label>
                            <flux:textarea wire:model="saml_idp_certificate" rows="5" placeholder="MIIDXTCCAkWg..." class="font-mono text-xs" />
                            <flux:description>{{ __('PEM-encoded certificate — without the -----BEGIN/END CERTIFICATE----- header and footer.') }}</flux:description>
                            <flux:error name="saml_idp_certificate" />
                        </flux:field>

                        <flux:separator text="{{ __('Service Provider (SP)') }}" />

                        <flux:field>
                            <flux:label>{{ __('SP Entity ID') }}</flux:label>
                            <flux:input wire:model="saml_sp_entity_id" :placeholder="$this->appUrl" />
                            <flux:description>{{ __('Defaults to the metadata URL if left blank.') }}</flux:description>
                            <flux:error name="saml_sp_entity_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('SP X.509 certificate') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional — for SP signing') }})</span></flux:label>
                            <flux:textarea wire:model="saml_sp_x509cert" rows="4" placeholder="MIIDXTCCAkWg..." class="font-mono text-xs" />
                            <flux:description>{{ __('PEM — no header/footer. When both cert and key are set, outgoing SAML requests are signed.') }}</flux:description>
                            <flux:error name="saml_sp_x509cert" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('SP private key') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional — for SP signing') }})</span></flux:label>
                            <flux:textarea wire:model="saml_sp_private_key" rows="4" placeholder="{{ __('Leave blank to keep current value') }}" class="font-mono text-xs" />
                            <flux:description>{{ __('PEM — no header/footer. Stored encrypted in the database.') }}</flux:description>
                            <flux:error name="saml_sp_private_key" />
                        </flux:field>

                        <flux:separator text="{{ __('Attribute mapping') }}" />

                        <flux:field>
                            <flux:label>{{ __('Email attribute') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional') }})</span></flux:label>
                            <flux:input wire:model="saml_attr_email" placeholder="email" />
                            <flux:description>{{ __('SAML attribute that contains the user\'s email. Leave blank to use the NameID (recommended for most IdPs).') }}</flux:description>
                            <flux:error name="saml_attr_email" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Display name attribute') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional') }})</span></flux:label>
                            <flux:input wire:model="saml_attr_name" placeholder="displayName" />
                            <flux:description>{{ __('SAML attribute for the display name. When blank, auto-detects displayName / givenName from common IdP schemas.') }}</flux:description>
                            <flux:error name="saml_attr_name" />
                        </flux:field>
                    @endif

                </div>

                <flux:separator text="{{ __('SCIM provisioning') }}" />

                <flux:field>
                    <flux:label>{{ __('SCIM bearer token') }}</flux:label>
                    <flux:input wire:model="scim_bearer_token" type="password" placeholder="{{ __('Leave blank to keep current value') }}" />
                    <flux:description>{{ __('Token Azure AD uses to authenticate against /scim/v2/Users. Generate a strong random string (min. 32 chars). Stored encrypted.') }}</flux:description>
                    <flux:error name="scim_bearer_token" />
                </flux:field>

                <flux:callout variant="info" icon="information-circle">
                    <flux:callout.heading>{{ __('Azure SCIM endpoint') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Configure this URL in your Azure Enterprise Application → Provisioning:') }}
                        <code class="font-mono text-sm">{{ $this->appUrl }}/scim/v2</code>
                    </flux:callout.text>
                </flux:callout>
            </div>
        @endif

        {{-- Security --}}
        @if ($activeTab === 'security')
            <div class="space-y-4 pt-4">
                <flux:field variant="inline">
                    <flux:label>{{ __('Require 2FA for all users') }}</flux:label>
                    <flux:switch wire:model="two_factor_required" />
                    <flux:description>{{ __('Users who have not set up 2FA will be redirected to the setup page on login.') }}</flux:description>
                </flux:field>
            </div>
        @endif

        {{-- Aliases --}}
        @if ($activeTab === 'aliases')
            <div class="space-y-4 pt-4">
                <flux:field>
                    <flux:label>{{ __('Maximum aliases per user') }}</flux:label>
                    <flux:input wire:model="alias_max_per_user" type="number" min="1" max="1000" />
                    <flux:error name="alias_max_per_user" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default alias type') }}</flux:label>
                    <flux:select wire:model="alias_default_type" class="max-w-xs">
                        <flux:select.option value="session">{{ __('Session') }}</flux:select.option>
                        <flux:select.option value="duration">{{ __('Duration') }}</flux:select.option>
                        @if ($alias_allow_permanent)
                            <flux:select.option value="permanent">{{ __('Permanent') }}</flux:select.option>
                        @endif
                    </flux:select>
                    <flux:description>{{ __('The type pre-selected when a user creates a new alias.') }}</flux:description>
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>{{ __('Allow permanent aliases') }}</flux:label>
                    <flux:switch wire:model.live="alias_allow_permanent" />
                    <flux:description>{{ __('If disabled, users can only create session or duration aliases.') }}</flux:description>
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>{{ __('Allow custom addresses') }}</flux:label>
                    <flux:switch wire:model="alias_allow_custom" />
                    <flux:description>{{ __('If disabled, only randomly-generated addresses are allowed — users cannot choose their own local part.') }}</flux:description>
                </flux:field>
            </div>
        @endif

        {{-- Email --}}
        @if ($activeTab === 'email')
            <div class="space-y-4 pt-4">
                <flux:field>
                    <flux:label>{{ __('Max email size (MB)') }}</flux:label>
                    <flux:input wire:model="alias_max_email_size_mb" type="number" min="1" max="100" />
                    <flux:description>{{ __('Emails exceeding this limit are stored as headers-only (body truncated).') }}</flux:description>
                    <flux:error name="alias_max_email_size_mb" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Max attachment size (MB)') }}</flux:label>
                    <flux:input wire:model="alias_max_attachment_size_mb" type="number" min="1" max="50" />
                    <flux:description>{{ __('Attachments larger than this are discarded (metadata kept).') }}</flux:description>
                    <flux:error name="alias_max_attachment_size_mb" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Max mailbox size (MB)') }}</flux:label>
                    <flux:input wire:model="alias_max_mailbox_size_mb" type="number" min="0" max="102400" />
                    <flux:description>{{ __('Maximum total storage per individual mailbox (alias). 0 = unlimited. Exceeding this drops the incoming email and notifies the owner.') }}</flux:description>
                    <flux:error name="alias_max_mailbox_size_mb" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Max user storage (MB)') }}</flux:label>
                    <flux:input wire:model="alias_max_user_storage_mb" type="number" min="0" max="1048576" />
                    <flux:description>{{ __('Maximum total storage across ALL mailboxes owned by a single user. 0 = unlimited. Checked in addition to the per-mailbox limit — the stricter limit wins.') }}</flux:description>
                    <flux:error name="alias_max_user_storage_mb" />
                </flux:field>

                <flux:callout variant="info" icon="information-circle" class="text-xs">
                    <flux:callout.heading>{{ __('Storage limits are additive') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Both limits are enforced independently. An email is dropped if either the per-mailbox quota OR the per-user quota is exceeded. Set both to 0 for unlimited storage.') }}</flux:callout.text>
                </flux:callout>

                <flux:field>
                    <flux:label>{{ __('Retention period (days)') }}</flux:label>
                    <flux:input wire:model="cleanup_retention_days" type="number" min="0" max="3650" />
                    <flux:description>{{ __('Soft-deleted aliases and emails are permanently removed after this many days. Set to 0 to purge immediately on the next cleanup run.') }}</flux:description>
                    <flux:error name="cleanup_retention_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Audit log retention (days)') }}</flux:label>
                    <flux:input wire:model="audit_log_retention_days" type="number" min="0" max="3650" />
                    <flux:description>{{ __('Audit logs older than this many days are automatically deleted. Set to 0 to keep logs indefinitely.') }}</flux:description>
                    <flux:error name="audit_log_retention_days" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>{{ __('Allow admins to read email bodies') }}</flux:label>
                    <flux:switch wire:model="admin_can_read_emails" />
                    <flux:description class="text-red-600 dark:text-red-400">{{ __('Enabling this gives admins full access to email content. Use with caution.') }}</flux:description>
                </flux:field>
            </div>
        @endif

        {{-- Domains --}}
        @if ($activeTab === 'domains')
            <div class="space-y-6 pt-4">

                <flux:callout variant="info" icon="information-circle">
                    <flux:callout.text>{{ __('Domains registered here determine which recipient addresses the SMTP receiver accepts. The primary domain is the default for new aliases.') }}</flux:callout.text>
                </flux:callout>

                {{-- Add domain form (NOT part of main settings save) --}}
                <form wire:submit.prevent="addDomain" class="flex items-start gap-3">
                    <div class="flex-1">
                        <flux:input
                            wire:model="newDomain"
                            placeholder="example.com"
                            label="{{ __('Add domain') }}"
                        />
                        <flux:error name="newDomain" />
                    </div>
                    <div class="pt-6">
                        <flux:button type="submit" variant="primary" size="sm">
                            {{ __('Add') }}
                        </flux:button>
                    </div>
                </form>

                {{-- Domain list --}}
                @if ($this->domains->isEmpty())
                    <flux:text class="text-sm text-zinc-400">
                        {{ __('No domains configured. The legacy domain from .env is used as fallback.') }}
                    </flux:text>
                @else
                    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($this->domains as $domain)
                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="truncate font-mono text-sm">{{ $domain->name }}</span>

                                    @if ($domain->is_primary)
                                        <flux:badge color="green" size="sm">{{ __('Primary') }}</flux:badge>
                                    @endif

                                    @if (isset($mxResults[$domain->id]))
                                        @if ($mxResults[$domain->id])
                                            <flux:badge color="green" size="sm" icon="check-circle">MX OK</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" icon="x-circle">{{ __('No MX') }}</flux:badge>
                                        @endif
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        wire:click="checkMx('{{ $domain->id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="checkMx('{{ $domain->id }}')"
                                    >
                                        {{ __('Check MX') }}
                                    </flux:button>

                                    @unless ($domain->is_primary)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            wire:click="setPrimary('{{ $domain->id }}')"
                                        >
                                            {{ __('Set primary') }}
                                        </flux:button>
                                    @endunless

                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="requestDeleteDomain('{{ $domain->id }}')"
                                        class="text-red-500 hover:text-red-600"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Delete confirmation modal --}}
                <flux:modal wire:model="showConfirmDeleteDomain" class="max-w-md">
                    <div class="space-y-4">
                        <flux:heading>{{ __('Remove domain?') }}</flux:heading>
                        <flux:text>{{ __('What should happen to aliases that use this domain?') }}</flux:text>

                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 @if($deleteDomainMode === 'keep') ring-2 ring-blue-500 @endif">
                                <input type="radio" wire:model.live="deleteDomainMode" value="keep" class="mt-0.5" />
                                <div>
                                    <div class="font-medium text-sm">{{ __('Keep aliases') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('Aliases remain active and will keep receiving mail. The domain will no longer appear in the domain selector for new aliases.') }}</div>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-red-200 p-3 dark:border-red-800 @if($deleteDomainMode === 'cascade') ring-2 ring-red-500 @endif">
                                <input type="radio" wire:model.live="deleteDomainMode" value="cascade" class="mt-0.5" />
                                <div>
                                    <div class="font-medium text-sm text-red-600 dark:text-red-400">{{ __('Delete all associated aliases') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('All aliases using this domain and their emails will be permanently deleted. This cannot be undone.') }}</div>
                                </div>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3">
                            <flux:button variant="ghost" wire:click="$set('showConfirmDeleteDomain', false)">{{ __('Cancel') }}</flux:button>
                            <flux:button variant="danger" wire:click="deleteDomain">{{ __('Remove domain') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>

            </div>
        @endif

        {{-- API Tokens --}}
        @if ($activeTab === 'app-tokens')
            <div class="space-y-6 pt-4">

                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>{{ __('App tokens grant machine-level access to protected API endpoints. Tokens are shown only once — copy them immediately after creation.') }}</flux:callout.text>
                </flux:callout>

                {{-- Newly created plain token --}}
                @if ($showPlainToken && $plainToken)
                    <flux:callout variant="success" icon="key">
                        <flux:callout.heading>{{ __('Token created — copy it now') }}</flux:callout.heading>
                        <flux:callout.text>
                            <code class="block select-all break-all rounded bg-zinc-100 p-2 font-mono text-sm dark:bg-zinc-800">{{ $plainToken }}</code>
                        </flux:callout.text>
                        <flux:button size="xs" variant="ghost" wire:click="dismissPlainToken" class="mt-2">
                            {{ __('I have copied this token') }}
                        </flux:button>
                    </flux:callout>
                @endif

                {{-- Create token form (NOT part of main settings save) --}}
                <form wire:submit.prevent="createAppToken" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Create new token') }}</flux:heading>

                    <flux:field>
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="newTokenName" placeholder="{{ __('e.g. SMTP receiver') }}" />
                        <flux:error name="newTokenName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Abilities') }} <span class="text-zinc-400 text-xs ml-1">({{ __('comma-separated') }})</span></flux:label>
                        <flux:input wire:model="newTokenAbilities" placeholder="read:domains" />
                        <flux:description>{{ __('Restrict what this token can do. Use * for unrestricted access. Example: read:domains') }}</flux:description>
                        <flux:error name="newTokenAbilities" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Expires at') }} <span class="text-zinc-400 text-xs ml-1">({{ __('optional') }})</span></flux:label>
                        <flux:input wire:model="newTokenExpiresAt" type="date" class="max-w-xs" />
                        <flux:description>{{ __('Leave blank for a non-expiring token.') }}</flux:description>
                        <flux:error name="newTokenExpiresAt" />
                    </flux:field>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" size="sm">
                            {{ __('Create token') }}
                        </flux:button>
                    </div>
                </form>

                {{-- Token list --}}
                @if ($this->appTokens->isEmpty())
                    <flux:text class="text-sm text-zinc-400">{{ __('No tokens created yet.') }}</flux:text>
                @else
                    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($this->appTokens as $token)
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-medium text-sm">{{ $token->name }}</span>
                                        @if ($token->expires_at?->isPast())
                                            <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                                        @elseif ($token->expires_at)
                                            <flux:badge color="yellow" size="sm">{{ __('Expires') }} {{ $token->expires_at->toDateString() }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('No expiry') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (($token->abilities ?? ['*']) as $ability)
                                            <flux:badge color="blue" size="sm" class="font-mono">{{ $ability }}</flux:badge>
                                        @endforeach
                                    </div>
                                    <flux:text class="text-xs text-zinc-400">
                                        {{ __('Created') }} {{ $token->created_at->diffForHumans() }}
                                        @if ($token->last_used_at)
                                            · {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                        @else
                                            · {{ __('Never used') }}
                                        @endif
                                    </flux:text>
                                </div>

                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="requestRevokeToken('{{ $token->id }}')"
                                    class="shrink-0 text-red-500 hover:text-red-600"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Revoke confirmation modal --}}
                <flux:modal wire:model="showConfirmRevokeToken" class="max-w-sm">
                    <div class="space-y-4">
                        <flux:heading>{{ __('Revoke token?') }}</flux:heading>
                        <flux:text>{{ __('Any service using this token will immediately lose access. This cannot be undone.') }}</flux:text>
                        <div class="flex justify-end gap-3">
                            <flux:button variant="ghost" wire:click="$set('showConfirmRevokeToken', false)">{{ __('Cancel') }}</flux:button>
                            <flux:button variant="danger" wire:click="revokeToken">{{ __('Revoke') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>

            </div>
        @endif

        {{-- Save button (hidden on tabs that manage their own actions) --}}
        @if (! in_array($activeTab, ['domains', 'app-tokens']))
        <div class="flex justify-end pt-2">
            <flux:button type="submit" variant="primary">
                {{ __('Save settings') }}
            </flux:button>
        </div>
        @endif

    </form>

</div>
