<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class EmailLink extends DataExtension
{
    private static $singular_name = 'Email Link';
    private static $plural_name = 'Email Links';

    private static $multi_add_title = 'Email address';

    private static $enable_url_field_validation = false;

    private static $db = [
        'Email'     =>  'Varchar(320)',
        'EmailCC'   =>  'Varchar(320)',
        'EmailBCC'  =>  'Varchar(320)',
        'Subject'   =>  'Varchar(255)',
        'Body'      =>  'Text'
    ];

    public function updateLinkFields(FieldList &$fields)
    {
        $fields = FieldList::create(
            EmailField::create('Email', _t(__CLASS__.'.Email', 'Email')),
            EmailField::create('EmailCC', _t(__CLASS__.'.EmailCC', 'CC')),
            EmailField::create('EmailBCC', _t(__CLASS__.'.EmailBCC', 'BCC')),
            TextField::create('Subject', _t(__CLASS__.'.Subject', 'Subject')),
            TextareaField::create('Body', _t(__CLASS__.'.Body', 'Body'))
        );
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->Email) {
            $result->addFieldError('Email', _t(__CLASS__.'.MustProvideEmail', 'You must provide an Email Address'));
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $text = $this->owner->Email;
    }

    public function updateHasTarget(&$hasTarget)
    {
        $email = $this->getOwner()->Email;
        $hasTarget = $email && !empty($email);
    }

    public function updateIsSiteURL(bool &$isSiteURL)
    {
        $isSiteURL = true;
    }

    public function updateLink(&$link)
    {
        if (!$this->owner->Email) {
            $link = null;
            return;
        }

        $link = 'mailto:' . $this->owner->Email;

        $parts = [];

        if ($this->owner->EmailCC) {
            $parts[] = 'cc=' . $this->owner->EmailCC;
        }
        if ($this->owner->EmailBCC) {
            $parts[] = 'bcc=' . $this->owner->EmailBCC;
        }
        if ($this->owner->Subject) {
            $parts[] = 'subject=' . urlencode($this->owner->Subject);
        }
        if ($this->owner->Body) {
            $parts[] = 'body=' . urlencode($this->owner->Body);
        }

        if (count($parts) > 0) {
            $link .= '&' . implode('&', $parts);
        }
    }

    public function updateAbsoluteLink(&$link)
    {
        $link = $this->owner->Link();
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->owner->dbObject('Email');
    }
}
