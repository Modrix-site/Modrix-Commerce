const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { Writable } = require('node:stream');
const React = require('react');
const { renderToPipeableStream } = require('react-dom/server');
const ts = require('typescript');

function runtime() {
    const loads = [];
    const context = {
        exports: {},
        require,
        glob(pattern) {
            assert.equal(pattern, '../../../themes/*/*/src/customizer.tsx');
            return Object.fromEntries(
                ['sunny', 'other'].map((name) => [
                    `../../../themes/acme/${name}/src/customizer.tsx`,
                    async () => {
                        loads.push(name);
                        return {
                            default: ({ theme, DefaultCustomizer }) =>
                                React.createElement(
                                    'section',
                                    { 'data-editor': name },
                                    React.createElement(DefaultCustomizer, {
                                        theme,
                                    }),
                                ),
                        };
                    },
                ]),
            );
        },
    };
    const source = fs
        .readFileSync(
            path.join(__dirname, '../../resources/js/lib/theme-customizer.ts'),
            'utf8',
        )
        .replaceAll('import.meta.glob', 'glob');
    vm.runInNewContext(
        ts.transpileModule(source, {
            compilerOptions: { module: ts.ModuleKind.CommonJS },
        }).outputText,
        context,
    );
    return { ...context.exports, loads };
}

function render(element) {
    return new Promise((resolve, reject) => {
        let html = '';
        const output = new Writable({
            write(chunk, encoding, next) {
                html += chunk;
                next();
            },
        });
        output.on('finish', () => resolve(html));
        const stream = renderToPipeableStream(element, {
            onAllReady() {
                stream.pipe(output);
            },
            onError: reject,
        });
    });
}

void test('the edited theme selects its own editor and can reuse default controls', async () => {
    const api = runtime();
    const Editor = api.themeCustomizer('acme/other', 'src/customizer.tsx');
    const html = await render(
        React.createElement(Editor, {
            theme: { name: 'acme/other' },
            DefaultCustomizer: ({ theme }) =>
                React.createElement('p', null, theme.name),
        }),
    );
    assert.match(html, /data-editor="other".*acme\/other/);
    assert.deepEqual(api.loads, ['other']);
    assert.equal(
        api.themeCustomizer('acme/other', 'src/customizer.tsx'),
        Editor,
    );
});

void test('undeclared, invalid and unbuilt theme editors fall back without importing code', () => {
    const api = runtime();
    assert.equal(api.themeCustomizer('acme/sunny', undefined), undefined);
    assert.equal(
        api.themeCustomizer('acme/sunny', '../customizer.tsx'),
        undefined,
    );
    assert.equal(
        api.themeCustomizer('acme/unbuilt', 'src/customizer.tsx'),
        undefined,
    );
    assert.deepEqual(api.loads, []);
});
