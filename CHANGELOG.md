# Netgen Remote Media & Ibexa CMS integration changelog

## 1.0.0

First release. The package contains the Ibexa integration which lived inside `netgen/remote-media-bundle` 2.x (eZ Platform 2.x), rebuilt on top of [Netgen Remote Media](https://github.com/netgen/NetgenRemoteMediaBundle/tree/3.0) 3.x for Ibexa CMS 4.4+. See [UPGRADE](UPGRADE.md) for the upgrade instructions.

The core bundle changed too, see the `netgen/remote-media-bundle` [CHANGELOG](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/CHANGELOG.md) (3.0.0 section) and [UPGRADE](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/UPGRADE.md) ("Upgrade from 2.0 to 3.0").

### BC breaks

* requires PHP 8.1+, `ibexa/core` and `ibexa/admin-ui` 4.4+ and `netgen/remote-media-bundle` 3.0+, eZ Platform / `ezsystems/ezpublish-kernel` 7.x is no longer supported
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
* Doctrine migration `Version010000` which creates the `ngrm_remote_media_content_link` table, with its own configuration `migrations/doctrine.yaml` and version table `ngrm_ibexa_migration_versions`
* `doctrine/doctrine-migrations-bundle` is required
* Croatian translations

### Changed

* standard field template takes the parameters `alias` (or `variation`), `raw_variation`, `html_attributes`, `force_video` and `use_thumbnail`
* field type name is the resource name (falls back to the remote ID), it was the resource ID
