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
  var nonTextInputs = ['button', 'checkbox', 'color', 'file', 'image', 'radio', 'range', 'reset', 'submit'];

  function trackModifier(event) {
    newTab = event.metaKey || event.ctrlKey;
  }

  ['keydown', 'keyup', 'pointerdown'].forEach(function (type) {
    document.addEventListener(type, trackModifier, true);
  });

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
    close();
    if (openInNewTab) {
      window.open(url, '_blank', 'noopener');
    }
    else {
      window.location.href = url;
    }
  }

  function toCommand(item) {
    return {
      name: item.name,
      label: item.label,
      searchLabel: item.searchLabel || item.label,
      category: item.category || 'view',
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

    return state[0];
  }

  dispatch.registerCommandLoader({name: 'wp-valet/content', hook: useContentSearch});

  var root = document.createElement('div');
  root.className = 'wp-valet-root';
  document.body.appendChild(root);
  wp.element.createRoot(root).render(el(wp.commands.CommandMenu));
})(window.wp, window.wpValetSettings);
