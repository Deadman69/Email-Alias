<?php

namespace App\Models;

use Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(InboundEmailFactory::class)]
#[Fillable(['alias_id', 'from_address', 'from_name', 'subject', 'body_html', 'body_text', 'headers', 'size_bytes', 'read_at'])]
class InboundEmail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function alias(): BelongsTo
    {
        return $this->belongsTo(Alias::class);
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
