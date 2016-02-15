<?php

return [
    'parser' => [
        'name'          => 'Copyright Compliance',
        'enabled'       => true,
        'report_file'   => '/^.*\.xml/i',
        'sender_map'    => [
            '/@copyright-compliance.com/',
            '/noreply@p2p.copyright-notice.com/',
        ],
        'body_map'      => [
            //
        ],
    ],

    'feeds' => [
        'default' => [
            'class'     => 'COPYRIGHT_INFRINGEMENT',
            'type'      => 'ABUSE',
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
