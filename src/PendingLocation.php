<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Illuminate\Database\Eloquent\Model;
use Mayaram\BrowserLocation\Models\BrowserLocation as BrowserLocationModel;

class PendingLocation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly Model $locationable,
        private readonly array $data
    ) {
    }

    public function toLocationCollection(string $name = 'default'): BrowserLocationModel
    {
        return app(LocationPersister::class)->persist(
            $this->locationable,
            $this->data,
            $name,
            false
        );
    }

    public function toSingleLocationCollection(string $name = 'default'): BrowserLocationModel
    {
        return app(LocationPersister::class)->persist(
            $this->locationable,
            $this->data,
            $name,
            true
        );
    }
}
