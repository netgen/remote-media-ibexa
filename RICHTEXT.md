# Remote media in the RichText field

The bundle adds a Netgen Remote Media button to the RichText (CKEditor 5) toolbar in the admin UI. Selected
resources are stored in the RichText XML as the `ngremotemedia` custom tag and rendered server side.

## Setup

No imports are needed, everything is registered by the bundle:

* the CKEditor plugin is added to the RichText editor build through
  `Resources/encore/ibexa.richtext.config.manager.js`
* the plugin configuration is exposed as `ibexa.richText.ngremotemedia` by an admin UI component
  (`script-head` group), so it is built only when the admin UI layout is rendered
* the `ngremotemedia` custom tag and the toolbar button (`admin_group`) are prepended to the configuration when
  `IbexaFieldTypeRichTextBundle` is enabled

Rebuild the admin assets after installing or updating the bundle, including the `richtext` build:

```
php bin/console ibexa:encore:compile --config-name richtext
```

The editor uses the Netgen Remote Media AJAX routes and the `ngrm/upload` policy, same as the field type.

## Remote resource locations

Each element stores the ID of a remote resource location, which holds the crop settings, the watermark text and the
alt text and caption overrides. Every version, translation and copy of the content starts with the same location IDs
as the version it was created from, so the editor never changes stored locations:

* when the editor loads, every element is switched to a new copy of its location (`copyLocationsOnLoad`), and all
  changes made in the editor go to the copy, so a draft never changes the published version or other drafts
* removing an element or replacing its resource does not delete the location, as the element can come back through
  undo, paste or drag and drop
* the form is submitted only after pending location requests have settled, and a failed request is reported with an
  error notification

Locations which are no longer referenced (e.g. copies made by an editor which was closed without saving, or
locations of deleted versions) stay in the `ngrm_remote_resource_location` table with the configured
`location_source`.

## Configuration

All settings are siteaccess aware and optional, these are the defaults:

```yaml
netgen_remote_media_ibexa:
    system:
        default:
            richtext:
                # Variation group used for cropping in the editor and for rendering on the site
                variation_group: default
                # Source stored on remote resource locations created from RichText
                location_source: ibexa_richtext
                allowed_types: []
                allowed_visibilities: []
                allowed_tags: []
                # Upload is also disabled for users without the ngrm/upload policy
                disable_upload: false
                # Folder paths for browsing and uploading, e.g. "media/articles"
                folder: ~
                parent_folder: ~
                upload_context: {}
```

A scope overrides only the settings it defines. The editor offers crop variations of the group configured for
the admin siteaccess, while the site renders with the group of its own siteaccess, so keep `variation_group` the
same for both (e.g. set it in the `default` scope).

The toolbar button is configured as usual for RichText:

```yaml
ibexa:
    system:
        admin_group:
            fieldtypes:
                ezrichtext:
                    toolbar:
                        ngremotemedia_group:
                            buttons:
                                ngremotemedia:
                                    visible: false
```

## Rendering

The custom tag is rendered with `@ibexadesign/ngremotemedia/richtext/custom_tag.html.twig`, which includes the
Netgen Remote Media template configured in `netgen_remote_media.templates.view_resource` (by default
`@NetgenRemoteMedia/app/view_resource.html.twig`) inside a `div.ngremotemedia-richtext` wrapper, which gets
`text-align` for the alignment set in the editor. Same as the field template, protected resources are rendered only
for users with the `ngrm/read_protected` policy, with an authenticated URL. The template receives the tag parameters
as `params`:

| Parameter       | Description                                                |
|-----------------|------------------------------------------------------------|
| `locationId`    | ID of the remote resource location                         |
| `variationName` | Selected variation, if any                                 |
| `cssClass`      | CSS class entered in the editor, if any                    |
| `alignment`     | Alignment set in the editor (`left`, `center`, `right` or `justify`), if any |

To override it, either add `templates/themes/<your theme>/ngremotemedia/richtext/custom_tag.html.twig`, or set
another template for the tag:

```yaml
ibexa_fieldtype_richtext:
    custom_tags:
        ngremotemedia:
            template: '@ibexadesign/richtext/remote_media.html.twig'
```

The custom tag is intentionally not enabled in `fieldtypes.ezrichtext.custom_tags`, as that would add the generic
custom tag button and form to the editor.
