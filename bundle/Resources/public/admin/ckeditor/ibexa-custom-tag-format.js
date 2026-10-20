/**
 * Stores the remote media element as the "ngremotemedia" Ibexa RichText custom tag,
 * the only block markup which survives conversion to DocBook. Rendering is done server side
 * by the custom tag template, so only values needed for rendering are stored.
 */
const tagName = 'ngremotemedia';
const valueKeys = ['locationId', 'variationName', 'cssClass', 'alignment'];

const readValues = (viewElement) => {
  const values = {};

  for (const configElement of viewElement.getChildren()) {
    if (!configElement.is('element') || configElement.getAttribute('data-ezelement') !== 'ezconfig') {
      continue;
    }

    for (const valueElement of configElement.getChildren()) {
      if (!valueElement.is('element') || valueElement.getAttribute('data-ezelement') !== 'ezvalue') {
        continue;
      }

      values[valueElement.getAttribute('data-ezvalue-key')] = [...valueElement.getChildren()]
        .filter(node => node.is('$text'))
        .map(node => node.data)
        .join('');
    }
  }

  return values;
};

const ibexaCustomTagFormat = {
  matcher: {
    name: 'div',
    attributes: {
      'data-ezelement': 'eztemplate',
      'data-ezname': tagName,
    },
  },
  // Ibexa upcasts every div[data-ezelement=eztemplate] as its own custom tag with normal priority
  priority: 'high',
  fromView: (viewElement) => {
    const values = readValues(viewElement);

    return {
      fieldId: null,
      locationId: values.locationId || null,
      cssClass: values.cssClass || null,
      variationName: values.variationName || null,
      alignment: values.alignment || null,
    };
  },
  toView: (writer, data) => {
    // "locationId" is a required custom tag attribute, so an element without a selected resource is not stored:
    // an empty div is dropped by the conversion to DocBook (an empty element, as a container element would get
    // a &nbsp; filler which ends up as invalid text in DocBook). Every model element still needs a view element,
    // otherwise CKEditor fails to map the positions of the following siblings.
    if (!data.locationId) {
      return writer.createEmptyElement('div');
    }

    const tag = writer.createContainerElement('div', {
      'data-ezelement': 'eztemplate',
      'data-ezname': tagName,
    });
    const config = writer.createContainerElement('span', { 'data-ezelement': 'ezconfig' });

    valueKeys
      .filter(key => data[key] !== null && data[key] !== undefined && data[key] !== '')
      .forEach((key) => {
        const value = writer.createContainerElement('span', {
          'data-ezelement': 'ezvalue',
          'data-ezvalue-key': key,
        });

        writer.insert(writer.createPositionAt(value, 0), writer.createText(String(data[key])));
        writer.insert(writer.createPositionAt(config, 'end'), value);
      });

    writer.insert(writer.createPositionAt(tag, 0), config);

    return tag;
  },
};

export default ibexaCustomTagFormat;
