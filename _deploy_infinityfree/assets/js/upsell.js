// assets/js/upsell.js
(function() {
  const container = document.getElementById('upsellContainer');

  const urlPattern = /^https?:\/\/\S+$/i;

  function isValidUrl(value) {
    if (!value || !value.trim()) return false;
    const normalized = value.trim();
    if (!urlPattern.test(normalized)) return false;
    try {
      const parsed = new URL(normalized);
      return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch (e) {
      return false;
    }
  }

  function validateUrlString(value) {
    if (!value || !value.trim()) {
      return 'This URL cannot be empty.';
    }
    if (!/^https?:\/\//i.test(value.trim())) {
      return 'URL must start with http:// or https://';
    }
    if (!isValidUrl(value)) {
      return 'Please enter a valid URL.';
    }
    return '';
  }

  function setFieldState(field, message) {
    const errorEl = field.classList.contains('upsell-url')
      ? field.closest('.upsell-row')?.querySelector('.upsell-error')
      : field.nextElementSibling;
    if (message) {
      field.classList.add('is-invalid');
      field.classList.remove('is-valid');
      if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
      }
      return false;
    }
    field.classList.add('is-valid');
    field.classList.remove('is-invalid');
    if (errorEl) {
      errorEl.textContent = '';
      errorEl.style.display = 'none';
    }
    return true;
  }

  function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return false;
    const error = validateUrlString(field.value);
    return setFieldState(field, error);
  }

  function createUpsellRow(value = '') {
    const wrapper = document.createElement('div');
    wrapper.className = 'upsell-row d-flex flex-wrap align-items-center gap-2 mb-2';

    const label = document.createElement('span');
    label.className = 'upsell-label fw-semibold';
    label.textContent = 'Upsell';
    label.style.minWidth = '90px';

    const input = document.createElement('input');
    input.type = 'url';
    input.className = 'form-control flex-grow-1 upsell-url';
    input.placeholder = 'https://example.com/upsell';
    input.value = value;
    input.name = 'upsell_urls[]';

    const runBtn = document.createElement('button');
    runBtn.type = 'button';
    runBtn.className = 'btn btn-sm btn-secondary upsell-run';
    runBtn.textContent = 'Run Test';

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-outline-primary btn-sm upsell-add';
    addBtn.textContent = '+';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline-danger btn-sm upsell-remove';
    removeBtn.textContent = '-';

    const error = document.createElement('div');
    error.className = 'invalid-feedback upsell-error';
    error.style.display = 'none';

    wrapper.append(label, input, runBtn, addBtn, removeBtn, error);

    input.addEventListener('input', function() {
      const errorMsg = validateUrlString(this.value);
      if (!errorMsg) {
        setFieldState(this, '');
      } else {
        this.classList.remove('is-valid');
      }
    });

    return wrapper;
  }

  function refreshUpsellLabels() {
    container.querySelectorAll('.upsell-row').forEach((row, idx) => {
      const label = row.querySelector('.upsell-label');
      if (label) label.textContent = 'Upsell ' + (idx + 1);
    });
  }

  function addRowAfter(row) {
    const newRow = createUpsellRow();
    if (row && row.parentNode) {
      row.parentNode.insertBefore(newRow, row.nextSibling);
    } else {
      container.appendChild(newRow);
    }
    refreshUpsellLabels();
    newRow.querySelector('input')?.focus();
  }

  function removeRow(row) {
    if (container.querySelectorAll('.upsell-row').length <= 1) return;
    row.remove();
    refreshUpsellLabels();
  }

  container.addEventListener('click', (event) => {
    const target = event.target;
    if (target.classList.contains('upsell-add')) {
      const row = target.closest('.upsell-row');
      addRowAfter(row);
      return;
    }
    if (target.classList.contains('upsell-remove')) {
      const row = target.closest('.upsell-row');
      removeRow(row);
      return;
    }
  });

  window.quickTestValidation = {
    validateField,
    validateUrlString,
    setFieldState
  };

  if (container.children.length === 0) {
    container.appendChild(createUpsellRow());
  }
  refreshUpsellLabels();
})();
