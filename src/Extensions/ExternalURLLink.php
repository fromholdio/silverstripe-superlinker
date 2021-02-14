<?php

namespace Fromholdio\SuperLinker\Extensions;

use BurnBright\ExternalURLField\ExternalURLField;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class ExternalURLLink extends DataExtension
{
    private static $singular_name = 'External Link';
    private static $plural_name = 'External Links';

    private static $multi_add_title = 'External URL';

    public function updateLinkFields(FieldList &$fields)
    {
        $fields->replaceField(
            'URL',
            $urlField = ExternalURLField::create('URL', $this->owner->fieldLabel('URL'))
        );

        $urlField->setConfig('removeparts', [
            'query'     =>  $this->owner->isQueryStringAllowed(),
            'fragment'  =>  $this->owner->isAnchorAllowed()
        ]);
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!Director::is_absolute_url($this->owner->URL)) {
            $result->addFieldError('URL', 'External URLs must be complete including http:// or https://');
        }
    }

    public function updateLink(&$link)
    {
        $link = $this->owner->URL;
    }

    public function updateAbsoluteLink(&$link)
    {
        $link = $this->owner->URL;
    }
}
