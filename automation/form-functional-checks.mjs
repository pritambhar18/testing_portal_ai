import fs from 'fs';
import path from 'path';
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DEFAULT_CONFIG_PATH = path.resolve(__dirname, 'form-functional-checks.config.json');
const DEFAULT_REPORT_PATH = path.resolve(__dirname, 'results', 'form-functional-checks-report.json');

function resolveInputPath(argPath, fallback) {
    if (!argPath) {
        return fallback;
    }
    return path.isAbsolute(argPath) ? argPath : path.resolve(__dirname, argPath);
}

function loadConfig(argPath) {
    const configPath = resolveInputPath(argPath, DEFAULT_CONFIG_PATH);
    if (!fs.existsSync(configPath)) {
        throw new Error(`Configuration not found at ${configPath}`);
    }
    const payload = fs.readFileSync(configPath, 'utf-8');
    return JSON.parse(payload);
}

function logEntry(results, label, status, detail, screenshotPath = '') {
    const entry = { label, status, detail, time: new Date().toISOString() };
    if (screenshotPath) {
        entry.screenshot_path = screenshotPath;
    }
    results.push(entry);
    console.log(`[${status}] ${label} - ${detail}`);
}

function safeFilePart(value) {
    return String(value || 'check')
        .replace(/[^a-z0-9_-]+/gi, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90) || 'check';
}

function createScreenshotPath(reportPath, label) {
    const screenshotDir = path.join(path.dirname(reportPath), 'screenshots');
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }
    return path.join(screenshotDir, `${Date.now()}_${safeFilePart(label)}.png`);
}

async function captureCheckScreenshot(page, reportPath, label) {
    if (!reportPath) {
        return '';
    }
    const screenshotPath = createScreenshotPath(reportPath, label);
    try {
        await page.screenshot({
            path: screenshotPath,
            fullPage: true,
            animations: 'disabled',
            caret: 'hide'
        });
        return screenshotPath;
    } catch (error) {
        return '';
    }
}

async function detectProspectForm(page) {
    const forms = await page.$$('form');
    if (forms.length === 0) {
        return {
            found: false,
            count: 0,
        };
    }

    const keywords = ['prospect', 'lead', 'contact', 'inquiry', 'quote', 'demo'];
    for (const form of forms) {
        const text = (await form.innerText()).toLowerCase();
        if (keywords.some((word) => text.includes(word))) {
            return {
                found: true,
                count: forms.length,
            };
        }
    }

    return {
        found: false,
        count: forms.length,
    };
}

async function fillField(page, field, value) {
    if (!field?.selector || value === undefined) {
        return false;
    }

    const locator = page.locator(field.selector).first();
    if ((await locator.count()) === 0) {
        return false;
    }

    switch (field.type) {
        case 'select':
            await locator.selectOption(String(value));
            break;
        case 'checkbox':
            if (Boolean(value)) {
                await locator.check();
            } else {
                await locator.uncheck();
            }
            break;
        case 'radio':
            await locator.check();
            break;
        default:
            await locator.fill(String(value));
    }
    return true;
}

async function fillForm(page, form, overrides = {}) {
    for (const field of form.fields ?? []) {
        const overrideValue = Object.prototype.hasOwnProperty.call(overrides, field.name)
            ? overrides[field.name]
            : field.validValue;

        if (overrideValue === undefined) {
            continue;
        }

        await fillField(page, field, overrideValue);
    }
}

async function submitForm(page, form) {
    const navigationPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 4000 }).catch(() => null);
    if (form.submitSelector) {
        await page.click(form.submitSelector, { timeout: form.submitTimeout ?? 5000 });
    } else if (form.formSelector) {
        await page.locator(form.formSelector).evaluate((frm) => frm.requestSubmit());
    } else {
        return;
    }
    const navigation = await navigationPromise;
    if (navigation) {
        await page.waitForTimeout(300);
        await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => null);
    }
}

async function readFieldValidation(page, field) {
    const locator = page.locator(field.selector).first();
    let errorText = '';
    let ariaInvalid = '';

    if (field.errorSelector) {
        const errorLoc = page.locator(field.errorSelector).first();
        if ((await errorLoc.count()) > 0) {
            errorText = (await errorLoc.innerText()).trim();
        }
    }

    if ((await locator.count()) > 0) {
        ariaInvalid = await locator.getAttribute('aria-invalid');
    }

    return {
        errorText,
        ariaInvalid: ariaInvalid || '',
    };
}

function generateFieldTestCases(field) {
    const tests = [];

    if (field.required) {
        tests.push({
            label: 'Mandatory field enforced',
            value: '',
            expectError: true,
            errorKey: 'required',
        });
    } else {
        tests.push({
            label: 'Optional field stays empty',
            value: '',
            expectError: false,
            errorKey: 'optional',
        });
    }

    if (field.maxLength) {
        const invalidLength = 'X'.repeat(field.maxLength + 5);
        tests.push({
            label: 'Max length validation',
            value: invalidLength,
            expectError: true,
            errorKey: 'maxLength',
        });
    }

    if (field.type === 'email') {
        tests.push({
            label: 'Email pattern validation',
            value: 'not-an-email',
            expectError: true,
            errorKey: 'type',
        });
    }

    if (field.type === 'phone') {
        tests.push({
            label: 'Phone field accepts numeric',
            value: field.validValue ?? '1234567890',
            expectError: false,
            errorKey: 'type',
        });
    }

    tests.push({
        label: 'Valid entry',
        value: field.validValue ?? '',
        expectError: false,
        errorKey: 'valid',
    });

    return tests;
}

async function evaluateFieldTestCase(page, formUrl, form, field, testCase) {
    await page.goto(formUrl, { waitUntil: 'domcontentloaded' });
    const overrides = {
        [field.name]: testCase.value,
    };
    await fillForm(page, form, overrides);
    await submitForm(page, form);
    await page.waitForTimeout(testCase.waitAfterSubmit ?? 600);

    const { errorText, ariaInvalid } = await readFieldValidation(page, field);
    const hasValidation = (errorText && errorText.length > 0) || ariaInvalid === 'true' || ariaInvalid === '1';
    const expectationMet = testCase.expectError ? hasValidation : !hasValidation;

    const detailParts = [];
    if (errorText) detailParts.push(`error="${errorText}"`);
    if (ariaInvalid) detailParts.push(`aria-invalid=${ariaInvalid}`);
    detailParts.push(`value="${testCase.value}"`);

    return {
        status: expectationMet ? 'PASS' : 'FAIL',
        detail: detailParts.join(', '),
        label: `${field.name} > ${testCase.label}`,
        errorObserved: errorText,
    };
}

async function runFormValidation(page, baseUrl, form, results) {
    const formUrl = new URL(form.path ?? '', baseUrl).href;
    for (const field of form.fields ?? []) {
        const testCases = generateFieldTestCases(field);
        for (const testCase of testCases) {
            const outcome = await evaluateFieldTestCase(page, formUrl, form, field, testCase);
            logEntry(results, `Form "${form.name}" - ${outcome.label}`, outcome.status, outcome.detail);
        }
    }
}

async function fillExtras(page, entries = []) {
    for (const entry of entries) {
        const locator = page.locator(entry.selector).first();
        if ((await locator.count()) === 0 || entry.value === undefined) {
            continue;
        }
        await locator.fill(String(entry.value));
    }
}

async function runGeoChecks(page, baseUrl, geo, results) {
    if (!geo) return;

    const geoUrl = new URL(geo.path ?? '', baseUrl).href;
    await page.goto(geoUrl, { waitUntil: 'domcontentloaded' });
    await fillExtras(page, geo.requiredFields ?? []);

    for (const entry of geo.zipMapping ?? []) {
        await fillExtras(page, geo.requiredFields ?? []);
        if (geo.countrySelector) {
            await page.selectOption(geo.countrySelector, entry.countryValue ?? entry.country);
        }
        if (geo.zipSelector) {
            await page.fill(geo.zipSelector, entry.zip);
        }
        await page.waitForTimeout(400);

        const stateValue = geo.stateDisplaySelector
            ? await page.locator(geo.stateDisplaySelector).inputValue()
            : geo.stateSelector && (await page.locator(geo.stateSelector).inputValue());
        const cityValue = geo.citySelector && (await page.locator(geo.citySelector).inputValue());

        const statePass = entry.expectedState ? stateValue && stateValue.includes(entry.expectedState) : Boolean(stateValue);
        const cityPass = entry.expectedCity ? cityValue && cityValue.includes(entry.expectedCity) : true;

        const status = statePass && cityPass ? 'PASS' : 'FAIL';
        const detail = `country=${entry.country}, zip=${entry.zip}, state="${stateValue}", city="${cityValue}"`;
        logEntry(results, `Geo lookup for ${entry.country}`, status, detail);
    }

    if (geo.statelessCountry) {
        await page.goto(geoUrl, { waitUntil: 'domcontentloaded' });
        await fillExtras(page, geo.requiredFields ?? []);
        if (geo.countrySelector) {
            await page.selectOption(geo.countrySelector, geo.statelessCountry);
        }
        if (geo.stateSelector) {
            await page.fill(geo.stateSelector, '');
        }
        await submitForm(page, geo);
        await page.waitForTimeout(800);

        const success = geo.successIndicatorSelector
            ? (await page.locator(geo.successIndicatorSelector).count()) > 0
            : page.url().includes(geo.successUrlFragment ?? 'thank-you');
        logEntry(
            results,
            `Stateless country order (${geo.statelessCountry})`,
            success ? 'PASS' : 'FAIL',
            success ? 'state blank allowed' : 'state required or order blocked'
        );
    }
}

async function runMaskingChecks(page, baseUrl, masking, results) {
    if (!masking) return;

    const maskUrl = new URL(masking.path ?? '', baseUrl).href;
    await page.goto(maskUrl, { waitUntil: 'domcontentloaded' });
    await fillExtras(page, masking.requiredFields ?? []);

    if (masking.phoneField) {
        const phoneLocator = page.locator(masking.phoneField.selector).first();
        if ((await phoneLocator.count()) > 0) {
            await phoneLocator.fill(masking.phoneField.valid || '1234567890');
            const maskedValue = await phoneLocator.inputValue();
            const maskDetected = maskedValue && maskedValue !== '1234567890';
            logEntry(
                results,
                'Phone input mask detection',
                maskDetected ? 'PASS' : 'FAIL',
                `masked="${maskedValue}"`
            );

            await phoneLocator.fill(masking.phoneField.invalid || '12345');
            await page.waitForTimeout(400);
            const { errorText, ariaInvalid } = await readFieldValidation(page, { selector: masking.phoneField.selector });
            const hasError = Boolean(errorText) || ariaInvalid === 'true';
            logEntry(
                results,
                'Phone validation for short value',
                hasError ? 'PASS' : 'FAIL',
                `error="${errorText}"`
            );

            if (masking.phoneField.beforeSelector) {
                await page.locator(masking.phoneField.beforeSelector).click();
                await page.press('Tab');
                const valueAfterTab = await phoneLocator.inputValue();
                logEntry(
                    results,
                    'Phone mask persistence after focus shift',
                    valueAfterTab === maskedValue ? 'PASS' : 'FAIL',
                    `afterTab="${valueAfterTab}"`
                );
            }
        }
    }

    if (masking.canadaZipField && masking.canadaZipField.selector) {
        if (masking.countrySelector && masking.canadaZipField.countryValue) {
            await page.selectOption(masking.countrySelector, masking.canadaZipField.countryValue);
        }
        const zipLocator = page.locator(masking.canadaZipField.selector).first();
        if ((await zipLocator.count()) > 0) {
            await zipLocator.fill(masking.canadaZipField.valid || 'A1A 1A1');
            const zipValue = await zipLocator.inputValue();
            const maskMatch = /[A-Z]\d[A-Z]\s?\d[A-Z]\d/i.test(zipValue);
            logEntry(
                results,
                'Canada zip mask validation',
                maskMatch ? 'PASS' : 'FAIL',
                `zip="${zipValue}"`
            );
        }
    }
}

async function isVisible(locator) {
    try {
        return await locator.isVisible({ timeout: 250 });
    } catch (error) {
        return false;
    }
}

function fieldDescriptorFromAttrs(attrs) {
    return [
        attrs.name,
        attrs.id,
        attrs.type,
        attrs.placeholder,
        attrs.autocomplete,
        attrs.label
    ].filter(Boolean).join(' ').toLowerCase();
}

function classifyDynamicField(attrs) {
    const descriptor = fieldDescriptorFromAttrs(attrs);
    const type = String(attrs.type || '').toLowerCase();

    if (/cvv|cvc|security code|card code/.test(descriptor)) {
        return 'cvv';
    }
    if (/card|ccnum|cc-number|credit|debit|accountnumber/.test(descriptor)) {
        return 'card';
    }
    if (/phone|mobile|tel/.test(descriptor) || type === 'tel') {
        return 'phone';
    }
    if (/billing/.test(descriptor) && /zip|postal|postcode|post code/.test(descriptor)) {
        return 'billing_zip';
    }
    if (/zip|postal|postcode|post code/.test(descriptor)) {
        return 'zip';
    }
    if (type === 'email') {
        return 'email';
    }
    return 'generic';
}

async function installSubmitGuard(page) {
    await page.evaluate(() => {
        if (window.__quickTestSubmitGuardInstalled) {
            return;
        }
        document.addEventListener('submit', (event) => {
            event.preventDefault();
        });
        window.__quickTestSubmitGuardInstalled = true;
    }).catch(() => null);
}

async function getFieldAttributes(fieldHandle, index) {
    return await fieldHandle.evaluate((element, fieldIndex) => {
        const id = element.getAttribute('id') || '';
        let label = '';
        if (id) {
            const explicitLabel = document.querySelector(`label[for="${CSS.escape(id)}"]`);
            label = explicitLabel ? explicitLabel.textContent.trim() : '';
        }
        if (!label) {
            const wrappingLabel = element.closest('label');
            label = wrappingLabel ? wrappingLabel.textContent.trim() : '';
        }

        return {
            index: fieldIndex,
            tag: element.tagName.toLowerCase(),
            type: (element.getAttribute('type') || element.type || '').toLowerCase(),
            name: element.getAttribute('name') || '',
            id,
            placeholder: element.getAttribute('placeholder') || '',
            autocomplete: element.getAttribute('autocomplete') || '',
            label,
            required: element.required || element.hasAttribute('required'),
            checked: element.checked || false,
            disabled: element.disabled || element.hasAttribute('disabled'),
            readonly: element.readOnly || element.hasAttribute('readonly'),
            selector: element.getAttribute('name')
                ? `${element.tagName.toLowerCase()}[name="${CSS.escape(element.getAttribute('name'))}"]`
                : id
                    ? `#${CSS.escape(id)}`
                    : `${element.tagName.toLowerCase()}:nth-of-type(${fieldIndex + 1})`
        };
    }, index);
}

function isTestableDynamicField(attrs) {
    if (attrs.disabled || attrs.readonly) {
        return false;
    }

    const ignoredTypes = new Set(['hidden', 'submit', 'button', 'reset', 'image', 'file']);
    return !ignoredTypes.has(String(attrs.type || '').toLowerCase());
}

async function readDynamicValidation(fieldLocator) {
    return await fieldLocator.evaluate((element) => {
        const describedBy = (element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        const messages = [];
        for (const id of describedBy) {
            const described = document.getElementById(id);
            if (described && described.textContent.trim()) {
                messages.push(described.textContent.trim());
            }
        }

        let parent = element.parentElement;
        for (let depth = 0; parent && depth < 3; depth += 1) {
            const candidates = parent.querySelectorAll('.invalid-feedback,.error,.field-error,[role="alert"],.help-block');
            for (const candidate of candidates) {
                const text = candidate.textContent.trim();
                if (text) {
                    messages.push(text);
                }
            }
            parent = parent.parentElement;
        }

        return {
            value: 'value' in element ? element.value : '',
            valid: typeof element.checkValidity === 'function' ? element.checkValidity() : true,
            validationMessage: element.validationMessage || '',
            ariaInvalid: element.getAttribute('aria-invalid') || '',
            className: element.className || '',
            messages: Array.from(new Set(messages))
        };
    });
}

async function triggerBlankValidation(page, formLocator) {
    await formLocator.evaluate((form) => {
        for (const element of form.querySelectorAll('input, textarea, select')) {
            const type = (element.type || '').toLowerCase();
            if (['checkbox', 'radio'].includes(type)) {
                element.checked = false;
                element.dispatchEvent(new Event('change', { bubbles: true }));
                continue;
            }
            if ('value' in element && !['hidden', 'button', 'submit', 'reset', 'file'].includes(type)) {
                element.value = '';
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    }).catch(() => null);
    await page.waitForTimeout(350);
}

async function runDynamicBlankChecks(page, formLocator, formIndex, fields, results, reportPath) {
    await triggerBlankValidation(page, formLocator);

    for (const field of fields) {
        const locator = formLocator.locator(field.selector).first();
        if ((await locator.count()) === 0 || !(await isVisible(locator))) {
            continue;
        }

        const validation = await readDynamicValidation(locator);
        const hasBlankValidation = field.required
            || validation.validationMessage
            || validation.ariaInvalid === 'true'
            || /\bis-invalid\b|error|invalid/i.test(validation.className)
            || validation.messages.length > 0
            || validation.valid === false;

        const fieldName = field.label || field.name || field.id || `${field.tag} ${field.index + 1}`;
        const kind = classifyDynamicField(field);
        const screenshotPath = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_blank_${fieldName}`);
        logEntry(
            results,
            kind === 'card' || kind === 'cvv'
                ? `Form ${formIndex + 1} - ${kind === 'cvv' ? 'CVV' : 'Card number'} blank validation after submit for ${fieldName}`
                : `Form ${formIndex + 1} - Blank validation for ${fieldName}`,
            hasBlankValidation ? 'PASS' : 'FAIL',
            hasBlankValidation
                ? `blank value blocked${validation.validationMessage ? `, message="${validation.validationMessage}"` : ''}`
                : 'blank value has no visible/native validation',
            screenshotPath
        );
    }
}

async function testInputValueRule(locator, value) {
    try {
        await locator.fill('');
        await locator.fill(value);
    } catch (error) {
        return {
            accepted: false,
            value: '',
            detail: `browser rejected value: ${error.message.split('\n')[0]}`
        };
    }

    const acceptedValue = await locator.inputValue().catch(() => '');
    return {
        accepted: acceptedValue.length > 0,
        value: acceptedValue,
        detail: `value="${acceptedValue}"`
    };
}

async function runDynamicFieldRuleChecks(page, formLocator, formIndex, fields, results, reportPath) {
    for (const field of fields) {
        const kind = classifyDynamicField(field);
        if (!['phone', 'zip', 'billing_zip', 'card', 'cvv'].includes(kind)) {
            continue;
        }

        const locator = formLocator.locator(field.selector).first();
        if ((await locator.count()) === 0 || !(await isVisible(locator))) {
            continue;
        }

        const fieldName = field.label || field.name || field.id || `${field.tag} ${field.index + 1}`;

        if (kind === 'phone') {
            const characterTest = await testInputValueRule(locator, 'abcXYZ');
            const characterAccepted = /[a-z]/i.test(characterTest.value);
            const characterScreenshot = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_phone_character_${fieldName}`);
            logEntry(
                results,
                `Form ${formIndex + 1} - Phone rejects characters for ${fieldName}`,
                characterAccepted ? 'FAIL' : 'PASS',
                characterTest.detail,
                characterScreenshot
            );

            const longPhoneTest = await testInputValueRule(locator, '12345678901');
            const digits = longPhoneTest.value.replace(/\D/g, '');
            const tooLongAccepted = digits.length > 10;
            const longPhoneScreenshot = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_phone_length_${fieldName}`);
            logEntry(
                results,
                `Form ${formIndex + 1} - Phone max 10 digits for ${fieldName}`,
                tooLongAccepted ? 'FAIL' : 'PASS',
                `${longPhoneTest.detail}, digits=${digits.length}`,
                longPhoneScreenshot
            );
        }

        if (kind === 'zip' || kind === 'billing_zip') {
            const zipTest = await testInputValueRule(locator, '123456');
            const digits = zipTest.value.replace(/\D/g, '');
            const tooLongAccepted = digits.length > 5;
            const zipScreenshot = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_zip_length_${fieldName}`);
            logEntry(
                results,
                `Form ${formIndex + 1} - ${kind === 'billing_zip' ? 'Billing ZIP' : 'ZIP'} max 5 digits for ${fieldName}`,
                tooLongAccepted ? 'FAIL' : 'PASS',
                `${zipTest.detail}, digits=${digits.length}`,
                zipScreenshot
            );
        }

        if (kind === 'card') {
            const placeholderText = String(field.placeholder || '').trim();
            const placeholderIdentifiesCard = /card|credit|debit/i.test(placeholderText);
            logEntry(
                results,
                `Form ${formIndex + 1} - Card number placeholder identifies card field for ${fieldName}`,
                placeholderIdentifiesCard ? 'PASS' : 'FAIL',
                placeholderText ? `placeholder="${placeholderText}"` : 'placeholder is empty or missing',
                await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_card_placeholder_${fieldName}`)
            );

            const sixteenDigitTest = await testInputValueRule(locator, '4111111111111111');
            const sixteenDigits = sixteenDigitTest.value.replace(/\D/g, '');
            logEntry(
                results,
                `Form ${formIndex + 1} - Card number accepts 16 digits for ${fieldName}`,
                sixteenDigits.length === 16 ? 'PASS' : 'FAIL',
                `${sixteenDigitTest.detail}, digits=${sixteenDigits.length}`,
                await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_card_16_digits_${fieldName}`)
            );

            const seventeenDigitTest = await testInputValueRule(locator, '41111111111111111');
            const seventeenDigits = seventeenDigitTest.value.replace(/\D/g, '');
            const tooLongAccepted = seventeenDigits.length > 16;
            logEntry(
                results,
                `Form ${formIndex + 1} - Card number max 16 digits for ${fieldName}`,
                tooLongAccepted ? 'FAIL' : 'PASS',
                `${seventeenDigitTest.detail}, digits=${seventeenDigits.length}`,
                await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_card_17_digits_${fieldName}`)
            );
        }

        if (kind === 'card' || kind === 'cvv') {
            const numericTest = await testInputValueRule(locator, kind === 'cvv' ? 'abc' : 'abcdEFGH');
            const characterAccepted = /[a-z]/i.test(numericTest.value);
            const cardScreenshot = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_${kind}_character_${fieldName}`);
            logEntry(
                results,
                `Form ${formIndex + 1} - ${kind === 'cvv' ? 'CVV' : 'Card number'} rejects characters for ${fieldName}`,
                characterAccepted ? 'FAIL' : 'PASS',
                numericTest.detail,
                cardScreenshot
            );
        }
    }
}

async function runDynamicCheckboxChecks(page, formLocator, formIndex, fields, results, reportPath) {
    for (const field of fields) {
        if (String(field.type || '').toLowerCase() !== 'checkbox') {
            continue;
        }

        const locator = formLocator.locator(field.selector).first();
        if ((await locator.count()) === 0 || !(await isVisible(locator))) {
            continue;
        }

        const fieldName = field.label || field.name || field.id || `${field.tag} ${field.index + 1}`;
        let clicked = false;
        let checked = false;
        try {
            await locator.check();
            clicked = true;
            checked = await locator.isChecked();
        } catch (error) {
            clicked = false;
        }

        const screenshotPath = await captureCheckScreenshot(page, reportPath, `form_${formIndex + 1}_checkbox_${fieldName}`);
        logEntry(
            results,
            `Form ${formIndex + 1} - Checkbox can be selected for ${fieldName}`,
            clicked && checked ? 'PASS' : 'FAIL',
            `checked=${checked}`,
            screenshotPath
        );
    }
}

async function runDynamicFormChecks(page, pageEntry, results, reportPath) {
    await page.goto(pageEntry.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await installSubmitGuard(page);

    const forms = page.locator('form');
    const formCount = await forms.count();
    const discoveryScreenshot = await captureCheckScreenshot(page, reportPath, `${pageEntry.label}_form_discovery`);
    logEntry(
        results,
        `${pageEntry.label} - Form discovery`,
        'PASS',
        formCount > 0 ? `forms=${formCount}` : 'No forms found; validation checks skipped.',
        discoveryScreenshot
    );

    for (let formIndex = 0; formIndex < formCount; formIndex += 1) {
        const formLocator = forms.nth(formIndex);
        const handles = await formLocator.locator('input, textarea, select').elementHandles();
        const fields = [];

        for (let index = 0; index < handles.length; index += 1) {
            const attrs = await getFieldAttributes(handles[index], index);
            if (!isTestableDynamicField(attrs)) {
                continue;
            }

            const locator = formLocator.locator(attrs.selector).first();
            if (!(await isVisible(locator))) {
                continue;
            }
            fields.push(attrs);
        }

        logEntry(
            results,
            `${pageEntry.label} - Form ${formIndex + 1} field discovery`,
            fields.length > 0 ? 'PASS' : 'FAIL',
            `fields=${fields.length}`,
            await captureCheckScreenshot(page, reportPath, `${pageEntry.label}_form_${formIndex + 1}_fields`)
        );

        if (fields.length > 0) {
            await runDynamicCheckboxChecks(page, formLocator, formIndex, fields, results, reportPath);
            await runDynamicBlankChecks(page, formLocator, formIndex, fields, results, reportPath);
            await runDynamicFieldRuleChecks(page, formLocator, formIndex, fields, results, reportPath);
        }
    }
}

async function main() {
    const args = process.argv.slice(2);
    const configArg = args[0];
    const baseUrlOverride = args[1];
    const outputPathArg = args[2];
    const pagesArg = args[3];

    const config = loadConfig(configArg);
    if (baseUrlOverride) {
        config.baseUrl = baseUrlOverride;
    }

    if (pagesArg) {
        try {
            const pagesJson = pagesArg.startsWith('base64:')
                ? Buffer.from(pagesArg.slice(7), 'base64').toString('utf8')
                : pagesArg;
            config.pages = JSON.parse(pagesJson);
        } catch (error) {
            throw new Error(`Invalid pages JSON: ${error.message}`);
        }
    }

    if (!config.baseUrl) {
        throw new Error('Base URL is required either via config or CLI override.');
    }

    const reportPath = resolveInputPath(outputPathArg, DEFAULT_REPORT_PATH);
    const reportDir = path.dirname(reportPath);
    if (!fs.existsSync(reportDir)) {
        fs.mkdirSync(reportDir, { recursive: true });
    }

    let browser;
    const results = [];
    try {
        // Attempt to launch Playwright Chromium. If browser binaries are not installed, provide a friendly fallback.
        browser = await chromium.launch({ headless: true });
    } catch (launchError) {
        console.error('Playwright browser launch failed:', launchError.message);
        // If Playwright can't run, perform an HTTP-only fallback
        async function fetchWithTimeout(url, opts = {}, timeoutMs = 10000) {
            const controller = new AbortController();
            const id = setTimeout(() => controller.abort(), timeoutMs);
            opts.signal = controller.signal;
            try {
                const res = await fetch(url, opts);
                clearTimeout(id);
                return res;
            } catch (e) {
                clearTimeout(id);
                throw e;
            }
        }

        function extractForms(html) {
            const forms = [];
            const formRegex = /<form\b([\s\S]*?)>([\s\S]*?)<\/form>/gi;
            let m;
            while ((m = formRegex.exec(html)) !== null) {
                const attrs = m[1];
                const inner = m[2];
                const actionMatch = attrs.match(/action=["']?([^"'\s>]+)["']?/i);
                const methodMatch = attrs.match(/method=["']?([^"'\s>]+)["']?/i);
                const enctypeMatch = attrs.match(/enctype=["']?([^"'\s>]+)["']?/i);
                const action = actionMatch ? actionMatch[1] : '';
                const method = methodMatch ? methodMatch[1].toUpperCase() : 'GET';
                const enctype = enctypeMatch ? enctypeMatch[1].toLowerCase() : 'application/x-www-form-urlencoded';
                const inputs = [];
                const inputRegex = /<(?:input|textarea|select)\b([^>]+)>/gi;
                let im;
                while ((im = inputRegex.exec(inner)) !== null) {
                    const iattrs = im[1];
                    const nameMatch = iattrs.match(/name=["']?([^"'\s>]+)["']?/i);
                    const typeMatch = iattrs.match(/type=["']?([^"'\s>]+)["']?/i);
                    const requiredMatch = iattrs.match(/required(=|\s|>)/i);
                    if (nameMatch) {
                        inputs.push({ name: nameMatch[1], type: typeMatch ? typeMatch[1].toLowerCase() : 'text', required: !!requiredMatch });
                    }
                }
                forms.push({ action, method, enctype, inputs });
            }
            return forms;
        }

        function sampleValueForInput(type, name) {
            const lname = (name || '').toLowerCase();
            if (/email/.test(lname) || type === 'email') return 'test@example.com';
            if (/phone|tel/.test(lname) || type === 'tel') return '1234567890';
            if (/zip|postal/.test(lname) || type === 'number') return '12345';
            if (/card|cc-number|credit/.test(lname)) return '4111111111111111';
            if (/cvv|cvc/.test(lname)) return '123';
            if (/name|first|last/.test(lname)) return 'Test User';
            if (/address/.test(lname)) return '123 Test St';
            if (/city/.test(lname)) return 'Testville';
            if (/state/.test(lname)) return 'TS';
            if (/country/.test(lname)) return 'US';
            if (/company/.test(lname)) return 'TestCorp';
            return 'test';
        }

        async function runHttpFallback(pages, reportPath) {
            const results = [];
            for (const entry of pages) {
                const url = entry.url;
                try {
                    const res = await fetchWithTimeout(url, { redirect: 'follow' }, 10000);
                    const html = await res.text().catch(() => '');
                    const forms = extractForms(html);
                    if (forms.length === 0) {
                        results.push({ label: entry.label || 'Page', status: 'NO_FORMS', detail: 'No forms detected; automation checks skipped.' });
                        continue;
                    }
                    for (let fi = 0; fi < forms.length; fi++) {
                        const form = forms[fi];
                        const actionUrl = form.action ? new URL(form.action, url).href : url;
                        const payload = {};
                        for (const inp of form.inputs) payload[inp.name] = sampleValueForInput(inp.type, inp.name);

                        // Decide whether to POST JSON or form-encoded data
                        const isJsonTarget = form.enctype && form.enctype.includes('json') || /\/api\//i.test(actionUrl);
                        if ((form.method || 'GET').toUpperCase() === 'GET') {
                            const qp = new URL(actionUrl);
                            Object.keys(payload).forEach(k => qp.searchParams.append(k, payload[k]));
                            try {
                                const r2 = await fetchWithTimeout(qp.href, { method: 'GET', redirect: 'follow' }, 10000);
                                results.push({ label: `${entry.label} - Form ${fi+1}`, status: r2.ok ? 'PASS' : 'FAIL', detail: `Submitted GET to ${qp.href} returned ${r2.status}` });
                            } catch (e) {
                                results.push({ label: `${entry.label} - Form ${fi+1}`, status: 'FAIL', detail: `Submission failed: ${e.message}` });
                            }
                        } else {
                            try {
                                let opts;
                                if (isJsonTarget) {
                                    opts = { method: 'POST', headers: { 'Content-Type': 'application/json', 'User-Agent': 'WebsiteTestPortal/1.0' }, body: JSON.stringify(payload), redirect: 'follow' };
                                } else {
                                    opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'WebsiteTestPortal/1.0' }, body: new URLSearchParams(payload).toString(), redirect: 'follow' };
                                }
                                const r2 = await fetchWithTimeout(actionUrl, opts, 15000);
                                results.push({ label: `${entry.label} - Form ${fi+1}`, status: r2.ok ? 'PASS' : 'FAIL', detail: `Submitted POST to ${actionUrl} returned ${r2.status}` });
                            } catch (e) {
                                results.push({ label: `${entry.label} - Form ${fi+1}`, status: 'FAIL', detail: `Submission failed: ${e.message}` });
                            }
                        }
                    }
                } catch (err) {
                    results.push({ label: entry.label || 'Page', status: 'FAIL', detail: 'Fetch failed: ' + err.message });
                }
            }
            try {
                fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
                console.log(`HTTP-only automation report written to ${reportPath}`);
                console.log(JSON.stringify({ reportPath, count: results.length }));
            } catch (werr) {
                console.error('Failed to write HTTP fallback report:', werr.message);
            }
            process.exit(0);
        }

        // Run HTTP-only fallback and exit
        await runHttpFallback(Array.isArray(config.pages) && config.pages.length>0 ? config.pages : [{ label: 'Quick Test Page', url: config.baseUrl }], reportPath);
    }

    const page = await browser.newPage({ viewport: { width: 1360, height: 768 } });

    try {
        const dynamicPages = Array.isArray(config.pages) && config.pages.length > 0
            ? config.pages
            : [{ label: 'Quick Test Page', url: config.baseUrl }];

        for (const entry of dynamicPages) {
            if (!entry?.url) {
                continue;
            }
            await runDynamicFormChecks(page, {
                label: entry.label || 'Quick Test Page',
                url: entry.url
            }, results, reportPath);
        }

        if (config.enableConfiguredChecks) {
            for (const form of config.forms ?? []) {
                await runFormValidation(page, config.baseUrl, form, results);
            }

            if (config.geo) {
                await runGeoChecks(page, config.baseUrl, config.geo, results);
            }

            if (config.masking) {
                await runMaskingChecks(page, config.baseUrl, config.masking, results);
            }
        }
    } finally {
        await browser.close();
    }

    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    console.log(`\nReport written to ${reportPath}`);
    console.log(JSON.stringify({ reportPath, count: results.length }));
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
