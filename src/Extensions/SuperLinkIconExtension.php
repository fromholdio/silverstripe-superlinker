<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SuperLinker\Model\SuperLink;
use Fromholdio\SuperLinker\Model\VersionedSuperLink;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class SuperLinkIconExtension extends DataExtension
{
    private static $settings = [
        'icon' => true
    ];

    private static $icon_folder_path = null;
    private static $icon_allowed_extensions = [];
    private static $icon_allowed_categories = ['image/supported'];

    private static $has_one = [
        'LinkIcon' => Image::class
    ];

    private static $owns = [
        'LinkIcon'
    ];

    private static $cascade_duplicates = [
        'LinkIcon'
    ];

    private static $field_labels = [
        'LinkIcon' => 'Icon'
    ];

    public function getIcon(): ?Image
    {
        if (!$this->getOwner()->isSettingEnabled('icon')) return null;

        /** @var ?Image $icon */
        $icon = $this->getOwner()->getComponent('LinkIcon');
        return $icon?->exists() ? $icon : null;
    }

    public function updateCMSLinkFieldsBeforeTypes(FieldList $fields, string $fieldPrefix): void
    {
        if (!$this->getOwner()->isSettingEnabled('icon')) return;

        $iconField = UploadField::create(
            $fieldPrefix . 'LinkIcon',
            $this->getOwner()->fieldLabel('LinkIcon')
        );
        $folderPath = $this->getOwner()->config()->get('icon_folder_path');
        if (!is_null($folderPath)) {
            $iconField->setFolderName($folderPath);
        }
        $iconExtensions = $this->getOwner()->config()->get('icon_allowed_extensions');
        if (!empty($iconExtensions)) {
            $iconField->setAllowedExtensions($iconExtensions);
        }
        else {
            $iconCategories = $this->getOwner()->config()->get('icon_allowed_categories');
            if (!empty($iconCategories)) {
                $iconField->setAllowedFileCategories(...$iconCategories);
            }
        }
        $fields->push($iconField);
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
