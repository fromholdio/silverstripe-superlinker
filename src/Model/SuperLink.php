<?php

namespace Fromholdio\SuperLinker\Model;

use SilverStripe\ORM\DataObject;

class SuperLink extends DataObject
{
    use SuperLinkTrait;

    private static $table_name = 'SuperLink';
}
