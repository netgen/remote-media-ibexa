const path = require('path');

module.exports = (ibexaConfig, ibexaConfigManager) => {
    /** Content editing */
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-css',
        newItems: [path.resolve(__dirname, '../public/admin/field_edit.scss')],
    });

    /** Location view */
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-admin-ui-location-view-css',
        newItems: [path.resolve(__dirname, '../public/admin/field_view.scss')],
    });
};
