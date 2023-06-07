<?php

namespace Fromholdio\SuperLinker\Extensions;

use BurnBright\ExternalURLField\ExternalURLField;
use SilverStripe\Forms\FieldList;

class ExternalLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'external';

    private static $types = [
        'external' => [
            'label' => 'External URL',
            'query_string' => true,
            'fragment' => true,
            'is_internal_url_allowed' => false,
            'allowed_schemes' => [
                'https' => true,
                'http' => true,
                'ftp' => false
            ]
        ]
    ];

    private static $db = [
        'ExternalURL' => 'Varchar(2083)'
    ];

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = $this->getOwner()->getURL();
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        /** @var ExternalURLField $urlField */
        $urlField = $this->getOwner()->dbObject('ExternalURL');
        $url = $urlField->URL();
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;
        $fields->push(ExternalURLField::create(
            $fieldPrefix . 'ExternalURL'
        ));
    }
}
