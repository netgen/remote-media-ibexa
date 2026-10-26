# Migration from Netgen Remote Media 2

Netgen Remote Media 2 (`netgen/remote-media-bundle` 2.x, eZ Platform) stored everything about a resource in the
content: the field value was the Cloudinary resource as JSON in `data_text`, and RichText referenced resources
by their Cloudinary ID. Netgen Remote Media 3 stores resources and their usages (locations) in its own tables, and
content only references a location.

The migration runs on the database of a project already upgraded from eZ Platform 2.5 to Ibexa 4, and converts:

* the `ngremotemedia` field values to remote resources, remote resource locations and content links
  (`ngrm_remote_resource`, `ngrm_remote_resource_location`, `ngrm_remote_media_content_link`)
* the `ngremotemedia` field definitions: Netgen Remote Media 2 had no field settings, the default ones are stored
* the `ngremotemedia` custom tags in RichText fields: a remote resource location is created for each tag, and the
  tag is converted to the format described in [RICHTEXT.md](RICHTEXT.md)

Archived versions are not migrated: their remote media fields are empty, and their RichText keeps the old tags,
which render as invalid data.

## Before the migration

* The core upgrade is done: XmlText is converted to RichText, and `ibexa:migrate:richtext-namespaces` has been run.
* Netgen Remote Media 3 is installed and configured, see [INSTALL.md](INSTALL.md). Cloudinary accounts created before
  June 2024 use the `fixed` folder mode, which must be configured (`netgen_remote_media.cloudinary.folder_mode`).
* The variations used by the content exist in the Netgen Remote Media 3 configuration. Variation names are kept:
  fields use the variation group named after the content type identifier, RichText uses the group configured in
  `netgen_remote_media_ibexa.system.<scope>.richtext.variation_group`.

## Steps

1. Back up the database.

2. Create the tables of Netgen Remote Media 3:

   ```
   mysql <database> < vendor/netgen/remote-media-ibexa/bundle/Resources/sql/upgrade/v2-to-v3/mysql.sql
   ```

   (`postgresql.sql` for PostgreSQL). Don't use `doctrine:schema:update`: the Ibexa tables aren't mapped
   with Doctrine ORM, so it would drop them.

3. Run the migration:

   ```
   php bin/console netgen:remote_media:ibexa:migrate_v2
   ```

   Fields and custom tags which are already migrated are skipped, so the command can run again, e.g. after an
   interruption. It clears the Ibexa cache pool at the end.

4. Reindex the content:

   ```
   php bin/console ibexa:reindex
   ```

5. Optionally, refresh the stored resources from Cloudinary (see below):

   ```
   php bin/console netgen:remote_media:refresh
   ```

   It replaces the alt text and caption of every resource with the ones on Cloudinary. Alt text and caption
   overrides of locations are not changed.

6. When everything is checked, drop the table of Netgen Remote Media 2:

   ```sql
   DROP TABLE ngremotemedia_field_link;
   ```

## Details

* Resources are created from the field values, without requests to Cloudinary. Resources used only in RichText
  are loaded from Cloudinary, in batches of 100.
* Netgen Remote Media 2 kept the alt text and caption in each field value (per version and translation) and the
  caption in each RichText tag. Netgen Remote Media 3 keeps them on the resource (synchronized with Cloudinary)
  and, per usage, as alt text and caption overrides of a location. A resource created from a field value gets the
  alt text and caption of that field value, which Netgen Remote Media 2 took from Cloudinary, a resource used only
  in RichText gets the ones loaded from Cloudinary. A field value or tag whose alt text or caption is not empty and
  differs from the one of the resource gets it as the override of its location, so nothing is lost and
  `netgen:remote_media:refresh` doesn't change it. Netgen Remote Media 2 never stored RichText captions on
  Cloudinary, so RichText captions mostly become overrides.
* The MD5 hash of the file is the Cloudinary etag where the field value has it, otherwise empty:
  `netgen:remote_media:refresh` sets it from Cloudinary.
* Each field version gets its own location, with the crops of the field value. The location source is
  `ibexa_content_<content type identifier>_<field identifier>`, as with fields edited in Netgen Remote Media 3.
* In RichText, all versions of a field share one location per resource and crop, with the source configured in
  `richtext.location_source`. An inline tag replaces its paragraph: the paragraph's content before and after it
  stays in a paragraph before and after the tag. `variation` becomes `variationName`, `cssclass` becomes
  `cssClass`, `align` becomes `alignment` (`middle` becomes `center`), and the caption becomes the caption
  override of the location (see above). Tags with the same resource and crops but different captions get
  different locations.
* A converted RichText field is stored only if it doesn't add RichText validation errors.
* Fields and tags which can't be migrated are listed in the output and left as they are, e.g. resources which
  no longer exist on Cloudinary, or whose URL, name or filename is longer than the 255 characters Netgen Remote
  Media 3 can store.
* The JSON of Netgen Remote Media 2 stays in `data_text` of migrated fields. Netgen Remote Media 3 doesn't read it,
  and new versions don't copy it.
* Remote media in Netgen Layouts blocks and collections is migrated by `netgen/layouts-remote-media` 2, with its
  `netgen-layouts:remote-media:migrate-v1-to-v2` command.
