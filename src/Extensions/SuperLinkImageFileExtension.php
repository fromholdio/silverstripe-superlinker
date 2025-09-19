<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;

class SuperLinkImageFileExtension extends Extension
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
