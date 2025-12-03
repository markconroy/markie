/* global define */
/* eslint-disable max-classes-per-file */

(function (e, t) {
  if (typeof exports === 'object' && typeof module === 'object') {
    module.exports = t();
  } else if (typeof define === 'function' && define.amd) {
    define([], t);
  } else if (typeof exports === 'object') {
    exports.CKEditor5 = t();
  } else {
    e.CKEditor5 = e.CKEditor5 || {};
    e.CKEditor5.highlightJs = t();
  }
})(typeof window !== 'undefined' ? window : this, () => {
  const moduleMap = {
    'ckeditor5/src/core.js': (e, t, n) => {
      e.exports = n('dll-reference CKEditor5.dll')('./src/core.js');
    },
    'ckeditor5/src/engine.js': (e, t, n) => {
      e.exports = n('dll-reference CKEditor5.dll')('./src/engine.js');
    },
    'ckeditor5/src/ui.js': (e, t, n) => {
      e.exports = n('dll-reference CKEditor5.dll')('./src/ui.js');
    },
    'ckeditor5/src/widget.js': (e, t, n) => {
      e.exports = n('dll-reference CKEditor5.dll')('./src/widget.js');
    },
    'dll-reference CKEditor5.dll': (e) => {
      e.exports = CKEditor5.dll;
    },
  };

  const cache = {};

  function requireModule(modulePath) {
    if (cache[modulePath] !== undefined) {
      return cache[modulePath].exports;
    }

    const module = {
      exports: {},
    };
    cache[modulePath] = module;

    moduleMap[modulePath](module, module.exports, requireModule);
    return module.exports;
  }

  requireModule.d = (exportTarget, getters) => {
    Object.keys(getters).forEach((key) => {
      if (
        requireModule.o(getters, key) &&
        !requireModule.o(exportTarget, key)
      ) {
        Object.defineProperty(exportTarget, key, {
          enumerable: true,
          get: getters[key],
        });
      }
    });
  };

  requireModule.o = (obj, prop) =>
    Object.prototype.hasOwnProperty.call(obj, prop);

  const exports = {};

  const core = requireModule('ckeditor5/src/core.js');
  const widget = requireModule('ckeditor5/src/widget.js');

  class HighlightJsCommand extends core.Command {
    execute(options) {
      const editingPlugin = this.editor.plugins.get('highlightJsEditing');
      const attributeMap = Object.entries(editingPlugin.attrs).reduce(
        (acc, [key, value]) => {
          acc[value] = key;
          return acc;
        },
        {},
      );

      const modelAttributes = Object.keys(options).reduce((acc, key) => {
        if (attributeMap[key]) {
          acc[attributeMap[key]] = options[key];
        }
        return acc;
      }, {});

      this.editor.model.change((writer) => {
        const element = writer.createElement('highlightJs', modelAttributes);
        this.editor.model.insertContent(element);
      });
    }

    refresh() {
      const model = this.editor.model;
      const selection = model.document.selection;
      const allowedParent = model.schema.findAllowedParent(
        selection.getFirstPosition(),
        'highlightJs',
      );
      this.isEnabled = allowedParent !== null;
    }
  }

  class HighlightJsEditing extends core.Plugin {
    static get requires() {
      return [widget.Widget];
    }

    init() {
      this.attrs = {
        highlightJsPluginConfig: 'data-plugin-config',
        highlightJsPluginId: 'data-plugin-id',
      };

      const config = this.editor.config.get('highlightJs');
      if (!config) {
        return;
      }

      const { previewURL, themeError } = config;
      this.previewUrl = previewURL;
      this.themeError =
        themeError ||
        `\n      <p>${Drupal.t('An error occurred while trying to preview the highlight js. Please save your work and reload this page.')}<p>\n    `;

      this._defineSchema();
      this._defineConverters();
      this.editor.commands.add(
        'highlightJs',
        new HighlightJsCommand(this.editor),
      );
    }

    async _fetchPreview(element) {
      const params = {
        plugin_id: element.getAttribute('highlightJsPluginId'),
        plugin_config: element.getAttribute('highlightJsPluginConfig'),
      };

      const response = await fetch(
        `${this.previewUrl}?${new URLSearchParams(params)}`,
      );

      if (response.ok) {
        return response.text();
      }
      return this.themeError;
    }

    _defineSchema() {
      this.editor.model.schema.register('highlightJs', {
        allowWhere: '$block',
        isObject: true,
        isContent: true,
        isBlock: true,
        allowAttributes: Object.keys(this.attrs),
      });

      this.editor.editing.view.domConverter.blockElements.push('highlight-js');
    }

    _defineConverters() {
      const conversion = this.editor.conversion;

      conversion.for('upcast').elementToElement({
        view: {
          name: 'highlight-js',
        },
        model: 'highlightJs',
      });

      conversion.for('dataDowncast').elementToElement({
        model: 'highlightJs',
        view: {
          name: 'highlight-js',
        },
      });

      conversion
        .for('editingDowncast')
        .elementToElement({
          model: 'highlightJs',
          view: (modelElement, { writer }) => {
            const container = writer.createContainerElement('figure');
            return widget.toWidget(container, writer, {
              label: Drupal.t('Highlight js'),
            });
          },
        })
        .add((dispatcher) => {
          dispatcher.on(
            'attribute:highlightJsPluginId:highlightJs',
            (evt, data, conversionApi) => {
              const writer = conversionApi.writer;
              const modelElement = data.item;
              const viewElement = conversionApi.mapper.toViewElement(data.item);

              const loadingElement = writer.createRawElement('div', {
                'data-highlight-js-preview': 'loading',
                class: 'highlight-js-preview',
              });

              writer.insert(
                writer.createPositionAt(viewElement, 0),
                loadingElement,
              );

              this._fetchPreview(modelElement).then((html) => {
                if (!loadingElement) {
                  return;
                }

                this.editor.editing.view.change((viewWriter) => {
                  const readyElement = viewWriter.createRawElement(
                    'div',
                    {
                      class: 'highlight-js-preview',
                      'data-highlight-js-preview': 'ready',
                    },
                    (domElement) => {
                      domElement.innerHTML = html;
                    },
                  );

                  viewWriter.insert(
                    viewWriter.createPositionBefore(loadingElement),
                    readyElement,
                  );
                  viewWriter.remove(loadingElement);
                });
              });
            },
          );

          return dispatcher;
        });

      Object.keys(this.attrs).forEach((attrKey) => {
        const attributeMapping = {
          model: {
            key: attrKey,
            name: 'highlightJs',
          },
          view: {
            name: 'highlight-js',
            key: this.attrs[attrKey],
          },
        };

        conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
        conversion.for('upcast').attributeToAttribute(attributeMapping);
      });
    }

    static get pluginName() {
      return 'highlightJsEditing';
    }
  }

  const ui = requireModule('ckeditor5/src/ui.js');
  const engine = requireModule('ckeditor5/src/engine.js');

  class DoubleClickObserver extends engine.DomEventObserver {
    constructor(view) {
      super(view);
      this.domEventType = 'dblclick';
    }

    onDomEvent(domEvent) {
      this.fire(domEvent.type, domEvent);
    }
  }

  class HighlightJsUI extends core.Plugin {
    init() {
      const editor = this.editor;
      const config = this.editor.config.get('highlightJs');

      if (!config) {
        return;
      }

      const { dialogURL, openDialog, dialogSettings = {} } = config;

      if (!dialogURL || typeof openDialog !== 'function') {
        return;
      }

      editor.ui.componentFactory.add('highlightJs', (locale) => {
        const command = editor.commands.get('highlightJs');
        const button = new ui.ButtonView(locale);

        button.set({
          label: Drupal.t('Highlight js'),
          icon: '<svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">	<defs>		<image  width="511" height="513" id="img1" href="data:image/png;base64,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"/>		<image  width="411" height="253" id="img2" href="data:image/png;base64,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"/>	</defs>	<style>		tspan { white-space:pre } 	</style>	<use id="code-xxl" href="#img1" x="0" y="-1"/>	<use id="Layer 3" href="#img2" x="48" y="158"/></svg>\n',
          tooltip: true,
        });

        button.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

        this.listenTo(button, 'execute', () => {
          openDialog(
            dialogURL,
            ({ attributes }) => {
              editor.execute('highlightJs', attributes);
            },
            dialogSettings,
          );
        });

        return button;
      });

      const view = editor.editing.view;
      const viewDocument = view.document;

      view.addObserver(DoubleClickObserver);

      editor.listenTo(viewDocument, 'dblclick', (evt, data) => {
        const modelElement = editor.editing.mapper.toModelElement(data.target);

        if (
          modelElement &&
          modelElement.name !== undefined &&
          modelElement.name === 'highlightJs'
        ) {
          const params = {
            plugin_id: modelElement.getAttribute('highlightJsPluginId'),
            plugin_config: modelElement.getAttribute('highlightJsPluginConfig'),
          };

          openDialog(
            `${dialogURL}?${new URLSearchParams(params)}`,
            ({ attributes }) => {
              editor.execute('highlightJs', attributes);
            },
            dialogSettings,
          );
        }
      });
    }
  }

  class HighlightJsPlugin extends core.Plugin {
    static get requires() {
      return [HighlightJsEditing, HighlightJsUI];
    }

    static get pluginName() {
      return 'highlightJs';
    }
  }

  const pluginExports = {
    HighlightJs: HighlightJsPlugin,
  };

  // Main module code
  requireModule.d(exports, {
    default: () => pluginExports,
  });

  return exports.default;
});
