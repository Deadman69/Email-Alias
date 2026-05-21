<div class="max-w-3xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('Platform Settings') }}</flux:heading>
        <flux:subheading>{{ __('These settings override .env values in real time. No server restart required.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Tab navigation --}}
        <flux:tab.group>
            <flux:tabs wire:model="activeTab">
                <flux:tab name="general">{{ __('General') }}</flux:tab>
                <flux:tab name="auth">{{ __('Authentication') }}</flux:tab>
                <flux:tab name="security">{{ __('Security') }}</flux:tab>
                <flux:tab name="aliases">{{ __('Aliases') }}</flux:tab>
                <flux:tab name="email">{{ __('Email') }}</flux:tab>
            </flux:tabs>

            {{-- General --}}
            <flux:tab.panel name="general">
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
            </flux:tab.panel>

            {{-- Authentication --}}
            <flux:tab.panel name="auth">
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

                    <flux:separator text="{{ __('SSO — Azure AD') }}" />

                    <flux:field variant="inline">
                        <flux:label>{{ __('Enable SSO (Azure AD)') }}</flux:label>
                        <flux:switch wire:model="sso_enabled" />
                    </flux:field>

                    <div @class(['space-y-4', 'opacity-40 pointer-events-none' => ! $sso_enabled])>
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
                    </div>

                    <flux:separator text="{{ __('SCIM provisioning (Azure AD)') }}" />

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
            </flux:tab.panel>

            {{-- Security --}}
            <flux:tab.panel name="security">
                <div class="space-y-4 pt-4">
                    <flux:field variant="inline">
                        <flux:label>{{ __('Require 2FA for all users') }}</flux:label>
                        <flux:switch wire:model="two_factor_required" />
                        <flux:description>{{ __('Users who have not set up 2FA will be redirected to the setup page on login.') }}</flux:description>
                    </flux:field>
                </div>
            </flux:tab.panel>

            {{-- Aliases --}}
            <flux:tab.panel name="aliases">
                <div class="space-y-4 pt-4">
                    <flux:field>
                        <flux:label>{{ __('Maximum aliases per user') }}</flux:label>
                        <flux:input wire:model="alias_max_per_user" type="number" min="1" max="1000" />
                        <flux:error name="alias_max_per_user" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Default alias type') }}</flux:label>
                        <flux:select wire:model="alias_default_type">
                            <flux:option value="session">{{ __('Session') }}</flux:option>
                            <flux:option value="duration">{{ __('Duration') }}</flux:option>
                            <flux:option value="permanent">{{ __('Permanent') }}</flux:option>
                        </flux:select>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:label>{{ __('Allow permanent aliases') }}</flux:label>
                        <flux:switch wire:model="alias_allow_permanent" />
                        <flux:description>{{ __('If disabled, users can only create session or duration aliases.') }}</flux:description>
                    </flux:field>
                </div>
            </flux:tab.panel>

            {{-- Email --}}
            <flux:tab.panel name="email">
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
                        <flux:description>{{ __('Maximum total storage per mailbox. 0 means unlimited.') }}</flux:description>
                        <flux:error name="alias_max_mailbox_size_mb" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Max user storage (MB)') }}</flux:label>
                        <flux:input wire:model="alias_max_user_storage_mb" type="number" min="0" max="1048576" />
                        <flux:description>{{ __('Maximum total storage across all mailboxes for a single user. 0 means unlimited.') }}</flux:description>
                        <flux:error name="alias_max_user_storage_mb" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Retention period (days)') }}</flux:label>
                        <flux:input wire:model="cleanup_retention_days" type="number" min="0" max="3650" />
                        <flux:description>{{ __('Soft-deleted aliases and emails are permanently removed after this many days. Set to 0 to purge immediately on the next cleanup run.') }}</flux:description>
                        <flux:error name="cleanup_retention_days" />
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:label>{{ __('Allow admins to read email bodies') }}</flux:label>
                        <flux:switch wire:model="admin_can_read_emails" />
                        <flux:description class="text-red-600 dark:text-red-400">{{ __('Enabling this gives admins full access to email content. Use with caution.') }}</flux:description>
                    </flux:field>
                </div>
            </flux:tab.panel>

        </flux:tab.group>

        {{-- Save button --}}
        <div class="flex justify-end pt-2">
            <flux:button type="submit" variant="primary">
                {{ __('Save settings') }}
            </flux:button>
        </div>

    </form>

</div>
