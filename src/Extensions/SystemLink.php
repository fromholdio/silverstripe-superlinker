<?php

namespace Fromholdio\SuperLinker\Extensions;

use Fromholdio\SuperLinker\Model\SuperLink;
use Fromholdio\SystemLinks\SystemLinks;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

class SystemLink extends DataExtension
{
    private static $singular_name = 'System Link';
    private static $plural_name = 'System Links';

    private static $multi_add_title = 'System link';

    private static $enable_url_field_validation = false;

    private static $system_links_source_class = SuperLink::class;

    private static $db = [
        'Key'       =>  'Varchar(30)'
    ];

    public function updateLinkFields(FieldList &$fields)
    {
        $links = $this->owner->getSystemLinks();
        if (!$links || empty($links)) {
            $fields = FieldList::create();
            return;
        }

        $keySource = [];
        foreach ($links as $key => $link) {
            $keySource[$key] = $link['title'];
        }

        $fields = FieldList::create(
            DropdownField::create(
                'Key',
                'System Links',
                $keySource
            )
                ->setHasEmptyDefault(false)
        );
    }

    public function updateValidate(ValidationResult &$result)
    {
        if (!$this->owner->Key) {
            $result->addFieldError('Key', 'You must select a system link');
        }
    }

    public function updateGenerateLinkText(&$text)
    {
        $link = $this->owner->getSystemLink($this->owner->Key);
        if (isset($link['title'])) {
            $text = $link['title'];
        } else {
            $text = null;
        }
    }

    public function updateHasTarget(&$hasTarget)
    {
        $link = $this->owner->getSystemLink($this->owner->Key);
        $hasTarget = isset($link['url']);
    }

    public function updateIsSiteURL(bool &$isSiteURL)
    {
        $isSiteURL = true;
    }

    public function updateLink(&$link)
    {
        $link = $this->owner->getSystemLink($this->owner->Key);
        if (isset($link['url'])) {
            $link = $link['url'];
        } else {
            $link = null;
        }
    }

    public function updateAbsoluteLink(&$absoluteLink)
    {
        $link = $this->owner->Link();
        if (!$link) {
            $absoluteLink = null;
        }

        if (Director::is_absolute_url($link)) {
            $absoluteLink = $link;
        } else {
            $absoluteLink = Director::absoluteURL($link);
        }
    }

    public function getSystemLinks()
    {
        $sourceClass = $this->owner->config()->get('system_links_source_class');
        $exists = ModuleLoader::inst()->getManifest()
            ->moduleExists('fromholdio/silverstripe-systemlinks');
        if ($exists || $sourceClass === SystemLinks::class) {
            $links = SystemLinks::get_raw_links();
        } else {
            $links = Config::inst()->get($sourceClass, 'system_links');
        }

        if ($this->owner->hasMethod('updateSystemLinks')) {
            $links = $this->owner->updateSystemLinks($links);
        }

        return $links;
    }

    public function getSystemLink($key)
    {
        $links = $this->owner->getSystemLinks();
        if (isset($links[$key])) {
            return $links[$key];
        }
        return null;
    }

    public function updateLinkTarget(&$target)
    {
        $target = $this->owner->getSystemLink($this->owner->Key);
    }
}
