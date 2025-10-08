# SilverStripe SuperLinker

A powerful, extensible link management system for SilverStripe that provides a unified interface for creating and managing all types of links in your CMS.

## Table of Contents

- [Overview](#overview)
- [Why SuperLinker?](#why-superlinker)
- [Requirements](#requirements)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Quick Start](#quick-start)
- [Link Types](#link-types)
- [Configuration](#configuration)
- [Usage](#usage)
- [Optional Features](#optional-features)
- [Dependencies](#dependencies)
- [Implementation Modules](#implementation-modules)
- [API Reference](#api-reference)
- [Real-World Examples](#real-world-examples)
- [Migration](#migration)
- [License](#license)

## Overview

SuperLinker is a comprehensive link management system that replaces fragmented link handling with a single, extensible DataObject. It provides:

- **8 built-in link types**: SiteTree, External, Email, Phone, File, System, GlobalAnchor, Null
- **Extensible architecture**: Add custom link types via extensions
- **Optional features**: Description, Icon, Image support
- **Consistent UX**: Same interface for all link types
- **Type-specific settings**: Configure behavior per link type
- **Automatic validation**: Link health checks and orphan detection
- **Template helpers**: isCurrent(), isSection(), LinkingMode()
- **Flexible rendering**: Type-specific templates

## Why SuperLinker?

**Before SuperLinker**, managing links in SilverStripe meant:
- Multiple incompatible link modules (Linkable, etc.)
- Inconsistent UX across different link types
- Difficult to add new link types
- No unified validation or health checks
- Fragmented template rendering

**With SuperLinker**, you get:
- Single DataObject for all link types
- Consistent CMS interface
- Easy extension with new types
- Built-in validation and health checks
- Unified template rendering
- Type-specific configuration

## Requirements

- SilverStripe CMS ^6.0
- PHP 8.1+

### Core Dependencies
- fromholdio/silverstripe-dbhtmlanchors ^2.1.0
- fromholdio/silverstripe-dependentgroupeddropdownfield ^4.0.0
- fromholdio/silverstripe-externalurlfield ^2.0.0
- fromholdio/silverstripe-globalanchors ^2.1.0
- fromholdio/silverstripe-minigridfield ^2.0.0
- fromholdio/silverstripe-systemlinks ^2.0.0
- innoweb/silverstripe-international-phone-number-field ^6.0.0
- unclecheese/display-logic ^4.0.0

## Installation

```bash
composer require fromholdio/silverstripe-superlinker
```

Run dev/build:
```bash
vendor/bin/sake dev/build flush=1
```

## Core Concepts

### Link Types

A **link type** defines a category of link (e.g., 'sitetree', 'external', 'email'). Each type:
- Has a unique key (e.g., 'sitetree')
- Provides its own fields and validation
- Can have type-specific settings
- Is implemented via Extension

### Type Extensions

Link types are added via **SuperLinkTypeExtension** subclasses that:
- Define the type key and label
- Add database fields for the type
- Provide CMS fields
- Implement URL generation logic
- Handle validation

### Settings

**Settings** control optional features:
- `link_text` - Custom link text field
- `open_in_new` - Open in new tab option
- `no_follow` - SEO nofollow option
- `link_description` - Description field (optional extension)
- `link_image` - Image upload (optional extension)
- `icon` - Icon upload (optional extension)

Settings can be enabled globally or per-type.

### Link Health

SuperLinker tracks link health:
- **Valid**: Has type, type is available, not orphaned, not empty
- **Orphaned**: Container object doesn't exist (future feature)
- **Empty**: No URL generated

### Template Rendering

Links render via templates with fallback chain:
```
ClassName_TypeKey.ss
ClassName.ss
SuperLink_TypeKey.ss
SuperLink.ss
```

## Quick Start

### 1. Basic has_one Relation

```php
use Fromholdio\SuperLinker\Model\SuperLink;

class Page extends SiteTree
{
    private static $has_one = [
        'CTALink' => SuperLink::class
    ];
}
```

### 2. Add CMS Fields

```php
use Fromholdio\HasOneEdit\HasOneMiniGridField;

public function getCMSFields(): FieldList
{
    $fields = parent::getCMSFields();

    $fields->addFieldToTab('Root.Main',
        HasOneMiniGridField::create('CTALink', 'Call to Action', $this)
    );

    return $fields;
}
```

### 3. Use in Templates

```html
<% if $CTALink %>
    $CTALink
<% end_if %>
```

Or with custom markup:

```html
<% if $CTALink %>
    <a href="$CTALink.URL"
       <% if $CTALink.isOpenInNew %>target="_blank"<% end_if %>
       <% if $CTALink.RelValue %>rel="$CTALink.RelValue"<% end_if %>>
        $CTALink.Title
    </a>
<% end_if %>
```

### 4. has_many Relations

```php
class Page extends SiteTree
{
    private static $has_many = [
        'Links' => SuperLink::class
    ];
}
```

```php
use Fromholdio\MiniGridField\MiniGridField;

$fields->addFieldToTab('Root.Main',
    MiniGridField::create('Links', 'Links', $this)
        ->setLimit(10)
);
```

## Link Types

### SiteTree Link

Links to pages within your SilverStripe site.

**Features**:
- TreeDropdownField for page selection
- Optional anchor selection (page content + global anchors)
- isCurrent() and isSection() support
- Configurable tree root

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    sitetree:
      allow_anchor: true
```

**Usage**:
```php
$link->getLinkedSiteTree(); // Returns SiteTree object
$link->getLinkedSiteTreeAnchor(); // Returns anchor string
```

### External Link

Links to external URLs.

**Features**:
- ExternalURLField with validation
- Protocol/scheme validation
- Query string and fragment support
- Internal URL blocking (optional)

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    external:
      is_internal_url_allowed: false
      allowed_schemes:
        https: true
        http: true
        ftp: false
```

### Email Link

Creates mailto: links with optional subject and body.

**Features**:
- Email validation
- Optional CC/BCC fields
- Subject and body fields
- Automatic mailto: URL generation

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    email:
      cc: false
      bcc: false
      subject: true
      body: true
```

### Phone Link

Creates tel: links for phone numbers.

**Features**:
- International phone number field
- Automatic formatting
- tel: URL generation

**Usage**:
```php
$link->getURL(); // Returns tel:+61412345678
```

### File Link

Links to files in the assets folder.

**Features**:
- TreeDropdownField or UploadField
- Force download option
- Configurable starting folder
- Upload enable/disable

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    file:
      use_upload_field: false
      allow_uploads: false
      starting_folder_path: 'downloads'
      allow_force_download: true
```

**Usage**:
```php
$link->getLinkedFile(); // Returns File object
$link->isDownloadForced(); // Returns boolean
```

### System Link

Links to system routes (login, logout, etc.).

**Features**:
- Dropdown of configured system links
- Dynamic URL generation
- Special URL processing ($Login, $Logout, etc.)

**Configuration**:
```yaml
Fromholdio\SystemLinks\SystemLinks:
  links:
    login:
      url: /Security/login
      title: Login
    logout:
      url: /Security/logout
      title: Logout
```

**Usage**:
```php
$link->getLinkedSystemLink(); // Returns ArrayData
```

### Global Anchor Link

Links to globally-defined page anchors.

**Features**:
- Dropdown of configured anchors
- Relative or absolute URL generation
- Current page context awareness

**Configuration**:
```yaml
Fromholdio\GlobalAnchors\GlobalAnchors:
  anchors:
    nav: 'Main Navigation'
    content: 'Page Content'
    footer: 'Footer'
```

**Usage**:
```php
$link->getLinkedGlobalAnchor(); // Returns anchor key
```

### Null Link

Text-only, no actual link.

**Features**:
- Displays text without href
- Useful for disabled menu items
- No URL generated

**Usage**: Automatically renders as text without `<a>` tag.

## Configuration

### Enabling/Disabling Link Types

```yaml
# Allow only specific types
Fromholdio\SuperLinker\Model\SuperLink:
  allowed_types:
    - sitetree
    - external
    - email

# Or disallow specific types
Fromholdio\SuperLinker\Model\SuperLink:
  disallowed_types:
    - phone
    - file
```

### Type-Specific Settings

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    external:
      settings:
        open_in_new: true  # Enable by default
        no_follow: true
    email:
      settings:
        open_in_new: false  # Disable for this type
        no_follow: false
```

### Global Settings

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  settings:
    link_text: true
    open_in_new: true
    no_follow: true
```

### Custom Type Configuration

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  types:
    mytype:
      label: 'My Custom Type'
      sort: 100
      settings:
        link_text: true
        open_in_new: false
```

## Usage

### In PHP

#### Getting Link Data

```php
// Get URL
$url = $link->getURL();
$absoluteURL = $link->getAbsoluteURL();

// Get title
$title = $link->getTitle(); // Custom or default
$defaultTitle = $link->getDefaultTitle(); // Always default

// Get link type
$type = $link->getType(); // 'sitetree', 'external', etc.
$typeLabel = $link->getTypeLabel(); // 'Page on this website', etc.

// Check link health
$isValid = $link->isLinkValid();
$isEmpty = $link->isLinkEmpty();
$isOrphaned = $link->isLinkOrphaned();

// Get HTML attributes
$attrs = $link->getDefaultAttributes(); // ['href' => '...', 'target' => '...', etc.]
$href = $link->getHrefValue();
$target = $link->getTargetValue(); // '_blank' or null
$rel = $link->getRelValue(); // 'nofollow noopener' or null
$class = $link->getClassValue();

// Check settings
$isOpenInNew = $link->isOpenInNew();
$isNoFollow = $link->isNoFollow();
$isNoOpener = $link->isNoOpener();

// Template helpers
$isCurrent = $link->isCurrent(); // For SiteTree links
$isSection = $link->isSection(); // For SiteTree links
$linkingMode = $link->LinkingMode(); // 'link', 'current', or 'section'
```

#### Adding CSS Classes

```php
$link->addExtraCSSClass('btn btn-primary');
$link->removeExtraCSSClass('btn-primary');
```

#### Filtering Valid Links

```php
use Fromholdio\SuperLinker\Model\SuperLink;

$links = $page->Links();
$validLinks = SuperLink::excludeInvalidLinks($links);
```

### In Templates

#### Basic Usage

```html
<!-- Automatic rendering -->
<% if $CTALink %>
    $CTALink
<% end_if %>

<!-- Manual rendering -->
<% if $CTALink %>
    <a href="$CTALink.URL" $CTALink.AttributesHTML>
        $CTALink.Title
    </a>
<% end_if %>
```

#### With Attributes

```html
<% if $CTALink %>
    <a href="$CTALink.URL"
       <% if $CTALink.TargetValue %>target="$CTALink.TargetValue"<% end_if %>
       <% if $CTALink.RelValue %>rel="$CTALink.RelValue"<% end_if %>
       <% if $CTALink.ClassValue %>class="$CTALink.ClassValue"<% end_if %>>
        $CTALink.Title
    </a>
<% end_if %>
```

#### Looping Links

```html
<% if $Links %>
    <ul>
        <% loop $Links %>
            <li>
                <a href="$URL" $AttributesHTML>$Title</a>
            </li>
        <% end_loop %>
    </ul>
<% end_if %>
```

#### With Linking Mode

```html
<% loop $Menu(1) %>
    <% if $MenuLink %>
        <li class="$MenuLink.LinkingMode">
            <a href="$MenuLink.URL">$MenuLink.Title</a>
        </li>
    <% end_if %>
<% end_loop %>
```

#### Type-Specific Rendering

```html
<% if $CTALink %>
    <% if $CTALink.Type == 'external' %>
        <a href="$CTALink.URL" target="_blank" rel="noopener">
            $CTALink.Title <span class="external-icon">↗</span>
        </a>
    <% else_if $CTALink.Type == 'file' %>
        <a href="$CTALink.URL" download>
            $CTALink.Title <span class="download-icon">⬇</span>
        </a>
    <% else %>
        $CTALink
    <% end_if %>
<% end_if %>
```

### Custom Templates

Create type-specific templates:

**templates/Fromholdio/SuperLinker/Model/SuperLink_external.ss**:
```html
<a href="$URL" target="_blank" rel="noopener" class="external-link">
    $Title
    <span class="icon-external"></span>
</a>
```

**templates/Fromholdio/SuperLinker/Model/SuperLink_file.ss**:
```html
<a href="$URL" <% if $isDownloadForced %>download<% end_if %> class="file-link">
    <span class="icon-download"></span>
    $Title
</a>
```

**templates/Fromholdio/SuperLinker/Model/SuperLink.ss** (fallback):
```html
<a href="$URL" $AttributesHTML>$Title</a>
```

## Optional Features

### Description Extension

Add description field to links.

**Installation**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkDescriptionExtension
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  link_description_rows: 3  # TextField if 1, TextareaField if > 1
  settings:
    link_description: true
```

**Usage**:
```php
$description = $link->getDescription();
$defaultDescription = $link->getDefaultDescription();
```

**Templates**:
```html
<% if $CTALink %>
    <a href="$CTALink.URL">
        <strong>$CTALink.Title</strong>
        <% if $CTALink.Description %>
            <span class="description">$CTALink.Description</span>
        <% end_if %>
    </a>
<% end_if %>
```

**Auto-Generated Descriptions**:

For SiteTree and File links, implement on target:
```php
class Page extends SiteTree
{
    public function getSuperLinkDefaultDescription(): ?string
    {
        return $this->MetaDescription ?: $this->Summary;
    }
}
```

### Icon Extension

Add icon upload to links.

**Installation**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkIconExtension
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  icon_folder_path: 'link-icons'
  icon_allowed_extensions:
    - svg
    - png
  settings:
    icon: true
```

**Usage**:
```php
$icon = $link->getIcon(); // Returns Image object
```

**Templates**:
```html
<% if $CTALink %>
    <a href="$CTALink.URL">
        <% if $CTALink.Icon %>
            <img src="$CTALink.Icon.URL" alt="" class="link-icon">
        <% end_if %>
        $CTALink.Title
    </a>
<% end_if %>
```

### Image Extension

Add image upload to links.

**Installation**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkImageExtension
```

**Configuration**:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  link_image_upload_path: 'link-images'
  settings:
    link_image: true
```

**Usage**:
```php
$image = $link->getImage(); // Returns Image object
$defaultImage = $link->getDefaultImage();
```

**Templates**:
```html
<% if $CTALink %>
    <a href="$CTALink.URL" class="card-link">
        <% if $CTALink.Image %>
            <img src="$CTALink.Image.FocusFill(400,300).URL" alt="$CTALink.Title">
        <% end_if %>
        <h3>$CTALink.Title</h3>
    </a>
<% end_if %>
```

**Auto-Generated Images**:

For SiteTree links:
```php
class Page extends SiteTree
{
    private static $has_one = [
        'FeaturedImage' => Image::class
    ];

    public function getSuperLinkDefaultImage(): ?Image
    {
        return $this->FeaturedImage()->exists()
            ? $this->FeaturedImage()
            : null;
    }
}
```

Apply helper extensions:
```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkImagePageExtension

SilverStripe\Assets\File:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkImageFileExtension
```

## Dependencies

SuperLinker relies on several companion modules that provide specific functionality.

### SystemLinks

**Purpose**: Define static system links (login, logout, etc.) for use in templates and CMS.

**Installation**: Included with SuperLinker

**Configuration**:
```yaml
Fromholdio\SystemLinks\SystemLinks:
  links:
    login:
      url: /Security/login
      title: Login
    logout:
      url: /Security/logout
      title: Logout
    lostpassword:
      url: /Security/lostpassword
      title: Lost Password
    admin:
      url: /admin
      title: CMS Admin
```

**Special URLs**:
- `$Login` - Automatically resolves to Security::login_url()
- `$Logout` - Automatically resolves to Security::logout_url() with token
- `$LostPassword` - Automatically resolves to Security::lost_password_url()

**Usage in Templates**:
```html
<a href="$SystemLink('login').URL">$SystemLink('login').Title</a>
```

**Usage in PHP**:
```php
use Fromholdio\SystemLinks\SystemLinks;

$link = SystemLinks::get_link('login'); // Returns ArrayData
$url = $link->URL;
$title = $link->Title;

// Get as map for dropdown
$map = SystemLinks::get_map(); // ['login' => 'Login', ...]
```

**Documentation**: See `vendor/fromholdio/silverstripe-systemlinks/README.md`

### GlobalAnchors

**Purpose**: Define static global HTML anchors for use across the site.

**Installation**: Included with SuperLinker

**Configuration**:
```yaml
Fromholdio\GlobalAnchors\GlobalAnchors:
  anchors:
    nav: 'Main Navigation'
    content: 'Page Content'
    footer: 'Footer'
    sidebar: 'Sidebar'
```

**Usage in Templates**:
```html
<nav id="nav">...</nav>
<main id="content">...</main>
<aside id="sidebar">...</aside>
<footer id="footer">...</footer>
```

**Usage in PHP**:
```php
use Fromholdio\GlobalAnchors\GlobalAnchors;

$anchors = GlobalAnchors::get_anchors(); // ['nav' => 'Main Navigation', ...]
$title = GlobalAnchors::get_anchor_title('nav'); // 'Main Navigation'
```

**Documentation**: See `vendor/fromholdio/silverstripe-globalanchors/README.md`

### RelativeURLField

**Purpose**: Form field for entering URL slugs with collision detection.

**Installation**: Included with SuperLinker

**Features**:
- Base URL display
- Collision checking against SiteTree
- Query string support (optional)
- Full path support (optional)
- Auto-incrementing on collision

**Usage**:
```php
use Fromholdio\RelativeURLField\Forms\RelativeURLField;

$field = RelativeURLField::create('URLSegment', 'URL');
$field->setBaseURL('https://example.com/redirects/');
$field->setIsQueryStringAllowed(true);
$field->setIsFullPathAllowed(false);
```

**Documentation**: See `vendor/fromholdio/silverstripe-relativeurlfield/README.md`

### HasOneEdit

**Purpose**: Inline editing of has_one relations.

**Installation**: Included with SuperLinker

**Usage**:
```php
use Fromholdio\HasOneEdit\HasOneMiniGridField;

$fields->addFieldToTab('Root.Main',
    HasOneMiniGridField::create('SuperLink', 'Link', $this)
);
```

**Note**: This module is aging and may be replaced in future versions.

**Documentation**: See `vendor/fromholdio/silverstripe-hasoneedit/README.md`

### Other Dependencies

- **DBHTMLAnchors**: Extracts anchors from HTML content
- **DependentGroupedDropdownField**: Dropdown that depends on another field
- **ExternalURLField**: URL field with validation
- **MiniGridField**: Compact GridField for has_many relations
- **InternationalPhoneNumberField**: Phone number field with formatting

## Implementation Modules

SuperLinker provides base functionality. These modules extend it for specific use cases.

### superlinker-ctas

**Purpose**: Call-to-Action / Button DataObjects

**Status**: Superseded by SuperLinker v3+. For new projects, create custom subclasses as needed.

**Installation**:
```bash
composer require fromholdio/silverstripe-superlinker-ctas
```

**What it provides**:
- `CTA` class extending `SuperLink`
- Pre-configured for button/CTA use cases

**Usage**:
```php
use Fromholdio\SuperLinkerCTAs\Model\CTA;

class Page extends SiteTree
{
    private static $has_one = [
        'PrimaryCTA' => CTA::class
    ];
}
```

**Documentation**: See `vendor/fromholdio/silverstripe-superlinker-ctas/README.md`

### superlinker-redirection

**Purpose**: Redirector pages using SuperLink targets

**Installation**:
```bash
composer require fromholdio/silverstripe-superlinker-redirection
```

**What it provides**:
- `RedirectionPage` - Replacement for core RedirectorPage
- `Redirection` DataObject - Standalone redirects
- Admin interface for managing redirects
- Support for all SuperLink types as targets

**Features**:
- Redirect to any link type (not just internal/external)
- HTTP status code selection (301, 302, 303, 307, 308)
- Redirect from custom URL paths
- Collision detection
- Hidden from site tree

**Usage**:
```php
// Create redirector page
$page = RedirectionPage::create();
$page->Title = 'Old Page';
$page->RedirectLink()->LinkType = 'sitetree';
$page->RedirectLink()->SiteTreeID = $newPage->ID;
$page->write();
```

**Documentation**: See `vendor/fromholdio/silverstripe-superlinker-redirection/README.md`

### superlinker-targets

**Purpose**: Basic link/target DataObjects

**Installation**:
```bash
composer require fromholdio/silverstripe-superlinker-targets
```

**What it provides**:
- `Target` class extending `SuperLink`
- Generic link objects for various uses

**Usage**:
```php
use Fromholdio\SuperLinkerTargets\Model\Target;

class Page extends SiteTree
{
    private static $has_many = [
        'RelatedLinks' => Target::class
    ];
}
```

**Documentation**: See `vendor/fromholdio/silverstripe-superlinker-targets/README.md`

## API Reference

### SuperLink Core Methods

#### Link Information
```php
public function getType(): ?string
public function getTypeLabel(?string $type = null): ?string
public function getTitle(): string
public function getDefaultTitle(): string
```

#### URL Methods
```php
public function getURL(): ?string
public function getAbsoluteURL(): ?string
public function getHrefValue(): ?string
```

#### Link Health
```php
public function isLinkValid(): bool
public function isLinkOrphaned(): bool
public function isLinkEmpty(): bool
public static function excludeInvalidLinks(SS_List $links): ArrayList
```

#### HTML Attributes
```php
public function getDefaultAttributes(): array
public function getTargetValue(): ?string
public function getRelValue(): ?string
public function getClassValue(): ?string
public function addExtraCSSClass(string $class): self
public function removeExtraCSSClass(string $class): self
```

#### Settings
```php
public function isLinkTextEnabled(?string $type = null): bool
public function isOpenInNewEnabled(?string $type = null): bool
public function isOpenInNew(): bool
public function isNoFollowEnabled(?string $type = null): bool
public function isNoFollow(): bool
public function isNoOpener(): bool
```

#### Template Helpers
```php
public function isCurrent(): bool
public function isSection(): bool
public function LinkOrCurrent(): string
public function LinkOrSection(): string
public function LinkingMode(): string
public function forTemplate(): string
```

#### CMS Fields
```php
public function getCMSFields(): FieldList
public function getCMSLinkFields(string $fieldPrefix = ''): FieldList
```

### Type Extension Methods

#### SiteTreeLink
```php
public function getLinkedSiteTree(): ?SiteTree
public function getLinkedSiteTreeAnchor(): ?string
public function getAvailableSiteTreeAnchors(int|string|null $siteTreeID): array
public function getAllowedLinkedSiteTreeRoot(): ?SiteTree
```

#### ExternalLink
```php
// Uses ExternalURL field - no additional methods
```

#### EmailLink
```php
// URL generated from Email, EmailCC, EmailBCC, EmailSubject, EmailBody fields
```

#### PhoneLink
```php
// Uses PhoneNumber field (DBPhone) - no additional methods
```

#### FileLink
```php
public function getLinkedFile(): ?File
public function isDownloadForced(): bool
```

#### SystemLink
```php
public function getLinkedSystemLink(): ?ArrayData
```

#### GlobalAnchorLink
```php
public function getLinkedGlobalAnchor(): ?string
```

### Optional Extension Methods

#### SuperLinkDescriptionExtension
```php
public function getDescription(): ?string
public function getDefaultDescription(): ?string
public function isLinkDescriptionEnabled(?string $type = null): bool
```

#### SuperLinkIconExtension
```php
public function getIcon(): ?Image
```

#### SuperLinkImageExtension
```php
public function getImage(): ?Image
public function getDefaultImage(): ?Image
public function isLinkImageEnabled(?string $type = null): bool
```

## Real-World Examples

### Example 1: Navigation Menu with SuperLinks

```php
class Page extends SiteTree
{
    private static $has_many = [
        'MenuLinks' => SuperLink::class
    ];
}
```

```php
public function getCMSFields(): FieldList
{
    $fields = parent::getCMSFields();

    $fields->addFieldToTab('Root.Menu',
        MiniGridField::create('MenuLinks', 'Menu Links', $this)
            ->setLimit(10)
    );

    return $fields;
}
```

**Template**:
```html
<nav>
    <ul>
        <% loop $MenuLinks %>
            <li class="$LinkingMode">
                <a href="$URL" $AttributesHTML>$Title</a>
            </li>
        <% end_loop %>
    </ul>
</nav>
```

### Example 2: CTA Buttons with Images

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkImageExtension
    - Fromholdio\SuperLinker\Extensions\SuperLinkDescriptionExtension
```

```php
class HomePage extends Page
{
    private static $has_many = [
        'FeatureCards' => SuperLink::class
    ];
}
```

**Template**:
```html
<div class="feature-cards">
    <% loop $FeatureCards %>
        <a href="$URL" class="card">
            <% if $Image %>
                <img src="$Image.FocusFill(400,300).URL" alt="$Title">
            <% end_if %>
            <h3>$Title</h3>
            <% if $Description %>
                <p>$Description</p>
            <% end_if %>
        </a>
    <% end_loop %>
</div>
```

### Example 3: Footer Links with Icons

```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - Fromholdio\SuperLinker\Extensions\SuperLinkIconExtension
```

```php
class SiteConfig extends DataExtension
{
    private static $has_many = [
        'SocialLinks' => SuperLink::class
    ];
}
```

**Template**:
```html
<footer>
    <div class="social-links">
        <% loop $SiteConfig.SocialLinks %>
            <a href="$URL" target="_blank" rel="noopener" class="social-link">
                <% if $Icon %>
                    <img src="$Icon.URL" alt="$Title">
                <% else %>
                    $Title
                <% end_if %>
            </a>
        <% end_loop %>
    </div>
</footer>
```

### Example 4: Custom Link Type

Create a custom link type for YouTube videos:

```php
namespace App\Extensions;

use Fromholdio\SuperLinker\Extensions\SuperLinkTypeExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

class YouTubeLink extends SuperLinkTypeExtension
{
    private static $extension_link_type = 'youtube';

    private static $types = [
        'youtube' => [
            'label' => 'YouTube Video',
            'settings' => [
                'open_in_new' => true
            ]
        ]
    ];

    private static $db = [
        'YouTubeID' => 'Varchar(20)'
    ];

    public function updateDefaultTitle(?string &$title): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $title = 'Watch on YouTube';
    }

    public function updateURL(?string &$url): void
    {
        if (!$this->isLinkTypeMatch()) return;
        $id = $this->getOwner()->getField('YouTubeID');
        $url = empty($id) ? null : 'https://www.youtube.com/watch?v=' . $id;
    }

    public function updateCMSLinkTypeFields(FieldList $fields, string $type, string $fieldPrefix): void
    {
        if (!$this->isLinkTypeMatch($type)) return;
        $fields->push(
            TextField::create($fieldPrefix . 'YouTubeID', 'YouTube Video ID')
                ->setDescription('e.g., dQw4w9WgXcQ')
        );
    }
}
```

Apply extension:
```yaml
Fromholdio\SuperLinker\Model\SuperLink:
  extensions:
    - App\Extensions\YouTubeLink
```

### Example 5: Versioned SuperLinks

For versioned content:

```php
use Fromholdio\SuperLinker\Model\VersionedSuperLink;

class Article extends Page
{
    private static $has_one = [
        'RelatedArticleLink' => VersionedSuperLink::class
    ];
}
```

## Migration

### From Linkable or Similar Modules

1. **Install SuperLinker**:
```bash
composer require fromholdio/silverstripe-superlinker
```

2. **Update DataObject Relations**:
```php
// Before
private static $has_one = [
    'ExternalLink' => Link::class
];

// After
private static $has_one = [
    'ExternalLink' => SuperLink::class
];
```

3. **Update CMS Fields**:
```php
// Before
$fields->addFieldToTab('Root.Main',
    LinkField::create('ExternalLinkID', 'Link')
);

// After
$fields->addFieldToTab('Root.Main',
    HasOneMiniGridField::create('ExternalLink', 'Link', $this)
);
```

4. **Update Templates**:
```html
<!-- Before -->
<% if $ExternalLink %>
    <a href="$ExternalLink.URL">$ExternalLink.Title</a>
<% end_if %>

<!-- After (same!) -->
<% if $ExternalLink %>
    <a href="$ExternalLink.URL">$ExternalLink.Title</a>
<% end_if %>
```

5. **Run dev/build**:
```bash
vendor/bin/sake dev/build flush=1
```

6. **Migrate Data**: Write a migration task to convert existing link data to SuperLink format.

## License

BSD-3-Clause

## Support

- **GitHub**: https://github.com/fromholdio/silverstripe-superlinker
- **Issues**: https://github.com/fromholdio/silverstripe-superlinker/issues

## Contributing

Contributions are welcome! Please open an issue or pull request on GitHub.

## Credits

Developed by [Luke Fromhold](https://fromhold.io)
