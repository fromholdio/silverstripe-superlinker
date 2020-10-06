<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class NoLink extends DataExtension
{
    private static $singular_name = 'No Link';
    private static $plural_name = 'No Links';

    private static $multi_add_title = 'Text Only - no link';

    private static $enable_url_field_validation = false;

    public function updateHasLink(&$hasLink)
    {
        $hasLink = false;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $titleField = $fields->dataFieldByName('CustomLinkText');
        $titleField->setDescription('');
    }

    public function updateLinkFields(FieldList &$fields)
    {
        $fields->removeByName('URL');
    }

    public function updateBehaviourFields(&$fields)
    {
        $fields = FieldList::create();
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->getOwner()->Title) {
            $result->addFieldError('Title', 'You must provide a Title');
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $text = '- No Link -';
    }

    public function updateHasTarget(&$hasTarget)
    {
        $text = $this->getOwner()->Title;
        $hasTarget = $text && !empty($text);
    }

    public function updateIsSiteURL(bool &$isSiteURL)
    {
        $isSiteURL = false;
    }

    public function updateLink(&$link)
    {
        $link = null;
    }

    public function updateAbsoluteLink(&$link)
    {
        $link = null;
    }
}
