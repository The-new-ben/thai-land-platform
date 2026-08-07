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
        // The prototype remains fully usable when storage is blocked.
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
    window.requestAnimationFrame(() => drawer.querySelector('.mobile-drawer__head [data-drawer-close]')?.focus());
  };

  const closeDrawer = () => {
    if (!drawer || drawer.hidden) return;
    drawer.hidden = true;
    drawerToggle?.setAttribute('aria-expanded', 'false');
    drawerToggle?.setAttribute('aria-label', 'פתיחת תפריט');
    body.classList.remove('drawer-open');
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

  /* Local, privacy-safe search prototype */
  const searchData = [
    { label: 'בנגקוק', type: 'מקום', icon: '⌖', keywords: 'בנגקוק bangkok กรุงเทพ שכונות עיר', href: '#places' },
    { label: 'קוסמוי למשפחות', type: 'מדריך', icon: '☼', keywords: 'קוסמוי סמוי koh samui משפחות ילדים', href: '#travel' },
    { label: 'מדריך קניית דירה בבנגקוק', type: 'נדל״ן', icon: '◇', keywords: 'דירה בנגקוק נדלן קניה השקעה', href: '#real-estate' },
    { label: 'פתיחת חברה בתאילנד', type: 'עסקים', icon: '◫', keywords: 'חברה עסק תאילנד רישום מס רישוי', href: '#business' },
    { label: 'עורך דין דובר עברית', type: 'שירות', icon: '✦', keywords: 'עורך דין עברית משפטים שירות', href: '#services' },
    { label: 'אירועים ישראליים בקוסמוי', type: 'קהילה', icon: '◎', keywords: 'אירועים ישראלים קהילה קוסמוי', href: '#community' },
    { label: 'חבילת גלישה לתאילנד', type: 'מוצר', icon: '▱', keywords: 'esim סים גלישה טלפון חנות', href: '#shop' },
    { label: 'צ׳יאנג מאי', type: 'מקום', icon: '⌖', keywords: 'ציאנג מאי chiang mai צפון', href: '#places' },
    { label: 'פוקט', type: 'מקום', icon: '⌖', keywords: 'פוקט phuket חוף אנדמן', href: '#places' }
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
    activeSuggestion = -1;
  };

  const chooseSuggestion = (item) => {
    if (searchInput) searchInput.value = item.label;
    closeSuggestions();
    doc.querySelector(item.href)?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    announce(`פותחים את ${item.label}`);
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
      empty.textContent = 'לא מצאנו התאמה מיידית — אפשר לחפש את הביטוי המלא.';
      empty.style.padding = '14px';
      suggestionBox.append(empty);
    } else {
      matches.forEach((item, index) => {
        const option = doc.createElement('button');
        option.type = 'button';
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
    event.preventDefault();
    const term = searchInput?.value.trim();
    if (!term) {
      announce('כתבו מקום, נושא או שירות לחיפוש');
      searchInput?.focus();
      return;
    }
    const exact = searchData.find((item) => normalize(item.label).includes(normalize(term)) || normalize(item.keywords).includes(normalize(term)));
    if (exact) chooseSuggestion(exact);
    else {
      closeSuggestions();
      announce(`החיפוש המלא עבור “${term}” ייפתח כאן`);
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
      name: 'בנגקוק', region: 'מרכז תאילנד', description: 'עיר של שכונות שונות לחלוטין, מרכזי עסקים, אוכל רחוב, קהילות בינלאומיות וחיבורים לכל המדינה.', best: 'עיר, עסקים, אוכל ומעבר', start: 'מדריך השכונות'
    },
    chiangmai: {
      name: 'צ׳יאנג מאי', region: 'צפון תאילנד', description: 'עיר ירוקה שמחברת תרבות לאנה, הרים, קהילה בינלאומית ועלויות מחיה נוחות יחסית.', best: 'תרבות, טבע ושהייה ארוכה', start: 'עיר עתיקה והסביבה'
    },
    phuket: {
      name: 'פוקט', region: 'חוף אנדמן', description: 'אי גדול של חופים ושכונות שונים מאוד, עם תיירות, בתי ספר, קהילות ושוק נדל״ן בינלאומי.', best: 'חופים, מגורים ונדל״ן', start: 'השוואת אזורי פוקט'
    },
    samui: {
      name: 'קוסמוי', region: 'מפרץ תאילנד', description: 'אי עם קצב חיים ברור, קהילה ישראלית, חופים מגוונים וגישה נוחה לפנגן ולטאו.', best: 'איים, קהילה ואורח חיים', start: 'מדריך חופי קוסמוי'
    },
    pattaya: {
      name: 'פטאיה', region: 'מזרח תאילנד', description: 'עיר חוף נגישה מבנגקוק עם אזורי מגורים, מסחר, תיירות וחיבור למסדרון הכלכלי המזרחי.', best: 'נגישות, חוף ועסקים', start: 'מפת האזורים'
    }
  };

  const atlas = doc.querySelector('[data-atlas]');
  const placeName = atlas?.querySelector('[data-place-name]');
  const placeRegion = atlas?.querySelector('[data-place-region]');
  const placeDescription = atlas?.querySelector('[data-place-description]');
  const placeBest = atlas?.querySelector('[data-place-best]');
  const placeStart = atlas?.querySelector('[data-place-start]');
  const placeLink = atlas?.querySelector('[data-place-link]');
  const panelSave = atlas?.querySelector('.atlas-panel [data-save]');

  atlas?.querySelectorAll('[data-place]').forEach((pin) => {
    pin.addEventListener('click', () => {
      const key = pin.dataset.place;
      const data = placeData[key];
      if (!data) return;
      atlas.querySelectorAll('[data-place]').forEach((other) => other.classList.toggle('is-selected', other === pin));
      if (placeName) placeName.textContent = data.name;
      if (placeRegion) placeRegion.textContent = data.region;
      if (placeDescription) placeDescription.textContent = data.description;
      if (placeBest) placeBest.textContent = data.best;
      if (placeStart) placeStart.textContent = data.start;
      if (placeLink) placeLink.textContent = `להכיר את ${data.name}`;
      if (panelSave) {
        panelSave.dataset.save = key;
        syncSaveButton(panelSave);
      }
    });
  });

  const modeNames = {
    places: 'מקומות', property: 'נדל״ן', services: 'שירותים', business: 'עסקים', community: 'קהילה', shop: 'קניות'
  };
  atlas?.querySelectorAll('[data-map-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      atlas.querySelectorAll('[data-map-mode]').forEach((other) => {
        const selected = other === button;
        other.classList.toggle('is-active', selected);
        other.setAttribute('aria-pressed', String(selected));
      });
      atlas.dataset.mode = button.dataset.mapMode;
      announce(`שכבת ${modeNames[button.dataset.mapMode]} מוצגת במפה`);
    });
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
  let savedItems = safeStorage.get('thailandPrototypeSaved', []);
  if (!Array.isArray(savedItems)) savedItems = [];

  function syncSaveButton(button) {
    const isSaved = savedItems.includes(button.dataset.save);
    button.setAttribute('aria-pressed', String(isSaved));
    const symbol = button.querySelector('[aria-hidden="true"]');
    if (symbol) symbol.textContent = isSaved ? '♥' : '♡';
    const label = button.querySelector('.sr-only');
    if (label) label.textContent = isSaved ? 'הסרה מהשמורים' : 'שמירה לפריטים שלי';
  }

  const updateSavedUI = () => {
    doc.querySelectorAll('[data-save]').forEach(syncSaveButton);
    doc.querySelectorAll('[data-saved-count]').forEach((count) => { count.textContent = String(savedItems.length); });
  };

  doc.addEventListener('click', (event) => {
    const button = event.target.closest('[data-save]');
    if (!button) return;
    const key = button.dataset.save;
    if (savedItems.includes(key)) {
      savedItems = savedItems.filter((item) => item !== key);
      announce('הפריט הוסר מהשמורים');
    } else {
      savedItems.push(key);
      announce('הפריט נשמר באזור שלכם');
    }
    safeStorage.set('thailandPrototypeSaved', savedItems);
    updateSavedUI();
  });
  updateSavedUI();

  /* Human feedback for prototype forms; no data leaves the page */
  doc.querySelector('[data-match-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const feedback = event.currentTarget.querySelector('.form-feedback');
    if (feedback) {
      feedback.textContent = 'הבחירה נשמרה — השלב הבא יציג כמה שאלות קצרות להתאמה מדויקת.';
      feedback.hidden = false;
    }
  });

  doc.querySelector('[data-alert-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const feedback = event.currentTarget.querySelector('.form-feedback');
    if (feedback) {
      feedback.textContent = 'נרשמתם. העדכון הראשון יגיע רק כשיש משהו שימושי.';
      feedback.hidden = false;
    }
    event.currentTarget.querySelector('input')?.setAttribute('disabled', '');
    event.currentTarget.querySelector('select')?.setAttribute('disabled', '');
    event.currentTarget.querySelector('button')?.setAttribute('disabled', '');
  });

  /* Display preference */
  const textSizeButton = doc.querySelector('[data-text-size]');
  const savedLargeText = safeStorage.get('thailandPrototypeLargeText', false) === true;
  body.classList.toggle('large-text', savedLargeText);
  textSizeButton?.setAttribute('aria-pressed', String(savedLargeText));
  textSizeButton?.addEventListener('click', () => {
    const isLarge = !body.classList.contains('large-text');
    body.classList.toggle('large-text', isLarge);
    textSizeButton.setAttribute('aria-pressed', String(isLarge));
    safeStorage.set('thailandPrototypeLargeText', isLarge);
    announce(isLarge ? 'התצוגה הוגדלה' : 'התצוגה חזרה לגודל הרגיל');
  });

  /* Back to top */
  doc.querySelector('[data-back-to-top]')?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
  });
})();
