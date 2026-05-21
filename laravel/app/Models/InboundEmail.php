<?php

namespace App\Models;

use Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[UseFactory(InboundEmailFactory::class)]
#[Fillable([
    'alias_id', 'from_address', 'from_name', 'subject',
    'body_html', 'body_text', 'headers', 'size_bytes',
    'read_at', 'is_truncated', 'truncated_reason',
])]
class InboundEmail extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alias_id'         => 'string',
            'headers'          => 'array',
            'read_at'          => 'datetime',
            'is_truncated'     => 'boolean',
        ];
    }

    /**
     * Human-readable file size.
     */
    protected function humanSize(): Attribute
    {
        return Attribute::make(
            get: function () {
                $bytes = $this->size_bytes;
                if ($bytes < 1024) return $bytes . ' B';
                if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
                return round($bytes / (1024 * 1024), 1) . ' MB';
            }
        );
    }

    /**
     * When an email is deleted (soft or force), delete its attachments first so
     * physical files on disk are always cleaned up — DB-level FK cascades would
     * bypass Eloquent model events and leave orphan files.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $email) {
            // Load fresh so we still catch attachments even on forceDelete
            $email->attachments()->each(fn (Attachment $a) => $a->delete());
        });

        // Keep search_vector in sync whenever subject, sender, or body changes.
        static::saved(function (self $email): void {
            if ($email->wasRecentlyCreated || $email->wasChanged(['subject', 'from_address', 'from_name', 'body_text'])) {
                DB::statement("
                    UPDATE inbound_emails
                    SET search_vector =
                        setweight(to_tsvector('simple', coalesce(subject, '')), 'A') ||
                        setweight(to_tsvector('simple', coalesce(from_address, '')), 'B') ||
                        setweight(to_tsvector('simple', coalesce(from_name, '')), 'C') ||
                        setweight(to_tsvector('simple', coalesce(body_text, '')), 'D')
                    WHERE id = ?
                ", [$email->id]);
            }
        });
    }

    public function alias(): BelongsTo
    {
        return $this->belongsTo(Alias::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'email_id');
    }

    /**
     * Mark the email as read.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark the email as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Scope to only unread emails.
     */
    public function scopeUnread(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Scope to only read emails.
     */
    public function scopeRead(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotNull('read_at');
    }

    /**
     * Full-text search on subject, sender, and body.
     * Uses PostgreSQL tsvector + plainto_tsquery for multi-word queries.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->whereRaw(
            "search_vector @@ plainto_tsquery('simple', ?)",
            [$term]
        )->orderByRaw(
            "ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC",
            [$term]
        );
    }
}
