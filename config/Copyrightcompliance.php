<?php

return [
    'parser' => [
        'name'          => 'Copyright Compliance',
        'enabled'       => true,
        'report_file'   => '/^.*\.xml/i',
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
