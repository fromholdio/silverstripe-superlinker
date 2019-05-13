<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class GlobalAnchorLink extends DataExtension
{
    private static $singular_name = 'Global Anchor Link';
    private static $plural_name = 'Global Anchor Links';

    private static $multi_add_title = 'Global Anchor';

    private static $allow_anchor = true;

    private static $enable_url_field_validation = false;

    public function updateLinkFields(FieldList &$fields)
    {
        $fields = FieldList::create(
            DropdownField::create(
                'Anchor',
                $this->owner->fieldLabel('Anchor'),
                $this->owner->getGlobalAnchors()
            )
        );
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->Anchor) {
            $result->addFieldError('Anchor', 'You must select an anchor');
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $text = $this->owner->getGlobalAnchor($this->owner->Anchor);
    }

    public function updateIsSiteURL(bool &$isSiteURL)
    {
        $isSiteURL = true;
    }

    public function updateLink(&$link)
    {
        if (!$this->owner->Anchor || !$this->owner->isAnchorAllowed()) {
            $link = null;
            return;
        }

        $link = '#' . $this->owner->Anchor;
    }

    public function updateAbsoluteLink(&$link)
    {
        if (!$this->owner->Anchor || !$this->owner->isAnchorAllowed()) {
            $link = null;
            return;
        }

        $currentController = Controller::curr();
        if (is_a($currentController, ContentController::class)) {
            $currentPage = $currentController->data();
            if ($currentPage && is_a($currentPage, SiteTree::class)) {
                $link = Controller::join_links(
                    $currentPage->AbsoluteLink(),
                    '#' . $this->owner->Anchor
                );
                return;
            }
        }

        $link = Controller::join_links(
            Director::absoluteBaseURL(),
            '#' . $this->owner->Anchor
        );
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->owner->dbObject('Anchor');
    }
}
