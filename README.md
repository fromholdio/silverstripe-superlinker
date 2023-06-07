# silverstripe-superlinker

Use master/v2.x (compatible with SS 4 & 5).

This branch is under active development. It **will** change and break.

## CMS fields testing snippets

```php
// for $has_one relation, testing inline fields
$linkFields = SuperLink::singleton()->getCMSLinkFields('SuperLink' . HasOneEdit::FIELD_SEPARATOR);
$fields->addFieldsToTab('Root.Main', $linkFields->toArray());

// for $has_one relation, testing with edit form
$fields->addFieldsToTab('Root.Main', [
    HasOneMiniGridField::create(
        'SuperLink',
        'SuperLink',
        $this
    )
]);

// for $has_many relation, testing with gridfield
$linksField = MiniGridField::create(
    'SuperLinks',
    'Links',
    $this
)->setLimit(7)->setShowLimitMessage(true);
$fields->addFieldToTab('Root.Main', $linksField);

// for the HasOne/MiniGridFields, currently adding these lines provides nicer UI
$config = $linksField->getGridConfig()?->addComponent(new GridField_ActionMenu());
$linksField->setGridConfig($config);
```

## v3 to-dos
- Validations for each link type
- Richer summary fields content
- Update MiniGridField to use GridField_ActionMenu
- Remove yml config currently in place for ease of development (convert to yml.example/readme or similar)
- `DependentDropdownField`/`DependentGroupedDropdownField` no longer detect changes from `TreeDropdownField`, so the Anchors dropdown for `SiteTreeLink` is no longer working.
- Modal for adding rather than `HasOneMiniGridField`
- Resolve indecision around handling, naming and accessors for Title vs LinkText
- Broken or empty link reporting
- Proper i18n/_t()/translations
- Permissions
- Add awareness of link container objects for orphan reporting/pruning (& potentially expanding config to container/relation)
- Migration script from v2 to v3
- Documentation/readme
- Formats/themes/styles as optional extensions
- Cleverer handling of settings/options
- Can we integrate this with a new TinyMCE plugin/button or existing ss_link?
- Apply display logic (and perhaps field sort) via yml config using linktypes x fieldnames (allowing link types to share fields rather than requiring each class to utilise its own fields)
