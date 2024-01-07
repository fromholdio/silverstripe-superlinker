<?php

namespace Fromholdio\SuperLinker\Extensions;

class NullLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'nolink';

    private static $types = [
        'nolink' => [
            'label' => 'Text only (no link)',
            'settings' => [
                'open_in_new' => false,
                'no_follow' => false
            ]
        ]
    ];

    public function updateIsLinkEmpty(bool &$value): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $value = false;
    }
}
