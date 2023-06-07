<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;

class EmailLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'email';

    private static $types = [
        'email' => [
            'label' => 'Email address',
            'cc' => false,
            'bcc' => false,
            'subject' => true,
            'body' => true,
            'settings' => [
                'open_in_new' => false,
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'Email' => 'Varchar',
        'EmailCC' => 'Varchar',
        'EmailBCC' => 'Varchar',
        'EmailSubject' => 'Varchar(255)',
        'EmailBody' => 'Text'
    ];

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = $this->getOwner()->getField('Email');
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $email = $this->getOwner()->getField('Email');
        if (empty($email)) {
            $url = null;
            return;
        }
        $url = 'mailto:' . $email;
        $urlParts = [
            'cc' => $this->getOwner()->getField('EmailCC'),
            'bcc' => $this->getOwner()->getField('EmailBCC'),
            'subject' => $this->getOwner()->getField('EmailSubject'),
            'body' => $this->getOwner()->getField('EmailBody')
        ];
        $urlParts = array_filter($urlParts);
        if (!empty($urlParts)) {
            $prefix = '?';
            foreach ($urlParts as $key => $value) {
                $url .= $prefix . $key . '=' . Convert::raw2mailto($value);
                $prefix = '&';
            }
        }
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;
        $fields->push(EmailField::create($fieldPrefix . 'Email'));
        $fields->push(TextField::create($fieldPrefix . 'EmailSubject'));
        $fields->push(TextareaField::create($fieldPrefix . 'EmailBody'));
    }
}
