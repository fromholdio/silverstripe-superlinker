<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataExtension;

class SuperLinkImageFileExtension extends DataExtension
{
    public function getSuperLinkDefaultImage(): ?Image
    {
        $image = null;
        if (is_a($this->getOwner(), Image::class))
        {
            /** @var ?Image $image */
            $image = $this->getOwner()?->exists() ? $this->getOwner() : null;
        }
        $this->getOwner()->invokeWithExtensions('updateSuperLinkDefaultImage', $image);
        return $image;
    }
}
