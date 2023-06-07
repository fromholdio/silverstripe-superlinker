<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\DependentGroupedDropdownField\Forms\DependentGroupedDropdownField;
use Fromholdio\GlobalAnchors\GlobalAnchors;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;

class SiteTreeLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'sitetree';

    private static $types = [
        'sitetree' => [
            'label' => 'Page on this website',
            'settings' => [
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'SiteTreeAnchor' => 'Varchar(255)'
    ];

    private static $has_one = [
        'SiteTree' => SiteTree::class
    ];

    public function getLinkedSiteTree(): ?SiteTree
    {
        if (!$this->isLinkTypeMatch()) return null;
        /** @var ?SiteTree $siteTree */
        $siteTree = $this->getOwner()->getComponent('SiteTree');
        return $siteTree?->exists() ? $siteTree : null;
    }

    public function getLinkedSiteTreeAnchor(): ?string
    {
        if (!$this->isLinkTypeMatch()) return null;
        return $this->getOwner()->getField('SiteTreeAnchor');
    }

    public function getAvailableSiteTreeAnchors(int|string|null $siteTreeID): array
    {
        $anchors = [];

        /** @var ?SiteTree $siteTree */
        $siteTree = SiteTree::get()->find('ID', $siteTreeID ?? -1);

        $contentAnchors = $siteTree?->getAnchorsOnPage();
        $this->getOwner()->invokeWithExtensions(
            'updateAvailableSiteTreeContentAnchors',
            $contentAnchors, $siteTree
        );
        if (!empty($contentAnchors)) {
            $anchors['Page content'] = array_combine($contentAnchors, $contentAnchors);
        }

        $globalAnchors = GlobalAnchors::get_anchors();
        if (!empty($globalAnchors)) {
            $anchors['Global anchors'] = $globalAnchors;
        }

        $this->getOwner()->invokeWithExtensions(
            'updateAvailableSiteTreeAnchors',
            $anchors, $siteTree
        );
        return $anchors;
    }

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = $this->getOwner()->getLinkedSiteTree()?->getTitle();
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $url = $this->getOwner()->getLinkedSiteTree()?->Link();
        $anchor = $this->getOwner()->getLinkedSiteTreeAnchor();
        if (!empty($anchor)) $url .= '#' . $anchor;
    }

    public function updateAbsoluteURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $url = $this->getOwner()->getLinkedSiteTree()?->AbsoluteLink();
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;

        $siteTreeField = TreeDropdownField::create(
            'SiteTreeID',
            $this->getOwner()->fieldLabel('SiteTree'),
            SiteTree::class
        );
        $siteTreeField->setEmptyString('-- Select a page --');
        $siteTreeField->setHasEmptyDefault(true);
        $fields->push($siteTreeField);

        $siteTreeLink = $this->getOwner();
        $anchorSource = function(int|string|null $siteTreeID) use ($siteTreeLink) {
            return $siteTreeLink->getAvailableSiteTreeAnchors($siteTreeID);
        };

        $anchorField = DependentGroupedDropdownField::create(
            'SiteTreeAnchor',
            $this->getOwner()->fieldLabel('SiteTreeAnchor'),
            $anchorSource
        );
        $anchorField
            ->setDepends($siteTreeField)
            ->setEmptyString('Select an anchor (optional)');
        $fields->push($anchorField);
    }
}
