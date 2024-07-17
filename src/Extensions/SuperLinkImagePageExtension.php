<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataExtension;

class SuperLinkImagePageExtension extends DataExtension
{
    private static $superlink_default_image_source_method = 'getFeatureImage';

    public function getSuperLinkDefaultImage(): ?Image
    {
        $image = null;
        $sourceMethod = $this->getOwner()->config()->get('superlink_default_image_source_method');
        if (!empty($sourceMethod) && $this->getOwner()->hasMethod($sourceMethod)) {
            $image = $this->getOwner()->$sourceMethod();
        }
        $this->getOwner()->invokeWithExtensions('updateSuperLinkDefaultImage', $image);
        return $image;
    }
}
