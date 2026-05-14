document.addEventListener('DOMContentLoaded', function () {
    const reportPanelId = 'reportStatusPanel';
    const resultsContainer = document.createElement('div');
    resultsContainer.id = reportPanelId;

    const container = document.querySelector('.content .container-fluid');
    if (container) {
        container.insertAdjacentElement('afterbegin', resultsContainer);
    }

    function setStatus(message, type) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + (type || 'info');
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        resultsContainer.replaceChildren(alert);
    }

    function clearStatus() {
        resultsContainer.replaceChildren();
    }

    function createLinkButton(url, label, download) {
        const btn = document.createElement('a');
        btn.href = url;
        btn.className = 'btn btn-sm btn-outline-secondary me-2';
        btn.textContent = label;
        if (download) {
            btn.setAttribute('download', 'test_report.pdf');
        }
        btn.target = '_blank';
        return btn;
    }

    function validateInput(input) {
        if (!input) {
            return false;
        }

        const validation = window.quickTestValidation || {};
        const value = input.value.trim();
        const error = typeof validation.validateUrlString === 'function'
            ? validation.validateUrlString(value)
            : (!/^https?:\/\/\S+$/i.test(value) ? 'Please enter a valid URL.' : '');

        if (typeof validation.setFieldState === 'function') {
            return validation.setFieldState(input, error);
        }

        if (error) {
            input.classList.add('is-invalid');
            return false;
        }

        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        return true;
    }

    function setButtonBusy(button, isBusy) {
        if (!button) {
            return;
        }
        button.disabled = isBusy;
        if (isBusy) {
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Testing';
            return;
        }
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }

    function getMainButtonContext(button) {
        const targetId = button.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : null;
        const label = button.dataset.pageLabel
            || input?.closest('.mb-4')?.querySelector('label')?.textContent?.trim()
            || 'Quick Test Page';

        return {
            button,
            input,
            label
        };
    }

    function getUpsellButtonContext(button) {
        const row = button.closest('.upsell-row');
        const input = row ? row.querySelector('.upsell-url') : null;
        const label = row?.querySelector('.upsell-label')?.textContent?.trim() || 'Upsell Page';

        return {
            button,
            input,
            label
        };
    }

    async function runTest(context) {
        const input = context.input;
        if (!validateInput(input)) {
            setStatus('Enter a valid URL before running this test.', 'danger');
            return;
        }

        const url = input.value.trim();
        clearStatus();
        setButtonBusy(context.button, true);

        try {
            setStatus('Testing ' + context.label + ' for broken links and SEO issues. This can take up to 30 seconds.', 'warning');

            const response = await fetch('../actions/run_test_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    url: url,
                    label: context.label,
                    pages: [
                        {
                            label: context.label,
                            url: url
                        }
                    ]
                })
            });

            if (!response.ok) {
                const text = await response.text();
                setStatus('Server error: ' + text, 'danger');
                return;
            }

            const data = await response.json();
            if (!data.success) {
                setStatus(data.error || 'Test report failed.', 'danger');
                return;
            }

            const issueSummary = [
                'Broken link and SEO checks completed for ' + context.label + '.',
                'Total issues: ' + (data.issues_total ?? 0),
                'SEO: ' + (data.seo_issues ?? 0),
                'Functional: ' + (data.functional_issues ?? 0),
                'SSL: ' + (data.ssl_issues ?? 0)
            ].join(' ');

            const reportLinks = document.createElement('div');
            reportLinks.className = 'mt-2';
            reportLinks.appendChild(createLinkButton(data.view_url, 'View Report', false));
            reportLinks.appendChild(createLinkButton(data.download_url, 'Download PDF', true));

            resultsContainer.replaceChildren();
            setStatus(issueSummary, 'success');
            resultsContainer.appendChild(reportLinks);

        } catch (err) {
            setStatus('Network error: ' + err.message, 'danger');
        } finally {
            setButtonBusy(context.button, false);
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.test-btn, .upsell-run');
        if (!button) {
            return;
        }

        const context = button.classList.contains('upsell-run')
            ? getUpsellButtonContext(button)
            : getMainButtonContext(button);

        if (!context.input) {
            setStatus('Unable to find the selected URL field.', 'danger');
            return;
        }

        runTest(context);
    });
});
