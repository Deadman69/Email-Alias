<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a share of an alias with another user.
 * By default shared users can only view the inbox and read emails.
 * When `editable` is true, the recipient gets the same read/delete
 * permissions as the alias owner (excluding managing shares or deleting the alias itself).
 */
class AliasShare extends Model
{
    use HasUlids;

    protected $fillable = ['alias_id', 'user_id', 'shared_by_id', 'editable'];

    protected $casts = [
        'editable' => 'boolean',
    ];

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
