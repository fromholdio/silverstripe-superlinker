<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SystemLinks\SystemLinks;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\View\ArrayData;

class SystemLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'system';

    private static $types = [
        'system' => [
            'label' => 'System link',
            'settings' => [
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'SystemLinkKey' => 'Varchar(30)'
    ];

    public function getLinkedSystemLink(): ?ArrayData
    {
        if (!$this->isLinkTypeMatch()) return null;
        return SystemLinks::get_link($this->getOwner()->getField('SystemLinkKey'));
    }

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = $this->getOwner()->getLinkedSystemLink()?->getField('Title');
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $url = $this->getOwner()->getLinkedSystemLink()?->getField('URL');
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;
        $fields->push(
            DropdownField::create(
                $fieldPrefix . 'SystemLinkKey',
                _t(__CLASS__ . '.SystemLink', 'System Link'),
                SystemLinks::get_map()
            )
        );
    }
}
