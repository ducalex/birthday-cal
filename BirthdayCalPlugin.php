<?php

namespace Sabre\CardDAV;

use Sabre\DAV;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject;

/**
 * Contact Birthdays Calendar.
 *
 * This plugin adds the ability to export ICS calendars containing contact birthdays.
 *
 * @license http://sabre.io/license/ Modified BSD License
 */
class BirthdayCalPlugin extends DAV\ServerPlugin
{
    /**
     * Reference to Server class.
     *
     * @var DAV\Server
     */
    protected $server;

    /**
     * Initializes the plugin and registers event handlers.
     */
    public function initialize(DAV\Server $server)
    {
        $this->server = $server;
        $this->server->on('method:GET', [$this, 'httpGet'], 90);
        $server->on('browserButtonActions', function ($path, $node, &$actions) {
            if ($node instanceof IAddressBook) {
                $actions .= ' <a href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '?calendar">birthdays</a>';
            }
        });
    }

    /**
     * Intercepts GET requests on addressbook urls ending with ?calendar.
     *
     * @return bool
     */
    public function httpGet(RequestInterface $request, ResponseInterface $response)
    {
        $queryParams = $request->getQueryParameters();
        if (!array_key_exists('calendar', $queryParams)) {
            return;
        }

        $path = $request->getPath();

        $node = $this->server->tree->getNodeForPath($path);
        if (!($node instanceof IAddressBook)) {
            return;
        }

        $this->server->transactionType = 'get-addressbook-calendar';

        // Checking ACL, if available.
        if ($aclPlugin = $this->server->getPlugin('acl')) {
            $aclPlugin->checkPrivileges($path, '{DAV:}read');
        }

        $prop = '{' . Plugin::NS_CARDDAV . '}address-data';
        $vcards = $this->server->getPropertiesForPath($path, [$prop], 1);
        $vcards = array_column(array_column($vcards, 200), $prop);

        $calendar = new VCalendar();
        
        foreach ($vcards as $card) {
            try {
                $card = VObject\Reader::read($card);

                if (!$card || !$card->FN || !$card->BDAY) {
                    continue;
                }

                $date = VObject\DateTimeParser::parseVCardDateTime($card->BDAY->getValue());
                $date = sprintf('%04d-%02d-%02d', $date['year'] ?: 2000, $date['month'], $date['date']);
            } catch (\Throwable $e) {
                continue;
            }

            $event = $calendar->add('VEVENT', [
                'SUMMARY' => $card->FN->getValue() . '\'s Birthday!',
                'DTSTART' => new \DateTime($date),
                'RRULE' => 'FREQ=YEARLY',
                'TRANSP' => 'TRANSPARENT',
                // These properties need to be static to please certain clients
                // So we derive them from the VCard properties
                'UID' => 'BDAY-' . ($card->UID ? $card->UID->getValue() : md5($card->serialize())),
                'DTSTAMP' => new \DateTime($card->REV ? $card->REV->getValue() : $date),
            ]);

            $event->DTSTART['VALUE'] = 'DATE';
        }

        $response->setHeader('Content-Disposition', 'filename="calendar.ics"');
        $response->setHeader('Content-Type', 'text/calendar');
        $response->setStatus(200);
        $response->setBody($calendar->serialize());

        // Returning false to break the event chain
        return false;
    }

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    public function getPluginName()
    {
        return 'birthday-cal';
    }

    /**
     * Returns a bunch of meta-data about the plugin.
     *
     * Providing this information is optional, and is mainly displayed by the
     * Browser plugin.
     *
     * The description key in the returned array may contain html and will not
     * be sanitized.
     *
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Adds the ability to export birthdays as an iCalendar.',
            'link'        => 'none',
        ];
    }
}
