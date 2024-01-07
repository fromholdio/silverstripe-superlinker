<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\DependentGroupedDropdownField\Forms\DependentGroupedDropdownField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class SiteTreeLink extends DataExtension
{
    private static $singular_name = 'Site Tree Link';
    private static $plural_name = 'Site Tree Links';

    private static $multi_add_title = 'Page on this website';

    private static $allow_anchor = true;

    private static $enable_url_field_validation = false;

    private static $has_one = [
        'SiteTree'      =>  SiteTree::class
    ];

    public function updateLinkFields(FieldList &$fields)
    {
        $globalAnchors = $this->getOwner()->getGlobalAnchors();

        $anchorSource = function($siteTreeID) use ($globalAnchors) {

            $anchors = [];

            $siteTree = SiteTree::get()->byID($siteTreeID);
            if ($siteTree && $siteTree->exists()) {
                $contentAnchors = $siteTree->dbObject('Content')->getAnchors();
                if ($contentAnchors) {
                    $anchors['Page Content'] = $contentAnchors;
                }
            }

            if ($globalAnchors && !empty($globalAnchors)) {
                $anchors['Global Template'] = $globalAnchors;
            }

            return $anchors;
        };

        $fields = FieldList::create(
            $siteTreeField = TreeDropdownField::create(
                'SiteTreeID',
                _t(__CLASS__.'.Page', 'Page'),
                SiteTree::class
            )
                ->setEmptyString(_t(__CLASS__.'.SelectAPage', 'Select a page'))
                ->setHasEmptyDefault(true)
            ,
            DependentGroupedDropdownField::create(
                'Anchor',
                _t(__CLASS__.'.Anchor', 'Anchor'),
                $anchorSource
            )
                ->setDepends($siteTreeField)
                ->setEmptyString(_t(__CLASS__.'.SelectAnchor', 'Select an anchor (optional)'))
        );
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->SiteTreeID) {
            $result->addFieldError('SiteTreeID', _t(__CLASS__.'.PageRequired', 'You must select a page to link to'));
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $text = $this->owner->SiteTree()->Title;
    }

    public function updateLink(&$link, &$queryString, &$anchor)
    {
        $link = Controller::join_links(
            $this->owner->SiteTree()->Link(),
            $queryString ? '?' . $queryString : null,
            $anchor ? '#' . $anchor : null
        );
    }

    public function updateAbsoluteLink(&$link, &$queryString, &$anchor)
    {
        $link = Controller::join_links(
            $this->owner->SiteTree()->AbsoluteLink(),
            $queryString ? '?' . $queryString : null,
            $anchor ? '#' . $anchor : null
        );
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->owner->SiteTree();
    }

    public function updateHasTarget(&$hasTarget)
    {
        $target = $this->getOwner()->SiteTree();
        $hasTarget = $target && $target->exists() && $target->canView();
    }

    public function updateLinkOrCurrent(&$linkOrCurrent)
    {
        $siteTree = $this->getOwner()->SiteTree();
        if ($siteTree) {
            $linkOrCurrent = $siteTree->LinkOrCurrent();
        }
    }

    public function updateLinkOrSection(&$linkOrSection)
    {
        $siteTree = $this->getOwner()->SiteTree();
        if ($siteTree) {
            $linkOrSection = $siteTree->LinkOrSection();
        }
    }

    public function updateLinkingMode(&$linkingMode)
    {
        $siteTree = $this->getOwner()->SiteTree();
        if ($siteTree) {
            $linkingMode = $siteTree->LinkingMode();
        }
    }

    public function updateInSection(&$inSection)
    {
        $siteTree = $this->getOwner()->SiteTree();
        if ($siteTree) {
            $inSection = $siteTree->InSection();
        }
    }
}
