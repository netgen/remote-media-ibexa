# Netgen Remote Media & Ibexa CMS integration upgrade instructions

## Upgrade from netgen/remote-media-bundle 2.x

Version 1.0 of `netgen/remote-media-ibexa` is the successor of the eZ Platform 2.x integration that was a part of `netgen/remote-media-bundle` 2.x. It targets Ibexa CMS 4.4+ and Netgen Remote Media 3.x. See [CHANGELOG](CHANGELOG.md) for the full list of changes.

The core bundle changed too, see the `netgen/remote-media-bundle` [CHANGELOG](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/CHANGELOG.md) (3.0.0 section) and [UPGRADE](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0.1/docs/UPGRADE.md) ("Upgrade from 2.0 to 3.0").

### Upgrade `composer.json`

* Upgrade the project to Ibexa CMS 4.4+ (PHP 8.1+) first, the package requires `ibexa/core` and `ibexa/admin-ui` `^4.4`.
* Require both packages and run `composer update`:

    ```
    composer require netgen/remote-media-ibexa:^1.0 netgen/remote-media-bundle:^3.0
    ```

* The legacy bridge extension from `bundle/ezpublish_legacy/ngremotemedia` is not shipped anymore, remove it from your legacy setup.

### Bundles

* Activate the bundles in `config/bundles.php`, the Ibexa bundle has to be activated after the Netgen Remote Media bundle:

    ```php
    Netgen\Bundle\RemoteMediaBundle\NetgenRemoteMediaBundle::class => ['all' => true],
    Netgen\Bundle\RemoteMediaIbexaBundle\NetgenRemoteMediaIbexaBundle::class => ['all' => true],
    ```


### Routing and configuration

* Remove the eZ admin routes (`netgen_remote_media_ezadmin_*`) and import the Netgen Remote Media routing file instead:

    ```yaml
    netgen_remote_media:
        resource: "@NetgenRemoteMediaBundle/Resources/config/routing.yml"
    ```

* Move the `ezpublish` configuration to `ibexa`. The Ibexa bundle prepends the field templates (`system.default.field_templates`, content edit `form_templates` for `admin_group`), the `ibexa_admin` image variations and the Doctrine mapping, so remove the 2.x entries for `@NetgenRemoteMedia/ezadminui/...` templates, the `ezrichtext` custom tag and the `alloy_editor` plugin.
* Run `php bin/console assets:install` again and rebuild the Ibexa admin UI assets, the bundle registers its styles through `bundle/Resources/encore/ibexa.config.manager.js`.
* Assign the new policies `ngrm / read_protected` and `ngrm / upload` to the roles which should see protected resources and upload files. Without them users cannot upload and see protected resources only as a pixelated preview.

### Database

* The field data is not stored in the `ngremotemedia_field_link` table anymore. The new table `ngrm_remote_media_content_link` references the `ngrm_remote_resource_location` table of Netgen Remote Media.
* The tables are created by the Doctrine migration shipped with the bundle (own configuration `migrations/doctrine.yaml`, version table `ngrm_ibexa_migration_versions`).
* Run the migrations of `netgen/remote-media-bundle` first (see its UPGRADE), then the migrations of this bundle:

    ```
    php bin/console doctrine:migrations:migrate --configuration=vendor/netgen/remote-media-ibexa/migrations/doctrine.yaml
    ```

* If the `ngrm_remote_media_content_link` table already exists (for example, created with `doctrine:schema:update` on an untagged version of this bundle), the migration fails. Create the version table, mark the migration as executed and run the migrations again:

    ```
    php bin/console doctrine:migrations:sync-metadata-storage --configuration=vendor/netgen/remote-media-ibexa/migrations/doctrine.yaml
    php bin/console doctrine:migrations:version 'Netgen\RemoteMediaIbexa\Migrations\Doctrine\Version010000' --add --configuration=vendor/netgen/remote-media-ibexa/migrations/doctrine.yaml
    php bin/console doctrine:migrations:migrate --configuration=vendor/netgen/remote-media-ibexa/migrations/doctrine.yaml
    ```
* Data migration from the v2 data (`ngremotemedia_field_link`) to the new tables is not part of 1.0.0, v2 data is not migrated automatically.

### Breaking changes

* `Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value` has been removed. Use `Netgen\RemoteMediaIbexa\FieldType\Value` (`remoteResourceLocation`).
* `Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Variation` has been removed. Use `Netgen\RemoteMedia\API\Values\RemoteResourceVariation`.
* `Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Type` has been moved. Use `Netgen\RemoteMediaIbexa\FieldType\Type`.
* `InputValue`, `AdminInputValue` and `UpdateFieldHelper` have been removed. Pass a `Netgen\RemoteMedia\API\Values\RemoteResourceLocation` (or a `Netgen\RemoteMediaIbexa\FieldType\Value`) when creating content.
* `RemoteMediaStorage` and `RemoteMediaStorage\Gateway\LegacyStorage` have been removed. Use `Netgen\RemoteMediaIbexa\FieldType\FieldStorage` and `FieldStorage\Gateway\DoctrineStorage`.
* `Core\Persistence\Legacy\Content\FieldValue\Converter\RemoteMediaConverter` has been removed. Use `Netgen\RemoteMediaIbexa\Persistence\Legacy\FieldValueConverter`.
* `Core\FieldType\RemoteMedia\SearchField` has been removed. Use `Netgen\RemoteMediaIbexa\FieldType\SearchFields`, which indexes `value` (the ID of the resource location) and other properties of the resource, update the search index.
* `RemoteMediaFieldType` and `Core\FieldType\RemoteMedia\FormMapper` form classes have been removed. Use `Netgen\Bundle\RemoteMediaIbexaBundle\Form\Field\FieldValueFormMapper` and `Form\FieldDefinition\FormMapper`.
* The `RemoteMediaFieldLink` entity and the `ngremotemedia_field_link` table have been removed. Use `Netgen\RemoteMediaIbexa\API\Values\NgRemoteMediaContentLink` and the `ngrm_remote_media_content_link` table.
* Services `ezpublish.fieldType.ngremotemedia*`, `ezpublish.ezadminui.form.ngremotemedia_field_type` and `netgen_remote_media.admin.field_update.helper` have been removed. The services are now registered by class name (for example `Netgen\RemoteMediaIbexa\FieldType\Type`).
* The legacy bridge extension, the `ezrichtext` custom tag `ngremotemedia`, the AlloyEditor plugin and the XmlText converter have been removed. The rich text integration is not available in 1.0.0.
* The eZ admin UI controllers have been removed.
* Commands `netgen:ngremotemedia:add:data`, `netgen:ngremotemedia:add:field`, `netgen:ngremotemedia:migrate:ezimage` and `netgen:ngremotemedia:refresh:ez_fields` have been removed. There are no replacements in 1.0.0.

### Templates and Twig

* The `ngremotemedia` field template is `@ibexadesign/ngremotemedia/field_view.html.twig` (registered through `field_templates`), replace the override of `NetgenRemoteMediaBundle::ngremotemedia_content_field.html.twig`.
* Parameters `format` (use `alias` or `variation`), `alt_text`, `title`, `dimensions`, `link_href`, `link_target`, `link_class` and `link_title` of the field template have been removed. The template now accepts `alias` (or `variation`), `raw_variation`, `html_attributes`, `force_video` and `use_thumbnail`.
* `field.value.resourceId`, `field.value.variations` and other properties of the 2.x value are not available. Use `field.value.remoteResourceLocation` and `field.value.remoteResourceLocation.remoteResource`.
* `ngrm_ibexa_admin_preview_html_tag` is a new Twig function for the admin UI preview.

### Deprecations

There are no deprecations in Netgen Remote Media & Ibexa CMS integration 1.0.
