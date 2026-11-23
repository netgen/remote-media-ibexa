// Plugin modules are imported directly: the plugin index also runs front-end rendering of stored elements
import NetgenRemoteMediaPlugin from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/plugin';
import { pluginKey } from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/constants';
import {
  hasPendingLocationRequests,
  waitForLocationRequests,
} from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/utils/location-requests';
import { deleteOwnLocations, getUnusedStoredLocations } from '@netgen-remote-media/bundle/Resources/public/js/ckeditor-plugin/utils/locations';
import ibexaCustomTagFormat from './ckeditor/ibexa-custom-tag-format';

(function (doc, ibexa) {
  // Provided by Netgen\Bundle\RemoteMediaIbexaBundle\Component\RichTextConfigurationComponent
  const { config, source, endpoints, locationIdPlaceholder, unusedLocationsParameter } = ibexa.richText.ngremotemedia;
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
        // created from, so the editor never changes stored locations: the first change of an element goes to a copy
        copyLocationsOnChange: true,
        onError: message => ibexa.helpers.notification.showErrorNotification(message),
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

  // The editor never deletes stored locations, other versions may reference them. The stored locations which are no
  // longer in the editor data (replaced by copies, or of removed elements) are sent with the form, and the server
  // deletes those which nothing references anymore once the draft is saved. The field is added only to the submission,
  // so the autosave never sends it: the editor can still bring the elements back through undo.
  const sendUnusedStoredLocations = (form) => {
    const locationIds = getUnusedStoredLocations(form);
    if (locationIds.length === 0) {
      return;
    }

    const input = doc.createElement('input');
    input.type = 'hidden';
    input.name = unusedLocationsParameter;
    input.value = locationIds.join(',');
    form.append(input);

    // The form data set is built right after the submit event, in the same task
    setTimeout(() => input.remove(), 0);
  };

  // Cancel deletes the draft, or creates nothing, so the locations the editor created or copied are never stored:
  // all of them are deleted, also those still in the editor data. Locations of an autosaved draft are deleted with
  // the draft as well, when nothing else references them.
  const isCancel = submitter => submitter?.name?.endsWith('[cancel]') ?? false;

  // An element is stored only with its location, so the form is submitted once the pending location requests
  // (e.g. creating the location of a just selected resource) have settled. Cancel waits for them too, so a location
  // created in the meantime is deleted as well.
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
        if (isCancel(event.submitter)) {
          deleteOwnLocations(form);
        } else {
          sendUnusedStoredLocations(form);
        }

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
