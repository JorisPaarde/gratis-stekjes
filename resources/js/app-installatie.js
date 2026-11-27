/** Installatie van Gratis planten als app: /app-scherm, balk en beginscherm-knop. Geen push. */

const STORAGE_GESLOTEN = 'gp-app-balk-gesloten';
const STORAGE_BEZOEKEN = 'gp-app-bezoeken';
const STORAGE_BEZOEK_GETELD = 'gp-app-bezoek-geteld';
const FLASH_WACHT_MS = 1500;

let deferredPrompt = null;
let balkGetoond = false;

export function isStandalone() {
  if (typeof window === 'undefined') {
    return false;
  }
  if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
    return true;
  }
  return window.navigator.standalone === true;
}

export function isIos() {
  const ua = window.navigator.userAgent || '';
  if (/iPad|iPhone|iPod/.test(ua)) {
    return true;
  }
  return window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
}

export function isIosSafari() {
  if (!isIos()) {
    return false;
  }
  const ua = window.navigator.userAgent || '';
  if (/CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo|FBAN|FBAV|Instagram|Line\//.test(ua)) {
    return false;
  }
  return /Safari/.test(ua);
}

export function isAndroid() {
  return /Android/i.test(window.navigator.userAgent || '');
}

export function isDesktop() {
  if (isIos() || isAndroid()) {
    return false;
  }
  const ua = window.navigator.userAgent || '';
  if (/Mobi|Mobile/.test(ua)) {
    return false;
  }
  if (typeof window.matchMedia === 'function' && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    return true;
  }
  return !/Mobi|Mobile|Android|iPhone|iPad/.test(ua);
}

export function kiesAppScherm({ promptBeschikbaar = false } = {}) {
  if (isStandalone()) {
    return 'geinstalleerd';
  }
  if (isDesktop()) {
    return 'desktop';
  }
  if (isIosSafari()) {
    return 'iphone';
  }
  if (isAndroid() || promptBeschikbaar) {
    return 'android';
  }
  return 'anders';
}

function isIosVoorBalk() {
  return isIos();
}

function balkIsGesloten() {
  try {
    return window.localStorage.getItem(STORAGE_GESLOTEN) === '1';
  } catch {
    return false;
  }
}

function balkMagOpDezePagina() {
  const pad = window.location.pathname || '';
  if (pad === '/app' || pad.startsWith('/app/')) {
    return false;
  }
  if (pad === '/beheer' || pad.startsWith('/beheer/')) {
    return false;
  }
  if (pad === '/feedback' || pad.startsWith('/feedback/')) {
    return false;
  }
  if (pad === '/account/gesprek' || pad.startsWith('/account/gesprek/')) {
    return false;
  }
  return true;
}

function setInstallVisible(visible) {
  document.querySelectorAll('[data-pwa-install]').forEach((btn) => {
    btn.hidden = !visible;
  });
  document.querySelectorAll('[data-pwa-aanbod-prompt]').forEach((el) => {
    el.hidden = !visible;
  });
  document.querySelectorAll('[data-pwa-install-hint]').forEach((el) => {
    el.hidden = visible;
  });
}

export function toonAppScherm(naam) {
  const pagina = document.querySelector('[data-app-pagina]');
  if (!pagina) {
    return;
  }
  pagina.querySelectorAll('[data-app-scherm]').forEach((kaart) => {
    kaart.hidden = kaart.getAttribute('data-app-scherm') !== naam;
  });
  const intro = pagina.querySelector('[data-app-intro]');
  if (intro) {
    intro.hidden = naam === 'geinstalleerd';
  }
}

function initAppPagina() {
  const pagina = document.querySelector('[data-app-pagina]');
  if (!pagina) {
    return;
  }

  const startScherm = kiesAppScherm({ promptBeschikbaar: Boolean(deferredPrompt) });
  toonAppScherm(startScherm);
  if (startScherm === 'android' && deferredPrompt) {
    setInstallVisible(true);
  }

  if (startScherm === 'android' && !deferredPrompt) {
    window.setTimeout(() => {
      if (deferredPrompt || isStandalone()) {
        return;
      }
      const zichtbaar = pagina.querySelector('[data-app-scherm="android"]:not([hidden])');
      if (zichtbaar) {
        toonAppScherm('anders');
      }
    }, 2000);
  }

  const kopieer = pagina.querySelector('[data-app-kopieer]');
  const status = pagina.querySelector('[data-app-kopieer-status]');
  if (kopieer) {
    kopieer.addEventListener('click', async () => {
      const tekst = window.location.href;
      let gelukt = false;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(tekst);
          gelukt = true;
        }
      } catch {
        gelukt = false;
      }
      if (!gelukt) {
        try {
          const veld = document.createElement('textarea');
          veld.value = tekst;
          veld.setAttribute('readonly', '');
          veld.style.position = 'fixed';
          veld.style.left = '-9999px';
          document.body.appendChild(veld);
          veld.select();
          gelukt = document.execCommand('copy');
          veld.remove();
        } catch {
          gelukt = false;
        }
      }
      if (status) {
        status.hidden = false;
        status.textContent = gelukt
          ? 'Gekopieerd. Plak de link in Safari of Chrome en kom terug op deze pagina.'
          : 'Plak de link in Safari of Chrome en kom terug op deze pagina.';
      }
    });
  }
}

function verbergFeedbackFab(verberg) {
  const wrap = document.querySelector('[data-feedback-fab-wrap]');
  if (!wrap) {
    return;
  }
  wrap.hidden = verberg;
}

function zetBalkRuimte() {
  const balk = document.querySelector('[data-app-balk]');
  if (!balk || balk.hidden) {
    document.body.classList.remove('app-balk-zichtbaar');
    document.documentElement.style.removeProperty('--app-balk-ruimte');
    return;
  }
  const top = balk.getBoundingClientRect().top;
  const ruimte = Math.max(0, Math.ceil(window.innerHeight - top));
  document.documentElement.style.setProperty('--app-balk-ruimte', `${ruimte}px`);
  document.body.classList.add('app-balk-zichtbaar');
}

function toonBalk() {
  const balk = document.querySelector('[data-app-balk]');
  if (!balk || balkGetoond || isStandalone() || balkIsGesloten() || !balkMagOpDezePagina()) {
    return;
  }
  balkGetoond = true;
  const actie = balk.querySelector('[data-app-balk-actie]');
  if (actie) {
    actie.textContent = isIosVoorBalk() ? 'Hoe?' : 'Installeren';
  }
  balk.hidden = false;
  verbergFeedbackFab(true);
  window.requestAnimationFrame(zetBalkRuimte);
}

function sluitBalk() {
  const balk = document.querySelector('[data-app-balk]');
  if (balk) {
    balk.hidden = true;
  }
  try {
    window.localStorage.setItem(STORAGE_GESLOTEN, '1');
  } catch {
    /* opslag niet beschikbaar — balk blijft deze sessie weg */
  }
  verbergFeedbackFab(false);
  zetBalkRuimte();
}

function telBezoek() {
  try {
    if (window.sessionStorage.getItem(STORAGE_BEZOEK_GETELD) === '1') {
      return Number(window.localStorage.getItem(STORAGE_BEZOEKEN) || '0');
    }
    const huidig = Number(window.localStorage.getItem(STORAGE_BEZOEKEN) || '0');
    const volgende = Number.isFinite(huidig) ? huidig + 1 : 1;
    window.localStorage.setItem(STORAGE_BEZOEKEN, String(volgende));
    window.sessionStorage.setItem(STORAGE_BEZOEK_GETELD, '1');
    return volgende;
  } catch {
    return 1;
  }
}

function initAppBalk() {
  const balk = document.querySelector('[data-app-balk]');
  if (!balk) {
    return;
  }

  const sluit = balk.querySelector('[data-app-balk-sluit]');
  if (sluit) {
    sluit.addEventListener('click', (event) => {
      event.preventDefault();
      sluitBalk();
    });
  }

  if (isStandalone() || balkIsGesloten() || !balkMagOpDezePagina()) {
    return;
  }

  if (document.querySelector('[data-app-balk-na-aanbod]')) {
    window.setTimeout(toonBalk, FLASH_WACHT_MS);
  }

  const bezoeken = telBezoek();
  if (bezoeken >= 2) {
    const bijEersteScroll = () => {
      window.removeEventListener('scroll', bijEersteScroll);
      toonBalk();
    };
    window.addEventListener('scroll', bijEersteScroll, { passive: true });
  }

  window.addEventListener('resize', zetBalkRuimte);
}

function initInstallatieKnop() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    if (!isStandalone() && !isDesktop()) {
      toonAppScherm('android');
      setInstallVisible(true);
    }
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    setInstallVisible(false);
    toonAppScherm('geinstalleerd');
  });

  document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-pwa-install]');
    if (!btn || !deferredPrompt) {
      return;
    }
    event.preventDefault();
    deferredPrompt.prompt();
    try {
      await deferredPrompt.userChoice;
    } catch {
      /* stil */
    }
    deferredPrompt = null;
    setInstallVisible(false);
  });
}

export function initAppInstallatie() {
  initInstallatieKnop();
  initAppPagina();
  initAppBalk();
}
