<?php

namespace AbuseIO\Parsers;

use AbuseIO\Models\Incident;

/**
 * Class Copyrightcompliance
 * @package AbuseIO\Parsers
 */
class Copyrightcompliance extends Parser
{
    /**
     * Create a new Copyrightcompliance instance
     *
     * @param \PhpMimeMailParser\Parser $parsedMail phpMimeParser object
     * @param array $arfMail array with ARF detected results
     */
    public function __construct($parsedMail, $arfMail)
    {
        // Call the parent constructor to initialize some basics
        parent::__construct($parsedMail, $arfMail, $this);
    }

    /**
     * Parse attachments
     * @return array    Returns array with failed or success data
     *                  (See parser-common/src/Parser.php) for more info.
     */
    public function parse()
    {
        // ACNS: Automated Copyright Notice System
        $foundAcnsFile = false;

        foreach ($this->parsedMail->getAttachments() as $attachment) {
            // Only use the Copyrightcompliance formatted reports, skip all others
            if (preg_match(config("{$this->configBase}.parser.report_file"), $attachment->filename) &&
                $attachment->contentType == 'application/xml'
            ) {
                $foundAcnsFile = true;

                $xmlReport = $attachment->getContent();

                $this->saveIncidents($xmlReport);
            }
        }

        // Sadly their report is not consistantly an attachment and might end up
        // in the body so we need to fallback to a body XML search if there was
        // nothing found in attachments.
        if ($foundAcnsFile === false) {
            if (preg_match(
                '/- - ---Start ACNS XML\n(.*)- - ---End ACNS XML/si',
                $this->parsedMail->getMessageBody(),
                $match
            )
            ) {
                $xmlReport = $match[1];

                $this->saveIncident($xmlReport);
            } else {
                $this->warningCount++;
            }
        }

        return $this->success();
    }

    /**
     * Uses the XML to create incidents
     *
     * @param string $report_xml
     */
    private function saveIncident($report_xml)
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
                    // incident has all requirements met, add!
                    $incident = new Incident();
                    $incident->source      = config("{$this->configBase}.parser.name");
                    $incident->source_id   = false;
                    $incident->ip          = $report['IP_Address'];
                    $incident->domain      = false;
                    $incident->uri         = false;
                    $incident->class       = config("{$this->configBase}.feeds.{$this->feedName}.class");
                    $incident->type        = config("{$this->configBase}.feeds.{$this->feedName}.type");
                    $incident->timestamp   = strtotime($report['TimeStamp']);
                    $incident->information = json_encode($report_raw);

                    $this->incidents[] = $incident;

                }
            }
        }
    }
}
