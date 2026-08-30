'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const page = fs.readFileSync(path.join(__dirname, '..', 'passwd.php'), 'utf8');
const scriptMatch = page.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(scriptMatch, 'The page must contain a frontend script.');
const script = scriptMatch[1];
assert.doesNotThrow(() => new Function(script), 'The frontend script must be valid JavaScript.');

class FakeClassList {
    constructor() {
        this.values = new Set();
    }

    toggle(name, force) {
        const enabled = force === undefined ? !this.values.has(name) : Boolean(force);
        if (enabled) {
            this.values.add(name);
        } else {
            this.values.delete(name);
        }
        return enabled;
    }

    contains(name) {
        return this.values.has(name);
    }
}

class FakeStyle {
    constructor() {
        this.values = new Map();
        this.display = '';
        this.width = '';
        this.backgroundColor = '';
        this.color = '';
    }

    setProperty(name, value) {
        this.values.set(name, value);
    }
}

function createElement(id) {
    const element = {
        id,
        value: '',
        min: '',
        max: '',
        disabled: false,
        style: new FakeStyle(),
        classList: new FakeClassList(),
        attributes: new Map(),
        listeners: new Map(),
        children: new Map(),
        addEventListener(type, listener) {
            this.listeners.set(type, listener);
        },
        dispatch(type, event = {}) {
            const listener = this.listeners.get(type);
            assert.equal(typeof listener, 'function', `${id} must register a ${type} listener.`);
            return listener(event);
        },
        setAttribute(name, value) {
            this.attributes.set(name, String(value));
        },
        getAttribute(name) {
            return this.attributes.get(name) ?? null;
        },
        querySelector(selector) {
            return this.children.get(selector) ?? null;
        },
        focus() {
            this.focused = true;
        },
        select() {
            this.selected = true;
        },
        setSelectionRange(start, end) {
            this.selectionRange = [start, end];
        },
    };
    return element;
}

function createPage(options = {}) {
    const elements = new Map();
    [
        'passwordForm',
        'result',
        'copyBtn',
        'generateBtn',
        'length',
        'decrement',
        'increment',
        'strengthIndicator',
        'strengthText',
        'light-theme',
        'dark-theme',
        'lowercase',
        'uppercase',
        'numbers',
        'symbols',
    ].forEach((id) => elements.set(id, createElement(id)));

    const result = elements.get('result');
    result.value = options.initialPassword ?? '';

    const length = elements.get('length');
    length.value = '16';
    length.min = '8';
    length.max = '50';

    const copyDefault = createElement('copy-default');
    const copySuccess = createElement('copy-success');
    copyDefault.style.display = 'inline-flex';
    copySuccess.style.display = 'none';
    elements.get('copyBtn').children.set('.state-default', copyDefault);
    elements.get('copyBtn').children.set('.state-success', copySuccess);

    const generateDefault = createElement('generate-default');
    const generateLoading = createElement('generate-loading');
    generateDefault.style.display = 'inline-flex';
    generateLoading.style.display = 'none';
    elements.get('generateBtn').children.set('.state-default', generateDefault);
    elements.get('generateBtn').children.set('.state-loading', generateLoading);

    const body = createElement('body');
    let domReadyListener = null;
    let execCommandCalls = 0;
    const document = {
        body,
        addEventListener(type, listener) {
            if (type === 'DOMContentLoaded') {
                domReadyListener = listener;
            }
        },
        getElementById(id) {
            return elements.get(id) ?? null;
        },
        execCommand(command) {
            execCommandCalls += 1;
            assert.equal(command, 'copy');
            return options.execCommandResult ?? true;
        },
    };

    const storage = new Map();
    if (options.storedTheme !== undefined) {
        storage.set('theme', options.storedTheme);
    }
    const localStorage = {
        getItem(key) {
            if (options.storageUnavailable) {
                throw new Error('Storage unavailable');
            }
            return storage.get(key) ?? null;
        },
        setItem(key, value) {
            if (options.storageUnavailable) {
                throw new Error('Storage unavailable');
            }
            storage.set(key, String(value));
        },
    };

    const timerCallbacks = [];
    const errors = [];
    const clipboard = options.clipboard === undefined
        ? undefined
        : { writeText: options.clipboard };
    const navigator = { clipboard };
    const window = {
        setTimeout(callback) {
            timerCallbacks.push(callback);
            return timerCallbacks.length;
        },
    };

    class FakeAbortController {
        constructor() {
            this.signal = { aborted: false };
        }

        abort() {
            this.signal.aborted = true;
        }
    }

    class FakeFormData {
        constructor(form) {
            this.form = form;
        }
    }

    class FakeURLSearchParams {
        constructor(data) {
            this.data = data;
        }
    }

    const fetch = options.fetch ?? (async () => ({
        ok: true,
        status: 200,
        json: async () => ({ password: 'aA2!aA2!aA2!aA2!' }),
    }));

    const context = {
        AbortController: FakeAbortController,
        FormData: FakeFormData,
        Math,
        URLSearchParams: FakeURLSearchParams,
        console: { error: (...args) => errors.push(args) },
        document,
        fetch,
        localStorage,
        navigator,
        window,
    };
    vm.createContext(context);
    vm.runInContext(script, context);
    assert.equal(typeof domReadyListener, 'function', 'DOMContentLoaded initialization must be registered.');
    domReadyListener();

    return {
        body,
        elements,
        errors,
        getExecCommandCalls: () => execCommandCalls,
        storage,
        timerCallbacks,
    };
}

async function flushPromises() {
    await new Promise((resolve) => setTimeout(resolve, 0));
}

async function run() {
    assert.ok(page.includes('id="strengthText">-</span>'), 'The initial strength text must remain unchanged.');

    const darkPage = createPage({ storedTheme: 'dark' });
    const lightButton = darkPage.elements.get('light-theme');
    const darkButton = darkPage.elements.get('dark-theme');
    assert.ok(darkPage.body.classList.contains('dark-mode'), 'Stored dark theme must be restored.');
    assert.equal(darkButton.getAttribute('aria-pressed'), 'true');
    assert.equal(lightButton.getAttribute('aria-pressed'), 'false');

    lightButton.dispatch('click');
    assert.ok(!darkPage.body.classList.contains('dark-mode'), 'Light theme button must switch the page theme.');
    assert.equal(darkPage.storage.get('theme'), 'light', 'Theme choice must be persisted.');
    assert.equal(lightButton.getAttribute('aria-pressed'), 'true');
    assert.equal(darkButton.getAttribute('aria-pressed'), 'false');

    const unavailableStoragePage = createPage({ storageUnavailable: true });
    assert.doesNotThrow(() => unavailableStoragePage.elements.get('dark-theme').dispatch('click'));
    assert.ok(
        unavailableStoragePage.body.classList.contains('dark-mode'),
        'Theme switching must continue when localStorage is unavailable.'
    );

    const generatedPage = createPage();
    generatedPage.elements.get('passwordForm').dispatch('submit', { preventDefault() {} });
    await flushPromises();
    assert.equal(generatedPage.elements.get('result').value, 'aA2!aA2!aA2!aA2!');
    assert.equal(generatedPage.elements.get('strengthText').textContent, '极强');
    assert.equal(generatedPage.elements.get('strengthIndicator').style.width, '100%');
    assert.equal(generatedPage.elements.get('generateBtn').disabled, false);

    const failedPage = createPage({
        fetch: async () => ({
            ok: false,
            status: 422,
            json: async () => ({ error: '错误：请至少选择一种字符类型。' }),
        }),
    });
    failedPage.elements.get('passwordForm').dispatch('submit', { preventDefault() {} });
    await flushPromises();
    assert.equal(failedPage.elements.get('result').value, '错误：请至少选择一种字符类型。');
    assert.equal(failedPage.elements.get('strengthText').textContent, '错误：请至少选择一种字符类型。');
    assert.equal(failedPage.elements.get('generateBtn').disabled, false);

    const copiedValues = [];
    const fallbackPage = createPage({
        initialPassword: 'aA2!aA2!aA2!aA2!',
        clipboard: async (value) => {
            copiedValues.push(value);
            throw new Error('Clipboard unavailable');
        },
    });
    fallbackPage.elements.get('copyBtn').dispatch('click');
    await flushPromises();
    assert.deepEqual(copiedValues, ['aA2!aA2!aA2!aA2!']);
    assert.equal(fallbackPage.getExecCommandCalls(), 1, 'Clipboard failure must use the selection fallback.');
    assert.equal(fallbackPage.elements.get('result').selected, true);
    assert.equal(fallbackPage.elements.get('copyBtn').querySelector('.state-success').style.display, 'inline-flex');

    console.log('Frontend interaction tests passed.');
}

run().catch((error) => {
    console.error(error);
    process.exit(1);
});
