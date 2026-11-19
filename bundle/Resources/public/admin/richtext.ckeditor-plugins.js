// Plugin modules are imported directly: the plugin index also runs front-end rendering of stored elements
import NetgenRemoteMediaPlugin from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/plugin';
import { pluginKey } from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/constants';
import {
  hasPendingLocationRequests,
  waitForLocationRequests,
} from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/utils/location-requests';
import ibexaCustomTagFormat from './ckeditor/ibexa-custom-tag-format';

(function (doc, ibexa) {
  // Provided by Netgen\Bundle\RemoteMediaIbexaBundle\Component\RichTextConfigurationComponent
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
        // Every version, translation and copy of the content references the same locations as the version it was
        // created from, so the editor works on copies and changes made in a draft never reach other versions
        copyLocationsOnLoad: true,
        onError: message => ibexa.helpers.notification.showErrorNotification(message),
        endpoints: {
          createLocation: endpoints.createLocation,
          updateLocation: withLocationId(endpoints.updateLocation),
          getSelectedImage: withLocationId(endpoints.getSelectedImage),
          viewResource: withLocationId(endpoints.viewResource),
        },
      },
    },
    true,
  );

  // An element is stored only with its location, so the form is submitted once the pending location requests
  // (e.g. creating the location of a just selected resource) have settled
  const waitingForms = new WeakSet();
  doc.addEventListener(
    'submit',
    (event) => {
      const form = event.target;
      if (waitingForms.has(form)) {
        event.preventDefault();
        event.stopImmediatePropagation();

        return;
      }

      if (!hasPendingLocationRequests()) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();
      waitingForms.add(form);

      waitForLocationRequests().then(() => {
        waitingForms.delete(form);
        form.requestSubmit(event.submitter);
      });
    },
    true,
  );

  // Ibexa autosave posts `new FormData(form)` with fetch, so it doesn't go through the submit listener. An element
  // whose location is still being created is left out of the autosaved draft, so the draft is autosaved again once
  // the pending location requests have settled. Building the form data fires the "formdata" event on the form.
  const autosaveButtonName = 'ezplatform_content_forms_content_edit[autosave]';
  const formsToAutosave = new WeakSet();
  doc.addEventListener(
    'formdata',
    (event) => {
      const form = event.target;
      const isAutosaveEnabled = ibexa.adminUiConfig?.autosave?.enabled
        && form.querySelector(`[name="${autosaveButtonName}"]`) !== null;

      if (!isAutosaveEnabled || waitingForms.has(form) || formsToAutosave.has(form) || !hasPendingLocationRequests()) {
        return;
      }

      formsToAutosave.add(form);

      waitForLocationRequests()
        .then(() => {
          formsToAutosave.delete(form);

          // A submit waiting for the same requests stores the element anyway
          if (waitingForms.has(form)) {
            return undefined;
          }

          // Same request as the Ibexa autosave
          const formData = new FormData(form);
          formData.set(autosaveButtonName, true);

          return fetch(form.target || window.location.href, { method: 'POST', body: formData });
        })
        .catch(() => {});
    },
    true,
  );
})(document, window.ibexa);
