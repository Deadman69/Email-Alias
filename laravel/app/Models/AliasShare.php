<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a read-only share of an alias with another user.
 * Shared users can view the inbox and read emails, but cannot
 * modify or delete the alias or its emails.
 */
class AliasShare extends Model
{
    use HasUlids;

    protected $fillable = ['alias_id', 'user_id', 'shared_by_id'];

    // ── Relations ─────────────────────────────────────────────────────────────────

    public function alias(): BelongsTo
    {
        return $this->belongsTo(Alias::class);
    }

    /**
     * The user this alias was shared with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who created the share.
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_id');
    }
}
