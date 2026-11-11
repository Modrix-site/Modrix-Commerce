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
    const state = { theme: undefined };
    const CorePage = ({ total }) =>
        React.createElement('p', null, `Total: ${total}`);
    const Override = ({ CorePage, ...props }) =>
        React.createElement(
            'section',
            { className: 'theme-checkout' },
            React.createElement(CorePage, props),
        );
    const Layout = ({ children }) =>
        React.createElement('main', null, children);
    const prefix = '../../../themes/acme/custom/src/';
    const context = {
        exports: {},
        require(name) {
            if (name === '@inertiajs/react')
                return {
                    usePage: () => ({
                        props: { storefront: { theme: state.theme } },
                    }),
                };
            return require(name);
        },
        glob(pattern, options = {}) {
            if (pattern.endsWith('pages/**/*.tsx'))
                return {
                    [`${prefix}pages/checkout.tsx`]: async () => ({
                        default: Override,
                    }),
                };
            if (pattern.endsWith('layout.tsx'))
                return {
                    [`${prefix}layout.tsx`]: options.eager
                        ? { default: Layout }
                        : async () => ({ default: Layout }),
                };
            const css = '[data-theme="acme/custom"] { color: red; }';
            return {
                [`${prefix}style.css`]: options.eager ? css : async () => css,
            };
        },
    };
    const source = fs
        .readFileSync(
            path.join(__dirname, '../../resources/js/lib/theme-runtime.tsx'),
            'utf8',
        )
        .replaceAll('import.meta.glob', 'glob');
    vm.runInNewContext(
        ts.transpileModule(source, {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                jsx: ts.JsxEmit.ReactJSX,
            },
        }).outputText,
        context,
    );
    return { ...context.exports, state, CorePage };
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

const theme = {
    name: 'acme/custom',
    frontend: {
        layout: 'src/layout.tsx',
        stylesheet: 'src/style.css',
        pages: { 'storefront/checkout': 'src/pages/checkout.tsx' },
    },
};

test('checkout quote reloads keep the same page component so address and delivery state survive', () => {
    const api = runtime();
    const Page = api.withThemePage('storefront/checkout', api.CorePage);
    for (let reload = 0; reload < 3; reload++) {
        assert.equal(
            api.withThemePage('storefront/checkout', api.CorePage),
            Page,
        );
    }
    // The same component can serve another route without reusing its wrapper.
    assert.notEqual(api.withThemePage('storefront/cart', api.CorePage), Page);
    const UpdatedCheckout = () => null;
    assert.notEqual(
        api.withThemePage('storefront/checkout', UpdatedCheckout),
        Page,
    );
});

test('a package page wraps the original page with unchanged commerce props', async () => {
    const api = runtime();
    api.state.theme = theme;
    const Page = api.withThemePage('storefront/checkout', api.CorePage);
    assert.match(
        await render(React.createElement(Page, { total: '42.00' })),
        /theme-checkout.*Total: 42.00/,
    );
});

test('switching themes and missing compiled entries fall back to core', async () => {
    const api = runtime();
    const Page = api.withThemePage('storefront/checkout', api.CorePage);
    for (const current of [
        undefined,
        { name: 'acme/custom' },
        { ...theme, name: 'acme/unbuilt' },
    ]) {
        api.state.theme = current;
        assert.equal(
            await render(React.createElement(Page, { total: '12.00' })),
            '<p>Total: 12.00</p>',
        );
    }
    api.state.theme = theme;
    assert.match(
        await render(React.createElement(Page, { total: '12.00' })),
        /theme-checkout.*Total: 12.00/,
    );
});

test('theme declarations cannot override admin or module pages', () => {
    const api = runtime();
    assert.equal(
        api.withThemePage('admin/dashboard', api.CorePage),
        api.CorePage,
    );
    assert.equal(
        api.withThemePage('module/acme/example/storefront/test', api.CorePage),
        api.CorePage,
    );
});

test('declared layout and stylesheet load independently of the homepage', async () => {
    const api = runtime();
    const Layout = api.themeLayout(theme);
    assert.equal(
        await render(React.createElement(Layout, null, 'Checkout')),
        '<main>Checkout</main>',
    );
    assert.match(
        await render(React.createElement(api.ThemeStyles, { theme })),
        /\[data-theme="acme\/custom"\]/,
    );
    assert.equal(api.themeLayout({ ...theme, frontend: {} }), undefined);
    assert.equal(
        await render(
            React.createElement(api.ThemeStyles, { theme: undefined }),
        ),
        '',
    );
});

test('core source has no direct dependency on any named theme package', () => {
    const root = path.join(__dirname, '../../resources');
    for (const file of fs.readdirSync(root, { recursive: true })) {
        if (!/\.(tsx?|css)$/.test(file)) continue;
        const source = fs.readFileSync(path.join(root, file), 'utf8');
        assert.doesNotMatch(
            source,
            /(?:from\s*|@import\s*|import\s*)['"][^'"\n]*themes\/[a-z0-9-]+\/[a-z0-9-]+\//,
            file,
        );
    }
});
