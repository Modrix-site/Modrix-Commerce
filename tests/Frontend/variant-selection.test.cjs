const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const ts = require('typescript');
const context = { exports: {} };
vm.runInNewContext(
    ts.transpileModule(
        fs.readFileSync(
            path.join(__dirname, '../../resources/js/lib/variant-selection.ts'),
            'utf8',
        ),
        { compilerOptions: { module: ts.ModuleKind.CommonJS } },
    ).outputText,
    context,
);
const { selectVariant, selectionForVariant } = context.exports;
const options = [
    { id: 1, values: [{ id: 10 }, { id: 11 }] },
    { id: 2, values: [{ id: 20 }, { id: 21 }] },
];
const variants = [
    { id: 1, option_value_ids: [10, 20], purchasable: true },
    { id: 2, option_value_ids: [11, 21], purchasable: true },
    { id: 3, option_value_ids: [10, 21], purchasable: false },
];
void test('missing combination resolves to an existing SKU retaining the clicked value', () => {
    assert.equal(
        selectVariant(options, variants, { 1: 10, 2: 20 }, 1, 11).id,
        2,
    );
});
void test('an exact sold-out combination stays selected to show its availability', () => {
    assert.equal(
        selectVariant(options, variants, { 1: 10, 2: 20 }, 2, 21).id,
        3,
    );
});
void test('unrelated changes preserve compatible selections', () => {
    assert.equal(
        selectVariant(options, variants, { 1: 10, 2: 21 }, 1, 11).id,
        2,
    );
});
void test('invalid values and empty variant sets do not resolve', () => {
    assert.equal(selectVariant(options, variants, {}, 1, 999), undefined);
    assert.equal(selectVariant(options, [], {}, 1, 10), undefined);
});
void test('selection uses the actual variant values and supports empty products', () => {
    assert.equal(
        JSON.stringify(selectionForVariant(options, variants[1])),
        JSON.stringify({ 1: 11, 2: 21 }),
    );
    assert.equal(JSON.stringify(selectionForVariant(options)), '{}');
});
