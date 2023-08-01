<?php

namespace Fromholdio\SuperLinker\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class VersionedSuperLink extends DataObject
{
    use SuperLinkTrait;

    private static $table_name = 'VersionedSuperLink';

    private static $extensions = [
        Versioned::class
    ];
}
