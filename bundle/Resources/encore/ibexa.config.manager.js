const path = require('path');

module.exports = (ibexaConfig, ibexaConfigManager) => {
    /** Content editing */
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-css',
        newItems: [path.resolve(__dirname, '../public/admin/field.scss')],
    });
};
