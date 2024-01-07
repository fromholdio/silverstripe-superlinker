<?php

namespace Fromholdio\SuperLinker\Extensions;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class PhoneLink extends DataExtension
{
    private static $singular_name = 'Phone Link';
    private static $plural_nbame = 'Phone Links';

    private static $multi_add_title = 'Phone number';

    private static $enable_url_field_validation = false;

    private static $default_country = 'AU';

    private static $db = [
        'Phone'     =>  'Varchar(30)'
    ];

    public function updateLinkFields(FieldList &$fields)
    {
        $fields = FieldList::create(
            TextField::create('Phone', _t(__CLASS__.'.Phone', 'Phone'))
        );
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->Phone) {
            $result->addFieldError('Phone', _t(__CLASS__.'.PhoneRequired', 'You must provide a phone number'));
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        if (!$this->owner->Phone) {
            return null;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $phone = $phoneUtil->parse(
            $this->owner->Phone,
            $this->owner->getDefaultCountry()
        );

        $text = $phoneUtil->format($phone, PhoneNumberFormat::E164);
    }

    public function updateHasTarget(&$hasTarget)
    {
        $phone = $this->getOwner()->Phone;
        $hasTarget = $phone && !empty($phone);
    }

    public function updateIsSiteURL(bool &$isSiteURL)
    {
        $isSiteURL = true;
    }

    public function updateLink(&$link)
    {
        if (!$this->owner->Phone) {
            $link = null;
            return;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $phone = $phoneUtil->parse(
            $this->owner->Phone,
            $this->owner->getDefaultCountry()
        );

        $link = $phoneUtil->format($phone, PhoneNumberFormat::RFC3966);
    }

    public function updateAbsoluteLink(&$link)
    {
        $link = $this->owner->Link();
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->owner->dbObject('Phone');
    }

    public function getDefaultCountry()
    {
        return $this->owner->config()->get('default_country');
    }
}
