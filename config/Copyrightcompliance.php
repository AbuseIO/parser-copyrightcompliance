<?php

return [
    'parser' => [
        'name'          => 'IP-Echelon',
        'enabled'       => true,
        'sender_map'    => [
            '/@copyright-compliance.com/',
        ],
        'body_map'      => [
            //
        ],
    ],

    'feeds' => [
        'default' => [
            'class'     => 'Copyright Infringement',
            'type'      => 'Abuse',
            'enabled'   => true,
            'fields'    => [
                'Type',
                'Port',
                'IP_Address',
                'TimeStamp'
            ],
            'filters'    => [
                'Notes',
                'Verification',
                'Service_Provider',
            ],
        ],

    ],
];
