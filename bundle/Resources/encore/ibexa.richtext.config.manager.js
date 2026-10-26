const path = require('path');

module.exports = (ibexaConfig, ibexaConfigManager) => {
    ibexaConfig.resolve.alias['@netgen-remote-media'] = path.resolve('./vendor/netgen/remote-media-bundle');

    /** CKEditor5 plugin */
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-richtext-onlineeditor-js',
        newItems: [path.resolve(__dirname, '../public/admin/richtext.ckeditor-plugins.js')],
    });
};
