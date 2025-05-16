const ngrmFieldPropertiesTables = document.querySelectorAll('[data-remote-media-properties]');

ngrmFieldPropertiesTables.forEach((ngrmFieldPropertiesTable) => {
    ngrmFieldPropertiesTable
        .querySelector('[data-remote-media-properties-toggle]')
        .addEventListener('click', () => {
            ngrmFieldPropertiesTable
                .querySelector('[data-remote-media-properties-table]')
                .classList.toggle('d-none');
        });
});
