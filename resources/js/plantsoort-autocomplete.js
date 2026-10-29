/**
 * Toegankelijke plantsoort-combobox (WAI-ARIA APG).
 * Debounced fetch, AbortController, pijltjes/Enter/Escape/Tab.
 * Zonder JS blijft het een gewoon tekstveld.
 *
 * CSP: alleen Vite-bundle ('self') + fetch naar eigen domein (connect-src 'self').
 * Geen externe scripts/CDN.
 */
(function () {
  var DEBOUNCE_MS = 280;

  function initCombobox(root) {
    var input = root.querySelector('[data-plantsoort-input]');
    var listbox = root.querySelector('[data-plantsoort-listbox]');
    var hidden = root.querySelector('[data-plantsoort-id]');
    var status = root.querySelector('[data-plantsoort-status]');
    var endpoint = root.getAttribute('data-plantsoort-url');
    if (!input || !listbox || !hidden || !endpoint) return;

    var activeIndex = -1;
    var items = [];
    var debounceTimer = null;
    var controller = null;
    var suppressFetch = false;

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listbox.id);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('spellcheck', 'false');

    function setStatus(text) {
      if (status) status.textContent = text || '';
    }

    function close() {
      listbox.hidden = true;
      listbox.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
      items = [];
    }

    function optionId(i) {
      return listbox.id + '-opt-' + i;
    }

    function render() {
      listbox.innerHTML = '';
      if (!items.length) {
        close();
        setStatus('Geen plantensoorten gevonden');
        return;
      }

      items.forEach(function (item, i) {
        var li = document.createElement('li');
        li.id = optionId(i);
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
        li.className = 'plantsoort-option' + (i === activeIndex ? ' is-active' : '');
        li.dataset.index = String(i);

        var naam = document.createElement('span');
        naam.className = 'plantsoort-option-naam';
        naam.textContent = item.naam;

        var latijn = document.createElement('span');
        latijn.className = 'plantsoort-option-latijn';
        latijn.textContent = item.latijn || '';

        li.appendChild(naam);
        if (item.latijn) li.appendChild(latijn);

        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
          choose(i);
        });

        listbox.appendChild(li);
      });

      listbox.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      setActive(activeIndex >= 0 ? activeIndex : 0);
      setStatus(items.length + (items.length === 1 ? ' suggestie' : ' suggesties'));
    }

    function setActive(index) {
      if (!items.length) {
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        return;
      }
      activeIndex = Math.max(0, Math.min(items.length - 1, index));
      var options = listbox.querySelectorAll('[role="option"]');
      options.forEach(function (opt, i) {
        var on = i === activeIndex;
        opt.setAttribute('aria-selected', on ? 'true' : 'false');
        opt.classList.toggle('is-active', on);
      });
      input.setAttribute('aria-activedescendant', optionId(activeIndex));
      var active = options[activeIndex];
      if (active && typeof active.scrollIntoView === 'function') {
        active.scrollIntoView({ block: 'nearest' });
      }
    }

    function choose(index) {
      var item = items[index];
      if (!item) return;
      suppressFetch = true;
      input.value = item.naam;
      hidden.value = String(item.id);
      close();
      setStatus('Gekozen: ' + item.naam);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      setTimeout(function () {
        suppressFetch = false;
      }, 0);
    }

    function clearLinkIfTypedAway() {
      // Bij typen na een keuze: koppeling loslaten tot opnieuw gekozen / exacte server-match.
      if (hidden.value !== '') {
        hidden.value = '';
      }
    }

    function fetchSuggestions(q) {
      if (controller) {
        controller.abort();
        controller = null;
      }
      if (!q || q.trim().length < 1) {
        close();
        setStatus('');
        return;
      }

      controller = new AbortController();
      var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q.trim());

      fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      })
        .then(function (res) {
          if (res.status === 429) {
            setStatus('Te vaak gezocht, even wachten');
            close();
            return null;
          }
          if (!res.ok) throw new Error('zoekfout');
          return res.json();
        })
        .then(function (data) {
          if (!data) return;
          items = Array.isArray(data.items) ? data.items : [];
          activeIndex = items.length ? 0 : -1;
          render();
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          close();
        });
    }

    function scheduleFetch() {
      if (suppressFetch) return;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        fetchSuggestions(input.value);
      }, DEBOUNCE_MS);
    }

    input.addEventListener('input', function () {
      clearLinkIfTypedAway();
      scheduleFetch();
    });

    input.addEventListener('keydown', function (e) {
      var open = input.getAttribute('aria-expanded') === 'true';

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!open) {
          fetchSuggestions(input.value);
          return;
        }
        setActive(activeIndex < 0 ? 0 : activeIndex + 1);
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!open) return;
        setActive(activeIndex <= 0 ? items.length - 1 : activeIndex - 1);
        return;
      }
      if (e.key === 'Enter') {
        if (open && activeIndex >= 0 && items[activeIndex]) {
          e.preventDefault();
          choose(activeIndex);
        }
        return;
      }
      if (e.key === 'Escape') {
        if (open) {
          e.preventDefault();
          close();
          setStatus('');
        }
        return;
      }
      if (e.key === 'Tab') {
        if (open) close();
      }
    });

    input.addEventListener('blur', function () {
      // Korte delay zodat mousedown op optie eerst kan fire'n.
      setTimeout(function () {
        close();
      }, 150);
    });

    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) close();
    });
  }

  document.querySelectorAll('[data-plantsoort-autocomplete]').forEach(initCombobox);
})();
