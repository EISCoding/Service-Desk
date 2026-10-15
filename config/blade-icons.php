<?php

/*
 * Icons: Boxicons 2.1.4 (MIT), stored in resources/svg/boxicons.
 * Regular icons:  <x-bx-envelope />   Solid icons:  <x-bxs-envelope />
 * Names in PHP (navigation, buttons) go through App\Support\Icon::component().
 */
return [

    'sets' => [
        'boxicons' => [
            'path' => 'resources/svg/boxicons/regular',
            'prefix' => 'bx',
        ],
        'boxicons-solid' => [
            'path' => 'resources/svg/boxicons/solid',
            'prefix' => 'bxs',
        ],
    ],

    'class' => '',

    'attributes' => [],

    'fallback' => '',

    'components' => [
        'disabled' => false,
        'default' => 'icon',
    ],

];
