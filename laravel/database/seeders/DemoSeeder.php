<?php

namespace Database\Seeders;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $domain = config('emailalias.domain', 'dev.local');

        // ── Users ─────────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'              => 'Admin',
                'password'          => Hash::make('password'),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ]
        );

        $dev = User::firstOrCreate(
            ['email' => 'dev@example.com'],
            [
                'name'              => 'Paul Développeur',
                'password'          => Hash::make('password'),
                'is_admin'          => false,
                'email_verified_at' => now(),
            ]
        );

        // ── Aliases ───────────────────────────────────────────────────────────
        $sessionAlias = Alias::firstOrCreate(
            ['address' => "session-test@{$domain}"],
            [
                'local_part' => 'session-test',
                'type'       => AliasType::Session,
                'user_id'    => $dev->id,
                'expires_at' => now()->addHours(2),
            ]
        );

        $durationAlias = Alias::firstOrCreate(
            ['address' => "paul-projet@{$domain}"],
            [
                'local_part' => 'paul-projet',
                'type'       => AliasType::Duration,
                'duration'   => '7d',
                'label'      => 'Projet Alpha',
                'user_id'    => $dev->id,
                'expires_at' => now()->addDays(7),
            ]
        );

        $permanentAlias = Alias::firstOrCreate(
            ['address' => "paul-permanent@{$domain}"],
            [
                'local_part' => 'paul-permanent',
                'type'       => AliasType::Permanent,
                'label'      => 'Tests régression',
                'user_id'    => $dev->id,
                'expires_at' => null,
            ]
        );

        // ── Inbound emails ────────────────────────────────────────────────────
        $emails = [
            [
                'alias'        => $durationAlias,
                'from_address' => 'noreply@github.com',
                'from_name'    => 'GitHub',
                'subject'      => '[EmailAlias] Pull request opened by octocat',
                'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2>Pull Request #42</h2><p>octocat opened a pull request on <strong>EmailAlias</strong>.</p><p><a href="https://github.com">View on GitHub</a></p><img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png" alt="GitHub" width="32"></div>',
                'body_text'    => "Pull Request #42\noctocat opened a pull request on EmailAlias.\nhttps://github.com",
                'read_at'      => null,
            ],
            [
                'alias'        => $durationAlias,
                'from_address' => 'notifications@gitlab.com',
                'from_name'    => 'GitLab',
                'subject'      => 'Pipeline failed for main · EmailAlias',
                'body_html'    => '<div style="font-family:sans-serif;padding:20px;color:#333"><h2 style="color:#e24329">Pipeline Failed</h2><p>The pipeline for branch <code>main</code> has failed at stage <em>test</em>.</p><ul><li>Job: <strong>pest</strong></li><li>Duration: 1m 23s</li></ul><p><a href="https://gitlab.com" style="background:#fc6d26;color:white;padding:8px 16px;text-decoration:none;border-radius:4px">View Pipeline</a></p></div>',
                'body_text'    => "Pipeline Failed\nThe pipeline for main has failed.\nJob: pest\nhttps://gitlab.com",
                'read_at'      => now()->subHours(1),
            ],
            [
                'alias'        => $permanentAlias,
                'from_address' => 'no-reply@stripe.com',
                'from_name'    => 'Stripe',
                'subject'      => 'Your test payment was successful',
                'body_html'    => '<div style="font-family:sans-serif;max-width:600px;margin:auto;padding:20px"><div style="background:#635bff;color:white;padding:20px;border-radius:8px 8px 0 0"><h1 style="margin:0">Payment Successful</h1></div><div style="background:#f6f9fc;padding:20px;border-radius:0 0 8px 8px"><p>A test payment of <strong>€42.00</strong> was processed successfully.</p><table style="width:100%;border-collapse:collapse"><tr><td style="padding:8px;border-bottom:1px solid #e6e9f0">Amount</td><td style="padding:8px;border-bottom:1px solid #e6e9f0;text-align:right"><strong>€42.00</strong></td></tr><tr><td style="padding:8px">Card</td><td style="padding:8px;text-align:right">Visa ****4242</td></tr></table></div></div>',
                'body_text'    => "Payment Successful\nA test payment of €42.00 was processed.\nCard: Visa ****4242",
                'read_at'      => null,
            ],
            [
                'alias'        => $permanentAlias,
                'from_address' => 'alerts@sentry.io',
                'from_name'    => 'Sentry',
                'subject'      => '[ALERT] TypeError: Cannot read properties of undefined',
                'body_html'    => '<div style="font-family:monospace;padding:20px;background:#1e1e2e;color:#cdd6f4;border-radius:8px"><h3 style="color:#f38ba8">TypeError</h3><p style="color:#a6e3a1">Cannot read properties of undefined (reading \'id\')</p><pre style="background:#313244;padding:12px;border-radius:4px;overflow:auto">at AliasService.create (AliasService.php:42)</pre><p><a href="https://sentry.io" style="color:#89b4fa">View in Sentry</a></p></div>',
                'body_text'    => "TypeError: Cannot read properties of undefined\nat AliasService.create",
                'read_at'      => null,
            ],
            [
                'alias'        => $sessionAlias,
                'from_address' => 'verify@service.com',
                'from_name'    => 'Service Test',
                'subject'      => 'Verify your email address',
                'body_html'    => '<div style="font-family:sans-serif;padding:30px;text-align:center"><h2>Email Verification</h2><p>Click the button below to verify your email address.</p><a href="https://example.com/verify/abc123" style="display:inline-block;background:#3b82f6;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;margin:16px 0">Verify Email</a><p style="color:#666;font-size:12px">This link expires in 24 hours.</p></div>',
                'body_text'    => "Email Verification\nClick here to verify: https://example.com/verify/abc123",
                'read_at'      => null,
            ],
        ];

        foreach ($emails as $data) {
            InboundEmail::create([
                'alias_id'     => $data['alias']->id,
                'from_address' => $data['from_address'],
                'from_name'    => $data['from_name'],
                'subject'      => $data['subject'],
                'body_html'    => $data['body_html'],
                'body_text'    => $data['body_text'],
                'headers'      => ['message-id' => '<demo-' . uniqid() . '@example.com>'],
                'size_bytes'   => strlen($data['body_html'] ?? ''),
                'read_at'      => $data['read_at'],
            ]);
        }

        // ── Audit log samples ─────────────────────────────────────────────────
        AuditLog::create([
            'user_id'  => $dev->id,
            'event'    => AuditEvent::UserLogin,
            'metadata' => ['method' => 'password'],
            'ip_address' => '127.0.0.1',
        ]);

        AuditLog::create([
            'user_id'        => $dev->id,
            'event'          => AuditEvent::AliasCreated,
            'auditable_type' => Alias::class,
            'auditable_id'   => $durationAlias->id,
            'metadata'       => ['address' => $durationAlias->address, 'type' => 'duration'],
        ]);

        $this->command->info('Demo data seeded:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',     'admin@example.com', 'password'],
                ['Developer', 'dev@example.com',   'password'],
            ]
        );
    }
}
