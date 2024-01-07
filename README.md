# silverstripe-superlinker

Requires Silverstripe 5+

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
- Modal for adding rather than `HasOneMiniGridField`
- Resolve indecision around handling, naming and accessors for Title vs LinkText
- Broken or empty link reporting
- Permissions
- Add awareness of link container objects for orphan reporting/pruning (& potentially expanding config to container/relation)
- Documentation/readme
- Formats/themes/styles as optional extensions
- Cleverer handling of settings/options
- Apply display logic (and perhaps field sort) via yml config using linktypes x fieldnames (allowing link types to share fields rather than requiring each class to utilise its own fields)
