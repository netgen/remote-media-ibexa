import { NetgenRemoteMediaPlugin, netgenremoteMediaPluginKey } from '../../../../../remote-media-bundle/bundle/Resources/public/js/ckeditor-plugin';

ibexa.addConfig('richText.CKEditor.extraPlugins', [ NetgenRemoteMediaPlugin ], true);
ibexa.addConfig('richText.CKEditor.extraConfig', { [netgenremoteMediaPluginKey]: { fieldId: 'content', variationGroup: 'iota_rich_text' } }, true);
