<?php

namespace AbuseIO\Parsers;

use Ddeboer\DataImport\Reader;
use Ddeboer\DataImport\Writer;
use Ddeboer\DataImport\Filter;
use Log;
use ReflectionClass;

class Copyrightcompliance extends Parser
{
    public $parsedMail;
    public $arfMail;

    /**
     * Create a new Blocklistde instance
     */
    public function __construct($parsedMail, $arfMail)
    {
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;
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

        $events = [ ];

        foreach ($this->parsedMail->getAttachments() as $attachment) {
            if (strpos($attachment->filename, '.xml') !== false
                && $attachment->contentType == 'application/xml'
            ) {
                $xmlReport = $attachment->getContent();
            } else {
                continue;
            }
        }

        // Sadly their report is not consistantly an attachment and might end up in the body so we need to fallback
        // to a body XML search if there was nothing found in attachments.
        if (empty($xmlReport)) {
            preg_match(
                '/\<\?xml.*\<\/Infringement\>/s',
                $this->parsedMail->getMessageBody(),
                $regs
            );
            $xmlReport = $regs[0];
        }

        if (!empty($xmlReport) && $xmlReport = simplexml_load_string($xmlReport)) {
            $this->feedName = 'default';

            if (!$this->isKnownFeed()) {
                return $this->failed(
                    "Detected feed {$this->feedName} is unknown."
                );
            }

            if (!$this->isEnabledFeed()) {
                return $this->success($events);
            }

            // Create a corrected array
            $xmlReport = json_encode($xmlReport);
            $xmlReport = json_decode($xmlReport, true);

            // Unset fields that are really crap
            unset($xmlReport['Source']['SubType']);

            $report = $this->applyFilters($xmlReport['Source']);

            if (!$this->hasRequiredFields($report)) {
                return $this->failed(
                    "Required field {$this->requiredField} is missing or the config is incorrect."
                );
            }

            $event = [
                'source'        => config("{$this->configBase}.parser.name"),
                'ip'            => $report['IP_Address'],
                'domain'        => false,
                'uri'           => false,
                'class'         => config("{$this->configBase}.feeds.{$this->feedName}.class"),
                'type'          => config("{$this->configBase}.feeds.{$this->feedName}.type"),
                'timestamp'     => strtotime($report['TimeStamp']),
                'information'   => json_encode($xmlReport),
            ];

            $events[] = $event;
        }

        return $this->success($events);
    }
}
