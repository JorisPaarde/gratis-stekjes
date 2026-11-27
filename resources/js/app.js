import './aanbod-detail.js';
import './bootstrap';
import { initAppInstallatie } from './app-installatie.js';

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  initFeedbackFabTip();
  initPwa();
  initAppInstallatie();
});

/** Service worker registreren. Installatieknop en balk staan in app-installatie.js. */
function initPwa() {
  const versionMeta = document.querySelector('meta[name="gp-app-version"]');
  const version = (versionMeta && versionMeta.getAttribute('content')) || '0';

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker
      .register('/sw.js?v=' + encodeURIComponent(version))
      .catch(() => {
        /* stil — offline is progressive enhancement */
      });
  }
}

/** Touch/no-hover: tip één keer per sessie ~5s tonen (geen cookies). */
function initFeedbackFabTip() {
  const wrap = document.querySelector('[data-feedback-fab-wrap]');
  const tip = document.querySelector('[data-feedback-fab-tip]');
  if (!wrap || !tip) {
    return;
  }

  const dismissBtn = tip.querySelector('[data-feedback-tip-dismiss]');
  const storageKey = 'gp-feedback-fab-tip-seen';
  let hideTimer = null;

  const hideTip = () => {
    tip.classList.remove('is-shown');
    if (dismissBtn) {
      dismissBtn.hidden = true;
    }
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
  };

  const showTip = () => {
    tip.classList.add('is-shown');
    if (dismissBtn) {
      dismissBtn.hidden = false;
    }
    hideTimer = setTimeout(hideTip, 5000);
  };

  if (dismissBtn) {
    dismissBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      hideTip();
    });
  }

  const canHover =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  if (canHover) {
    return;
  }

  let alreadySeen = false;
  try {
    alreadySeen = sessionStorage.getItem(storageKey) === '1';
  } catch {
    alreadySeen = false;
  }

  if (alreadySeen) {
    return;
  }

  try {
    sessionStorage.setItem(storageKey, '1');
  } catch {
    /* sessie-opslag niet beschikbaar — tip mag één keer op deze pageview */
  }

  showTip();
}
