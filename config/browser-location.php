<?php

declare(strict_types=1);

return [
    'defaults' => [
        'enable_high_accuracy' => true,
        'timeout' => 12000,
        'maximum_age' => 0,
    ],

    'accuracy' => [
        'excellent_meters' => 20,
        'good_meters' => 100,
    ],

    'validation' => [
        'required' => false,
        'require_accuracy' => false,
        'max_accuracy_meters' => 200,
        'require_authentication' => false,
    ],

    'storage' => [
        'persist' => true,
        'attach_authenticated_user' => true,
        'coordinate_precision' => 7,
    ],

    'component' => [
        'button_text' => 'Share GPS location',
        'auto_capture' => false,
        'watch' => false,
        'livewire_method' => 'setBrowserLocation',
    ],
];
