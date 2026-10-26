// Plugin and constants are imported directly: the plugin index also runs front-end rendering of stored elements
import NetgenRemoteMediaPlugin from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/plugin';
import { pluginKey } from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/constants';
import ibexaCustomTagFormat from './ckeditor/ibexa-custom-tag-format';

(function (ibexa) {
  // Provided by Netgen\RemoteMediaIbexa\RichText\ConfigurationProvider
  const { config, source, endpoints, locationIdPlaceholder } = ibexa.richText.ngremotemedia;
  const withLocationId = path => locationId => path.replace(locationIdPlaceholder, locationId);

  // The Symfony form textarea backing the editor, unique per RichText field on the page
  const getFieldId = editor => editor.sourceElement.closest('.ibexa-data-source').querySelector('textarea').id;

  ibexa.addConfig('richText.CKEditor.extraPlugins', [NetgenRemoteMediaPlugin], true);
  ibexa.addConfig(
    'richText.CKEditor.extraConfig',
    {
      [pluginKey]: {
        fieldId: getFieldId,
        config,
        source,
        format: ibexaCustomTagFormat,
        endpoints: {
          createLocation: endpoints.createLocation,
          updateLocation: withLocationId(endpoints.updateLocation),
          deleteLocation: withLocationId(endpoints.deleteLocation),
          getSelectedImage: withLocationId(endpoints.getSelectedImage),
          viewResource: withLocationId(endpoints.viewResource),
        },
      },
    },
    true,
  );
})(window.ibexa);
