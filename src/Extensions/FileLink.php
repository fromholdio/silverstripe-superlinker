<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Assets\File;
use SilverStripe\ORM\ValidationResult;

class FileLink extends DataExtension
{
    private static $singular_name = 'File Link';
    private static $plural_name = 'File Links';

    private static $multi_add_title = 'Download a file';

    private static $enable_url_field_validation = false;

    private static $has_one = [
        'File'      =>  File::class
    ];

    private static $owns = [
        'File'
    ];

    public function updateLinkFields(FieldList &$fields)
    {
        $fields = FieldList::create(
            $uploadField = UploadField::create('File', $this->owner->fieldLabel('File'))
        );

        $uploadField->setAllowedMaxFileNumber(1);
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->FileID) {
            $result->addFieldError('File', 'You must upload or select a file to link to');
        }
    }

    public function updateAttributes(array &$attributes)
    {
        $fileName = $this->owner->generateLinkText();
        $extension = $this->owner->File()->getExtension();

        if ($fileName && $extension) {
            $attributes['download'] = $fileName . '.' . $extension;
        } else {
            $attributes['download'] = true;
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $text = $this->owner->File()->Title;
    }

    public function updateLink(&$link)
    {
        $link = $this->owner->File()->getURL();
    }

    public function updateAbsoluteLink(&$link)
    {
        $link = $this->owner->File()->getAbsoluteURL();
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->File();
    }
}
