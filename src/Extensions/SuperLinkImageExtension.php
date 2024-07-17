<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SuperLinker\Model\SuperLink;
use Fromholdio\SuperLinker\Model\VersionedSuperLink;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Apply to your SuperLink classes to enable image upload for your link.
 * Hooks available for sourcing a default image based on link target, particularly
 * Page and Image links.
 *
 * 1. Apply this extension to your SuperLink class/subclass.
 * 2. Optionally apply SuperLinkImageFileExtension, and SuperLinkImagePageExtension
 * to the Image and Page classes respectively for quick/ootb default images.
 */
class SuperLinkImageExtension extends DataExtension
{
    private static $link_image_upload_path = null;

    private static $has_one = [
        'LinkImage' => Image::class,
    ];

    private static $owns = [
        'LinkImage',
    ];

    private static $settings = [
        'link_image' => true,
    ];

    private static $field_labels = [
        'LinkImage' => 'Image',
    ];

    public function getImage(): ?Image
    {
        $image = null;
        if ($this->getOwner()->isLinkImageEnabled())
        {
            $image = $this->getOwner()->getComponent('LinkImage');
            if (!$image->exists()) {
                $image = $this->getOwner()->getDefaultImage();
            }
        }
        $this->getOwner()->invokeWithExtensions('updateImage', $image);
        return $image;
    }

    public function getDefaultImage(): ?Image
    {
        $image = null;
        $linkedObj = null;
        $type = $this->getOwner()->getType();
        if (!empty($type) && $this->getOwner()->isLinkImageEnabled($type))
        {
            switch ($type)
            {
                case 'sitetree':
                    $linkedObj = $this->getOwner()->getLinkedSiteTree();
                    break;

                case 'file':
                    $linkedObj = $this->getOwner()->getLinkedFile();
                    break;

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
            if (!is_null($linkedObj) && $linkedObj->hasMethod('getSuperLinkDefaultImage')) {
                $image = $linkedObj->getSuperLinkDefaultImage();
            }
        }
        $this->getOwner()->invokeWithExtensions('updateDefaultImage', $image, $type, $linkedObj);
        return $image;
    }

    public function isLinkImageEnabled(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getOwner()->getType();
        return $this->getOwner()->isTypeSettingEnabled('link_image', $type);
    }

    public function getImageUploadPath(): ?string
    {
        $path = $this->getOwner()->config()->get('link_image_upload_path');
        $this->getOwner()->invokeWithExtensions('updateImageUploadPath', $path);
        return $path;
    }

    public function updateCMSLinkFieldsBeforeTypes(FieldList $fields, string $fieldPrefix): void
    {
        $imageTypes = $this->getOwner()->getTypesByEnabledSetting('link_image');
        if (!empty($imageTypes))
        {
            $imageField = UploadField::create($fieldPrefix . 'LinkImage', 'Upload/select');
            $uploadPath = $this->getOwner()->getImageUploadPath();
            if (!empty($uploadPath)) {
                $imageField->setFolderName($uploadPath);
            }
            $this->getOwner()->applySettingFieldDisplayLogic($imageField, $imageTypes, $fieldPrefix);
            $fields->push($imageField);
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
