'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const page = fs.readFileSync(path.join(__dirname, '..', 'passwd.php'), 'utf8');
const scriptMatch = page.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(scriptMatch, 'The page must contain a frontend script.');

const script = scriptMatch[1];
new Function(script);

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

function expectStrength(password, text, percentage) {
    const strength = calculatePasswordStrength(password);
    assert.equal(strength.text, text, `Unexpected strength text for ${JSON.stringify(password)}.`);
    assert.equal(strength.percentage, percentage, `Unexpected strength percentage for ${JSON.stringify(password)}.`);
}

const allCharacterTypes = 'aA2!';

expectStrength('', '-', 0);
expectStrength('错误：密码生成失败，请稍后重试。', '-', 0);
expectStrength(allCharacterTypes.repeat(2), '中等', 40);
expectStrength(`${allCharacterTypes.repeat(2)}aA`, '强', 60);
expectStrength(`${allCharacterTypes.repeat(3)}aA2`, '很强', 80);
expectStrength(allCharacterTypes.repeat(4), '极强', 100);
expectStrength('2345678923456789', '中等', 40);

console.log('Password strength tests passed.');
