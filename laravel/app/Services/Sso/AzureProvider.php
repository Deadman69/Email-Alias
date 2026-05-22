<?php

namespace App\Services\Sso;

/**
 * Azure AD / Entra ID OIDC provider.
 *
 * Azure supports the standard OIDC discovery protocol. This provider extends
 * OidcProvider to construct the correct issuer URL from the configured tenant ID
 * instead of requiring a separate `oidc_issuer_url` setting.
 *
 * Configure via Admin → Settings → Authentication:
 *   - azure_tenant_id    → your Azure AD tenant ID (or "common" for multi-tenant)
 *   - azure_client_id    → the Application (client) ID
 *   - azure_client_secret
 */
class AzureProvider extends OidcProvider
{
    protected function getIssuerUrl(): string
    {
        $tenantId = config('emailalias.sso_azure_tenant_id', 'common');

        if (empty($tenantId)) {
            throw new \RuntimeException(
                'Azure SSO is enabled but azure_tenant_id is not configured. '
                . 'Set it in Admin → Settings → Authentication.'
            );
        }

        // Microsoft identity platform v2.0 OIDC discovery endpoint.
        // Discovery doc: https://login.microsoftonline.com/{tenant}/v2.0/.well-known/openid-configuration
        return "https://login.microsoftonline.com/{$tenantId}/v2.0";
    }
}
