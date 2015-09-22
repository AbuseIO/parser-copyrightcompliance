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
        $configBase = 'parsers.' . $reflect->getShortName();

        Log::info(
            get_class($this) . ': Received message from: ' .
            $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            config("{$configBase}.parser.name")
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
            $feedName = 'default';

            // Create a corrected array
            $xmlReport = json_encode($xmlReport);
            $xmlReport = json_decode($xmlReport, true);

            // Unset fields that are really crap
            unset($xmlReport['Source']['SubType']);

            // Apply filters from configuration
            foreach (config("{$configBase}.feeds.{$feedName}.filters") as $filter) {
                unset($xmlReport[$filter]);
            }

            $fields = $xmlReport['Source'];
            $columns = array_filter(config("{$configBase}.feeds.{$feedName}.fields"));

            if (count($columns) > 0) {
                foreach ($columns as $column) {
                    if (!isset($fields[$column])) {
                        return $this->failed(
                            "Required field ${column} is missing in the report or config is incorrect."
                        );
                    }
                }
            }

            if (config("{$configBase}.feeds.{$feedName}.enabled") !== true) {
                return $this->success($events);
            }

            $event = [
                'source'        => config("{$configBase}.parser.name"),
                'ip'            => $fields['IP_Address'],
                'domain'        => false,
                'uri'           => false,
                'class'         => config("{$configBase}.feeds.{$feedName}.class"),
                'type'          => config("{$configBase}.feeds.{$feedName}.type"),
                'timestamp'     => strtotime($fields['TimeStamp']),
                'information'   => json_encode($xmlReport),
            ];

            $events[] = $event;
        }

        return $this->success($events);
    }
}
