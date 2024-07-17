<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SuperLinker\Model\SuperLink;
use Fromholdio\SuperLinker\Model\VersionedSuperLink;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

/**
 * Apply to your SuperLink classes to enable a text description in addition to
 * link title. TextField if rows = 1, TextareaField if rows > 1. Hooks available
 * for generating a default description based on link target, particularly
 * Page and File links.
 *
 * Just apply this extension to your SuperLink class/subclass.
 */
class SuperLinkDescriptionExtension extends DataExtension
{
    private static $link_description_rows = 1;

    private static $db = [
        'LinkDescription' => 'Text',
    ];

    private static $settings = [
        'link_description' => true,
    ];

    private static $field_labels = [
        'LinkDescription' => 'Description',
    ];

    public function getDescription(): ?string
    {
        $description = null;
        if ($this->getOwner()->isLinkDescriptionEnabled())
        {
            $description = $this->getOwner()->getField('LinkDescription');
            if (empty($description)) {
                $description = $this->getOwner()->getDefaultDescription();
            }
        }
        $this->getOwner()->invokeWithExtensions('updateDescription', $description);
        return $description;
    }

    public function getDefaultDescription(): ?string
    {
        $description = null;
        $linkedObj = null;
        $type = $this->getOwner()->getType();
        if (!empty($type) && $this->getOwner()->isLinkDescriptionEnabled($type))
        {
            switch ($type)
            {
                case 'sitetree':
                    $linkedObj = $this->getOwner()->getLinkedSiteTree();
                    break;

                case 'file':
                case 'email':
                case 'phone':
                case 'external':
                case 'globalanchor':
                case 'systemlink':
                case 'nolink':
                default:
                    $linkedObj = null;
                    break;
            }
            if (!is_null($linkedObj) && $linkedObj->hasMethod('getSuperLinkDefaultDescription')) {
                $description = $linkedObj->getSuperLinkDefaultDescription();
            }
        }
        $this->getOwner()->invokeWithExtensions('updateDefaultDescription', $description, $type, $linkedObj);
        return $description;
    }

    public function isLinkDescriptionEnabled(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getOwner()->getType();
        return $this->getOwner()->isTypeSettingEnabled('link_description', $type);
    }

    public function getLinkDescriptionFieldRows(): int
    {
        $rows = (int) $this->getOwner()->config()->get('link_description_rows');
        $this->getOwner()->invokeWithExtensions('updateLinkDescriptionFieldRows', $rows);
        return $rows;
    }

    public function updateCMSLinkFieldsBeforeTypes(FieldList $fields, string $fieldPrefix): void
    {
        $linkDescTypes = $this->getOwner()->getTypesByEnabledSetting('link_description');
        if (!empty($linkDescTypes))
        {
            $rows = $this->getOwner()->getLinkDescriptionFieldRows();
            if ($rows > 1) {
                $linkDescField = TextareaField::create(
                    $fieldPrefix . 'LinkDescription',
                    $this->getOwner()->fieldLabel('LinkDescription')
                );
                $linkDescField->setRows($rows);
            }
            else {
                $linkDescField = TextField::create(
                    $fieldPrefix . 'LinkDescription',
                    $this->getOwner()->fieldLabel('LinkDescription')
                );
            }
            $linkDescField->setAttribute(
                'placeholder',
                $this->getOwner()->getDefaultDescription()
            );

            $this->getOwner()->applySettingFieldDisplayLogic($linkDescField, $linkDescTypes, $fieldPrefix);
            $fields->push($linkDescField);
        }
    }


    /**
     * @return SuperLink|VersionedSuperLink
     */
    public function getOwner()
    {
        /** @var SuperLink $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
