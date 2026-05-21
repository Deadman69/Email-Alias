<?php

namespace App\Enums;

enum SsoProvider: string
{
    case Azure    = 'azure';
    case Keycloak = 'keycloak'; // Generic OIDC — Keycloak, Okta, Auth0, Dex, etc.
    case Saml     = 'saml';     // SAML 2.0 — requires aacotroneo/laravel-saml2

    public function label(): string
    {
        return match ($this) {
            self::Azure    => 'Azure AD',
            self::Keycloak => 'Keycloak / Generic OIDC',
            self::Saml     => 'SAML 2.0',
        };
    }

    public function driver(): string
    {
        return match ($this) {
            self::Azure    => 'azure',
            self::Keycloak => 'oidc',
            self::Saml     => 'saml',
        };
    }
}
