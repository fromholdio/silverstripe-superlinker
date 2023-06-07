<?php

namespace Fromholdio\SuperLinker\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;

class FileLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'file';

    private static $types = [
        'file' => [
            'label' => 'Download a file',
            'use_upload_field' => false,
            'allow_uploads' => false,
            'starting_folder_path' => '',
            'settings' => [
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'DoForceDownload' => 'Boolean'
    ];

    private static $has_one = [
        'File' => File::class
    ];

    private static $owns = [
        'File'
    ];

    public function getLinkedFile(): ?File
    {
        if (!$this->isLinkTypeMatch()) return null;
        /** @var ?File $file */
        $file = $this->getOwner()->getComponent('File');
        return $file?->exists() && !($file instanceof Folder)
            ? $file
            : null;
    }

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = $this->getOwner()->getLinkedFile()?->getTitle();
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $url = $this->getOwner()->getLinkedFile()?->Link();
    }

    public function updateAbsoluteURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $url = $this->getOwner()->getLinkedFile()?->AbsoluteLink();
    }

    public function isDownloadForced(): bool
    {
        if (!$this->isLinkTypeMatch()) return false;
        return (bool) $this->getOwner()->getField('DoForceDownload');
    }

    public function updateDefaultAttributes(array &$attrs): void
    {
        if (!$this->isLinkTypeMatch()) return;
        if ($this->getOwner()->isDownloadForced()) {
            $attrs['download'] = $this->getOwner()->File()?->getField('Name');
        }
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;

        $folderPath = $this->getOwner()->getTypeConfigValue('starting_folder_path', $type);
        if (!empty($folderPath)) {
            $folder = Folder::find_or_make($folderPath);
        }

        if ($this->getOwner()->getTypeConfigValue('use_upload_field', $type))
        {
            $fileField = UploadField::create(
                'File',
                $this->getOwner()->fieldLabel('File')
            );
            if (!$this->getOwner()->getTypeConfigValue('allow_uploads', $type)) {
                $fileField->setUploadEnabled(false);
            }
            if (!is_null($folderPath)) {
                $fileField->setFolderName($folderPath);
            }
        }
        else {
            $fileField = TreeDropdownField::create(
                'FileID',
                $this->getOwner()->fieldLabel('File'),
                File::class,
                'ID',
                'Title'
            );
            $fileField->setFilterFunction(function($item) {
                if (is_a($item, Folder::class)) {
                    if ($item->Children()->count() < 1) {
                        return false;
                    }
                }
                return true;
            });
            $fileField->setDisableFunction(function($item) {
                return is_a($item, Folder::class);
            });
            if (!is_null($folderPath)) {
                $folderID = empty($folder) ? 0 : (int) $folder->getField('ID');
                $fileField->setTreeBaseID($folderID);
            }
        }
        $fields->push($fileField);

        $doForceDownloadField = DropdownField::create(
            'DoForceDownload',
            'Display mode',
            //$this->getOwner()->fieldLabel('DoForceDownload'),
            [
                1 => 'Download file directly to user\'s device',
                0 => 'Display file in browser window (when possible)'
            ]
        );
        $fields->push($doForceDownloadField);
    }
}
