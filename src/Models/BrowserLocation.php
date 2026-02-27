<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BrowserLocation extends Model
{
    protected $table = 'browser_locations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'locationable_type',
        'locationable_id',
        'collection_name',
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'accuracy_meters',
        'accuracy_level',
        'is_accurate',
        'address',
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
        'locationable_id' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'accuracy_meters' => 'float',
        'is_accurate' => 'boolean',
        'meta' => 'array',
        'captured_at' => 'datetime',
    ];

    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }
}
