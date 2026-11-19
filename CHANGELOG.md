# Netgen Remote Media & Ibexa CMS integration changelog

## 1.1.0

Remote media in the RichText field, see [RICHTEXT.md](RICHTEXT.md) and [UPGRADE](UPGRADE.md#upgrade-from-10-to-11), and the migration of Netgen Remote Media 2 data, see [MIGRATION.md](MIGRATION.md).

### BC breaks

* requires `netgen/remote-media-bundle` 3.2.0+ (CKEditor 5 plugin), `ibexa/fieldtype-richtext` 4.4+ and `symfony/polyfill-mbstring` 1.31+ (`mb_trim` on PHP < 8.4)

### Added

* Netgen Remote Media button in the RichText (CKEditor 5) toolbar of the admin UI, selected resources are stored in the RichText XML as the `ngremotemedia` custom tag (`locationId`, `variationName`, `cssClass` and `alignment`) and rendered server side with `@ibexadesign/ngremotemedia/richtext/custom_tag.html.twig`
* the editor works on copies of the stored remote resource locations, so changes made in a draft, translation or copy never change the locations of other versions or content
* remote resource locations of deleted RichText fields (discarded drafts, deleted versions, translations, content and field definitions) are deleted at the end of the request or console command, when no other RichText field of any version or content and no Netgen Remote Media field references them, by `Netgen\RemoteMediaIbexa\RichText\FieldStorage`, which decorates the RichText external storage
* the content edit form is autosaved again once pending location requests have settled, when the Ibexa autosave ran while a location was being created
* siteaccess aware `netgen_remote_media_ibexa.system.<scope>.richtext` semantic configuration (`variation_group`, `location_source`, `allowed_types`, `allowed_visibilities`, `allowed_tags`, `disable_upload`, `folder`, `parent_folder` and `upload_context`)
* `Resources/encore/ibexa.richtext.config.manager.js` which adds the plugin to the `richtext` Encore build
* the plugin configuration is exposed as `ibexa.richText.ngremotemedia` by an admin UI component (`script-head` group) rendering `@ibexadesign/ngremotemedia/richtext/editor_configuration.html.twig`
* Twig functions `ngrm_ibexa_can_read_protected`, `ngrm_ibexa_view_resource_template` and `ngrm_ibexa_richtext_editor_configuration`
* the RichText custom tag template renders the caption override of the location in a `<figure>` with a `<figcaption>`
* `netgen:remote_media:ibexa:migrate_v2` command which migrates Netgen Remote Media 2 field values, field definitions and RichText custom tags, with per-usage alt texts and captions as location overrides, see [MIGRATION.md](MIGRATION.md)
* alt text and caption override inputs in the admin field edit are styled like the other inputs

### Fixed

* the `ngrm / upload` and `ngrm / read_protected` policies are checked with Ibexa authorization attributes, so they work without a voter for `ibexa:module:function` strings (e.g. from `lolautruche/ez-core-extra-bundle`)
* alt text and caption overrides of a field's location are kept when the content is saved or copied to a new version (`Converter\Hash`)
* the standard field template renders a field without a variation with `ngrm_remote_resource_location_html_tag`, so the alt text and caption overrides are used
* search fields `alttext` and `caption` and the admin field view use the alt text and caption overrides of the location when they are set

## 1.0.0

First release. The package contains the Ibexa integration which lived inside `netgen/remote-media-bundle` 2.x (eZ Platform 2.x), rebuilt on top of [Netgen Remote Media](https://github.com/netgen/NetgenRemoteMediaBundle/tree/3.0) 3.x for Ibexa CMS 4.4+. See [UPGRADE](UPGRADE.md) for the upgrade instructions.

The core bundle changed too, see the `netgen/remote-media-bundle` [CHANGELOG](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/CHANGELOG.md) (3.0.0 section) and [UPGRADE](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/UPGRADE.md) ("Upgrade from 2.0 to 3.0").

### BC breaks

* requires PHP 8.1+, `ibexa/core` and `ibexa/admin-ui` 4.4+ and `netgen/remote-media-bundle` 3.0.2+, eZ Platform / `ezsystems/ezpublish-kernel` 7.x is no longer supported
* the integration is a separate package `netgen/remote-media-ibexa` (type `symfony-bundle`), activate `Netgen\Bundle\RemoteMediaIbexaBundle\NetgenRemoteMediaIbexaBundle` after the `NetgenRemoteMediaBundle`
* the field type identifier `ngremotemedia` is kept, but the field type classes now live in `Netgen\RemoteMediaIbexa\` (`lib/`) and the bundle classes in `Netgen\Bundle\RemoteMediaIbexaBundle\` (`bundle/`), `Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\*` no longer exists
* the field type value is now `Netgen\RemoteMediaIbexa\FieldType\Value` with a single `remoteResourceLocation` property (`Netgen\RemoteMedia\API\Values\RemoteResourceLocation`), the 2.x `Value`, `Variation`, `InputValue`, `AdminInputValue` and `UpdateFieldHelper` classes are removed
* field data is stored in the new `ngrm_remote_media_content_link` table (`field_id`, `version` and a foreign key `remote_resource_location_id` to the Netgen Remote Media `ngrm_remote_resource_location` table, unique on `field_id` + `version`) through the Doctrine ORM entity `NgRemoteMediaContentLink`, the `ngremotemedia_field_link` table and the `RemoteMediaStorage` / `LegacyStorage` classes are no longer used, v2 data is not migrated automatically
* the 2.x `Core\Persistence\Legacy\...\RemoteMediaConverter` and `SearchField` are replaced by `Persistence\Legacy\FieldValueConverter` and `FieldType\SearchFields`, the search index fields are different (see Added)
* the legacy bridge extension `ezpublish_legacy/ngremotemedia` (legacy datatype, templates, template operator and settings) is removed
* the rich text integration is removed, the `ngremotemedia` `ezrichtext` custom tag, the AlloyEditor plugin and the XmlText converter / XSL are not part of 1.0.0
* eZ admin UI controllers and the `netgen_remote_media_ezadmin_*` routes are removed, the edit form uses the `RemoteMediaType` Symfony form type from Netgen Remote Media 3.x
* the 2.x field template `NetgenRemoteMediaBundle::ngremotemedia_content_field.html.twig` is replaced by `@ibexadesign/ngremotemedia/field_view.html.twig`, and its parameters `format`, `alt_text`, `title`, `dimensions` and `link_*` are removed
* the `ezpublish.fieldType.ngremotemedia.*` service IDs are replaced by class name service IDs
* the 2.x console commands `netgen:ngremotemedia:add:data`, `add:field`, `migrate:ezimage` and `refresh:ez_fields` are removed, this package ships no console commands
* Norwegian translations are not carried over

### Added

* field type `ngremotemedia` for Ibexa 4.4+ (`Type`, `Value`, `FieldStorage`, `DoctrineStorage` gateway), `Converter\Hash` converts the value to and from the stored hash (`remote_id`, `source`, `crop_settings`, `watermark_text`)
* field definition settings `allowedTypes`, `allowedVisibilities`, `allowedTags`, `allowedVariations`, `locationSource`, `disableUpload`, `parentFolder` and `folder`, with validation of the settings and of the stored resource (type, visibility and tags) in the field type
* admin UI field definition form (`FormMapper`) and content edit form (`FieldValueFormMapper`) with the `RemoteMediaType` and `RemoteMediaFolderType` form types
* policies `ngrm / read_protected` and `ngrm / upload` (`RemoteMediaPolicyProvider`) which control access to protected resources and uploading
* support for protected and private resources in the templates (authenticated URLs, thumbnails and pixelated previews for users without `read_protected`)
* search fields `value`, `remoteresourceid`, `remoteid`, `type`, `watermarktext`, `name`, `folder`, `originalfilename`, `visibility`, `alttext`, `caption` and `tags`
* Ibexa admin templates for field edit and field view (with a toggle for resource properties), SCSS for both and an Encore config manager
* Twig function `ngrm_ibexa_admin_preview_html_tag` and the `ibexa_admin` image variation group (`preview`, `preview_image`, `preview_protected`, `preview_protected_image`)
* the bundle prepends the `ibexa` (field templates), `netgen_remote_media` (variation group) and `doctrine` (entity mapping) configuration
* Croatian translations
* the `ngrm_remote_media_content_link` table is created by the Doctrine migration `Version030002` of `netgen/remote-media-bundle` 3.0.2+, this package ships no migrations, see [installation instructions](INSTALL.md#database)

### Changed

* standard field template takes the parameters `alias` (or `variation`), `raw_variation`, `html_attributes`, `force_video` and `use_thumbnail`
* field type name is the resource name (falls back to the remote ID), it was the resource ID
