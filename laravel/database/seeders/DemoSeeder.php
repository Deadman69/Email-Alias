<?php

namespace Database\Seeders;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Domains ───────────────────────────────────────────────────────────
        $primary = Domain::firstOrCreate(
            ['name' => 'mailbox.dev'],
            ['is_primary' => true, 'is_active' => true],
        );
        $secondary = Domain::firstOrCreate(
            ['name' => 'staging.io'],
            ['is_primary' => false, 'is_active' => true],
        );

        // ── Users ─────────────────────────────────────────────────────────────
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name'              => 'Super Admin',
                'password'          => Hash::make('password'),
                'role'              => Role::SuperAdmin,
                'email_verified_at' => now(),
            ]
        );

        $alice = User::firstOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name'              => 'Alice Martin',
                'password'          => Hash::make('password'),
                'role'              => Role::Admin,
                'email_verified_at' => now(),
            ]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name'              => 'Bob Dupont',
                'password'          => Hash::make('password'),
                'role'              => Role::User,
                'email_verified_at' => now(),
            ]
        );

        $carol = User::firstOrCreate(
            ['email' => 'carol@example.com'],
            [
                'name'              => 'Carol Sanchez',
                'password'          => Hash::make('password'),
                'role'              => Role::User,
                'email_verified_at' => now(),
            ]
        );

        // ── Aliases ───────────────────────────────────────────────────────────
        $aliasDef = [
            // Bob — primary domain
            'bob-support' => [
                'local_part' => 'support-projet',
                'domain'     => $primary->name,
                'domain_id'  => $primary->id,
                'type'       => AliasType::Permanent,
                'label'      => 'Support Projet X',
                'user_id'    => $bob->id,
            ],
            'bob-github' => [
                'local_part' => 'notif-github',
                'domain'     => $primary->name,
                'domain_id'  => $primary->id,
                'type'       => AliasType::Session,
                'label'      => 'GitHub Notifications',
                'user_id'    => $bob->id,
                'expires_at' => now()->addHour(),
            ],
            'bob-billing' => [
                'local_part' => 'stripe-2024',
                'domain'     => $primary->name,
                'domain_id'  => $primary->id,
                'type'       => AliasType::Duration,
                'duration'   => '30d',
                'label'      => 'Billing Stripe',
                'user_id'    => $bob->id,
                'expires_at' => now()->addDays(30),
            ],
            // Bob — secondary domain
            'bob-freelance' => [
                'local_part' => 'freelance',
                'domain'     => $secondary->name,
                'domain_id'  => $secondary->id,
                'type'       => AliasType::Permanent,
                'label'      => 'Missions freelance',
                'user_id'    => $bob->id,
            ],
            // Carol — primary domain
            'carol-signup' => [
                'local_part' => 'signup-tmp',
                'domain'     => $primary->name,
                'domain_id'  => $primary->id,
                'type'       => AliasType::Session,
                'label'      => 'Tests inscription',
                'user_id'    => $carol->id,
                'expires_at' => now()->addHours(2),
            ],
            'carol-sentry' => [
                'local_part' => 'sentry-alerts',
                'domain'     => $primary->name,
                'domain_id'  => $primary->id,
                'type'       => AliasType::Permanent,
                'label'      => 'Alertes Sentry',
                'user_id'    => $carol->id,
            ],
            // Alice (admin) — for completeness
            'alice-ops' => [
                'local_part' => 'ops-alert',
                'domain'     => $secondary->name,
                'domain_id'  => $secondary->id,
                'type'       => AliasType::Duration,
                'duration'   => '7d',
                'label'      => 'OPS Monitoring',
                'user_id'    => $alice->id,
                'expires_at' => now()->addDays(7),
            ],
        ];

        $aliases = [];
        foreach ($aliasDef as $key => $data) {
            $address = "{$data['local_part']}@{$data['domain']}";
            $aliases[$key] = Alias::firstOrCreate(
                ['address' => $address],
                array_merge($data, ['is_active' => true]),
            );
        }

        // ── Emails — Bob (support alias) ──────────────────────────────────────
        $this->email($aliases['bob-support'], [
            'from_address' => 'noreply@github.com',
            'from_name'    => 'GitHub',
            'subject'      => '[repo/email-alias] Pull request opened by octocat',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2>Pull Request #47</h2><p><strong>octocat</strong> opened a pull request on <strong>repo/email-alias</strong>.</p><blockquote>Add support for DKIM verification on inbound emails</blockquote><p><a href="#" style="background:#238636;color:white;padding:8px 16px;text-decoration:none;border-radius:6px">Review PR</a></p></div>',
            'body_text'    => "PR #47 opened by octocat\nAdd support for DKIM verification on inbound emails",
            'read_at'      => null,
        ]);

        $this->email($aliases['bob-support'], [
            'from_address' => 'noreply@github.com',
            'from_name'    => 'GitHub',
            'subject'      => '[repo/email-alias] CI failed on branch fix/alias-expiry',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2 style="color:#da3633">CI Failed</h2><p>Job <code>pest (PHP 8.4)</code> failed on branch <strong>fix/alias-expiry</strong>.</p><pre style="background:#f6f8fa;padding:12px;border-radius:6px">FAILED Tests\\Feature\\Mailbox\\InboxTest::it marks email as read</pre><p><a href="#">View run</a></p></div>',
            'body_text'    => "CI Failed\nJob pest (PHP 8.4) failed on fix/alias-expiry",
            'read_at'      => now()->subHours(2),
        ]);

        $this->email($aliases['bob-support'], [
            'from_address' => 'support@jira.atlassian.com',
            'from_name'    => 'Jira',
            'subject'      => '[EMAIL-42] Alias email not marked read on first view',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><p>A ticket was assigned to you.</p><table style="width:100%;border-collapse:collapse"><tr><td style="padding:8px;border:1px solid #dfe1e6"><strong>Summary</strong></td><td style="padding:8px;border:1px solid #dfe1e6">Alias email not marked read on first view</td></tr><tr><td style="padding:8px;border:1px solid #dfe1e6"><strong>Priority</strong></td><td style="padding:8px;border:1px solid #dfe1e6;color:#e5493a">High</td></tr></table></div>',
            'body_text'    => "Ticket EMAIL-42 assigned to you\nAlias email not marked read on first view\nPriority: High",
            'read_at'      => null,
        ]);

        // ── Emails — Bob (billing alias) ──────────────────────────────────────
        $this->email($aliases['bob-billing'], [
            'from_address' => 'receipts@stripe.com',
            'from_name'    => 'Stripe',
            'subject'      => 'Your receipt from Stripe — Invoice #INV-2024-0041',
            'body_html'    => '<div style="font-family:sans-serif;max-width:600px;margin:auto;padding:20px"><div style="background:#635bff;color:white;padding:20px;border-radius:8px 8px 0 0"><h1 style="margin:0">Receipt</h1></div><div style="padding:20px;background:#f6f9fc;border-radius:0 0 8px 8px"><table style="width:100%"><tr><td>Invoice</td><td style="text-align:right"><strong>#INV-2024-0041</strong></td></tr><tr><td>Amount paid</td><td style="text-align:right"><strong>€129.00</strong></td></tr><tr><td>Card</td><td style="text-align:right">Visa ****4242</td></tr><tr><td>Date</td><td style="text-align:right">2 Jun 2024</td></tr></table><p><a href="#" style="background:#635bff;color:white;padding:8px 16px;border-radius:4px;text-decoration:none">Download PDF</a></p></div></div>',
            'body_text'    => "Receipt — Invoice #INV-2024-0041\nAmount: €129.00\nCard: Visa ****4242",
            'read_at'      => now()->subDay(),
        ]);

        $this->email($aliases['bob-billing'], [
            'from_address' => 'billing@vercel.com',
            'from_name'    => 'Vercel',
            'subject'      => 'Your Pro plan invoice for May 2024',
            'body_html'    => '<div style="font-family:sans-serif;padding:24px"><img src="" alt="Vercel" style="height:32px"><h2>Invoice — May 2024</h2><p>Your Pro plan has been billed.</p><table style="width:100%;border-collapse:collapse"><tr style="background:#f4f4f5"><td style="padding:10px">Pro Plan</td><td style="padding:10px;text-align:right">$20.00</td></tr></table><p style="color:#666;font-size:13px">Thank you for using Vercel.</p></div>',
            'body_text'    => "Invoice May 2024\nPro Plan: $20.00",
            'read_at'      => null,
        ]);

        // ── Emails — Bob (github alias — session, expires soon) ───────────────
        $this->email($aliases['bob-github'], [
            'from_address' => 'noreply@github.com',
            'from_name'    => 'GitHub',
            'subject'      => 'Verify your GitHub email address',
            'body_html'    => '<div style="font-family:sans-serif;padding:30px;text-align:center"><h2>Confirm your email address</h2><p>Click the button to verify your address on GitHub.</p><a href="#" style="display:inline-block;background:#238636;color:white;padding:12px 24px;text-decoration:none;border-radius:6px">Verify email</a><p style="color:#666;font-size:12px">This link expires in 24 hours.</p></div>',
            'body_text'    => "Confirm your email address\nhttps://github.com/verify/token123",
            'read_at'      => null,
        ]);

        // ── Emails — Bob (freelance alias) ────────────────────────────────────
        $this->email($aliases['bob-freelance'], [
            'from_address' => 'hello@malt.com',
            'from_name'    => 'Malt',
            'subject'      => 'Une nouvelle mission correspond à votre profil',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2>Nouvelle opportunité</h2><p>Un client recherche un développeur Laravel pour une mission de 3 mois.</p><ul><li><strong>Durée :</strong> 3 mois</li><li><strong>TJM :</strong> 600€</li><li><strong>Lieu :</strong> Remote</li></ul><a href="#" style="background:#ff5533;color:white;padding:10px 20px;text-decoration:none;border-radius:4px">Voir la mission</a></div>',
            'body_text'    => "Nouvelle mission Laravel — 3 mois, 600€/j, Remote",
            'read_at'      => null,
        ]);

        $this->email($aliases['bob-freelance'], [
            'from_address' => 'hello@comet.co',
            'from_name'    => 'Comet',
            'subject'      => 'Votre profil a été consulté 5 fois cette semaine',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2>Activité de votre profil</h2><p>Votre profil a été consulté <strong>5 fois</strong> cette semaine.</p><p>3 entreprises ont enregistré votre profil.</p><a href="#">Voir les visiteurs</a></div>',
            'body_text'    => "Votre profil a été consulté 5 fois cette semaine",
            'read_at'      => now()->subDays(2),
        ]);

        // ── Emails — Carol (sentry alias) ─────────────────────────────────────
        $this->email($aliases['carol-sentry'], [
            'from_address' => 'alerts@sentry.io',
            'from_name'    => 'Sentry',
            'subject'      => '[ALERT] TypeError in ProcessInboundEmail.php — 14 occurrences',
            'body_html'    => '<div style="font-family:monospace;padding:20px;background:#1e1e2e;color:#cdd6f4;border-radius:8px"><h3 style="color:#f38ba8">TypeError — 14 occurrences (last 1h)</h3><p style="color:#a6e3a1">Cannot read properties of undefined (reading \'alias_id\')</p><pre style="background:#313244;padding:12px;border-radius:4px">at ProcessInboundEmail::handle (line 87)</pre><p><a href="#" style="color:#89b4fa">View in Sentry →</a></p></div>',
            'body_text'    => "TypeError — 14 occurrences\nCannot read properties of undefined (reading 'alias_id')",
            'read_at'      => null,
        ]);

        $this->email($aliases['carol-sentry'], [
            'from_address' => 'alerts@sentry.io',
            'from_name'    => 'Sentry',
            'subject'      => '[RESOLVED] TypeError in ProcessInboundEmail.php',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><h2 style="color:#238636">Issue Resolved</h2><p>The issue <strong>TypeError in ProcessInboundEmail.php</strong> has been resolved.</p></div>',
            'body_text'    => "Issue resolved: TypeError in ProcessInboundEmail.php",
            'read_at'      => now()->subHour(),
        ]);

        // ── Emails — Carol (signup alias) ─────────────────────────────────────
        $this->email($aliases['carol-signup'], [
            'from_address' => 'no-reply@slack.com',
            'from_name'    => 'Slack',
            'subject'      => "You've been invited to join workspace EmailAlias",
            'body_html'    => '<div style="font-family:sans-serif;padding:30px;text-align:center"><h2>You have been invited!</h2><p><strong>alice@example.com</strong> invited you to join the <strong>EmailAlias</strong> workspace on Slack.</p><a href="#" style="display:inline-block;background:#4a154b;color:white;padding:12px 32px;text-decoration:none;border-radius:4px">Accept Invitation</a></div>',
            'body_text'    => "You've been invited to join EmailAlias on Slack by alice@example.com",
            'read_at'      => null,
        ]);

        $this->email($aliases['carol-signup'], [
            'from_address' => 'no-reply@figma.com',
            'from_name'    => 'Figma',
            'subject'      => 'Confirm your email to get started with Figma',
            'body_html'    => '<div style="font-family:sans-serif;padding:30px;text-align:center"><h2>Welcome to Figma</h2><p>Please confirm your email address.</p><a href="#" style="background:#0acf83;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;margin-top:16px">Confirm email</a><p style="color:#999;font-size:12px">Link expires in 72 hours.</p></div>',
            'body_text'    => "Welcome to Figma — please confirm your email",
            'read_at'      => null,
        ]);

        // ── Emails — Alice (ops alias) ────────────────────────────────────────
        $this->email($aliases['alice-ops'], [
            'from_address' => 'noreply@uptime.robot',
            'from_name'    => 'UptimeRobot',
            'subject'      => 'ALERT: mailbox.dev is DOWN',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><div style="background:#e53e3e;color:white;padding:16px;border-radius:8px"><h2 style="margin:0">Monitor is DOWN</h2></div><p>Your monitor <strong>mailbox.dev</strong> is currently <strong>DOWN</strong>.</p><p>Downtime started: 2024-06-01 14:22:05 UTC</p></div>',
            'body_text'    => "Monitor mailbox.dev is DOWN since 2024-06-01 14:22:05 UTC",
            'read_at'      => null,
        ]);

        $this->email($aliases['alice-ops'], [
            'from_address' => 'noreply@uptime.robot',
            'from_name'    => 'UptimeRobot',
            'subject'      => 'RECOVERY: mailbox.dev is UP',
            'body_html'    => '<div style="font-family:sans-serif;padding:20px"><div style="background:#38a169;color:white;padding:16px;border-radius:8px"><h2 style="margin:0">Monitor is UP again</h2></div><p>Your monitor <strong>mailbox.dev</strong> is back online.</p><p>Downtime duration: 4 minutes 12 seconds</p></div>',
            'body_text'    => "Monitor mailbox.dev is UP — downtime: 4m 12s",
            'read_at'      => now()->subMinutes(30),
        ]);

        // ── Cross-user sharing ────────────────────────────────────────────────
        // Bob shares his support alias with Alice (read access)
        AliasShare::firstOrCreate(
            ['alias_id' => $aliases['bob-support']->id, 'user_id' => $alice->id],
            ['shared_by_id' => $bob->id],
        );
        // Carol shares her sentry alias with Alice
        AliasShare::firstOrCreate(
            ['alias_id' => $aliases['carol-sentry']->id, 'user_id' => $alice->id],
            ['shared_by_id' => $carol->id],
        );

        // ── Audit log samples ─────────────────────────────────────────────────
        foreach ([$alice, $bob, $carol] as $user) {
            AuditLog::create([
                'user_id'    => $user->id,
                'event'      => AuditEvent::UserLogin,
                'metadata'   => ['method' => 'password'],
                'ip_address' => '127.0.0.1',
            ]);
        }

        AuditLog::create([
            'user_id'        => $bob->id,
            'event'          => AuditEvent::AliasCreated,
            'auditable_type' => Alias::class,
            'auditable_id'   => $aliases['bob-support']->id,
            'metadata'       => ['address' => $aliases['bob-support']->address, 'type' => 'permanent'],
        ]);

        AuditLog::create([
            'user_id'        => $bob->id,
            'event'          => AuditEvent::AliasShared,
            'auditable_type' => Alias::class,
            'auditable_id'   => $aliases['bob-support']->id,
            'metadata'       => ['shared_with' => $alice->email],
        ]);

        // ── Summary ───────────────────────────────────────────────────────────
        $this->command->info('Demo data seeded:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                [Role::SuperAdmin->value, 'superadmin@example.com', 'password'],
                [Role::Admin->value,      'alice@example.com',       'password'],
                [Role::User->value,       'bob@example.com',         'password'],
                [Role::User->value,       'carol@example.com',       'password'],
            ]
        );
    }

    private function email(Alias $alias, array $data): InboundEmail
    {
        return InboundEmail::create([
            'alias_id'     => $alias->id,
            'from_address' => $data['from_address'],
            'from_name'    => $data['from_name'] ?? null,
            'subject'      => $data['subject'],
            'body_html'    => $data['body_html'] ?? null,
            'body_text'    => $data['body_text'] ?? null,
            'headers'      => ['message-id' => '<demo-' . uniqid() . '@example.com>'],
            'size_bytes'   => strlen($data['body_html'] ?? $data['body_text'] ?? ''),
            'read_at'      => $data['read_at'] ?? null,
        ]);
    }
}
