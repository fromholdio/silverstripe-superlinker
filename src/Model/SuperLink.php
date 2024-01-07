<?php

namespace Fromholdio\SuperLinker\Model;

use Fromholdio\GlobalAnchors\GlobalAnchors;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Versioned\Versioned;

class SuperLink extends DataObject
{
    protected $attributes = [];

    private static $table_name = 'SuperLink';
    private static $singular_name = 'Link';
    private static $plural_name = 'Links';

    private static $allow_anchor = false;
    private static $allow_query_string = false;

    private static $enable_url_field_validation = true;
    private static $enable_custom_link_text = true;
    private static $enable_tabs = false;

    private static $extensions = [
        Versioned::class
    ];

    private static $db = [
        'URL'               =>  'Varchar(2083)',
        'DoOpenNewWindow'   =>  'Boolean',
        'DoNoFollow'        =>  'Boolean',
        'Anchor'            =>  'Varchar(255)',
        'QueryString'       =>  'Varchar(255)',
        'CustomLinkText'    =>  'Varchar(2000)'
    ];

    private static $defaults = [
        'DoOpenNewWindow'   =>  false,
        'DoNoFollow'        =>  false
    ];

    private static $casting = [
        'Title'             =>  'Varchar',
        'Href'              =>  'HTMLFragment',
        'AttributesHTML'    =>  'HTMLFragment'
    ];

    public function Link()
    {
        $queryString = ($this->isQueryStringAllowed() && $this->QueryString)
            ? $this->QueryString
            : null;

        $anchor = ($this->isAnchorAllowed() && $this->Anchor)
            ? $this->Anchor
            : null;

        $link = Controller::join_links(
            $this->URL,
            $queryString ? '?' . $queryString : null,
            $anchor ? '#' . $anchor : null
        );

        $this->extend('updateLink', $link, $queryString, $anchor);
        return $link;
    }

    public function AbsoluteLink()
    {
        $queryString = ($this->isQueryStringAllowed() && $this->QueryString)
            ? $this->QueryString
            : null;

        $anchor = ($this->isAnchorAllowed() && $this->Anchor)
            ? $this->Anchor
            : null;

        $url = $this->URL ?? '';

        if (Director::is_absolute_url($url)) {
            $absoluteURL = $url;
        } else {
            $absoluteURL = Director::absoluteURL($url);
        }

        $link = Controller::join_links(
            $absoluteURL,
            $queryString ? '?' . $queryString : null,
            $anchor ? '#' . $anchor : null
        );

        $this->extend('updateAbsoluteLink', $link, $queryString, $anchor);
        return $link;
    }

    public function LinkOrCurrent()
    {
        $linkOrCurrent = null;
        $this->extend('updateLinkOrCurrent', $linkOrCurrent);
        return $linkOrCurrent;
    }

    public function LinkOrSection()
    {
        $linkOrSection = null;
        $this->extend('updateLinkOrSection');
        return $linkOrSection;
    }

    public function LinkingMode()
    {
        $linkingMode = null;
        $this->extend('updateLinkingMode', $linkingMode);
        return $linkingMode;
    }

    public function InSection($sectionName)
    {
        $inSection = null;
        $this->extend('updateInSection', $inSection);
        return $inSection;
    }

    public function HasLink()
    {
        $hasLink = true;
        $this->extend('updateHasLink', $hasLink);
        return $hasLink;
    }

    public function HasTarget()
    {
        $url = $this->URL;
        $hasTarget = $url && !empty($url);
        $this->extend('updateHasTarget', $hasTarget);
        return $hasTarget;
    }

    public function getLinkText()
    {
        if ($this->isCustomLinkTextEnabled() && $this->CustomLinkText) {
            $text = $this->CustomLinkText;
        } else {
            $text = $this->generateLinkText();
        }

        $this->extend('updateLinkText', $text);
        return $text;
    }

    public function getTitle()
    {
        if ($this->dbObject('Title') !== null) {
            return $this->dbObject('Title');
        }
        $title = $this->getLinkText();
        $this->extend('updateTitle', $title);
        return $title;
    }

    public function generateLinkText()
    {
        $text = $this->URL;
        $this->extend('updateGenerateLinkText', $text);
        return $text;
    }

    public function getHref($forceAbsolute = false)
    {
        $href = ($forceAbsolute) ? $this->AbsoluteLink() : $this->Link();
        $this->extend('updateHref', $href, $forceAbsolute);
        return $href;
    }

    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function getAttribute($name)
    {
        $attributes = $this->getAttributes();

        if (isset($attributes[$name])) {
            return $attributes[$name];
        }
        return null;
    }

    public function getAttributes()
    {
        $attributes = [];

        $attributes['href'] = $this->getHref();

        if (!$this->isSiteURL()) {
            $attributes['rel'][] = 'noopener';
        }

        if ($this->DoOpenNewWindow) {
            $attributes['target'] = '_blank';
        }

        if ($this->DoNoFollow) {
            $attributes['rel'][] = 'nofollow';
        }

        $attributes = array_merge($attributes, $this->attributes);

        $this->extend('updateAttributes', $attributes);
        return $attributes;
    }

    public function getAttributesHTML($excluded = null)
    {
        $attributes = $this->getAttributes();

        // Remove excluded
        $excluded = (is_string($excluded)) ? func_get_args() : null;
        if ($excluded) {
            $attributes = array_diff_key($attributes, array_flip($excluded));
        }

        // Create markup
        $parts = [];
        foreach ($attributes as $name => $value) {

            if ($value === null) continue;

            if (is_array($value)) {
                $partValue = implode(' ', $value);
            } else if ($value === true) {
                $partValue = false;
            } else {
                $partValue = $value;
            }

            $attribute = $name;
            if ($partValue !== false) {
                $attribute .= '="' . Convert::raw2att($partValue) . '"';
            }
            $parts[] = $attribute;
        }

        $this->extend('updateAttributesHTML', $parts, $attributes);
        return implode(' ', $parts);
    }

    public function isSiteURL()
    {
        $isSiteURL = Director::is_site_url($this->Link());
        $this->extend('updateIsSiteURL', $isSiteURL);
        return $isSiteURL;
    }

    public function isAnchorAllowed()
    {
        $allowed = (bool) $this->config()->get('allow_anchor');
        $this->extend('updateIsAnchorAllowed', $allowed);
        return $allowed;
    }

    public function isQueryStringAllowed()
    {
        $allowed = (bool) $this->config()->get('allow_query_string');
        $this->extend('updateIsQueryStringAllowed', $allowed);
        return $allowed;
    }

    public function isURLFieldValidationEnabled()
    {
        $enabled = (bool) $this->config()->get('enable_url_field_validation');
        $this->extend('updateIsURLFieldValidationEnabled', $enabled);
        return $enabled;
    }

    public function isCustomLinkTextEnabled()
    {
        $enabled = (bool) $this->config()->get('enable_custom_link_text');
        $this->extend('updateIsCustomLinkTextEnabled', $enabled);
        return $enabled;
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(
            $rootTabSet = TabSet::create('Root')
        );

        $isCustomLinkTextEnabled = $this->isCustomLinkTextEnabled();
        if ($isCustomLinkTextEnabled) {
            $customLinkTextField = TextField::create(
                'CustomLinkText',
                _t(__CLASS__.'.CustomLinkText', 'Link Text')
            );
            if (!$this->isInDB()) {
                $customLinkTextField->setDescription(_t(__CLASS__.'.OptionalWillBeGenerated', 'Optional. Will be auto-generated if left blank.'));
            }
            if ($this->generateLinkText()) {
                $customLinkTextField->setAttribute('placeholder', $this->generateLinkText());
            }
        }

        if ($this->config()->get('enable_tabs')) {

            $mainTabSet = TabSet::create('Main');

            $linkFields = $this->getLinkFields()->toArray();
            $hasLinkFields = ($linkFields && count($linkFields) > 0);
            if ($hasLinkFields) {
                $targetTab = Tab::create('SuperLinkTargetTab', _t(__CLASS__.'.TabTarget', 'Target'));
                if ($isCustomLinkTextEnabled) {
                    $targetTab->push($customLinkTextField);
                }
                foreach ($linkFields as $field) {
                    $targetTab->push($field);
                }
                $mainTabSet->push($targetTab);
            }

            $behaviourFields = $this->getBehaviourFields()->toArray();
            $hasBehaviourFields = ($behaviourFields && count($behaviourFields) > 0);
            if ($hasBehaviourFields) {
                $behaviourTab = Tab::create('SuperLinkBehaviourTab', _t(__CLASS__.'.TabBehaviour', 'Behaviour'));
                if (!$hasBehaviourFields && $isCustomLinkTextEnabled) {
                    $behaviourTab->push($customLinkTextField);
                }
                foreach ($behaviourFields as $field) {
                    $behaviourTab->push($field);
                }
                $mainTabSet->push($behaviourTab);
            }

            if ($hasLinkFields || $hasBehaviourFields) {
                $rootTabSet->push($mainTabSet);
            }
        }
        else {

            $mainTab = Tab::create('Main');

            if ($isCustomLinkTextEnabled) {
                $mainTab->push($customLinkTextField);
            }

            foreach ($this->getLinkFields()->toArray() as $field) {
                $mainTab->push($field);
            }

            foreach ($this->getBehaviourFields()->toArray() as $field) {
                $mainTab->push($field);
            }

            $rootTabSet->push($mainTab);
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function getLinkFields()
    {
        $fields = FieldList::create(
            TextField::create('URL', _t(__CLASS__.'.URL', 'URL'))
        );

        $this->extend('updateLinkFields', $fields);
        return $fields;
    }

    public function getBehaviourFields()
    {
        $fields = FieldList::create(
            FieldGroup::create(
                _t(__CLASS__.'.NewWindow', 'New Window'),
                CheckboxField::create(
                    'DoOpenNewWindow',
                    _t(__CLASS__.'.DoOpenNewWindow', 'Open link in a new window')
                )
            ),
            FieldGroup::create(
                'SEO',
                CheckboxField::create(
                    'DoNoFollow',
                    _t(__CLASS__.'.DoNoFollow', 'Instruct search engines not to follow this link')
                )
            )
        );
        $this->extend('updateBehaviourFields', $fields);
        return $fields;
    }

    public function validate()
    {
        $result = ValidationResult::create();

        if ($this->isURLFieldValidationEnabled()) {
            if (!$this->URL) {
                $result->addFieldError('URL', _t(__CLASS__.'.URLRequired', 'You must provide a URL'));
            } else if (!filter_var($this->URL, FILTER_VALIDATE_URL)) {
                $result->addFieldError('URL', _t(__CLASS__.'.URLInvalid', 'You must provide a valid URL'));
            }
        }

        $this->extend('updateValidate', $result);
        return $result;
    }

    public function saveURL($value)
    {
        $parts = parse_url($value);

        if (isset($parts['fragment'])) {
            if ($this->isAnchorAllowed()) {
                $this->Anchor = $parts['fragment'];
            }
            unset($parts['fragment']);
        }

        if (isset($parts['query'])) {
            if ($this->isQueryStringAllowed()) {
                $this->QueryString = $parts['query'];
            }
            unset($parts['query']);
        }

        $this->URL = rtrim(http_build_url($parts), '/');

        $this->extend('updateSaveURL', $value);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->isAnchorAllowed()) {
            $this->Anchor = null;
        }
        if (!$this->isQueryStringAllowed()) {
            $this->QueryString = null;
        }
    }

    public function getLinkTarget()
    {
        $target = $this->dbObject('URL');
        $this->extend('updateLinkTarget', $target);
        return $target;
    }

    public function getMultiAddTitle()
    {
        $title = $this->config()->get('multi_add_title');
        $this->extend('updateMultiAddTitle', $title);
        return $title;
    }

    public function getGlobalAnchors()
    {
        $anchors = null;

        $isGlobalAnchorsEnabled = ModuleLoader::inst()
            ->getManifest()
            ->moduleExists('fromholdio/silverstripe-globalanchors');
        if ($isGlobalAnchorsEnabled) {
            $anchors = GlobalAnchors::get_anchors();
        }
        $this->extend('updateGlobalAnchors', $anchors);
        return $anchors;
    }

    public function getGlobalAnchor($key)
    {
        $anchors = $this->getGlobalAnchors();
        if (!$anchors) return null;
        if (!isset($anchors[$key])) return null;
        return $anchors[$key];
    }

    public function forTemplate()
    {
        return;
    }
}
