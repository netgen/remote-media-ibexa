# Remote media in the RichText field

The bundle adds a Netgen Remote Media button to the RichText (CKEditor 5) toolbar in the admin UI. Selected
resources are stored in the RichText XML as the `ngremotemedia` custom tag and rendered server side.

## Setup

No imports are needed, everything is registered by the bundle:

* the CKEditor plugin is added to the RichText editor build through
  `Resources/encore/ibexa.richtext.config.manager.js`
* the plugin configuration is exposed to the admin UI by a RichText configuration provider
  (`ibexa.richText.ngremotemedia`)
* the `ngremotemedia` custom tag and the toolbar button (`admin_group`) are prepended to the configuration

Rebuild the admin assets after installing or updating the bundle, including the `richtext` build:

```
php bin/console ibexa:encore:compile --frontend-configs-name ibexa,internals,libs,richtext
```

The editor uses the Netgen Remote Media AJAX routes and the `ngrm/upload` policy, same as the field type.

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
Netgen Remote Media `@NetgenRemoteMedia/app/view_resource.html.twig` template. The template receives the tag
parameters as `params`:

| Parameter       | Description                                  |
|-----------------|----------------------------------------------|
| `locationId`    | ID of the remote resource location           |
| `variationName` | Selected variation, if any                   |
| `cssClass`      | CSS class entered in the editor, if any      |
| `alignment`     | Alignment set in the editor, if any          |

When the resource has a caption (edited in the Netgen Remote Media dialog, shared by all usages of the resource),
the template wraps the resource in a `<figure class="ngremotemedia-figure">` with the caption in a `<figcaption>`.

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
