<?php

namespace Fromholdio\SuperLinker\Extensions;

use Innoweb\InternationalPhoneNumberField\Forms\InternationalPhoneNumberField;
use Innoweb\InternationalPhoneNumberField\ORM\DBPhone;
use SilverStripe\Forms\FieldList;

class PhoneLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'phone';

    private static $types = [
        'phone' => [
            'label' => 'Phone number',
            'settings' => [
                'open_in_new' => false,
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'PhoneNumber' => 'Phone'
    ];

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        /** @var DBPhone $phoneField */
        $phoneField = $this->getOwner()->dbObject('PhoneNumber');
        $title = $phoneField->International();
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        /** @var DBPhone $phoneField */
        $phoneField = $this->getOwner()->dbObject('PhoneNumber');
        $phoneNumber = $phoneField->URL();
        $url = empty($phoneNumber) ? null : $phoneNumber;
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;
        $fields->push(InternationalPhoneNumberField::create(
            $fieldPrefix . 'PhoneNumber',
            _t(__CLASS__ . '.PhoneNumber', 'Phone number')
        ));
    }
}
