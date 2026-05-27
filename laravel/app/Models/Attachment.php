<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'email_id', 'original_filename', 'stored_filename', 'mime_type', 'size_bytes', 'disk', 'path', 'checksum',
    ];

    protected function casts(): array
    {
        return [
            'email_id'   => 'string',
            'size_bytes' => 'integer',
        ];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(InboundEmail::class, 'email_id');
    }

    /**
     * Whether the attachment is an inline image (displayable directly).
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Human-readable file size.
     */
    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    /**
     * Delete the file from storage when the model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}
