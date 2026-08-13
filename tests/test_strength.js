'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const page = fs.readFileSync(path.join(__dirname, '..', 'passwd.php'), 'utf8');
const scripts = [...page.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
assert.ok(scripts.length >= 3, 'The page must contain theme and frontend scripts.');
scripts.forEach((script, index) => {
    assert.doesNotThrow(() => new Function(script), `Inline script ${index + 1} must be valid JavaScript.`);
});

const script = scripts.find((source) => source.includes('function calculatePasswordStrength(password) {'));
assert.ok(script, 'The main frontend script could not be found.');

const functionStart = script.indexOf('function calculatePasswordStrength(password) {');
const functionEnd = script.indexOf("document.addEventListener('DOMContentLoaded'", functionStart);
assert.ok(functionStart >= 0 && functionEnd > functionStart, 'The strength calculation function could not be isolated.');

const context = { Math };
vm.createContext(context);
vm.runInContext(
    `${script.slice(functionStart, functionEnd)}\nglobalThis.calculatePasswordStrengthForTest = calculatePasswordStrength;`,
    context
);

const calculatePasswordStrength = context.calculatePasswordStrengthForTest;
assert.equal(typeof calculatePasswordStrength, 'function', 'The strength calculation function must be available.');

assert.ok(!page.includes('@import'), 'The page must not import external stylesheets.');
assert.ok(!page.includes('fonts.googleapis.com'), 'The page must not request Google Fonts.');
assert.ok(!/<script[^>]+src\s*=/i.test(page), 'The page must not load external scripts.');
assert.ok(!/<link[^>]+rel=["']stylesheet["'][^>]+href=["']https?:\/\//i.test(page), 'The page must not load external stylesheets.');
assert.ok(!/url\(\s*["']?https?:\/\//i.test(page), 'The page must not load external CSS resources.');
assert.ok(!/fetch\(\s*["']https?:\/\//i.test(page), 'The page must not fetch external resources.');
assert.ok(page.includes("header('X-Content-Type-Options: nosniff')"), 'The nosniff header must be present.');
assert.ok(page.includes("header('X-Frame-Options: DENY')"), 'The frame denial header must be present.');
assert.ok(page.includes("header('Referrer-Policy: no-referrer')"), 'The referrer policy header must be present.');
assert.ok(page.includes("connect-src 'self'"), 'The CSP must allow same-origin password requests.');
assert.ok(page.includes("document.execCommand('copy')"), 'The clipboard fallback must be present.');
const copyFunctionStart = script.indexOf('async function copyPassword() {');
const copyFunctionEnd = script.indexOf('// 复制按钮', copyFunctionStart);
assert.ok(copyFunctionStart >= 0 && copyFunctionEnd > copyFunctionStart, 'The copy function could not be isolated.');
const copyFunction = script.slice(copyFunctionStart, copyFunctionEnd);
assert.ok(
    copyFunction.indexOf('navigator.clipboard.writeText') < copyFunction.indexOf('copied = copyUsingSelection()'),
    'The Clipboard API must be attempted before the selection fallback.'
);
assert.ok(page.includes('复制失败，请手动选择复制'), 'The manual copy fallback message must be present.');
assert.ok(page.includes('aria-label="生成的密码"'), 'The password result must have an accessible name.');
assert.ok(page.includes('aria-label="减少密码长度"'), 'The decrement button must have an accessible name.');
assert.ok(page.includes('aria-label="增加密码长度"'), 'The increment button must have an accessible name.');
assert.ok(page.includes('htmlspecialchars($generated_password)'), 'Server-rendered passwords must remain escaped.');

function expectStrength(password, text, percentage) {
    const strength = calculatePasswordStrength(password);
    assert.equal(strength.text, text, `Unexpected strength text for ${JSON.stringify(password)}.`);
    assert.equal(strength.percentage, percentage, `Unexpected strength percentage for ${JSON.stringify(password)}.`);
}

const allCharacterTypes = 'aA2!';

expectStrength('', '未生成', 0);
expectStrength('错误：密码生成失败，请稍后重试。', '未生成', 0);
expectStrength(allCharacterTypes.repeat(2), '中等', 40);
expectStrength(`${allCharacterTypes.repeat(2)}aA`, '强', 60);
expectStrength(`${allCharacterTypes.repeat(3)}aA2`, '很强', 80);
expectStrength(allCharacterTypes.repeat(4), '极强', 100);
expectStrength('2345678923456789', '中等', 40);

console.log('Password strength tests passed.');
