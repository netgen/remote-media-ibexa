const path = require('path');

module.exports = (ibexaConfig, ibexaConfigManager) => {
    /** CKEditor5 plugin */
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-richtext-onlineeditor-js',
        newItems: [path.resolve(__dirname, '../public/admin/richtext.ckeditor-plugins.js')],
    });
};
