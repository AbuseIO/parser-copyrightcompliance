<?php

namespace AbuseIO\Parsers;

use ReflectionClass;
use Log;

class Copyrightcompliance extends Parser
{
    /**
     * Create a new Blocklistde instance
     */
    public function __construct($parsedMail, $arfMail)
    {
        // Call the parent constructor to initialize some basics
        parent::__construct($parsedMail, $arfMail);
    }

    /**
     * Parse attachments
     * @return Array    Returns array with failed or success data
     *                  (See parser-common/src/Parser.php) for more info.
     */
    public function parse()
    {
        // Generalize the local config based on the parser class name.
        $reflect = new ReflectionClass($this);
        $this->configBase = 'parsers.' . $reflect->getShortName();

        Log::info(
            get_class($this) . ': Received message from: ' .
            $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            config("{$this->configBase}.parser.name")
        );

        // ACNS: Automated Copyright Notice System
        $foundAcnsFile = false;

        foreach ($this->parsedMail->getAttachments() as $attachment) {
            // Only use the Copyrightcompliance formatted reports, skip all others
            if (preg_match(config("{$this->configBase}.parser.report_file"), $attachment->filename) &&
                $attachment->contentType == 'application/xml'
            ) {
                $xmlReport = $attachment->getContent();

                $this->saveEvent($xmlReport);
            }
        }

        // Sadly their report is not consistantly an attachment and might end up
        // in the body so we need to fallback to a body XML search if there was
        // nothing found in attachments.
        if ($foundAcnsFile === false) {
            preg_match(
                '/- - ---Start ACNS XML\n(.*)- - ---End ACNS XML/si',
                $this->parsedMail->getMessageBody(),
                $match
            );
            $xmlReport = $match[1];

            $this->saveEvent($xmlReport);
        }

        return $this->success();
    }

    private function saveEvent($report_xml)
    {
        if (!empty($report_xml) && $report_xml = simplexml_load_string($report_xml)) {
            $this->feedName = 'default';

            // If feed is known and enabled, validate data and save report
            if ($this->isKnownFeed() && $this->isEnabledFeed()) {
                // Create a corrected array
                $report_raw = json_decode(json_encode($report_xml), true);
                // Sanity check
                $report = $this->applyFilters($report_raw['Source']);
                if ($this->hasRequiredFields($report) === true) {
                    // Event has all requirements met, add!
                    $this->events[] = [
                        'source'        => config("{$this->configBase}.parser.name"),
                        'ip'            => $report['IP_Address'],
                        'domain'        => false,
                        'uri'           => false,
                        'class'         => config("{$this->configBase}.feeds.{$this->feedName}.class"),
                        'type'          => config("{$this->configBase}.feeds.{$this->feedName}.type"),
                        'timestamp'     => strtotime($report['TimeStamp']),
                        'information'   => json_encode($report_raw),
                    ];
                }
            }
        }
    }
}
