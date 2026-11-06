/**
 * WP Valet: extends the core command palette.
 *
 * - Shift+Space opens the palette (outside text fields), like Drupal Valet.
 * - Registers Valet's commands; Cmd/Ctrl+Enter or Cmd/Ctrl+click opens them
 *   in a new tab.
 * - On the front end, mounts core's CommandMenu and adds the saved admin
 *   menu plus a content search.
 */
(function (wp, settings) {
  'use strict';

  if (!wp || !wp.commands || !wp.data || !settings) {
    return;
  }

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var store = wp.commands.store;
  var newTab = false;
  var navigated = false;
  var nonTextInputs = ['button', 'checkbox', 'color', 'file', 'image', 'radio', 'range', 'reset', 'submit'];
  var navigationKeys = ['ArrowUp', 'ArrowDown', 'Home', 'End'];
  var inputSelector = '.commands-command-menu__header input';

  // Paths from @wordpress/icons, which isn't exposed as a global.
  var iconPaths = {
    pencil: ['m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z'],
    page: [
      'M15.5 7.5h-7V9h7V7.5Zm-7 3.5h7v1.5h-7V11Zm7 3.5h-7V16h7v-1.5Z',
      'M17 4H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2ZM7 5.5h10a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5H7a.5.5 0 0 1-.5-.5V6a.5.5 0 0 1 .5-.5Z'
    ],
    post: ['m7.3 9.7 1.4 1.4c.2-.2.3-.3.4-.5 0 0 0-.1.1-.1.3-.5.4-1.1.3-1.6L12 7 9 4 7.2 6.5c-.6-.1-1.1 0-1.6.3 0 0-.1 0-.1.1-.3.1-.4.2-.6.4l1.4 1.4L4 11v1h1l2.3-2.3zM4 20h9v-1.5H4V20zm0-5.5V16h16v-1.5H4z']
  };

  function icon(name) {
    var paths = iconPaths[name];
    if (!paths) {
      return undefined;
    }
    return el(wp.primitives.SVG, {xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', fill: 'currentColor'}, paths.map(function (d) {
      return el(wp.primitives.Path, {key: d, d: d});
    }));
  }

  function trackModifier(event) {
    newTab = event.metaKey || event.ctrlKey;
    if (event.type === 'keydown' && navigationKeys.indexOf(event.key) !== -1) {
      navigated = true;
    }
  }

  ['keydown', 'keyup', 'pointerdown'].forEach(function (type) {
    document.addEventListener(type, trackModifier, true);
  });

  // Core keeps the last search when a command closes the palette itself, so
  // clear it through React's input handling before closing.
  function resetSearch() {
    var input = document.querySelector(inputSelector);
    if (!input) {
      return;
    }
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, '');
    input.dispatchEvent(new Event('input', {bubbles: true}));
  }

  // Results that load after the list renders don't move cmdk's selection, so
  // reselect the first item unless the user has already moved it.
  function selectFirst() {
    var input = document.querySelector(inputSelector);
    if (input && !navigated) {
      input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Home', bubbles: true}));
      navigated = false;
    }
  }

  function isEditable(target) {
    if (!target || !target.tagName) {
      return false;
    }
    if (target.isContentEditable || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
      return true;
    }
    return target.tagName === 'INPUT' && nonTextInputs.indexOf(target.type) === -1;
  }

  document.addEventListener('keydown', function (event) {
    if (event.code !== 'Space' || !event.shiftKey || event.metaKey || event.ctrlKey || event.altKey) {
      return;
    }
    if (isEditable(event.target)) {
      return;
    }
    event.preventDefault();
    wp.data.dispatch(store).open();
  });

  function go(url, close) {
    var openInNewTab = newTab;
    newTab = false;
    resetSearch();
    close();
    if (openInNewTab) {
      window.open(url, '_blank', 'noopener');
    }
    else {
      window.location.href = url;
    }
  }

  function toCommand(item) {
    var category = item.category || 'view';
    return {
      name: item.name,
      label: item.label,
      searchLabel: item.searchLabel || item.label,
      category: category,
      icon: icon(item.icon || (category === 'edit' ? 'pencil' : '')),
      callback: function (args) {
        go(item.url, args.close);
      }
    };
  }

  var dispatch = wp.data.dispatch(store);

  (settings.menuCommands || []).concat(settings.commands || []).forEach(function (item) {
    dispatch.registerCommand(toCommand(item));
  });

  if (!settings.mount) {
    return;
  }

  // Core only searches content inside wp-admin, so search any post type here.
  function useContentSearch(props) {
    var search = props.search;
    var state = useState({commands: [], isLoading: false});
    var setState = state[1];

    useEffect(function () {
      if (!search) {
        setState({commands: [], isLoading: false});
        return;
      }

      var cancelled = false;
      navigated = false;
      setState(function (prev) {
        return {commands: prev.commands, isLoading: true};
      });

      var timer = setTimeout(function () {
        wp.apiFetch({
          path: wp.url.addQueryArgs('/wp/v2/search', {search: search, type: 'post', subtype: 'any', per_page: 10})
        }).then(function (results) {
          if (cancelled) {
            return;
          }
          setState({
            isLoading: false,
            commands: results.map(function (result) {
              var title = wp.htmlEntities.decodeEntities(result.title) || wp.i18n.__('(no title)', 'wp-valet');
              return toCommand({
                name: 'wp-valet/content-' + result.id,
                label: title,
                searchLabel: title + ' ' + result.id,
                category: 'edit',
                icon: result.subtype === 'page' ? 'page' : 'post',
                url: settings.editUrl.replace('%d', result.id)
              });
            })
          });
        }).catch(function () {
          if (!cancelled) {
            setState({commands: [], isLoading: false});
          }
        });
      }, 250);

      return function () {
        cancelled = true;
        clearTimeout(timer);
      };
    }, [search]);

    useEffect(function () {
      if (state[0].commands.length) {
        window.requestAnimationFrame(selectFirst);
      }
    }, [state[0].commands]);

    return state[0];
  }

  dispatch.registerCommandLoader({name: 'wp-valet/content', hook: useContentSearch});

  var root = document.createElement('div');
  root.className = 'wp-valet-root';
  document.body.appendChild(root);
  wp.element.createRoot(root).render(el(wp.commands.CommandMenu));
})(window.wp, window.wpValetSettings);
