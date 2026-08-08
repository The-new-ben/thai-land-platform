(() => {
  'use strict';

  const root = document.querySelector('.thp-content');
  if (!root) return;

  const body = document.body;
  const toggle = root.querySelector('.thp-menu-toggle');
  const drawer = root.querySelector('#thp-mobile-nav');
  const panel = drawer?.querySelector('.thp-mobile-nav-panel');
  const desktop = window.matchMedia('(min-width: 1231px)');
  let previousFocus = null;
  let previousOverflow = '';
  let isolatedElements = [];

  const focusables = () => Array.from(panel?.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])') || []).filter((element) => !element.hidden);

  const isolatePage = () => {
    const candidates = [
      root.querySelector('.thp-skip-link'),
      root.querySelector('.thp-header-inner'),
      root.querySelector('main'),
      root.querySelector('.thp-site-footer'),
      ...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')
    ].filter(Boolean);

    isolatedElements = candidates.map((element) => ({
      element,
      inert: element.inert,
      ariaHidden: element.getAttribute('aria-hidden')
    }));
    isolatedElements.forEach(({ element }) => {
      element.inert = true;
      element.setAttribute('aria-hidden', 'true');
    });
  };

  const restorePage = () => {
    isolatedElements.forEach(({ element, inert, ariaHidden }) => {
      element.inert = inert;
      if (ariaHidden === null) element.removeAttribute('aria-hidden');
      else element.setAttribute('aria-hidden', ariaHidden);
    });
    isolatedElements = [];
  };

  const closeMenu = ({ restoreFocus = true } = {}) => {
    if (!drawer || drawer.hidden) return;
    drawer.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'פתיחת תפריט');
    restorePage();
    body.style.overflow = previousOverflow;
    body.classList.remove('thp-content-menu-open');
    if (restoreFocus && previousFocus instanceof HTMLElement) previousFocus.focus();
  };

  const openMenu = () => {
    if (!drawer || !toggle) return;
    previousFocus = document.activeElement;
    previousOverflow = body.style.overflow;
    drawer.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'סגירת תפריט');
    body.style.overflow = 'hidden';
    body.classList.add('thp-content-menu-open');
    isolatePage();
    focusables()[0]?.focus();
  };

  toggle?.addEventListener('click', () => {
    if (drawer?.hidden) openMenu();
    else closeMenu();
  });

  drawer?.addEventListener('click', (event) => {
    if (event.target.closest('[data-thp-menu-close]') || event.target.closest('a[href]')) closeMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (!drawer || drawer.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeMenu();
      return;
    }
    if (event.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  desktop.addEventListener('change', (event) => {
    if (event.matches && drawer && !drawer.hidden) {
      closeMenu({ restoreFocus: false });
      root.querySelector('.thp-brand')?.focus();
    }
  });
  window.addEventListener('pagehide', () => closeMenu({ restoreFocus: false }));

  const prose = root.querySelector('.thp-prose');
  const toc = root.querySelector('[data-thp-toc]');
  const headings = Array.from(prose?.querySelectorAll('h2') || []);
  if (toc && headings.length) {
    headings.forEach((heading, index) => {
      if (!heading.id) heading.id = `thp-section-${index + 1}`;
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = `#${heading.id}`;
      link.textContent = heading.textContent.trim();
      item.append(link);
      toc.append(item);
    });
  } else {
    toc?.closest('.thp-toc')?.setAttribute('hidden', '');
  }
})();
