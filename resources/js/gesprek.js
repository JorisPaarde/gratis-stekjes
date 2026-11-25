/**
 * Gesprekspagina: blijf bij de laatste berichten, houd het invoervak
 * boven het toetsenbord, en geef alleen op desktop meteen focus.
 */
function isFinePointer() {
  return (
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(hover: hover) and (pointer: fine)').matches
  );
}

function scrollToLatest(chat) {
  if (!chat) {
    return;
  }
  chat.scrollTop = chat.scrollHeight;
}

function syncKeyboardInset() {
  const vv = window.visualViewport;
  if (!vv) {
    document.documentElement.style.setProperty('--kb-inset', '0px');
    return;
  }
  const inset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
  document.documentElement.style.setProperty('--kb-inset', `${inset}px`);
}

function fitTextarea(field) {
  if (!field) {
    return;
  }
  field.style.height = 'auto';
  const cap = 160;
  field.style.height = `${Math.min(field.scrollHeight, cap)}px`;
}

function initGesprek() {
  const root = document.querySelector('[data-gesprek]');
  if (!root) {
    return;
  }

  const chat = root.querySelector('[data-gesprek-chat]');
  const field = root.querySelector('[data-gesprek-input]');
  const form = root.querySelector('[data-gesprek-composer]');

  const keepLatestVisible = () => {
    syncKeyboardInset();
    scrollToLatest(chat);
  };

  keepLatestVisible();
  fitTextarea(field);

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', keepLatestVisible);
    window.visualViewport.addEventListener('scroll', keepLatestVisible);
  }
  window.addEventListener('resize', keepLatestVisible);

  if (field && isFinePointer()) {
    field.focus({ preventScroll: true });
    scrollToLatest(chat);
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
    field.addEventListener('focus', () => {
      window.setTimeout(keepLatestVisible, 50);
      window.setTimeout(keepLatestVisible, 300);
    });
    field.addEventListener('input', () => {
      fitTextarea(field);
      scrollToLatest(chat);
    });
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
