import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import format from '../../bundle/Resources/public/admin/ckeditor/ibexa-custom-tag-format.js';

/** Minimal stand-in for CKEditor view nodes and the downcast writer, enough for the format */
class Text {
  constructor(data) {
    this.data = data;
  }

  is(type) {
    return type === '$text';
  }
}

class Element {
  constructor(name, attributes = {}, empty = false) {
    this.name = name;
    this.attributes = attributes;
    this.empty = empty;
    this.children = [];
  }

  is(type) {
    return type === 'element';
  }

  getAttribute(name) {
    return this.attributes[name];
  }

  getChildren() {
    return this.children[Symbol.iterator]();
  }
}

const writer = {
  createEmptyElement: (name, attributes) => new Element(name, attributes, true),
  createContainerElement: (name, attributes) => new Element(name, attributes),
  createText: data => new Text(data),
  createPositionAt: (parent, offset) => ({ parent, offset }),
  insert({ parent, offset }, node) {
    parent.children.splice(offset === 'end' ? parent.children.length : offset, 0, node);
  },
};

const serialize = (node) => {
  if (node instanceof Text) {
    return node.data;
  }

  const attributes = Object.entries(node.attributes ?? {}).map(([name, value]) => ` ${name}="${value}"`).join('');

  return `<${node.name}${attributes}>${node.children.map(serialize).join('')}</${node.name}>`;
};

describe('ibexa custom tag format', () => {
  it('stores the element as the ngremotemedia custom tag', () => {
    const view = format.toView(writer, {
      fieldId: 'field_1',
      locationId: 42,
      cssClass: 'wide',
      variationName: 'article',
      alignment: 'center',
    });

    // DocBook conversion of Ibexa RichText keeps only eztemplate markup with ezconfig values, the field id is not stored
    assert.equal(
      serialize(view),
      '<div data-ezelement="eztemplate" data-ezname="ngremotemedia"><span data-ezelement="ezconfig">'
        + '<span data-ezelement="ezvalue" data-ezvalue-key="locationId">42</span>'
        + '<span data-ezelement="ezvalue" data-ezvalue-key="variationName">article</span>'
        + '<span data-ezelement="ezvalue" data-ezvalue-key="cssClass">wide</span>'
        + '<span data-ezelement="ezvalue" data-ezvalue-key="alignment">center</span>'
        + '</span></div>',
    );
  });

  it('omits empty values', () => {
    const view = format.toView(writer, { fieldId: 'field_1', locationId: 42, cssClass: '', variationName: null, alignment: undefined });

    assert.equal(
      serialize(view),
      '<div data-ezelement="eztemplate" data-ezname="ngremotemedia"><span data-ezelement="ezconfig">'
        + '<span data-ezelement="ezvalue" data-ezvalue-key="locationId">42</span>'
        + '</span></div>',
    );
  });

  it('stores an element without a location as an empty element', () => {
    for (const locationId of [null, undefined, '']) {
      const view = format.toView(writer, { fieldId: 'field_1', locationId, cssClass: 'wide' });

      assert.equal(view.empty, true);
      assert.deepEqual(view.attributes, {});
    }
  });

  it('reads stored values back', () => {
    const view = format.toView(writer, { locationId: 42, cssClass: 'wide', variationName: 'article', alignment: 'right' });

    assert.deepEqual(format.fromView(view), {
      fieldId: null,
      locationId: '42',
      cssClass: 'wide',
      variationName: 'article',
      alignment: 'right',
    });
  });

  it('reads missing values as null and ignores other markup', () => {
    const view = new Element('div', { 'data-ezelement': 'eztemplate', 'data-ezname': 'ngremotemedia' });
    const config = new Element('span', { 'data-ezelement': 'ezconfig' });
    const value = new Element('span', { 'data-ezelement': 'ezvalue', 'data-ezvalue-key': 'locationId' });
    value.children.push(new Text('4'), new Text('2'));
    config.children.push(value, new Text('\n'));
    view.children.push(new Element('span', { 'data-ezelement': 'ezcontent' }), config);

    assert.deepEqual(format.fromView(view), {
      fieldId: null,
      locationId: '42',
      cssClass: null,
      variationName: null,
      alignment: null,
    });
  });

  it('matches only the ngremotemedia custom tag, before the generic Ibexa custom tag converter', () => {
    assert.deepEqual(format.matcher, {
      name: 'div',
      attributes: { 'data-ezelement': 'eztemplate', 'data-ezname': 'ngremotemedia' },
    });
    assert.equal(format.priority, 'high');
  });
});
