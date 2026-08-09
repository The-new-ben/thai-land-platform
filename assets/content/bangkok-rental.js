(() => {
  'use strict';

  const explorer = document.querySelector('[data-thp-bkk-explorer]');
  if (!explorer) return;

  const cards = Array.from(explorer.querySelectorAll('[data-thp-bkk-area]'));
  const markers = Array.from(explorer.querySelectorAll('[data-thp-bkk-marker]'));
  const controls = explorer.querySelector('[data-thp-bkk-controls]');
  const resultBar = explorer.querySelector('[data-thp-bkk-result-bar]');
  const calculator = explorer.querySelector('[data-thp-bkk-calculator]');
  const budget = explorer.querySelector('[data-thp-bkk-budget]');
  const bedroom = explorer.querySelector('[data-thp-bkk-bedroom]');
  const lifestyle = explorer.querySelector('[data-thp-bkk-lifestyle]');
  const rail = explorer.querySelector('[data-thp-bkk-rail]');
  const budgetOutput = explorer.querySelector('[data-thp-bkk-budget-output]');
  const resultOutput = explorer.querySelector('[data-thp-bkk-results]');
  const status = explorer.querySelector('[data-thp-bkk-status]');
  const reset = explorer.querySelector('[data-thp-bkk-reset]');
  const formatter = new Intl.NumberFormat('he-IL');
  let selectedArea = '';

  const tokens = (value) => String(value || '').split('|').filter(Boolean);
  const bahtText = (value) => `${formatter.format(value)} באט`;
  const prefersReducedMotion = () => (
    typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );

  const setSelected = (areaId, { moveFocus = false, scroll = false } = {}) => {
    selectedArea = areaId;
    cards.forEach((card) => card.classList.toggle('is-selected', card.dataset.areaId === areaId));
    markers.forEach((marker) => {
      const selected = marker.dataset.areaId === areaId;
      marker.classList.toggle('is-selected', selected);
      marker.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });

    const card = cards.find((item) => item.dataset.areaId === areaId && !item.hidden);
    if (!card) return;
    if (scroll) card.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
    if (moveFocus) card.querySelector('summary, a, button')?.focus({ preventScroll: true });
  };

  const update = () => {
    const roomKey = bedroom?.value === 'two' ? 'Two' : 'One';
    const maximum = Number(budget?.value || 100000);
    const lifeValue = lifestyle?.value || 'all';
    const railValue = rail?.value || 'all';
    const visibleIds = [];

    cards.forEach((card) => {
      const minimum = Number(card.dataset[`min${roomKey}`] || 0);
      const matchesBudget = minimum <= maximum;
      const matchesLife = lifeValue === 'all' || tokens(card.dataset.lifestyle).includes(lifeValue);
      const matchesRail = railValue === 'all' || tokens(card.dataset.rail).includes(railValue);
      const visible = matchesBudget && matchesLife && matchesRail;
      card.hidden = !visible;
      if (visible) visibleIds.push(card.dataset.areaId);
    });

    markers.forEach((marker) => {
      marker.hidden = !visibleIds.includes(marker.dataset.areaId);
    });

    const formattedBudget = bahtText(maximum);
    if (budgetOutput) budgetOutput.textContent = formattedBudget;
    budget?.setAttribute('aria-valuetext', formattedBudget);
    if (resultOutput) resultOutput.textContent = `${visibleIds.length} אזורים מתאימים`;
    if (status) status.textContent = visibleIds.length
      ? `מוצגים ${visibleIds.length} אזורים לפי הבחירה שלכם`
      : 'לא נמצא אזור בטווח הזה. נסו להגדיל את התקציב או לנקות מסנן.';

    if (!visibleIds.includes(selectedArea)) setSelected(visibleIds[0] || '');
  };

  budget?.addEventListener('input', update);
  [bedroom, lifestyle, rail].forEach((control) => control?.addEventListener('change', update));

  reset?.addEventListener('click', () => {
    if (budget) budget.value = budget.dataset.defaultValue || '50000';
    if (bedroom) bedroom.value = 'one';
    if (lifestyle) lifestyle.value = 'all';
    if (rail) rail.value = 'all';
    update();
    budget?.focus();
  });

  markers.forEach((marker) => {
    marker.addEventListener('click', () => setSelected(marker.dataset.areaId, { moveFocus: true, scroll: true }));
  });

  cards.forEach((card) => {
    card.addEventListener('mouseenter', () => setSelected(card.dataset.areaId));
    card.addEventListener('focusin', () => setSelected(card.dataset.areaId));
  });

  const rent = explorer.querySelector('[data-thp-bkk-cost-rent]');
  const rentOutput = explorer.querySelector('[data-thp-bkk-cost-rent-output]');
  const depositOutput = explorer.querySelector('[data-thp-bkk-cost-deposit]');
  const entryOutput = explorer.querySelector('[data-thp-bkk-cost-entry]');
  const updateCost = () => {
    const monthlyRent = Number(rent?.value || 0);
    const formattedRent = bahtText(monthlyRent);
    if (rentOutput) rentOutput.textContent = formattedRent;
    rent?.setAttribute('aria-valuetext', formattedRent);
    if (depositOutput) depositOutput.textContent = bahtText(monthlyRent * 2);
    if (entryOutput) entryOutput.textContent = bahtText(monthlyRent * 3);
  };
  rent?.addEventListener('input', updateCost);

  update();
  updateCost();
  markers.forEach((marker) => { marker.disabled = false; });
  controls?.removeAttribute('hidden');
  resultBar?.removeAttribute('hidden');
  calculator?.removeAttribute('hidden');
  explorer.classList.add('is-interactive');
})();
