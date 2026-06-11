<?php

use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\Attachment;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Support\Str;

// --- Web routes ---

beforeEach(function () {
    $this->user      = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

test('user cannot open the mailbox of another user', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);

    $this->actingAs($this->user)
        ->get(route('mailbox.inbox', $alias))
        ->assertForbidden();
});

test('user cannot see emails listed for a mailbox they do not own', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    InboundEmail::factory()->count(3)->create(['alias_id' => $alias->id]);

    $this->actingAs($this->user)
        ->get(route('mailbox.inbox', $alias))
        ->assertForbidden();
});

test('user cannot view an email that belongs to another user', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);

    $this->actingAs($this->user)
        ->get(route('mailbox.email', $email))
        ->assertForbidden();
});

test('shared alias is accessible by the recipient', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);

    AliasShare::create([
        'alias_id'     => $alias->id,
        'user_id'      => $this->user->id,
        'shared_by_id' => $this->otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('mailbox.inbox', $alias))
        ->assertOk();
});

test('shared alias is not accessible by an unrelated third user', function () {
    $alias     = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $thirdUser = User::factory()->create();

    AliasShare::create([
        'alias_id'     => $alias->id,
        'user_id'      => $this->user->id,
        'shared_by_id' => $this->otherUser->id,
    ]);

    $this->actingAs($thirdUser)
        ->get(route('mailbox.inbox', $alias))
        ->assertForbidden();
});

test('accessing a soft-deleted alias mailbox returns a graceful redirect, not a 500', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $alias->delete(); // soft-delete

    $this->actingAs($this->user)
        ->get(route('mailbox.inbox', $alias->id))
        ->assertNotFound();
});

test('user cannot download an attachment belonging to another users email', function () {
    $alias      = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $email      = InboundEmail::factory()->create(['alias_id' => $alias->id]);
    $attachment = Attachment::create([
        'email_id'          => $email->id,
        'original_filename' => 'secret.pdf',
        'stored_filename'   => 'secret.pdf',
        'mime_type'         => 'application/pdf',
        'size_bytes'        => 1024,
        'disk'              => 'local',
        'path'              => 'attachments/' . Str::ulid() . '.pdf',
        'checksum'          => sha1('fake'),
    ]);

    $this->actingAs($this->user)
        ->get(route('attachment.show', $attachment))
        ->assertForbidden();
});
