<?php

namespace Fromholdio\SuperLinker\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
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

    private static $field_labels = [
        'CustomLinkText'    =>  'Link text',
        'DoOpenNewWindow'   =>  'Open link in a new window',
        'DoNoFollow'        =>  'Instruct search engines not to follow this link'
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

        $link = Controller::join_links($this->URL, $queryString, $anchor);

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

        $url = $this->URL;

        if (Director::is_absolute_url($url)) {
            $absoluteURL = $url;
        } else {
            $absoluteURL = Director::absoluteURL($url);
        }

        $link = Controller::join_links($absoluteURL, $queryString, $anchor);

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

    public function getLinkText()
    {
        if ($this->CustomLinkText) {
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
        $this->extend('updateIsURLFieldValidationEnabled');
        return $enabled;
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function(FieldList $fields) {

            $fields->removeByName('URL');
            $fields->removeByName('DoOpenNewWindow');
            $fields->removeByName('DoNoFollow');
            $fields->removeByName('QueryString');
            $fields->removeByName('Anchor');

            $titleField = $fields->dataFieldByName('CustomLinkText');
            $titleField->setDescription('Optional. Will be auto-generated if left blank.');

            if ($this->config()->get('enable_tabs')) {

                $fields->addFieldsToTab(
                    'Root.LinkTarget',
                    $this->getLinkFields()->toArray()
                );

                $fields->addFieldsToTab(
                    'Root.LinkBehaviour',
                    $this->getBehaviourFields()->toArray()
                );

            } else {

                foreach (array_reverse($this->getLinkFields()->toArray()) as $field) {
                    $fields->insertAfter('CustomLinkText', $field);
                }

                foreach (array_reverse($this->getBehaviourFields()->toArray()) as $field) {
                    $fields->insertAfter('CustomLinkText', $field);
                }
            }
        });

        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getLinkFields()
    {
        $fields = FieldList::create(
            TextField::create('URL', $this->fieldLabel('URL'))
        );

        $this->extend('updateLinkFields', $fields);
        return $fields;
    }

    public function getBehaviourFields()
    {
        $fields = FieldList::create(
            FieldGroup::create(
                'Behaviour',
                CheckboxField::create('DoOpenNewWindow', $this->fieldLabel('DoOpenNewWindow')),
                CheckboxField::create('DoNoFollow', $this->fieldLabel('DoNoFollow'))
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
                $result->addFieldError('URL', 'You must provide a URL');
            } else if (!filter_var($this->URL, FILTER_VALIDATE_URL)) {
                $result->addFieldError('URL', 'You must provide a valid URL');
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
                $this->QueryString = $parts['fragment'];
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

    public function forTemplate()
    {
        return;
    }
}
