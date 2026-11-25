/**
 * Gesprekspagina: focus in het invoervak (ook op mobiel) en pas
 * de hoogte van het veld aan tijdens typen.
 *
 * iOS opent het toetsenbord bij autofocus/focus() meestal niet zonder
 * een tik van de bezoeker; de focus in het veld zetten we wél.
 */
function fitTextarea(field) {
  if (!field) {
    return;
  }
  field.style.height = 'auto';
  const cap = 160;
  field.style.height = `${Math.min(field.scrollHeight, cap)}px`;
}

function focusComposer(field) {
  if (!field) {
    return;
  }
  field.focus();
}

function initGesprek() {
  const root = document.querySelector('[data-gesprek]');
  if (!root) {
    return;
  }

  const field = root.querySelector('[data-gesprek-input]');
  const form = root.querySelector('[data-gesprek-composer]');

  if (typeof history !== 'undefined' && 'scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }
  window.scrollTo(0, 0);
  fitTextarea(field);
  focusComposer(field);
  if (form && typeof form.scrollIntoView === 'function') {
    form.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  }

  const meer = root.querySelector('[data-gesprek-meer]');
  const syncMeer = () => {
    if (!meer || typeof window.matchMedia !== 'function') {
      return;
    }
    if (window.matchMedia('(min-width: 40rem)').matches) {
      meer.open = true;
    }
  };
  syncMeer();
  if (typeof window.matchMedia === 'function') {
    const mq = window.matchMedia('(min-width: 40rem)');
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncMeer);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncMeer);
    }
  }

  if (field) {
    field.addEventListener('input', () => fitTextarea(field));
  }

  if (form && field) {
    field.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' || (!event.metaKey && !event.ctrlKey)) {
        return;
      }
      event.preventDefault();
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', initGesprek);
