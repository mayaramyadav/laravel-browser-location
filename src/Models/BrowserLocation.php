<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Models;

use Illuminate\Database\Eloquent\Model;

class BrowserLocation extends Model
{
    protected $table = 'browser_locations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'accuracy_level',
        'is_accurate',
        'permission_state',
        'error_code',
        'error_message',
        'source',
        'meta',
        'captured_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'is_accurate' => 'boolean',
        'meta' => 'array',
        'captured_at' => 'datetime',
    ];
}
