// assets/js/upsell.js
// Dynamic Quick Test page rows.
(function () {
  const container = document.getElementById('quickTestRows');
  if (!container) {
    return;
  }

  const pageLabels = [
    { value: 'Index Page', text: 'Index Page' },
    { value: 'Presell Page', text: 'Presell Page' },
    { value: 'Thank You Page', text: 'Thank You Page' },
    { value: 'Checkout Page', text: 'Checkout Page' }
  ];

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
    const errorEl = field.closest('.quick-test-row')?.querySelector('.quick-url-error') || field.nextElementSibling;
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

  function createLabelSelect() {
    const select = document.createElement('select');
    select.className = 'form-select quick-page-label';
    select.name = 'page_labels[]';
    pageLabels.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.value;
      option.textContent = item.text;
      select.appendChild(option);
    });
    return select;
  }

  function createQuickTestRow(value = '', labelValue = '') {
    const wrapper = document.createElement('div');
    wrapper.className = 'quick-test-row';

    const labelGroup = document.createElement('div');
    const labelText = document.createElement('label');
    labelText.className = 'form-label';
    labelText.textContent = 'Page Label';
    const select = createLabelSelect();
    if (labelValue) {
      select.value = labelValue;
    }
    labelGroup.append(labelText, select);

    const urlGroup = document.createElement('div');
    const urlLabel = document.createElement('label');
    urlLabel.className = 'form-label';
    urlLabel.textContent = 'Page URL';
    const input = document.createElement('input');
    input.type = 'url';
    input.className = 'form-control quick-page-url';
    input.placeholder = 'https://example.com/page';
    input.value = value;
    input.name = 'page_urls[]';
    input.required = true;
    const error = document.createElement('div');
    error.className = 'invalid-feedback quick-url-error';
    error.style.display = 'none';
    urlGroup.append(urlLabel, input, error);

    const testBtn = document.createElement('button');
    testBtn.type = 'button';
    testBtn.className = 'btn btn-primary btn-sm quick-row-test';
    testBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i>Test';

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-outline-primary btn-sm quick-add';
    addBtn.title = 'Add page';
    addBtn.setAttribute('aria-label', 'Add page');
    addBtn.innerHTML = '<i class="bi bi-plus-lg"></i>';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline-danger btn-sm quick-remove';
    removeBtn.title = 'Remove page';
    removeBtn.setAttribute('aria-label', 'Remove page');
    removeBtn.innerHTML = '<i class="bi bi-dash-lg"></i>';

    wrapper.append(labelGroup, urlGroup, testBtn, addBtn, removeBtn);

    input.addEventListener('input', function () {
      const errorMsg = validateUrlString(this.value);
      if (!errorMsg) {
        setFieldState(this, '');
      } else {
        this.classList.remove('is-valid');
      }
    });

    return wrapper;
  }

  function addRowAfter(row) {
    const newRow = createQuickTestRow();
    if (row && row.parentNode) {
      row.parentNode.insertBefore(newRow, row.nextSibling);
    } else {
      container.appendChild(newRow);
    }
    newRow.querySelector('.quick-page-url')?.focus();
  }

  function removeRow(row) {
    if (container.querySelectorAll('.quick-test-row').length <= 1) return;
    row.remove();
  }

  function getPages() {
    return Array.from(container.querySelectorAll('.quick-test-row'))
      .map((row) => {
        const label = row.querySelector('.quick-page-label')?.value?.trim() || 'Quick Test Page';
        const input = row.querySelector('.quick-page-url');
        return { row, input, label, url: input?.value?.trim() || '' };
      })
      .filter((page) => page.url !== '');
  }

  function getPageFromRow(row) {
    if (!row) {
      return null;
    }

    const label = row.querySelector('.quick-page-label')?.value?.trim() || 'Quick Test Page';
    const input = row.querySelector('.quick-page-url');
    return { row, input, label, url: input?.value?.trim() || '' };
  }

  function validateRow(row) {
    const page = getPageFromRow(row);
    if (!page?.input) {
      return false;
    }

    return setFieldState(page.input, validateUrlString(page.input.value));
  }

  function validateRows() {
    const rows = Array.from(container.querySelectorAll('.quick-test-row'));
    let valid = true;
    let filled = 0;

    rows.forEach((row) => {
      const input = row.querySelector('.quick-page-url');
      if (!input) {
        return;
      }

      if (!input.value.trim()) {
        input.classList.remove('is-valid', 'is-invalid');
        const errorEl = row.querySelector('.quick-url-error');
        if (errorEl) {
          errorEl.textContent = '';
          errorEl.style.display = 'none';
        }
        return;
      }

      filled += 1;
      valid = setFieldState(input, validateUrlString(input.value)) && valid;
    });

    if (filled === 0) {
      const firstInput = rows[0]?.querySelector('.quick-page-url');
      if (firstInput) {
        valid = setFieldState(firstInput, 'Enter at least one page URL.') && valid;
      }
    }

    return valid && filled > 0;
  }

  container.addEventListener('click', (event) => {
    const addButton = event.target.closest('.quick-add');
    if (addButton) {
      addRowAfter(addButton.closest('.quick-test-row'));
      return;
    }

    const removeButton = event.target.closest('.quick-remove');
    if (removeButton) {
      removeRow(removeButton.closest('.quick-test-row'));
    }
  });

  window.quickTestValidation = {
    validateUrlString,
    setFieldState,
    validateRow,
    getPageFromRow,
    validateRows,
    getPages
  };

  if (container.children.length === 0) {
    container.appendChild(createQuickTestRow());
  }
})();
