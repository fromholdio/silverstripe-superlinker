<?php

namespace Fromholdio\SuperLinker\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\SingleSelectField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\AttributesHTML;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * @mixin DataObject
 */
trait SuperLinkTrait
{
    use AttributesHTML;

    private static $singular_name = 'Link';
    private static $plural_name = 'Links';

    private static $types = [];
    private static $allowed_types = [];
    private static $disallowed_types = [];

    private static $settings = [
        'link_text' => true,
        'open_in_new' => true,
        'no_follow' => true
    ];

    private static $linking_mode_default = 'link';
    private static $linking_mode_section = 'section';
    private static $linking_mode_current = 'current';

    private static $link_type_field_class = DropdownField::class;
    private static $link_type_attr_name = 'data-superlinker-type';

    private static $db = [
        'LinkText' => 'Varchar',
        'LinkType' => 'Varchar(20)',
        'DoOpenInNew' => 'Boolean',
        'DoNoFollow' => 'Boolean'
    ];

    private static $casting = [
        'AttributesHTML' => 'HTMLFragment',
        'getAttributesHTML' => 'HTMLFragment',
    ];


    /**
     * Link text / title
     * ----------------------------------------------------
     */

    public function getTitle(): string
    {
        if ($this->isLinkTextEnabled()) {
            $title = $this->getField('LinkText');
            $this->extend('updateTitle', $title);
        }
        if (empty($title)) {
            $title = $this->getDefaultTitle();
        }
        return $title;
    }

    public function getDefaultTitle(): string
    {
        $title = null;
        $this->extend('updateDefaultTitle', $title);
        return empty($title)
            ? $this->getTypeLabel() ?? _t(__CLASS__ . '.NotConfigured', 'Not configured')
            : $title;
    }

    public function isLinkTextEnabled(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getType();
        return $this->isTypeSettingEnabled('link_text', $type);
    }


    /**
     * Link health checks
     * ----------------------------------------------------
     */

    public static function excludeInvalidLinks(SS_List $links): ArrayList
    {
        $list = ArrayList::create();
        foreach ($links as $link) {
            if ($link->isLinkValid()) $list->push($link);
        }
        return $list;
    }

    public function isLinkValid(): bool
    {
        $isValid = !empty($this->getType())
            && $this->isTypeValid()
            && $this->isTypeAvailable()
            && !$this->isLinkOrphaned()
            && !$this->isLinkEmpty();
        $this->extend('updateIsLinkValid', $isValid);
        return $isValid;
    }

    public function isLinkOrphaned(): bool
    {
//        $container = $this->getContainerObject();
//        $isOrphaned = (bool) $container?->exists();
        $isOrphaned = false;
        $this->extend('updateIsLinkOrphaned', $isOrphaned);
        return $isOrphaned;
    }

    public function isLinkEmpty(): bool
    {
        $isEmpty = empty($this->getURL());
        $this->extend('updateIsLinkEmpty', $isEmpty);
        return $isEmpty;
    }


    /**
     * Link Types
     * ----------------------------------------------------
     */

    public function getType(): ?string
    {
        return $this->getField('LinkType');
    }

    public function getTypeLabel(?string $type = null): ?string
    {
        if (empty($type)) $type = $this->getType();
        if (empty($type)) return null;
        return $this->getTypeConfigValue('label', $type);
    }

    protected function isTypeValid(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getType();
        if (empty($type)) return false;
        return in_array($type, $this->getAllTypes());
    }

    protected function isTypeAvailable(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getType();
        if (empty($type)) return false;
        return in_array($type, $this->getAvailableTypes());
    }

    protected function getAvailableTypes(bool $isLabelRequired = true): array
    {
        $availableTypes = [];
        $allTypes = $this->getAllTypes();

        $allowedTypes = $this->getAllowedTypes();
        if (empty($allowedTypes)) {
            $availableTypes = array_combine($allTypes, $allTypes);
        }
        else {
            foreach ($allTypes as $allType) {
                if (in_array($allType, $allowedTypes)) {
                    $availableTypes[$allType] = $allType;
                }
            }
        }

        $disallowedTypes = $this->getDisallowedTypes();
        if (!empty($disallowedTypes)) {
            foreach ($disallowedTypes as $disallowedType) {
                unset($availableTypes[$disallowedType]);
            }
        }

        $this->extend('updateAvailableTypes', $availableTypes);

        if ($isLabelRequired) {
            foreach ($availableTypes as $availableType) {
                if (empty($this->getTypeLabel($availableType))) {
                    unset($availableTypes[$availableType]);
                }
            }
        }

        return array_values($availableTypes);
    }

    protected function getAllTypes(): array
    {
        $types = static::config()->get('types') ?? [];
        $types = array_keys(array_filter($types));
        return $this->doSortTypes($types);
    }

    protected function getAllowedTypes(): array
    {
        $types = static::config()->get('allowed_types') ?? [];
        $this->extend('updateAllowedTypes', $types);
        return $types;
    }

    protected function getDisallowedTypes(): array
    {
        $types = static::config()->get('disallowed_types') ?? [];
        $this->extend('updateDisallowedTypes', $types);
        return $types;
    }

    protected function doSortTypes(array $types): array
    {
        $sorterFn = function($typeA, $typeB) {
            $sortA = $this->getTypeConfigValue('sort', $typeA);
            $sortB = $this->getTypeConfigValue('sort', $typeB);
            if ($sortA === $sortB) {
                return 0;
            }
            return ($sortA < $sortB) ? -1 : 1;
        };
        usort($types, $sorterFn);
        return $types;
    }


    /**
     * Link element HTML attributes
     * ----------------------------------------------------
     */

    public function getDefaultAttributes(): array
    {
        $attrs = [
            'href' => $this->getHrefValue(),
            'target' => $this->getTargetValue(),
            'rel' => $this->getRelValue(),
            'class' => $this->getClassValue()
        ];
        $type = $this->getType();
        $typeAttrName = static::config()->get('link_type_attr_name');
        if (!empty($typeAttrName)) {
            $attrs[$typeAttrName] = Convert::raw2att($type);
        }
        $this->extend('updateDefaultAttributes', $attrs);
        return array_filter($attrs);
    }


    /**
     * URL / Href attr
     * ----------------------------------------------------
     */

    public function getHrefValue(): ?string
    {
        $href = $this->getURL();
        $this->extend('updateHrefValue', $href);
        return $href;
    }

    public function getURL(): ?string
    {
        $url = null;
        $this->extend('updateURL', $url);
        return $url;
    }

    public function getAbsoluteURL(): ?string
    {
        $url = $this->getURL();
        if (!empty($url) && Director::is_relative_url($url)) {
            $url = Director::absoluteURL($url);
        }
        $this->extend('updateAbsoluteURL', $url);
        return $url;
    }


    /**
     * Target attr
     * ----------------------------------------------------
     */

    public function getTargetValue(): ?string
    {
        $target = null;
        if ($this->isOpenInNew()) {
            $target = '_blank';
        }
        $this->extend('updateTargetValue', $target);
        return $target;
    }

    public function isOpenInNewEnabled(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getType();
        return $this->isTypeSettingEnabled('open_in_new', $type);
    }

    public function isOpenInNew(): bool
    {
        $do = $this->isOpenInNewEnabled() && $this->getField('DoOpenInNew');
        $this->extend('updateIsOpenInNew', $do);
        return $do;
    }


    /**
     * Rel attr
     * ----------------------------------------------------
     */

    public function getRelValue(): ?string
    {
        $parts = $this->getRelValueParts();
        return empty($parts) ? null : implode(' ', $parts);
    }

    protected function getRelValueParts(): array
    {
        $relParts = [];
        if ($this->isNoFollow()) {
            $relParts[] = 'nofollow';
        }
        if ($this->isNoOpener()) {
            $relParts[] = 'noopener';
        }
        $this->extend('updateRelValueParts', $relParts);
        return $relParts;
    }

    public function isNoFollowEnabled(?string $type = null): bool
    {
        if (empty($type)) $type = $this->getType();
        return $this->isTypeSettingEnabled('no_follow', $type);
    }

    public function isNoFollow(): bool
    {
        $do = $this->isNoFollowEnabled() && $this->getField('DoNoFollow');
        $this->extend('updateIsNoFollow', $do);
        return $do;
    }

    public function isNoOpener(): bool
    {
        $url = $this->getURL();
        $do = !empty($url) && !Director::is_site_url($url);
        $this->extend('updateIsNoOpener', $do);
        return $do;
    }


    /**
     * Class attr
     * ----------------------------------------------------
     */

    protected array $extraCSSClasses = [];

    public function getClassValue(): ?string
    {
        $value = implode(' ', $this->extraCSSClasses);
        $this->extend('updateClassValue', $value);
        return $value;
    }

    public function addExtraCSSClass(string $class): self
    {
        $newClasses = explode(' ', $class);
        foreach ($newClasses as $newClass) {
            $this->extraCSSClasses[$newClass] = $newClass;
        }
        return $this;
    }

    public function removeExtraCSSClass(string $class): self
    {
        $removeClasses = explode(' ', $class);
        foreach ($removeClasses as $removeClass) {
            unset($this->extraCSSClasses[$removeClass]);
        }
        return $this;
    }


    /**
     * Template helpers
     * ----------------------------------------------------
     */

    public function isCurrent(): bool
    {
        $isCurrent = false;
        $this->extend('updateIsCurrent', $isCurrent);
        return $isCurrent;
    }

    public function isSection(): bool
    {
        $isSection = false;
        $this->extend('updateIsSection', $isSection);
        return $isSection;
    }

    public function LinkOrCurrent(): string
    {
        return $this->isCurrent()
            ? static::config()->get('linking_mode_current')
            : static::config()->get('linking_mode_default');
    }

    public function LinkOrSection(): string
    {
        return $this->isSection()
            ? static::config()->get('linking_mode_section')
            : static::config()->get('linking_mode_default');
    }

    public function LinkingMode(): string
    {
        return $this->isCurrent()
            ? static::config()->get('linking_mode_current')
            : $this->LinkOrSection();
    }


    /**
     * Rendering
     * ----------------------------------------------------
     */

    public function forTemplate(): DBHTMLText
    {
        $html = $this->isLinkValid()
            ? $this->renderWith($this->getRenderTemplates())
            : '';
        $this->extend('updateForTemplate', $html);
        if (is_a($html, DBHTMLText::class)) {
            return $html;
        } else {
            return DBHTMLText::create()->setValue($html);
        }
    }

    protected function getRenderTemplates(?string $suffix = null): array
    {
        $classes = ClassInfo::ancestry($this->getField('ClassName'));
        $classes = array_reverse($classes);
        $baseClass = self::class;

        $type = $this->getType() ?? '';
        if (!empty($type)) $type = '_' . $type;
        $templates = [];
        foreach ($classes as $key => $class) {
            if (!empty($type)) {
                $templates[$class][] = $class . $type . $suffix;
            }
            $templates[$class][] = $class . $suffix;
            if ($class === $baseClass) {
                break;
            }
        }

        $this->extend('updateRenderTemplates', $templates, $suffix);
        return $templates;
    }


    /**
     * Data processing and validation methods
     * ----------------------------------------------------
     */



    /**
     * Link Type config values & settings booleans
     * ----------------------------------------------------
     */

    public function getTypeConfigValue(string $key, string $type): mixed
    {
        return $this->getTypeConfigData($type)[$key] ?? null;
    }

    public function getTypeConfigData(string $type): array
    {
        return static::config()->get('types')[$type] ?? [];
    }

    public function isSettingEnabled(string $key): bool
    {
        return static::config()->get('settings')[$key] ?? false;
    }

    public function isTypeSettingEnabled(string $key, ?string $type): bool
    {
        $isEnabled = $this->isSettingEnabled($key);
        if ($isEnabled && !empty($type)) {
            $isTypeEnabled = $this->getTypeConfigValue('settings', $type)[$key] ?? null;
            if ($isTypeEnabled === false) {
                $isEnabled = false;
            }
        }
        return $isEnabled;
    }

    public function getTypesByEnabledSetting(string $key, bool $onlyAvailableTypes = true): array
    {
        $resultTypes = [];
        $types = $onlyAvailableTypes ? $this->getAvailableTypes() : $this->getAllTypes();
        foreach ($types as $type) {
            $resultTypes[$type] = $this->isTypeSettingEnabled($key, $type);
        }
        return array_keys(array_filter($resultTypes));
    }


    /**
     * CMS Fields
     * ----------------------------------------------------
     */

    public function getCMSFields(): FieldList
    {
        $linkFieldsWrapper = Wrapper::create(
            $this->getCMSLinkFields()
        );
        $linkFieldsWrapper->setName('LinkMainFieldsWrapper');

        $fields = FieldList::create(
            TabSet::create(
                'Root',
                $tab = Tab::create('Main', $linkFieldsWrapper)
            )
        );
        $tab->setTitle(_t(__CLASS__ . '.MainTab', 'Main'));

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function getCMSLinkFields(string $fieldPrefix = ''): FieldList
    {
        $fields = FieldList::create();

        $linkTypeField = $this->getLinkTypeField($fieldPrefix);
        if (empty($linkTypeField)) return $fields;
        $fields->push($linkTypeField);

        $linkTextTypes = $this->getTypesByEnabledSetting('link_text');
        if (!empty($linkTextTypes))
        {
            $linkTextField = TextField::create(
                $fieldPrefix . 'LinkText',
                _t(__CLASS__ . '.LinkText', 'Text')
            );
            $linkTextField->setDescription(
                _t(__CLASS__ . '.OptionalAutoGenerated', 'Optional. Will be auto-generated from link if left blank.')
            );
            if ($this->isInDB()) {
                $linkTextField->setAttribute(
                    'placeholder',
                    $this->getDefaultTitle()
                );
            }
            $this->applySettingFieldDisplayLogic($linkTextField, $linkTextTypes, $fieldPrefix);
            $fields->push($linkTextField);
        }

        $this->extend('updateCMSLinkFieldsBeforeTypes', $fields, $fieldPrefix);

        $types = $this->getAvailableTypes();
        foreach ($types as $type) {
            $typeFields = $this->getCMSLinkTypeFields($type, $fieldPrefix);
            if ($typeFields->count() < 1) continue;
            $typeWrapper = Wrapper::create($typeFields);
            $typeWrapper->setName($fieldPrefix . 'TypeWrapper_' . $type);
            $typeWrapper->hideUnless($fieldPrefix. 'LinkType')->isEqualTo($type);
            $fields->push($typeWrapper);
        }

        $optionsGroup = FieldGroup::create();
        $optionsGroup->setTitle(_t(__CLASS__ . '.OptionsGroup', 'Options'));
        $optionsGroup->setName($fieldPrefix . 'OptionsGroup');
        $optionsGroupTypes = [];

        $this->extend('updateCMSLinkFieldsAfterTypes', $fields, $fieldPrefix);

        $openNewTypes = $this->getTypesByEnabledSetting('open_in_new');
        if (!empty($openNewTypes))
        {
            $openNewField = CheckboxField::create(
                $fieldPrefix . 'DoOpenInNew',
                _t(__CLASS__ . '.DoOpenInNew', 'Open in new tab')
            );
            $this->applySettingFieldDisplayLogic(
                $openNewField,
                $openNewTypes,
                $fieldPrefix
            );
            $optionsGroup->push($openNewField);
            $optionsGroupTypes += $openNewTypes;
        }

        $noFollowTypes = $this->getTypesByEnabledSetting('no_follow');
        if (!empty($noFollowTypes))
        {
            $noFollowField = CheckboxField::create(
                $fieldPrefix . 'DoNoFollow',
                _t(__CLASS__ . '.DoNoFollow', 'Ask search engines to ignore')
            );
            $this->applySettingFieldDisplayLogic(
                $noFollowField,
                $noFollowTypes,
                $fieldPrefix
            );
            $optionsGroup->push($noFollowField);
            $optionsGroupTypes += $noFollowTypes;
        }

        if ($optionsGroup->FieldList()->count() > 0)
        {
            $optionsGroupWrapper = Wrapper::create($optionsGroup);
            $this->applySettingFieldDisplayLogic(
                $optionsGroupWrapper,
                $optionsGroupTypes,
                $fieldPrefix
            );
            $fields->push($optionsGroupWrapper);
        }

        $this->extend('updateCMSLinkFields', $fields, $fieldPrefix);
        return $fields;
    }

    protected function getCMSLinkTypeFields(string $type, string $fieldPrefix = ''): FieldList
    {
        $fields = FieldList::create();
        $this->extend('updateCMSLinkTypeFields', $fields, $type, $fieldPrefix);
        return $fields;
    }

    protected function getLinkTypeField(string $fieldPrefix = ''): ?SingleSelectField
    {
        $field = null;
        $class = $this->getLinkTypeFieldClassName();
        if (is_a($class, SingleSelectField::class, true))
        {
            $source = [];
            $types = $this->getAvailableTypes();
            foreach ($types as $type) {
                $source[$type] = $this->getTypeLabel($type);
            }
            $this->extend('updateLinkTypeFieldSource', $source);
            if (!empty($source))
            {
                $field = $class::create(
                    $fieldPrefix . 'LinkType',
                    _t(__CLASS__ . '.LinkType', 'Type'),
                    $source
                );
                if (is_a($class, DropdownField::class, true))
                {
                    $field->setHasEmptyDefault(true);
                    $field->setEmptyString('-- ' . _t(__CLASS__ . '.SelectLinkType', 'Select link type') . ' --');
                }
            }
        }
        $this->extend('updateLinkTypeField', $field, $class, $source, $fieldPrefix);
        return $field;
    }

    protected function getLinkTypeFieldClassName(): string
    {
        $class = static::config()->get('link_type_field_class');
        $this->extend('updateLinkTypeFieldClassName', $class);
        return $class;
    }

    public function applySettingFieldDisplayLogic(
        FormField $field,
        array $types,
        string $fieldPrefix = ''
    ): void
    {
        foreach ($types as $type) {
            $criteria = empty($criteria)
                ? $field->displayIf($fieldPrefix . 'LinkType')->isEqualTo($type)
                : $criteria->orIf($fieldPrefix . 'LinkType')->isEqualTo($type);
        }
        if (!empty($criteria)) $criteria->end();
    }


    /**
     * Permissions
     * ----------------------------------------------------
     */
}
