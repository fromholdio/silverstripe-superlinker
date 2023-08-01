<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SuperLinker\Model\SuperLink;
use Fromholdio\SuperLinker\Model\VersionedSuperLink;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\SingleSelectField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;

class SuperLinkTypeExtension extends DataExtension
{
    private static $extension_link_type = '';

    public function updateTitle(?string &$title): void {}

    public function updateDefaultTitle(?string &$title): void {}

    public function updateIsLinkValid(bool &$value): void {}

    public function updateIsLinkOrphaned(bool &$value): void {}

    public function updateIsLinkEmpty(bool &$value): void {}

    public function updateAvailableTypes(array &$types): void {}

    public function updateAllowedTypes(array &$types): void {}

    public function updateDisallowedTypes(array &$types): void {}

    public function updateDefaultAttributes(array &$attrs): void {}

    public function updateHrefValue(?string &$value): void {}

    public function updateURL(?string &$url): void {}

    public function updateAbsoluteURL(?string &$url): void {}

    public function updateTargetValue(?string &$value): void {}

    public function updateIsOpenInNew(bool $value): void {}

    public function updateRelValueParts(?array $parts): void {}

    public function updateIsNoFollow(bool $value): void {}

    public function updateIsNoOpener(bool $value): void {}

    public function updateClassValue(string &$value): void {}

    public function updateIsCurrent(bool $value): void {}

    public function updateIsSection(bool $value): void {}

    public function updateForTemplate(string|DBHTMLText|null &$html): void {}

    public function updateRenderTemplates(array &$templates): void {}

//    public function updateCMSFields(FieldList $fields): void {}

    public function updateCMSLinkFields(FieldList $fields): void {}

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void {}

    public function updateCMSLinkTypeFieldSource(array &$source): void {}

    public function updateCMSLinkTypeField(?SingleSelectField &$field, string $class, array $source, string $fieldPrefix): void {}

    public function updateLinkTypeFieldClassName(string &$class): void {}

    protected function getExtensionLinkType(): string
    {
        $type = Config::inst()->get(
            static::class,
            'extension_link_type',
            Config::UNINHERITED
        );
        if (empty($type)) {
            throw new \UnexpectedValueException(
                'Missing configuration, please set string value for '
                . static::class . '::$extension_link_type'
            );
        }
        return $type;
    }

    protected function isLinkTypeMatch(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getOwner()->getType();
        return $type === $this->getExtensionLinkType();
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
