(() => {
  'use strict';

  const doc = document;
  const body = doc.body;
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const safeStorage = {
    get(key, fallback) {
      try {
        const value = window.localStorage.getItem(key);
        return value === null ? fallback : JSON.parse(value);
      } catch (_) {
        return fallback;
      }
    },
    set(key, value) {
      try {
        window.localStorage.setItem(key, JSON.stringify(value));
      } catch (_) {
        // The page remains fully usable when storage is blocked.
      }
    }
  };

  let toastTimer;
  const toast = doc.querySelector('.toast');
  const announce = (message) => {
    if (!toast) return;
    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.hidden = false;
    toastTimer = window.setTimeout(() => {
      toast.hidden = true;
    }, 2600);
  };

  /* Compact sticky header */
  const header = doc.querySelector('[data-site-header]');
  if (header) {
    const updateHeader = () => header.classList.toggle('is-compact', window.scrollY > 48);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  }

  /* Desktop mega navigation */
  const navTriggers = [...doc.querySelectorAll('.nav-trigger')];
  const closeMegaMenus = (except = null) => {
    navTriggers.forEach((trigger) => {
      if (trigger === except) return;
      trigger.setAttribute('aria-expanded', 'false');
      const panel = doc.getElementById(trigger.getAttribute('aria-controls'));
      if (panel) panel.hidden = true;
    });
  };

  navTriggers.forEach((trigger, index) => {
    const panel = doc.getElementById(trigger.getAttribute('aria-controls'));
    if (!panel) return;

    trigger.addEventListener('click', () => {
      const shouldOpen = trigger.getAttribute('aria-expanded') !== 'true';
      closeMegaMenus(trigger);
      trigger.setAttribute('aria-expanded', String(shouldOpen));
      panel.hidden = !shouldOpen;
    });

    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        const direction = event.key === 'ArrowLeft' ? 1 : -1;
        navTriggers[(index + direction + navTriggers.length) % navTriggers.length].focus();
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (trigger.getAttribute('aria-expanded') !== 'true') trigger.click();
        panel.querySelector('a, button')?.focus();
      }
    });

    panel.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeMegaMenus();
        trigger.focus();
      }
    });
  });

  doc.addEventListener('click', (event) => {
    if (!event.target.closest('.primary-nav')) closeMegaMenus();
  });

  doc.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMegaMenus();
  });

  /* Mobile navigation drawer with focus containment */
  const drawer = doc.getElementById('mobile-drawer');
  const drawerToggle = doc.querySelector('.menu-toggle');
  const drawerPanel = drawer?.querySelector('.mobile-drawer__panel');
  const pageRoot = doc.querySelector('.thp-home') || body;
  let inertSiblings = [];
  let drawerReturnFocus = null;

  const getFocusable = (container) => [...container.querySelectorAll(
    'a[href], button:not([disabled]), summary, input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )].filter((element) => !element.hidden && element.offsetParent !== null);

  const openDrawer = () => {
    if (!drawer || !drawerToggle) return;
    drawerReturnFocus = doc.activeElement;
    drawer.hidden = false;
    drawerToggle.setAttribute('aria-expanded', 'true');
    drawerToggle.setAttribute('aria-label', 'סגירת תפריט');
    body.classList.add('drawer-open');
    inertSiblings = pageRoot
      ? [...pageRoot.children].filter((element) => element !== drawer).map((element) => ({
          element,
          ariaHidden: element.getAttribute('aria-hidden')
        }))
      : [];
    inertSiblings.forEach(({ element }) => {
      element.inert = true;
      element.setAttribute('aria-hidden', 'true');
    });
    window.requestAnimationFrame(() => drawer.querySelector('.mobile-drawer__head [data-drawer-close]')?.focus());
  };

  const closeDrawer = () => {
    if (!drawer || drawer.hidden) return;
    drawer.hidden = true;
    drawerToggle?.setAttribute('aria-expanded', 'false');
    drawerToggle?.setAttribute('aria-label', 'פתיחת תפריט');
    body.classList.remove('drawer-open');
    inertSiblings.forEach(({ element, ariaHidden }) => {
      element.inert = false;
      if (ariaHidden === null) element.removeAttribute('aria-hidden');
      else element.setAttribute('aria-hidden', ariaHidden);
    });
    inertSiblings = [];
    if (drawerReturnFocus instanceof HTMLElement) drawerReturnFocus.focus();
  };

  drawerToggle?.addEventListener('click', () => {
    if (drawer.hidden) openDrawer(); else closeDrawer();
  });
  drawer?.querySelectorAll('[data-drawer-close]').forEach((button) => button.addEventListener('click', closeDrawer));
  drawer?.querySelectorAll('a[href^="#"]').forEach((link) => link.addEventListener('click', closeDrawer));

  drawerPanel?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDrawer();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = getFocusable(drawerPanel);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && doc.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && doc.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  const mobileDetails = [...doc.querySelectorAll('.mobile-nav details')];
  mobileDetails.forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) return;
      mobileDetails.forEach((other) => {
        if (other !== details) other.open = false;
      });
    });
  });

  /* Local suggestions with a real WordPress search fallback */
  const searchData = [
    { label: 'בנגקוק', type: 'יעד', icon: '⌖', keywords: 'בנגקוק bangkok กรุงเทพ שכונות עיר', href: '/בנגקוק-תאילנד/' },
    { label: 'תיירות בתאילנד', type: 'מדריך', icon: '☼', keywords: 'טיול תכנון יעדים תאילנד', href: '/תיירות-בתאילנד/' },
    { label: 'פוקט או קוסמוי', type: 'השוואה', icon: '☼', keywords: 'פוקט קוסמוי סמוי phuket koh samui איים', href: '/פוקט-או-קו-סמוי/' },
    { label: 'מתי לטוס לתאילנד', type: 'עונות', icon: '◒', keywords: 'מזג אוויר גשם עונה', href: '/מהו-הזמן-הטוב-ביותר-לחופשה-בתאילנד/' },
    { label: 'מסלול בבנגקוק', type: 'מסלול', icon: '⌖', keywords: 'יומיים שלושה ארבעה ימים טיול', href: '/טיול-בבנגקוק-ליומיים-3-ימים-או-4-ימים-מדר/' },
    { label: 'חיפוש טיסות לתאילנד', type: 'טיסות', icon: '✈', keywords: 'טיסה טיסות מחיר כרטיס', href: '/המדריך-האולטימטיבי-למציאת-טיסות-זולו/' },
    { label: 'תאילנדית שימושית', type: 'שפה', icon: '○', keywords: 'מילים ביטויים שפה תאילנדית', href: '/איך-אומרים-בתאילנדית/' },
    { label: 'קישורים שימושיים לתאילנד', type: 'קישורים', icon: '↗', keywords: 'אתרים קישורים תכנון', href: '/תאילנד-קישורים-שימושיים/' },
    { label: 'מפת תאילנד', type: 'מפה', icon: '⌖', keywords: 'מפה אזורים בנגקוק ציאנג מאי פוקט קוסמוי', href: '#atlas' },
    { label: 'חיים בתאילנד', type: 'נושא', icon: '⌂', keywords: 'מגורים עיר צפון איים יום־יום', href: '#living' },
    { label: 'נדל״ן בתאילנד', type: 'נושא', icon: '◇', keywords: 'נכס בעלות חוזה מסים עלויות ניהול', href: '#real-estate' },
    { label: 'עסקים בתאילנד', type: 'נושא', icon: '◫', keywords: 'חברה רישוי מס שוק עובדים תפעול', href: '#business' }
  ];

  const normalize = (value) => value.toLocaleLowerCase('he').replace(/[״׳'"\-]/g, '').trim();
  const searchForm = doc.querySelector('[data-global-search]');
  const searchInput = doc.getElementById('hero-search');
  const suggestionBox = doc.getElementById('search-suggestions');
  let activeSuggestion = -1;

  const closeSuggestions = () => {
    if (!suggestionBox || !searchInput) return;
    suggestionBox.hidden = true;
    suggestionBox.innerHTML = '';
    searchInput.setAttribute('aria-expanded', 'false');
    searchInput.removeAttribute('aria-activedescendant');
    activeSuggestion = -1;
  };

  const chooseSuggestion = (item) => {
    if (searchInput) searchInput.value = item.label;
    closeSuggestions();
    window.location.assign(item.href);
  };

  const renderSuggestions = (value) => {
    if (!suggestionBox || !searchInput) return;
    const term = normalize(value);
    const matches = term
      ? searchData.filter((item) => normalize(`${item.label} ${item.keywords}`).includes(term)).slice(0, 5)
      : searchData.slice(0, 5);

    suggestionBox.innerHTML = '';
    if (!matches.length) {
      const empty = doc.createElement('div');
      empty.className = 'search-empty';
      empty.textContent = 'לא נמצאה התאמה ברשימת המדריכים. חפשו באתר את הביטוי המלא.';
      empty.style.padding = '14px';
      suggestionBox.append(empty);
    } else {
      matches.forEach((item, index) => {
        const option = doc.createElement('button');
        option.type = 'button';
        option.id = `search-option-${index}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.innerHTML = `<span class="suggestion-icon" aria-hidden="true">${item.icon}</span><strong>${item.label}</strong><small>${item.type}</small>`;
        option.addEventListener('click', () => chooseSuggestion(item));
        option.addEventListener('mouseenter', () => {
          activeSuggestion = index;
          updateActiveSuggestion();
        });
        suggestionBox.append(option);
      });
    }
    suggestionBox.hidden = false;
    searchInput.setAttribute('aria-expanded', 'true');
    activeSuggestion = -1;
  };

  const updateActiveSuggestion = () => {
    const options = [...(suggestionBox?.querySelectorAll('[role="option"]') || [])];
    options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeSuggestion)));
    if (activeSuggestion >= 0 && options[activeSuggestion]) {
      searchInput?.setAttribute('aria-activedescendant', options[activeSuggestion].id);
    } else {
      searchInput?.removeAttribute('aria-activedescendant');
    }
  };

  searchInput?.addEventListener('focus', () => renderSuggestions(searchInput.value));
  searchInput?.addEventListener('input', () => renderSuggestions(searchInput.value));
  searchInput?.addEventListener('keydown', (event) => {
    const options = [...(suggestionBox?.querySelectorAll('[role="option"]') || [])];
    if (event.key === 'ArrowDown' && options.length) {
      event.preventDefault();
      activeSuggestion = (activeSuggestion + 1) % options.length;
      updateActiveSuggestion();
    } else if (event.key === 'ArrowUp' && options.length) {
      event.preventDefault();
      activeSuggestion = (activeSuggestion - 1 + options.length) % options.length;
      updateActiveSuggestion();
    } else if (event.key === 'Enter' && activeSuggestion >= 0) {
      event.preventDefault();
      options[activeSuggestion].click();
    } else if (event.key === 'Escape') {
      closeSuggestions();
    }
  });

  searchForm?.addEventListener('submit', (event) => {
    const term = searchInput?.value.trim();
    if (!term) {
      event.preventDefault();
      announce('הקלידו יעד או נושא, למשל בנגקוק, עונות או טיסות');
      searchInput?.focus();
      return;
    }
  });

  doc.addEventListener('click', (event) => {
    if (!event.target.closest('[data-global-search]')) closeSuggestions();
  });

  doc.querySelectorAll('[data-search-term]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!searchInput) return;
      searchInput.value = button.dataset.searchTerm;
      searchInput.focus();
      renderSuggestions(searchInput.value);
    });
  });

  const jumpToSearch = () => {
    closeDrawer();
    doc.getElementById('top')?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    window.setTimeout(() => searchInput?.focus(), prefersReducedMotion ? 0 : 450);
  };
  doc.querySelectorAll('[data-search-jump]').forEach((button) => button.addEventListener('click', jumpToSearch));

  /* Atlas interaction: modes, map/list and location selection */
  const placeData = {
    bangkok: {
      name: 'בנגקוק', region: 'מרכז תאילנד', description: 'בנגקוק כוללת שכונות מגורים, מרכזי עסקים, שווקים, מסעדות, רכבות עירוניות וקישורים לשאר תאילנד.', topics: 'שכונות, תחבורה, אוכל ועסקים', start: 'בנגקוק: אזורים ותחבורה', href: '/בנגקוק-תאילנד/', linkLabel: 'בנגקוק: שכונות, תחבורה ומסלולים'
    },
    chiangmai: {
      name: 'צ׳יאנג מאי', region: 'צפון תאילנד', description: 'צ׳יאנג מאי משלבת את העיר העתיקה, שכונות מגורים, שווקים וגישה להרי צפון תאילנד.', topics: 'עיר עתיקה, שכונות, טבע ושהייה ארוכה', start: 'צ׳יאנג מאי והצפון', href: '#places', linkLabel: 'צ׳יאנג מאי: עיר, טבע ומגורים'
    },
    phuket: {
      name: 'פוקט', region: 'חוף אנדמן', description: 'פוקט כוללת אזורי חוף, את העיר פוקט, שדה תעופה וקישורי מעבורת לאיים סמוכים.', topics: 'חופים, אזורים, נכסים ותחבורה', start: 'פוקט מול קוסמוי', href: '/פוקט-או-קו-סמוי/', linkLabel: 'פוקט או קוסמוי: ההבדלים'
    },
    samui: {
      name: 'קוסמוי', region: 'מפרץ תאילנד', description: 'קוסמוי כוללת אזורי חוף ומגורים, שדה תעופה ומעבורות לקופנגן ולקו טאו.', topics: 'חופים, מגורים, עסקים ואיים', start: 'קוסמוי מול פוקט', href: '/פוקט-או-קו-סמוי/', linkLabel: 'קוסמוי או פוקט: ההבדלים'
    }
  };

  const atlas = doc.querySelector('[data-atlas]');
  const placeName = atlas?.querySelector('[data-place-name]');
  const placeRegion = atlas?.querySelector('[data-place-region]');
  const placeDescription = atlas?.querySelector('[data-place-description]');
  const placeTopics = atlas?.querySelector('[data-place-topics]');
  const placeStart = atlas?.querySelector('[data-place-start]');
  const placeLink = atlas?.querySelector('[data-place-link]');
  const panelSave = atlas?.querySelector('.atlas-panel [data-save]');

  const selectPlace = (key) => {
    const data = placeData[key];
    if (!atlas || !data) return;
    atlas.querySelectorAll('[data-place]').forEach((pin) => pin.classList.toggle('is-selected', pin.dataset.place === key));
    if (placeName) placeName.textContent = data.name;
    if (placeRegion) placeRegion.textContent = data.region;
    if (placeDescription) placeDescription.textContent = data.description;
    if (placeTopics) placeTopics.textContent = data.topics;
    if (placeStart) placeStart.textContent = data.start;
    if (placeLink) {
      placeLink.href = data.href;
      placeLink.textContent = data.linkLabel;
    }
    if (panelSave) {
      panelSave.dataset.save = key;
      syncSaveButton(panelSave);
    }
  };

  atlas?.querySelectorAll('[data-place]').forEach((pin) => {
    pin.addEventListener('click', () => selectPlace(pin.dataset.place));
  });

  doc.addEventListener('click', (event) => {
    const placeControl = event.target.closest('[data-atlas-place]');
    if (!placeControl) return;
    const key = placeControl.dataset.atlasPlace;
    if (!placeData[key]) return;
    event.preventDefault();
    selectPlace(key);
    atlas?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
  });

  atlas?.querySelectorAll('[data-atlas-view]').forEach((button) => {
    button.addEventListener('click', () => {
      const listMode = button.dataset.atlasView === 'list';
      atlas.classList.toggle('list-mode', listMode);
      atlas.querySelectorAll('[data-atlas-view]').forEach((other) => {
        const selected = other === button;
        other.classList.toggle('is-active', selected);
        other.setAttribute('aria-pressed', String(selected));
      });
      const list = atlas.querySelector('.atlas-list');
      if (list) list.hidden = !listMode;
    });
  });

  /* Saved items */
  let savedItems = safeStorage.get('thailandPlatformSaved', []);
  if (!Array.isArray(savedItems)) savedItems = [];
  savedItems = [...new Set(savedItems.filter((key) => Object.prototype.hasOwnProperty.call(placeData, key)))];
  const savedList = doc.querySelector('[data-saved-list]');

  function syncSaveButton(button) {
    const isSaved = savedItems.includes(button.dataset.save);
    button.setAttribute('aria-pressed', String(isSaved));
    const symbol = button.querySelector('[aria-hidden="true"]');
    if (symbol) symbol.textContent = isSaved ? '♥' : '♡';
    const label = button.querySelector('.sr-only');
    if (label) label.textContent = isSaved ? 'הסרה מהשמורים' : 'שמירת המקום';
  }

  const updateSavedUI = () => {
    doc.querySelectorAll('[data-save]').forEach(syncSaveButton);
    doc.querySelectorAll('[data-saved-count]').forEach((count) => { count.textContent = String(savedItems.length); });
    if (!savedList) return;
    savedList.replaceChildren();
    if (!savedItems.length) {
      const empty = doc.createElement('span');
      empty.className = 'saved-list__empty';
      empty.textContent = 'המקומות שתשמרו במפה יופיעו כאן.';
      savedList.append(empty);
      return;
    }
    savedItems.forEach((key) => {
      const button = doc.createElement('button');
      button.type = 'button';
      button.dataset.atlasPlace = key;
      button.textContent = placeData[key].name;
      savedList.append(button);
    });
  };

  doc.addEventListener('click', (event) => {
    const button = event.target.closest('[data-save]');
    if (!button) return;
    const key = button.dataset.save;
    if (savedItems.includes(key)) {
      savedItems = savedItems.filter((item) => item !== key);
      announce('המקום הוסר מהשמורים');
    } else {
      savedItems.push(key);
      announce('המקום נשמר במכשיר הזה');
    }
    safeStorage.set('thailandPlatformSaved', savedItems);
    updateSavedUI();
  });
  updateSavedUI();

  /* Display preference */
  const textSizeButton = doc.querySelector('[data-text-size]');
  const savedLargeText = safeStorage.get('thailandPlatformLargeText', false) === true;
  body.classList.toggle('large-text', savedLargeText);
  textSizeButton?.setAttribute('aria-pressed', String(savedLargeText));
  textSizeButton?.addEventListener('click', () => {
    const isLarge = !body.classList.contains('large-text');
    body.classList.toggle('large-text', isLarge);
    textSizeButton.setAttribute('aria-pressed', String(isLarge));
    safeStorage.set('thailandPlatformLargeText', isLarge);
    announce(isLarge ? 'התצוגה הוגדלה' : 'התצוגה חזרה לגודל הרגיל');
  });

  /* Keep the existing Tawk chat compact while preserving its current callback. */
  let tawkVisitorInteracted = false;
  const tawkVisitorIsActive = (api) => {
    if (tawkVisitorInteracted) return true;

    try {
      const visitorEngaged = typeof api.isVisitorEngaged === 'function' && api.isVisitorEngaged();
      const chatOngoing = typeof api.isChatOngoing === 'function' && api.isChatOngoing();
      return visitorEngaged || chatOngoing;
    } catch {
      return true;
    }
  };

  const minimizeTawkWidget = () => {
    const api = window.Tawk_API;
    if (!api || typeof api.minimize !== 'function') return 'retry';
    if (tawkVisitorIsActive(api)) return 'preserve';

    try {
      api.minimize();
      const minimized = typeof api.isChatMinimized === 'function'
        ? api.isChatMinimized()
        : api.onLoaded === true;
      return minimized ? 'settled' : 'retry';
    } catch {
      return 'retry';
    }
  };

  let tawkRetryDelay = 250;
  let tawkRetryTimer = 0;
  let tawkGreetingTimer = 0;
  let tawkResumeAfterPageShow = false;
  const settleTawkWidget = () => {
    const state = minimizeTawkWidget();
    if (state !== 'retry') {
      if (tawkRetryTimer) window.clearTimeout(tawkRetryTimer);
      tawkRetryTimer = 0;
      tawkRetryDelay = 250;
      return;
    }
    if (tawkRetryTimer) return;

    const delay = tawkRetryDelay;
    tawkRetryDelay = Math.min(tawkRetryDelay * 2, 4000);
    tawkRetryTimer = window.setTimeout(() => {
      tawkRetryTimer = 0;
      settleTawkWidget();
    }, delay);
  };

  const tawkApi = window.Tawk_API = window.Tawk_API || {};
  const previousTawkOnLoad = tawkApi.onLoad;
  tawkApi.onLoad = function (...args) {
    try {
      if (typeof previousTawkOnLoad === 'function') previousTawkOnLoad.apply(this, args);
    } finally {
      settleTawkWidget();
    }
  };

  const queueTawkGreetingSettle = () => {
    if (tawkGreetingTimer) window.clearTimeout(tawkGreetingTimer);
    tawkGreetingTimer = window.setTimeout(() => {
      tawkGreetingTimer = 0;
      settleTawkWidget();
    }, 250);
  };

  const wrapTawkCallback = (callbackName, afterCallback) => {
    const previousCallback = tawkApi[callbackName];
    tawkApi[callbackName] = function (...args) {
      try {
        if (typeof previousCallback === 'function') previousCallback.apply(this, args);
      } finally {
        afterCallback();
      }
    };
  };
  wrapTawkCallback('onChatMessageVisitor', () => { tawkVisitorInteracted = true; });
  wrapTawkCallback('onChatMessageAgent', queueTawkGreetingSettle);
  wrapTawkCallback('onChatMessageSystem', queueTawkGreetingSettle);

  settleTawkWidget();
  window.addEventListener('pagehide', () => {
    tawkResumeAfterPageShow = Boolean(tawkRetryTimer || tawkGreetingTimer);
    if (tawkRetryTimer) window.clearTimeout(tawkRetryTimer);
    if (tawkGreetingTimer) window.clearTimeout(tawkGreetingTimer);
    tawkRetryTimer = 0;
    tawkGreetingTimer = 0;
  });
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted || tawkResumeAfterPageShow) settleTawkWidget();
    tawkResumeAfterPageShow = false;
  });

  /* Back to top */
  doc.querySelector('[data-back-to-top]')?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
  });
})();
