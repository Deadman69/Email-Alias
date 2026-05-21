<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform setting stored in the database.
 * The primary key is the setting key (string), not an auto-increment integer.
 * All reads/writes should go through App\Services\SettingService, which
 * handles caching and type casting.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'group'];
}
