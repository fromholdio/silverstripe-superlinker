<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\GlobalAnchors\GlobalAnchors;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\DropdownField;

class GlobalAnchorLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'globalanchor';

    private static $types = [
        'globalanchor' => [
            'label' => 'Global anchor',
            'settings' => [
                'open_in_new' => false,
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'GlobalAnchorKey' => 'Varchar(30)'
    ];

    private static $field_labels = [
        'GlobalAnchorKey' => 'Global anchor'
    ];

    public function getLinkedGlobalAnchor(): ?string
    {
        if (!$this->isLinkTypeMatch()) return null;
        $anchors = GlobalAnchors::get_anchors();
        $key = $this->getOwner()->getField('GlobalAnchorKey');
        return isset($anchors[$key]) ? $key : null;
    }

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $anchor = $this->getOwner()->getLinkedGlobalAnchor();
        $title = GlobalAnchors::get_anchor_title($anchor);
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $anchor = $this->getOwner()->getLinkedGlobalAnchor();
        $url = empty($anchor) ? null : '#' . $anchor;
    }

    public function updateAbsoluteURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $anchor = $this->getOwner()->getLinkedGlobalAnchor();
        if (empty($anchor)) {
            $url = null;
            return;
        }
        $curr = Controller::curr();
        $link = $curr instanceof ContentController
            ? $curr->Link()
            : Director::absoluteBaseURL();
        $url = Controller::join_links($link, '#' . $anchor);
    }

    public function updateCMSLinkTypeFields($fields, $type, $fieldPrefix): void
    {
        if ($type !== 'globalanchor') return;
        $fields->push(
            DropdownField::create(
                $fieldPrefix . 'GlobalAnchorKey',
                $this->getOwner()->fieldLabel('GlobalAnchorKey'),
                GlobalAnchors::get_anchors()
            )
        );
    }
}
