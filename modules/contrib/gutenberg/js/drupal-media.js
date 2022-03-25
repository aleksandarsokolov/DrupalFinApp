/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/'use strict';

(function (wp, $, Drupal, drupalSettings, DrupalGutenberg) {
  var blocks = wp.blocks,
      blockEditor = wp.blockEditor,
      data = wp.data;
  var dispatch = data.dispatch;
  var RichText = blockEditor.RichText;
  var DrupalMediaEntity = DrupalGutenberg.Components.DrupalMediaEntity;

  var gutenberg = drupalSettings.gutenberg || {};
  var isMediaLibraryEnabled = gutenberg['media-library-enabled'] || false;
  var isMediaEnabled = gutenberg['media-enabled'] || false;
  var __ = Drupal.t;

  var registerBlock = function registerBlock() {
    var blockId = 'drupalmedia/drupal-media-entity';

    blocks.registerBlockType(blockId, {
      title: Drupal.t('Media'),
      icon: 'admin-media',
      category: 'common',
      supports: {
        align: false,
        html: false,
        reusable: false
      },
      attributes: {
        mediaEntityIds: {
          type: 'array'
        },
        viewMode: {
          type: 'string',
          default: 'default'
        },
        caption: {
          type: 'string',
          default: ''
        },
        lockViewMode: {
          type: 'boolean',
          default: false
        },
        allowedTypes: {
          type: 'array',
          default: ['image', 'video', 'audio', 'application']
        }
      },
      edit: function edit(_ref) {
        var attributes = _ref.attributes,
            className = _ref.className,
            setAttributes = _ref.setAttributes,
            isSelected = _ref.isSelected,
            clientId = _ref.clientId;
        var mediaEntityIds = attributes.mediaEntityIds,
            caption = attributes.caption;


        return React.createElement(
          'figure',
          { className: className },
          React.createElement(DrupalMediaEntity, {
            attributes: attributes,
            className: className,
            setAttributes: setAttributes,
            isSelected: isSelected,
            isMediaLibraryEnabled: isMediaLibraryEnabled,
            clientId: clientId,
            onError: function onError(err) {
              dispatch('core/notices').createWarningNotice(err[2]);
            }
          }),
          mediaEntityIds && mediaEntityIds.length > 0 && (!RichText.isEmpty(caption) || isSelected) && React.createElement(RichText, {
            tagName: 'figcaption',
            placeholder: __('Write caption…'),
            value: caption,
            onChange: function onChange(value) {
              return setAttributes({ caption: value });
            }
          })
        );
      },
      save: function save() {
        return null;
      }
    });
  };

  var registerDrupalMedia = function registerDrupalMedia() {
    return new Promise(function (resolve) {
      if (isMediaEnabled) {
        registerBlock();
      }

      resolve();
    });
  };

  window.DrupalGutenberg = window.DrupalGutenberg || {};
  window.DrupalGutenberg.registerDrupalMedia = registerDrupalMedia;
})(wp, jQuery, Drupal, drupalSettings, DrupalGutenberg);