<?php

namespace App\Models;

use Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
