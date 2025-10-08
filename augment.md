# SuperLinker Module - AI Technical Deep Dive

This document provides comprehensive technical details about the SuperLinker module for AI assistants. It covers architecture, implementation mechanics, patterns, edge cases, and debugging strategies.

## Architecture Overview

### Core Components

1. **SuperLink DataObject** (`src/Model/SuperLink.php`)
   - Minimal class using SuperLinkTrait
   - Table name: 'SuperLink'
   - All functionality in trait for reusability

2. **SuperLinkTrait** (`src/Model/SuperLinkTrait.php`)
   - 718 lines of core functionality
   - Extensible type system
   - Settings management
   - URL generation
   - HTML attribute generation
   - CMS field generation
   - Template rendering

3. **VersionedSuperLink** (`src/Model/VersionedSuperLink.php`)
   - Versioned variant for draft/published workflow
   - Uses same SuperLinkTrait

4. **SuperLinkTypeExtension** (`src/Extensions/SuperLinkTypeExtension.php`)
   - Base class for all link type extensions
   - Provides hook methods for customization
   - Type matching logic

5. **Link Type Extensions** (8 built-in)
   - SiteTreeLink - Links to pages
   - ExternalLink - External URLs
   - EmailLink - mailto: links
   - PhoneLink - tel: links
   - FileLink - File downloads
   - SystemLink - System routes
   - GlobalAnchorLink - Global anchors
   - NullLink - Text only

6. **Optional Feature Extensions** (3 available)
   - SuperLinkDescriptionExtension - Description field
   - SuperLinkIconExtension - Icon upload
   - SuperLinkImageExtension - Image upload

### Design Philosophy

**Problem Solved**: SilverStripe lacked a unified, extensible link management system. Existing solutions:
- Were fragmented (multiple incompatible modules)
- Had inconsistent UX
- Were difficult to extend
- Lacked validation and health checks
- Had no unified rendering

**Solution**: Single DataObject with:
- Extensible type system via Extensions
- Consistent CMS interface
- Type-specific configuration
- Built-in validation
- Unified template rendering
- Optional features via extensions

**Key Insight**: By using Extensions for types rather than subclasses, SuperLinker allows:
- Multiple types on single DataObject
- Easy addition of new types
- Type-specific fields without schema conflicts
- Configuration-driven behavior

## Type System Architecture

### Type Registration

Types are registered via Extension configuration:

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SiteTreeLink
    - Fromholdio\SuperLinker\Extensions\ExternalLink
    # etc.
```

Each extension defines:
```php
private static $extension_link_type = 'sitetree';

private static $types = [
    'sitetree' => [
        'label' => 'Page on this website',
        'sort' => 10,
        'allow_anchor' => true,
        'settings' => [
            'no_follow' => false
        ]
    ]
];
```

### Type Resolution Flow

```
User selects type in CMS
└─ LinkType field set to 'sitetree'
   └─ getType() returns 'sitetree'
      └─ Type-specific extension checks isLinkTypeMatch()
         └─ Extension provides fields, URL, validation
```

**Key Methods**:

1. **`getType(): ?string`**
   - Returns value of LinkType field
   - Used throughout to determine active type

2. **`isLinkTypeMatch(?string $type = null): bool`** (in SuperLinkTypeExtension)
   - Compares given type with extension's type
   - Used by extensions to conditionally execute logic
   - Pattern: `if (!$this->isLinkTypeMatch()) return;`

3. **`getAvailableTypes(): array`**
   - Returns types that are:
     - Registered via extensions
     - Not in disallowed_types
     - In allowed_types (if specified)
     - Have a label
   - Used to populate type dropdown

4. **`getAllTypes(): array`**
   - Returns all registered types from extensions
   - Sorted by 'sort' config value

### Type Configuration

**Type Config Structure**:
```php
'typename' => [
    'label' => 'Display Name',        // Required
    'sort' => 10,                      // Sort order (lower first)
    'allow_anchor' => true,            // Type-specific config
    'settings' => [                    // Override global settings
        'link_text' => true,
        'open_in_new' => false,
        'no_follow' => false
    ]
]
```

**Accessing Type Config**:
```php
$config = $link->getTypeConfigData('sitetree');
$label = $link->getTypeConfigValue('label', 'sitetree');
$allowAnchor = $link->getTypeConfigValue('allow_anchor', 'sitetree');
```

### Type-Specific Fields

Each type extension adds its own database fields:

**SiteTreeLink**:
```php
private static $db = [
    'SiteTreeAnchor' => 'Varchar(255)'
];

private static $has_one = [
    'SiteTree' => SiteTree::class
];
```

**ExternalLink**:
```php
private static $db = [
    'ExternalURL' => 'Varchar(2083)'
];
```

**EmailLink**:
```php
private static $db = [
    'Email' => 'Varchar',
    'EmailCC' => 'Varchar',
    'EmailBCC' => 'Varchar',
    'EmailSubject' => 'Varchar(255)',
    'EmailBody' => 'Text'
];
```

**Key Point**: All fields coexist on single DataObject. Only fields for active type are populated.

## Settings System

### Settings Architecture

**Global Settings** (enabled by default):
```php
private static $settings = [
    'link_text' => true,
    'open_in_new' => true,
    'no_follow' => true
];
```

**Type-Specific Overrides**:
```yaml
types:
  email:
    settings:
      open_in_new: false  # Disable for email links
      no_follow: false
```

**Optional Feature Settings** (via extensions):
```php
// SuperLinkDescriptionExtension
private static $settings = [
    'link_description' => true
];

// SuperLinkIconExtension
private static $settings = [
    'icon' => true
];

// SuperLinkImageExtension
private static $settings = [
    'link_image' => true
];
```

### Settings Resolution

**`isSettingEnabled(string $key): bool`**
- Checks global settings config
- Returns true if setting enabled globally

**`isTypeSettingEnabled(string $key, ?string $type): bool`**
- Checks if setting enabled for specific type
- First checks global setting
- Then checks type-specific override
- Type override of `false` disables even if globally enabled

**`getTypesByEnabledSetting(string $key): array`**
- Returns array of types where setting is enabled
- Used to apply display logic to fields
- Example: Show "Open in new tab" only for types where enabled

### Settings Usage Pattern

**In CMS Fields**:
```php
$openNewTypes = $this->getTypesByEnabledSetting('open_in_new');
if (!empty($openNewTypes)) {
    $field = CheckboxField::create('DoOpenInNew', 'Open in new tab');
    $this->applySettingFieldDisplayLogic($field, $openNewTypes, $fieldPrefix);
    $fields->push($field);
}
```

**In Templates/Logic**:
```php
if ($link->isOpenInNewEnabled()) {
    // Setting is enabled for this type
    if ($link->isOpenInNew()) {
        // User has checked the checkbox
        $target = '_blank';
    }
}
```

## URL Generation System

### URL Generation Flow

```
getURL()
└─ Calls updateURL() hook
   └─ Type extension implements updateURL()
      └─ Generates type-specific URL
         └─ Returns via reference parameter
```

**Core Method**:
```php
public function getURL(): ?string
{
    $url = null;
    $this->extend('updateURL', $url);
    return $url;
}
```

**Type Extension Implementation**:
```php
public function updateURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;
    $url = $this->getOwner()->getLinkedSiteTree()?->Link();
    $anchor = $this->getOwner()->getLinkedSiteTreeAnchor();
    if (!empty($anchor)) $url .= '#' . $anchor;
}
```

### Type-Specific URL Generation

**SiteTreeLink**:
```php
$url = $siteTree->Link();
if ($anchor) $url .= '#' . $anchor;
```

**ExternalLink**:
```php
$urlField = $this->dbObject('ExternalURL');
$url = $urlField->URL(); // Uses ExternalURLField validation
```

**EmailLink**:
```php
$url = 'mailto:' . $email;
// Add query params for cc, bcc, subject, body
$url .= '?subject=' . urlencode($subject);
```

**PhoneLink**:
```php
$phoneField = $this->dbObject('PhoneNumber');
$url = $phoneField->URL(); // Returns tel:+61412345678
```

**FileLink**:
```php
$url = $file->Link();
```

**SystemLink**:
```php
$sysLink = SystemLinks::get_link($key);
$url = $sysLink->URL; // Processed by SystemLinks
```

**GlobalAnchorLink**:
```php
$url = '#' . $anchorKey;
```

**NullLink**:
```php
// No URL - updateIsLinkEmpty() returns false
```

### Absolute URL Generation

```php
public function getAbsoluteURL(): ?string
{
    $url = $this->getURL();
    if (!empty($url) && Director::is_relative_url($url)) {
        $url = Director::absoluteURL($url);
    }
    $this->extend('updateAbsoluteURL', $url);
    return $url;
}
```

Type extensions can override:
```php
public function updateAbsoluteURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;
    $url = $this->getOwner()->getLinkedSiteTree()?->AbsoluteLink();
}
```

## CMS Fields Generation

### Field Generation Flow

```
getCMSFields()
└─ Creates TabSet with Main tab
   └─ Calls getCMSLinkFields()
      └─ Generates LinkType dropdown
      └─ Generates LinkText field (if enabled)
      └─ Calls updateCMSLinkFieldsBeforeTypes hook
      └─ For each type:
         └─ Calls getCMSLinkTypeFields(type)
            └─ Type extension implements updateCMSLinkTypeFields
               └─ Adds type-specific fields
               └─ Wraps in display logic (show when type selected)
      └─ Calls updateCMSLinkFieldsAfterTypes hook
      └─ Generates Options group (DoOpenInNew, DoNoFollow)
      └─ Calls updateCMSLinkFields hook
```

### Link Type Field

**Generated by `getLinkTypeField()`**:
```php
$field = DropdownField::create('LinkType', 'Type', $source);
$field->setHasEmptyDefault(true);
$field->setEmptyString('-- Select link type --');
```

**Source populated from**:
```php
$types = $this->getAvailableTypes();
foreach ($types as $type) {
    $source[$type] = $this->getTypeLabel($type);
}
```

**Field class configurable**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  link_type_field_class: SilverStripe\Forms\OptionsetField
```

### Link Text Field

**Generated when `link_text` setting enabled**:
```php
$linkTextTypes = $this->getTypesByEnabledSetting('link_text');
if (!empty($linkTextTypes)) {
    $field = TextField::create('LinkText', 'Text');
    $field->setDescription('Optional. Will be auto-generated from link if left blank.');
    $field->setAttribute('placeholder', $this->getDefaultTitle());
    $this->applySettingFieldDisplayLogic($field, $linkTextTypes, $fieldPrefix);
    $fields->push($field);
}
```

### Type-Specific Fields

**Hook Method**:
```php
protected function getCMSLinkTypeFields(string $type, string $fieldPrefix = ''): FieldList
{
    $fields = FieldList::create();
    $this->extend('updateCMSLinkTypeFields', $fields, $type, $fieldPrefix);
    return $fields;
}
```

**Extension Implementation**:
```php
public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
{
    if (!$this->isLinkTypeMatch($type)) return;
    
    $fields->push(TreeDropdownField::create(
        $fieldPrefix . 'SiteTreeID',
        'Page on this website',
        SiteTree::class
    ));
    
    // More fields...
}
```

**Display Logic Applied**:
```php
$typeWrapper = Wrapper::create($typeFields);
$typeWrapper->setName($fieldPrefix . 'TypeWrapper_' . $type);
$typeWrapper->hideUnless($fieldPrefix. 'LinkType')->isEqualTo($type);
$fields->push($typeWrapper);
```

### Field Prefix Pattern

**Purpose**: Support inline editing via HasOneEdit

**Usage**:
```php
$linkFields = SuperLink::singleton()->getCMSLinkFields('SuperLink' . HasOneEdit::FIELD_SEPARATOR);
```

**Result**: Fields named like `SuperLink__LinkType`, `SuperLink__LinkText`, etc.

**Why**: HasOneEdit uses separator to map fields to relation object

## HTML Attribute Generation

### Attribute Generation Flow

```
getDefaultAttributes()
└─ Builds array of attributes
   ├─ href: getHrefValue()
   ├─ target: getTargetValue()
   ├─ rel: getRelValue()
   ├─ class: getClassValue()
   └─ data-superlinker-type: getType()
└─ Calls updateDefaultAttributes() hook
└─ Filters empty values
└─ Returns array
```

### AttributesHTML Rendering

**Trait**: Uses `AttributesHTML` trait from SilverStripe

**Method**: `getAttributesHTML()`

**Usage in Templates**:
```html
<a $AttributesHTML>$Title</a>
```

**Renders as**:
```html
<a href="/page" target="_blank" rel="noopener" class="btn" data-superlinker-type="sitetree">Title</a>
```

### Href Attribute

```php
public function getHrefValue(): ?string
{
    $href = $this->getURL();
    $this->extend('updateHrefValue', $href);
    return $href;
}
```

### Target Attribute

```php
public function getTargetValue(): ?string
{
    $target = null;
    if ($this->isOpenInNew()) {
        $target = '_blank';
    }
    $this->extend('updateTargetValue', $target);
    return $target;
}
```

### Rel Attribute

```php
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
```

**isNoOpener() Logic**:
```php
public function isNoOpener(): bool
{
    $url = $this->getURL();
    $do = !empty($url) && !Director::is_site_url($url);
    $this->extend('updateIsNoOpener', $do);
    return $do;
}
```

**Key Point**: `noopener` automatically added for external URLs (security best practice)

### Class Attribute

```php
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
```

**Usage**:
```php
$link->addExtraCSSClass('btn btn-primary');
```

## Link Validation System

### Validation Methods

**`isLinkValid(): bool`**
- Comprehensive validation check
- Returns true if all conditions met:
  - Has a type
  - Type is available (not disabled)
  - Not orphaned
  - Not empty

**`isLinkOrphaned(): bool`**
- Future feature for detecting broken relations
- Currently always returns false
- Hook: `updateIsLinkOrphaned($do)`

**`isLinkEmpty(): bool`**
- Checks if link generates a URL
- Returns true if `getURL()` returns null/empty
- Hook: `updateIsLinkEmpty($do)`
- NullLink overrides to return false

### Validation Flow

```
isLinkValid()
├─ Check hasType()
├─ Check isTypeAvailable()
├─ Check !isLinkOrphaned()
└─ Check !isLinkEmpty()
```

**Usage**:
```php
if ($link->isLinkValid()) {
    // Safe to render
}
```

### Filtering Invalid Links

**Static Method**:
```php
public static function excludeInvalidLinks(SS_List $links): ArrayList
{
    $validLinks = ArrayList::create();
    foreach ($links as $link) {
        if ($link->isLinkValid()) {
            $validLinks->push($link);
        }
    }
    return $validLinks;
}
```

**Usage**:
```php
$links = $page->Links();
$validLinks = SuperLink::excludeInvalidLinks($links);
```

**Template Usage**:
```html
<% with $Links.filterByCallback('isLinkValid') %>
    <% loop $Me %>
        <a href="$URL">$Title</a>
    <% end_loop %>
<% end_with %>
```

## Title Generation System

### Title Resolution Flow

```
getTitle()
├─ Check LinkText field (custom title)
│  └─ If not empty, return LinkText
└─ Fall back to getDefaultTitle()
   └─ Calls updateDefaultTitle() hook
      └─ Type extension provides default
         └─ Returns type-specific default
```

**Core Method**:
```php
public function getTitle(): string
{
    $title = $this->getField('LinkText');
    if (empty($title)) {
        $title = $this->getDefaultTitle();
    }
    $this->extend('updateTitle', $title);
    return $title;
}
```

**Default Title Method**:
```php
public function getDefaultTitle(): string
{
    $title = '';
    $this->extend('updateDefaultTitle', $title);
    if (empty($title)) {
        $title = $this->getTypeLabel();
    }
    return $title;
}
```

### Type-Specific Default Titles

**SiteTreeLink**:
```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;
    $siteTree = $this->getOwner()->getLinkedSiteTree();
    $title = $siteTree?->MenuTitle ?: $siteTree?->Title;
}
```

**ExternalLink**:
```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;
    $url = $this->getOwner()->getField('ExternalURL');
    $title = $url;
}
```

**EmailLink**:
```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;
    $email = $this->getOwner()->getField('Email');
    $title = $email;
}
```

**FileLink**:
```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;
    $file = $this->getOwner()->getLinkedFile();
    $title = $file?->Title ?: $file?->Name;
}
```

**SystemLink**:
```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;
    $sysLink = $this->getOwner()->getLinkedSystemLink();
    $title = $sysLink?->Title;
}
```

## Template Rendering System

### Template Resolution

**Method**: `forTemplate(): string`

**Template Search Order**:
1. `{ClassName}_{TypeKey}.ss` (e.g., `SuperLink_sitetree.ss`)
2. `{ClassName}.ss` (e.g., `SuperLink.ss`)
3. Fallback to default rendering

**Default Rendering**:
```php
$attrs = $this->getDefaultAttributes();
$title = $this->getTitle();
return sprintf('<a %s>%s</a>', $this->getAttributesHTML(), $title);
```

**Hook**: `updateForTemplate($html)`

### Template Locations

**Project Templates**:
```
themes/yourtheme/templates/Fromholdio/SuperLinker/Model/
├── SuperLink.ss
├── SuperLink_sitetree.ss
├── SuperLink_external.ss
└── SuperLink_email.ss
```

**Module Templates**:
```
vendor/fromholdio/silverstripe-superlinker/templates/Fromholdio/SuperLinker/Model/
└── SuperLink.ss
```

### Custom Template Example

**SuperLink_external.ss**:
```html
<a href="$URL" target="_blank" rel="noopener" class="external-link">
    $Title
    <span class="icon-external">↗</span>
</a>
```

**SuperLink_file.ss**:
```html
<a href="$URL" <% if $isDownloadForced %>download<% end_if %> class="file-link">
    <span class="icon-download">⬇</span>
    $Title
    <% if $LinkedFile %>
        <span class="file-size">($LinkedFile.Size.Nice)</span>
    <% end_if %>
</a>
```

## Template Helper Methods

### isCurrent() and isSection()

**Purpose**: Determine if link points to current page or section

**Implementation** (SiteTreeLink):
```php
public function updateIsCurrent(?bool &$isCurrent): void
{
    if (!$this->isLinkTypeMatch()) return;
    $siteTree = $this->getOwner()->getLinkedSiteTree();
    if (!$siteTree || !$siteTree->exists()) return;

    $currentPage = Director::get_current_page();
    if (!$currentPage || !$currentPage->exists()) return;

    $isCurrent = ($siteTree->ID === $currentPage->ID);
}

public function updateIsSection(?bool &$isSection): void
{
    if (!$this->isLinkTypeMatch()) return;
    $siteTree = $this->getOwner()->getLinkedSiteTree();
    if (!$siteTree || !$siteTree->exists()) return;

    $currentPage = Director::get_current_page();
    if (!$currentPage || !$currentPage->exists()) return;

    $isSection = in_array($siteTree->ID, $currentPage->getAncestors()->column('ID'));
}
```

**Usage in Templates**:
```html
<% loop $MenuLinks %>
    <li class="<% if $isCurrent %>current<% else_if $isSection %>section<% end_if %>">
        <a href="$URL">$Title</a>
    </li>
<% end_loop %>
```

### LinkingMode()

**Returns**: 'current', 'section', or 'link'

**Implementation**:
```php
public function LinkingMode(): string
{
    if ($this->isCurrent()) {
        return 'current';
    }
    if ($this->isSection()) {
        return 'section';
    }
    return 'link';
}
```

**Usage**:
```html
<li class="$LinkingMode">
    <a href="$URL">$Title</a>
</li>
```

### LinkOrCurrent() and LinkOrSection()

**Purpose**: Return 'link' or 'current'/'section' for CSS classes

**Implementation**:
```php
public function LinkOrCurrent(): string
{
    return $this->isCurrent() ? 'current' : 'link';
}

public function LinkOrSection(): string
{
    return $this->isSection() ? 'section' : 'link';
}
```

## Dependency Integration

### SystemLinks Integration

**Purpose**: Provide dropdown of system links

**Configuration**:
```yaml
Fromholdio\SystemLinks\SystemLinks:
  links:
    login:
      url: $Login
      title: Login
    logout:
      url: $Logout
      title: Logout
```

**Special URL Processing**:
- `$Login` → `Security::login_url()`
- `$Logout` → `Security::logout_url()` + SecurityToken
- `$LostPassword` → `Security::lost_password_url()`

**Custom Processing Hook**:
```php
// In Controller
public function doProcessSystemLinkURL(string $url): string
{
    if ($url === '$CustomRoute') {
        return $this->Link('custom-action');
    }
    return $url;
}
```

**SystemLink Extension Usage**:
```php
public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
{
    if (!$this->isLinkTypeMatch($type)) return;

    $map = SystemLinks::get_map('title');
    $fields->push(
        DropdownField::create($fieldPrefix . 'SystemLinkKey', 'System Link', $map)
    );
}

public function updateURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;
    $sysLink = $this->getOwner()->getLinkedSystemLink();
    $url = $sysLink?->URL;
}
```

### GlobalAnchors Integration

**Purpose**: Provide dropdown of global anchors

**Configuration**:
```yaml
Fromholdio\GlobalAnchors\GlobalAnchors:
  anchors:
    nav: 'Main Navigation'
    content: 'Page Content'
    footer: 'Footer'
```

**GlobalAnchorLink Extension Usage**:
```php
public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
{
    if (!$this->isLinkTypeMatch($type)) return;

    $anchors = GlobalAnchors::get_anchors();
    $fields->push(
        DropdownField::create($fieldPrefix . 'GlobalAnchorKey', 'Anchor', $anchors)
    );
}

public function updateURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;
    $key = $this->getOwner()->getField('GlobalAnchorKey');
    $url = empty($key) ? null : '#' . $key;
}
```

**Absolute URL for GlobalAnchors**:
```php
public function updateAbsoluteURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;
    $key = $this->getOwner()->getField('GlobalAnchorKey');
    if (empty($key)) return;

    $currentPage = Director::get_current_page();
    if ($currentPage && $currentPage->exists()) {
        $url = $currentPage->AbsoluteLink() . '#' . $key;
    }
}
```

### DBHTMLAnchors Integration

**Purpose**: Extract anchors from HTML content

**Used by**: SiteTreeLink for anchor dropdown

**Usage**:
```php
use Fromholdio\DBHTMLAnchors\DBHTMLAnchors;

$contentAnchors = DBHTMLAnchors::get_anchors($page->Content);
// Returns: ['anchor1' => 'Anchor 1', 'anchor2' => 'Anchor 2']
```

**SiteTreeLink Implementation**:
```php
public function getAvailableSiteTreeAnchors(int|string|null $siteTreeID): array
{
    $anchors = [];

    // Get content anchors
    $siteTree = SiteTree::get()->byID($siteTreeID);
    if ($siteTree && $siteTree->exists()) {
        $contentAnchors = DBHTMLAnchors::get_anchors($siteTree->Content);
        if (!empty($contentAnchors)) {
            $anchors['Content Anchors'] = $contentAnchors;
        }
    }

    // Get global anchors
    $globalAnchors = GlobalAnchors::get_anchors();
    if (!empty($globalAnchors)) {
        $anchors['Global Anchors'] = $globalAnchors;
    }

    return $anchors;
}
```

### DependentGroupedDropdownField Integration

**Purpose**: Anchor dropdown that depends on page selection

**Usage in SiteTreeLink**:
```php
use Fromholdio\DependentGroupedDropdownField\DependentGroupedDropdownField;

$anchorField = DependentGroupedDropdownField::create(
    $fieldPrefix . 'SiteTreeAnchor',
    'Anchor on page',
    $this->getOwner()
);
$anchorField->setDependsOn($fieldPrefix . 'SiteTreeID');
$anchorField->setSourceCallback([$this->getOwner(), 'getAvailableSiteTreeAnchors']);
```

**How it works**:
1. User selects page in SiteTreeID dropdown
2. JavaScript triggers AJAX request with selected page ID
3. Server calls `getAvailableSiteTreeAnchors($pageID)`
4. Anchor dropdown updates with page-specific anchors

## Optional Feature Extensions

### SuperLinkDescriptionExtension

**Database Fields**:
```php
private static $db = [
    'LinkDescription' => 'Text'
];
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  link_description_rows: 3  # TextField if 1, TextareaField if > 1
```

**CMS Field Generation**:
```php
public function updateCMSLinkFields(FieldList $fields, string $fieldPrefix): void
{
    $types = $this->getOwner()->getTypesByEnabledSetting('link_description');
    if (empty($types)) return;

    $rows = $this->getOwner()->config()->get('link_description_rows');
    $field = ($rows > 1)
        ? TextareaField::create($fieldPrefix . 'LinkDescription', 'Description')
            ->setRows($rows)
        : TextField::create($fieldPrefix . 'LinkDescription', 'Description');

    $field->setAttribute('placeholder', $this->getOwner()->getDefaultDescription());
    $this->getOwner()->applySettingFieldDisplayLogic($field, $types, $fieldPrefix);
    $fields->push($field);
}
```

**Default Description Generation**:
```php
public function getDefaultDescription(): ?string
{
    $description = null;

    // Try linked object's method
    $linkedObject = $this->getLinkedObjectForDefaultDescription();
    if ($linkedObject && $linkedObject->hasMethod('getSuperLinkDefaultDescription')) {
        $description = $linkedObject->getSuperLinkDefaultDescription();
    }

    $this->getOwner()->extend('updateDefaultDescription', $description);
    return $description;
}

protected function getLinkedObjectForDefaultDescription(): ?DataObject
{
    $type = $this->getOwner()->getType();

    if ($type === 'sitetree') {
        return $this->getOwner()->getLinkedSiteTree();
    }
    if ($type === 'file') {
        return $this->getOwner()->getLinkedFile();
    }

    return null;
}
```

**Implementing on Target Objects**:
```php
class Page extends SiteTree
{
    public function getSuperLinkDefaultDescription(): ?string
    {
        return $this->MetaDescription ?: $this->Summary;
    }
}
```

### SuperLinkIconExtension

**Database Fields**:
```php
private static $has_one = [
    'Icon' => Image::class
];

private static $owns = [
    'Icon'
];
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  icon_folder_path: 'link-icons'
  icon_allowed_extensions:
    - svg
    - png
    - jpg
  icon_allowed_categories:
    - image
```

**CMS Field Generation**:
```php
public function updateCMSLinkFields(FieldList $fields, string $fieldPrefix): void
{
    $types = $this->getOwner()->getTypesByEnabledSetting('icon');
    if (empty($types)) return;

    $field = UploadField::create($fieldPrefix . 'Icon', 'Icon');
    $field->setFolderName($this->getOwner()->config()->get('icon_folder_path'));
    $field->setAllowedExtensions($this->getOwner()->config()->get('icon_allowed_extensions'));
    $field->setAllowedFileCategories($this->getOwner()->config()->get('icon_allowed_categories'));

    $this->getOwner()->applySettingFieldDisplayLogic($field, $types, $fieldPrefix);
    $fields->push($field);
}
```

### SuperLinkImageExtension

**Database Fields**:
```php
private static $has_one = [
    'LinkImage' => Image::class
];

private static $owns = [
    'LinkImage'
];
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  link_image_upload_path: 'link-images'
```

**Default Image Generation**:
```php
public function getDefaultImage(): ?Image
{
    $image = null;

    $linkedObject = $this->getLinkedObjectForDefaultImage();
    if ($linkedObject && $linkedObject->hasMethod('getSuperLinkDefaultImage')) {
        $image = $linkedObject->getSuperLinkDefaultImage();
    }

    $this->getOwner()->extend('updateDefaultImage', $image);
    return $image;
}

protected function getLinkedObjectForDefaultImage(): ?DataObject
{
    $type = $this->getOwner()->getType();

    if ($type === 'sitetree') {
        return $this->getOwner()->getLinkedSiteTree();
    }
    if ($type === 'file') {
        $file = $this->getOwner()->getLinkedFile();
        return ($file && $file instanceof Image) ? $file : null;
    }

    return null;
}
```

**Helper Extensions**:

**SuperLinkImagePageExtension** (apply to SiteTree):
```php
public function getSuperLinkDefaultImage(): ?Image
{
    // Override in subclasses
    return null;
}
```

**SuperLinkImageFileExtension** (apply to File):
```php
public function getSuperLinkDefaultImage(): ?Image
{
    $file = $this->getOwner();
    return ($file instanceof Image && $file->exists()) ? $file : null;
}
```

## Creating Custom Link Types

### Step-by-Step Guide

**1. Create Extension Class**:

```php
namespace App\Extensions;

use Fromholdio\SuperLinker\Extensions\SuperLinkTypeExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

class CustomLink extends SuperLinkTypeExtension
{
    // Define the type key
    private static $extension_link_type = 'custom';

    // Configure the type
    private static $types = [
        'custom' => [
            'label' => 'Custom Link Type',
            'sort' => 100,
            'settings' => [
                'link_text' => true,
                'open_in_new' => true,
                'no_follow' => false
            ]
        ]
    ];

    // Add database fields
    private static $db = [
        'CustomField' => 'Varchar(255)'
    ];

    // Add relations if needed
    private static $has_one = [
        'CustomObject' => DataObject::class
    ];
}
```

**2. Implement URL Generation**:

```php
public function updateURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) return;

    $customField = $this->getOwner()->getField('CustomField');
    if (empty($customField)) return;

    $url = 'https://example.com/' . urlencode($customField);
}
```

**3. Implement Default Title**:

```php
public function updateDefaultTitle(?string &$title): void
{
    if (!$this->isLinkTypeMatch()) return;

    $customField = $this->getOwner()->getField('CustomField');
    $title = $customField ?: 'Custom Link';
}
```

**4. Add CMS Fields**:

```php
public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
{
    if (!$this->isLinkTypeMatch($type)) return;

    $fields->push(
        TextField::create($fieldPrefix . 'CustomField', 'Custom Field')
            ->setDescription('Enter custom value')
    );
}
```

**5. Apply Extension**:

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - App\Extensions\CustomLink
```

**6. Run dev/build**:

```bash
vendor/bin/sake dev/build flush=1
```

### Advanced Custom Type Example: YouTube Link

```php
namespace App\Extensions;

use Fromholdio\SuperLinker\Extensions\SuperLinkTypeExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxField;

class YouTubeLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'youtube';

    private static $types = [
        'youtube' => [
            'label' => 'YouTube Video',
            'sort' => 50,
            'settings' => [
                'link_text' => true,
                'open_in_new' => true,
                'no_follow' => false
            ]
        ]
    ];

    private static $db = [
        'YouTubeID' => 'Varchar(20)',
        'YouTubeStartTime' => 'Int',
        'YouTubeAutoplay' => 'Boolean'
    ];

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;

        $id = $this->getOwner()->getField('YouTubeID');
        if (empty($id)) return;

        $url = 'https://www.youtube.com/watch?v=' . $id;

        $params = [];
        $startTime = $this->getOwner()->getField('YouTubeStartTime');
        if ($startTime > 0) {
            $params['t'] = $startTime;
        }
        if ($this->getOwner()->getField('YouTubeAutoplay')) {
            $params['autoplay'] = 1;
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
    }

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = 'Watch on YouTube';
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;

        $fields->push(
            TextField::create($fieldPrefix . 'YouTubeID', 'YouTube Video ID')
                ->setDescription('e.g., dQw4w9WgXcQ (from https://www.youtube.com/watch?v=dQw4w9WgXcQ)')
        );

        $fields->push(
            TextField::create($fieldPrefix . 'YouTubeStartTime', 'Start Time (seconds)')
                ->setDescription('Optional. Start video at specific time.')
        );

        $fields->push(
            CheckboxField::create($fieldPrefix . 'YouTubeAutoplay', 'Autoplay')
        );
    }

    // Custom getter
    public function getYouTubeEmbedURL(): ?string
    {
        if (!$this->isLinkTypeMatch()) return null;

        $id = $this->getOwner()->getField('YouTubeID');
        if (empty($id)) return null;

        return 'https://www.youtube.com/embed/' . $id;
    }
}
```

**Custom Template** (`SuperLink_youtube.ss`):
```html
<div class="youtube-link">
    <a href="$URL" target="_blank" rel="noopener" class="youtube-link__button">
        <span class="icon-youtube"></span>
        $Title
    </a>
    <% if $YouTubeEmbedURL %>
        <div class="youtube-link__embed">
            <iframe src="$YouTubeEmbedURL" frameborder="0" allowfullscreen></iframe>
        </div>
    <% end_if %>
</div>
```

## Edge Cases and Gotchas

### 1. Field Prefix in Inline Editing

**Problem**: When using HasOneEdit, fields need special naming

**Solution**: Always pass field prefix to getCMSLinkFields():
```php
$linkFields = SuperLink::singleton()->getCMSLinkFields('SuperLink' . HasOneEdit::FIELD_SEPARATOR);
```

**Why**: HasOneEdit uses separator (`__`) to map fields to relation object

### 2. Type-Specific Fields Persisting

**Problem**: When changing link type, old type's fields remain in database

**Solution**: This is by design. Only active type's fields are used.

**Best Practice**: Clear old fields on type change:
```php
public function onBeforeWrite(): void
{
    parent::onBeforeWrite();

    if ($this->isChanged('LinkType')) {
        // Clear fields from other types
        $currentType = $this->getType();
        foreach ($this->getAllTypes() as $type) {
            if ($type !== $currentType) {
                $this->clearFieldsForType($type);
            }
        }
    }
}
```

### 3. Display Logic Not Working

**Problem**: Type-specific fields showing for wrong type

**Solution**: Ensure display logic applied correctly:
```php
$this->applySettingFieldDisplayLogic($field, $types, $fieldPrefix);
```

**Check**: Field prefix must match LinkType field prefix

### 4. URL Not Generating

**Problem**: getURL() returns null

**Debug Steps**:
1. Check type is set: `$link->getType()`
2. Check type extension exists and is applied
3. Check updateURL() is being called
4. Check type-specific fields are populated
5. Check isLinkTypeMatch() returns true

**Common Causes**:
- Type extension not applied in YAML
- Type key mismatch
- Required fields empty

### 5. isCurrent() Always False

**Problem**: Navigation links not showing as current

**Solution**: Ensure Director::get_current_page() returns valid page

**Check**:
```php
$currentPage = Director::get_current_page();
var_dump($currentPage); // Should be SiteTree object
```

**Common Cause**: Called outside page context (e.g., in task, email)

### 6. Versioned Links Not Publishing

**Problem**: Changes to VersionedSuperLink not appearing on live

**Solution**: Ensure link is owned by parent:
```php
class Page extends SiteTree
{
    private static $has_one = [
        'CTALink' => VersionedSuperLink::class
    ];

    private static $owns = [
        'CTALink'
    ];
}
```

**Why**: Ownership ensures link publishes with parent

### 7. Anchor Dropdown Empty

**Problem**: SiteTreeAnchor dropdown shows no options

**Causes**:
1. Page has no content anchors
2. GlobalAnchors not configured
3. DependentGroupedDropdownField not working

**Debug**:
```php
$anchors = $link->getAvailableSiteTreeAnchors($pageID);
var_dump($anchors);
```

### 8. Settings Not Applying

**Problem**: Type-specific setting override not working

**Check Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    email:
      settings:
        open_in_new: false  # Must be under 'settings' key
```

**Not**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    email:
      open_in_new: false  # Wrong - not under 'settings'
```

## Debugging Strategies

### 1. Check Type Configuration

```php
// Get all types
$allTypes = $link->getAllTypes();
var_dump($allTypes);

// Get available types
$availableTypes = $link->getAvailableTypes();
var_dump($availableTypes);

// Get type config
$config = $link->getTypeConfigData('sitetree');
var_dump($config);
```

### 2. Check Link Health

```php
echo "Type: " . $link->getType() . "\n";
echo "Has Type: " . ($link->hasType() ? 'Yes' : 'No') . "\n";
echo "Type Available: " . ($link->isTypeAvailable() ? 'Yes' : 'No') . "\n";
echo "Is Orphaned: " . ($link->isLinkOrphaned() ? 'Yes' : 'No') . "\n";
echo "Is Empty: " . ($link->isLinkEmpty() ? 'Yes' : 'No') . "\n";
echo "Is Valid: " . ($link->isLinkValid() ? 'Yes' : 'No') . "\n";
```

### 3. Check URL Generation

```php
echo "URL: " . $link->getURL() . "\n";
echo "Absolute URL: " . $link->getAbsoluteURL() . "\n";
echo "Href: " . $link->getHrefValue() . "\n";
```

### 4. Check Title Generation

```php
echo "Title: " . $link->getTitle() . "\n";
echo "Default Title: " . $link->getDefaultTitle() . "\n";
echo "Link Text: " . $link->getField('LinkText') . "\n";
```

### 5. Check Attributes

```php
$attrs = $link->getDefaultAttributes();
var_dump($attrs);

echo "Target: " . $link->getTargetValue() . "\n";
echo "Rel: " . $link->getRelValue() . "\n";
echo "Class: " . $link->getClassValue() . "\n";
```

### 6. Check Settings

```php
echo "Link Text Enabled: " . ($link->isLinkTextEnabled() ? 'Yes' : 'No') . "\n";
echo "Open In New Enabled: " . ($link->isOpenInNewEnabled() ? 'Yes' : 'No') . "\n";
echo "Open In New: " . ($link->isOpenInNew() ? 'Yes' : 'No') . "\n";
echo "No Follow Enabled: " . ($link->isNoFollowEnabled() ? 'Yes' : 'No') . "\n";
echo "No Follow: " . ($link->isNoFollow() ? 'Yes' : 'No') . "\n";
```

### 7. Check Extension Hooks

Add debug output to extension methods:

```php
public function updateURL(?string &$url): void
{
    if (!$this->isLinkTypeMatch()) {
        error_log('Type mismatch: ' . $this->getOwner()->getType() . ' !== ' . $this->getExtensionLinkType());
        return;
    }

    error_log('Generating URL for ' . $this->getExtensionLinkType());
    // ... URL generation
    error_log('Generated URL: ' . $url);
}
```

### 8. Check CMS Fields

```php
$fields = $link->getCMSFields();
foreach ($fields as $field) {
    echo $field->getName() . ': ' . get_class($field) . "\n";
}

$linkFields = $link->getCMSLinkFields();
foreach ($linkFields as $field) {
    echo $field->getName() . ': ' . get_class($field) . "\n";
}
```

## Performance Considerations

### 1. Eager Loading Relations

**Problem**: N+1 queries when looping links

**Solution**: Eager load relations:
```php
$links = SuperLink::get()
    ->leftJoin('SiteTree', '"SuperLink"."SiteTreeID" = "SiteTree"."ID"')
    ->leftJoin('File', '"SuperLink"."FileID" = "File"."ID"');
```

### 2. Caching URL Generation

**Problem**: Complex URL generation on every call

**Solution**: Cache in field:
```php
private static $db = [
    'CachedURL' => 'Varchar(2083)'
];

public function onBeforeWrite(): void
{
    parent::onBeforeWrite();
    $this->CachedURL = $this->getURL();
}

public function getURL(): ?string
{
    if ($this->exists() && !empty($this->CachedURL)) {
        return $this->CachedURL;
    }
    // Generate URL...
}
```

### 3. Filtering Invalid Links

**Problem**: Checking validity in template loop

**Bad**:
```html
<% loop $Links %>
    <% if $isLinkValid %>
        <a href="$URL">$Title</a>
    <% end_if %>
<% end_loop %>
```

**Good**:
```php
public function getValidLinks(): ArrayList
{
    return SuperLink::excludeInvalidLinks($this->Links());
}
```

```html
<% loop $ValidLinks %>
    <a href="$URL">$Title</a>
<% end_loop %>
```

### 4. Template Rendering

**Problem**: Complex template logic on every render

**Solution**: Cache rendered HTML:
```php
public function getCachedHTML(): string
{
    $cacheKey = 'superlink_' . $this->ID . '_' . $this->LastEdited;
    $cache = Injector::inst()->get(CacheInterface::class . '.SuperLinkCache');

    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }

    $html = $this->forTemplate();
    $cache->set($cacheKey, $html);
    return $html;
}
```

## Testing Strategies

### Unit Tests

**Test Type Registration**:
```php
public function testTypeRegistration(): void
{
    $link = SuperLink::create();
    $types = $link->getAllTypes();

    $this->assertArrayHasKey('sitetree', $types);
    $this->assertArrayHasKey('external', $types);
    $this->assertEquals('Page on this website', $types['sitetree']['label']);
}
```

**Test URL Generation**:
```php
public function testSiteTreeLinkURL(): void
{
    $page = $this->objFromFixture(Page::class, 'page1');
    $link = SuperLink::create([
        'LinkType' => 'sitetree',
        'SiteTreeID' => $page->ID
    ]);

    $this->assertEquals($page->Link(), $link->getURL());
}
```

**Test Validation**:
```php
public function testLinkValidation(): void
{
    $link = SuperLink::create(['LinkType' => 'external']);
    $this->assertFalse($link->isLinkValid()); // No URL

    $link->ExternalURL = 'https://example.com';
    $this->assertTrue($link->isLinkValid());
}
```

### Integration Tests

**Test CMS Fields**:
```php
public function testCMSFields(): void
{
    $link = SuperLink::create();
    $fields = $link->getCMSFields();

    $this->assertNotNull($fields->dataFieldByName('LinkType'));
    $this->assertNotNull($fields->dataFieldByName('LinkText'));
}
```

**Test Template Rendering**:
```php
public function testTemplateRendering(): void
{
    $page = $this->objFromFixture(Page::class, 'page1');
    $link = SuperLink::create([
        'LinkType' => 'sitetree',
        'SiteTreeID' => $page->ID,
        'LinkText' => 'Test Link'
    ]);

    $html = $link->forTemplate();
    $this->assertStringContainsString('href="' . $page->Link() . '"', $html);
    $this->assertStringContainsString('Test Link', $html);
}
```

### Functional Tests

**Test Link in Page Context**:
```php
public function testLinkInPageContext(): void
{
    $page = $this->objFromFixture(Page::class, 'page1');
    $link = $this->objFromFixture(SuperLink::class, 'link1');

    $this->get($page->Link());
    $this->assertPartialMatchBySelector('a[href="' . $link->getURL() . '"]', [
        'Test Link'
    ]);
}
```

## Common Patterns

### Pattern 1: Menu Links with SuperLinker

```php
class Page extends SiteTree
{
    private static $has_many = [
        'MenuLinks' => SuperLink::class
    ];

    public function getValidMenuLinks(): ArrayList
    {
        return SuperLink::excludeInvalidLinks($this->MenuLinks());
    }
}
```

### Pattern 2: CTA Buttons

```php
class HomePage extends Page
{
    private static $has_one = [
        'PrimaryCTA' => SuperLink::class,
        'SecondaryCTA' => SuperLink::class
    ];

    private static $owns = [
        'PrimaryCTA',
        'SecondaryCTA'
    ];
}
```

### Pattern 3: Social Links

```php
class SiteConfigExtension extends DataExtension
{
    private static $has_many = [
        'SocialLinks' => SuperLink::class
    ];

    public function getSocialLinks(): DataList
    {
        return $this->getOwner()->SocialLinks()
            ->filter('LinkType', ['external', 'email'])
            ->filterByCallback('isLinkValid');
    }
}
```

### Pattern 4: Related Content Links

```php
class Article extends Page
{
    private static $many_many = [
        'RelatedArticles' => SuperLink::class
    ];

    public function getRelatedArticleLinks(): ManyManyList
    {
        return $this->RelatedArticles()
            ->filter('LinkType', 'sitetree')
            ->filterByCallback('isLinkValid');
    }
}
```

## Summary

SuperLinker provides a comprehensive, extensible link management system for SilverStripe. Key takeaways:

1. **Extensible Type System**: Add new link types via Extensions
2. **Configuration-Driven**: Control behavior via YAML
3. **Consistent UX**: Same interface for all link types
4. **Built-in Validation**: Health checks and orphan detection
5. **Template Flexibility**: Type-specific templates
6. **Optional Features**: Description, Icon, Image extensions
7. **Dependency Integration**: SystemLinks, GlobalAnchors, etc.

When working with SuperLinker:
- Always check `isLinkTypeMatch()` in extensions
- Use field prefixes for inline editing
- Apply display logic to type-specific fields
- Filter invalid links before rendering
- Eager load relations to avoid N+1 queries
- Cache complex operations when possible

For questions or issues, refer to the main README.md or GitHub repository.
