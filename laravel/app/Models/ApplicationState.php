<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationState extends Model
{
    protected $table = 'application_state';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return self::find($key)?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}