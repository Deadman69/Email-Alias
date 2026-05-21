<?php

namespace App\Providers;

use App\Listeners\DeleteSessionAliasesOnLogout;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Models\User;
use App\Policies\AliasPolicy;
use App\Policies\InboundEmailPolicy;
use App\Models\PersonalAccessToken;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
use App\Services\SettingService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Laravel\Sanctum\Sanctum;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(HtmlSanitizer::class);
        $this->app->singleton(SettingService::class);

        // Use our extended token model so restricted_alias_ids and expires_at work
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureDefaults();
        $this->registerListeners();
        $this->configureScramble();
    }

    /**
     * Register model policies.
     */
    private function registerListeners(): void
    {
        // Delete session-type aliases when the user logs out
        Event::listen(Logout::class, DeleteSessionAliasesOnLogout::class);
    }

    private function configurePolicies(): void
    {
        Gate::policy(Alias::class, AliasPolicy::class);
        Gate::policy(InboundEmail::class, InboundEmailPolicy::class);
    }

    /**
     * Configure Scramble (auto-generated OpenAPI spec).
     * The Bearer token security scheme is declared globally so every endpoint
     * shows the auth requirement without per-route annotation.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformer(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                        ->setDescription('Personal access token — create one in Settings → API Tokens.')
                );
            });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
