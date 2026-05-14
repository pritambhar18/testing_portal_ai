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

    function setRowControlsDisabled(row, disabled) {
        if (!row) {
            return;
        }
        row.querySelectorAll('select, input, .quick-add, .quick-remove').forEach((control) => {
            control.disabled = disabled;
        });
    }

    async function runTest(button) {
        const validation = window.quickTestValidation || {};
        const row = button.closest('.quick-test-row');
        const isValid = typeof validation.validateRow === 'function'
            ? validation.validateRow(row)
            : false;

        if (!isValid) {
            setStatus('Enter a valid URL before running this test.', 'danger');
            return;
        }

        const page = typeof validation.getPageFromRow === 'function'
            ? validation.getPageFromRow(row)
            : null;

        if (!page || !page.url) {
            setStatus('Enter a valid URL before running this test.', 'danger');
            return;
        }

        const pages = [{ label: page.label, url: page.url }];
        clearStatus();
        setButtonBusy(button, true);
        setRowControlsDisabled(row, true);

        try {
            setStatus('Running form validation scenarios for ' + page.label + ': form discovery, blank validation, phone, ZIP, and card number checks with screenshots.', 'warning');

            const response = await fetch('../actions/run_test_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    url: page.url,
                    label: page.label,
                    pages: pages
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

            if (Array.isArray(data.automation_warnings) && data.automation_warnings.length > 0) {
                setStatus('Report generated, but form automation did not fully run: ' + data.automation_warnings.join(' '), 'warning');
            }

            const issueSummary = [
                'Quick Test completed for ' + page.label + '.',
                'Total issues: ' + (data.issues_total ?? 0),
                'SEO: ' + (data.seo_issues ?? 0),
                'Functional: ' + (data.functional_issues ?? 0),
                'SSL: ' + (data.ssl_issues ?? 0),
                'Automation checks: ' + (data.automation_checks_count ?? 0)
            ].join(' ');

            const reportLinks = document.createElement('div');
            reportLinks.className = 'mt-2';
            if (data.view_url) {
                reportLinks.appendChild(createLinkButton(data.view_url, 'View Report', false));
            }
            if (data.download_url) {
                reportLinks.appendChild(createLinkButton(data.download_url, 'Download PDF', true));
            }
            if (!data.download_url) {
                const note = document.createElement('div');
                note.className = 'small text-muted mt-2';
                note.textContent = 'PDF download is unavailable on this server; use View Report to inspect the HTML report.';
                reportLinks.appendChild(note);
            }

            resultsContainer.replaceChildren();
            setStatus(issueSummary, 'success');
            resultsContainer.appendChild(reportLinks);

        } catch (err) {
            setStatus('Network error: ' + err.message, 'danger');
        } finally {
            setButtonBusy(button, false);
            setRowControlsDisabled(row, false);
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.quick-row-test');
        if (!button) {
            return;
        }

        event.preventDefault();
        runTest(button);
    });
});
