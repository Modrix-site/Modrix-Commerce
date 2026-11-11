const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const React = require('react');
const { renderToStaticMarkup } = require('react-dom/server');
const ts = require('typescript');

const root = path.resolve(__dirname, '../..');
const theme = path.join(root, 'themes/modrixcommerce/linden');
const marker = (name, value = '') =>
    React.createElement('i', { 'data-module': name }, String(value));
const link = ({ children, href, ...props }) =>
    React.createElement('a', { href, className: props.className }, children);
const slots = (items) =>
    React.createElement(
        React.Fragment,
        null,
        ...(items ?? []).map((x, i) =>
            React.createElement(
                'i',
                { key: i, 'data-module': x.component },
                x.props?.text,
            ),
        ),
    );
const storefront = {
    storeName: 'Linden & Vale',
    categories: [],
    navigation: {},
    placeSlots: {},
    footerSlots: [
        { component: 'footer-module', props: { text: 'Footer module' } },
    ],
};
const state = { props: { storefront }, url: '/search' };
const cache = new Map();
const form = ({ children }) =>
    React.createElement(
        'form',
        null,
        typeof children === 'function'
            ? children({ processing: false, errors: {} })
            : children,
    );
function load(file) {
    file = path.resolve(file);
    if (cache.has(file)) return cache.get(file);
    const context = {
        exports: {},
        require(name) {
            if (name === 'lucide-react')
                return new Proxy(
                    {},
                    { get: () => () => React.createElement('svg') },
                );
            if (name.startsWith('.'))
                return load(
                    path.resolve(
                        path.dirname(file),
                        name + (path.extname(name) ? '' : '.tsx'),
                    ),
                );
            if (name === '@inertiajs/react')
                return {
                    usePage: () => state,
                    Form: form,
                    Link: link,
                    router: {},
                };
            if (name.includes('store-menu-link'))
                return { StoreMenuLink: link };
            if (name.includes('/theme-sections'))
                return { blockAttributes: () => ({}) };
            if (name.includes('/place-slot'))
                return {
                    usePlaceItems: (place) =>
                        place === 'product.after_description'
                            ? [{ component: place, props: {} }]
                            : [],
                    PlaceSlot: ({ place, extraProps }) =>
                        marker(place, extraProps?.product?.id ?? ''),
                };
            if (name.includes('/extension-slot'))
                return { ExtensionSlot: ({ items }) => slots(items) };
            if (name.includes('/product-labels'))
                return {
                    ProductLabels: ({ labels }) =>
                        marker('labels', labels?.[0]?.text ?? ''),
                };
            if (name.includes('/sale-notice'))
                return {
                    SaleNotice: ({ sale }) => marker('sale', sale?.name ?? ''),
                    SaleTag: ({ sale }) =>
                        sale ? marker('sale-tag', sale.name) : null,
                };
            if (name.includes('/variant-selector'))
                return { VariantSelector: () => null };
            if (name.includes('/buy-now-button'))
                return { BuyNowButton: () => marker('buy-now') };
            if (name.includes('/sticky-add-to-cart'))
                return { StickyAddToCart: () => null };
            if (name.includes('/product-description'))
                return {
                    ProductDescription: ({ product }) =>
                        React.createElement('div', {
                            dangerouslySetInnerHTML: {
                                __html: product.description ?? '',
                            },
                        }),
                };
            if (name.includes('/input-error')) return { default: () => null };
            if (name.includes('/CartController'))
                return { default: { store: { form: () => ({}) } } };
            if (name.includes('/use-store-theme'))
                return {
                    useStoreTheme: () => ({
                        theme: { settings: {}, layout: { container: 'wide' } },
                    }),
                };
            if (name.includes('/store-menu'))
                return {
                    StoreMenuTree: () => null,
                    themeMenus: () => ({ footer: [] }),
                };
            if (name.includes('/ui/sheet'))
                return Object.fromEntries(
                    [
                        'Sheet',
                        'SheetTitle',
                        'SheetDescription',
                        'SheetTrigger',
                        'SheetContent',
                    ].map((n) => [
                        n,
                        n === 'SheetContent'
                            ? () => null
                            : ({ children }) =>
                                  React.createElement(
                                      React.Fragment,
                                      null,
                                      children,
                                  ),
                    ]),
                );
            if (name.includes('@/pages/storefront/cart'))
                return {
                    PlatformCartPage: (props) =>
                        React.createElement(
                            'div',
                            null,
                            slots(props.slots),
                            marker('core-cart-lines', props.cart?.item_count),
                            marker(
                                'core-cross-sells',
                                props.crossSells?.length,
                            ),
                        ),
                };
            return require(name);
        },
    };
    vm.runInNewContext(
        ts.transpileModule(fs.readFileSync(file, 'utf8'), {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                jsx: ts.JsxEmit.ReactJSX,
            },
        }).outputText,
        context,
        { filename: file },
    );
    cache.set(file, context.exports);
    return context.exports;
}
function schema(type) {
    return JSON.parse(
        fs.readFileSync(path.join(theme, 'sections', type + '.json'), 'utf8'),
    );
}
function section(type) {
    return {
        settings: Object.fromEntries(
            schema(type).settings.map((x) => [x.id, x.default]),
        ),
        blocks: [],
    };
}
function render(type, page, current = section(type)) {
    const Component = load(
        path.join(theme, 'src/sections', type + '.tsx'),
    ).default;
    return renderToStaticMarkup(
        React.createElement(Component, { section: current, page }),
    );
}
function once(html, name) {
    assert.equal(
        (
            html.match(
                new RegExp(
                    'data-module="' + name.replaceAll('.', '\\.') + '"',
                    'g',
                ),
            ) ?? []
        ).length,
        1,
        name,
    );
}

test('product module places, labels and flash-sale notices survive theme rendering', () => {
    const product = {
        id: 7,
        name: 'Shower cleanser',
        slug: 'shower-cleanser',
        images: [],
        options: [],
        labels: [{ text: 'New arrival' }],
        sale: { name: 'Family event' },
        description: '<p>Product description</p>',
    };
    const productSection = section('main-product');
    productSection.settings.show_buy_now = true;
    const html = render(
        'main-product',
        {
            product,
            variant: {
                id: 1,
                sku: 'BABY-1',
                available: 8,
                purchasable: true,
                price: { formatted: '$12.00' },
            },
            imageIndex: 0,
            selected: {},
            breadcrumbs: [],
            related: [],
            slots: [{ component: 'legacy-product-details', props: {} }],
        },
        productSection,
    );
    for (const place of [
        'product.after_title',
        'product.after_price',
        'product.after_add_to_cart',
        'product.after_description',
        'labels',
        'sale',
        'buy-now',
        'legacy-product-details',
    ])
        once(html, place);
    assert.match(html, /New arrival/);
    assert.match(html, /Family event/);
});

test('every product card passes its own product into extension blocks', () => {
    const { Card } = load(path.join(theme, 'src/components.tsx'));
    const html = renderToStaticMarkup(
        React.createElement(Card, {
            product: {
                id: 42,
                name: 'Towel',
                slug: 'towel',
                in_stock: true,
                price: { amount: '12', formatted: '$12' },
                labels: [{ text: 'Soft' }],
            },
            quickAdd: false,
        }),
    );
    once(html, 'product.card');
    assert.match(html, /data-module="product.card">42/);
    assert.match(html, /Soft/);
});

test('catalog renders the before-products hook and legacy after-products slots exactly once', () => {
    const html = render('main-collection', {
        title: 'Smartphones',
        products: { data: [], total: 0, last_page: 1, current_page: 1 },
        filters: {},
        filterBrands: [],
        filterOptions: [],
        breadcrumbs: [],
    });
    once(html, 'catalog.before_products');
    const app = render('apps', {
        slots: [
            {
                component: 'catalog-after-module',
                props: { text: 'Catalog module' },
            },
        ],
    });
    once(app, 'catalog-after-module');
});

test('header actions, footer extensions and cart commerce props remain available', () => {
    once(render('header'), 'header.actions');
    once(render('footer'), 'footer-module');
    const html = render('main-cart', {
        cart: { item_count: 3 },
        slots: [{ component: 'cart-total-module', props: {} }],
        crossSells: [{ id: 1 }],
    });
    once(html, 'cart-total-module');
    assert.match(html, /data-module="core-cart-lines">3/);
    assert.match(html, /data-module="core-cross-sells">1/);
});

test('all page-slot templates ship an enabled app section for arbitrary installed modules', () => {
    for (const name of [
        'index',
        'collection',
        'search',
        'product',
        'cart',
        'page',
    ]) {
        const template = JSON.parse(
            fs.readFileSync(
                path.join(theme, 'templates', name + '.json'),
                'utf8',
            ),
        );
        const apps = template.order.filter(
            (id) =>
                template.sections[id].type === 'apps' &&
                !template.sections[id].disabled,
        );
        assert.equal(apps.length, 1, name);
        const html = render('apps', {
            slots: [
                {
                    component: name + '-plugin',
                    props: { text: 'Arbitrary installed module' },
                },
            ],
        });
        once(html, name + '-plugin');
    }
});

test('theme layout retains the platform shell around checkout, account, orders and global overlays', () => {
    const Layout = load(path.join(theme, 'src/layout.tsx')).default;
    for (const page of [
        'checkout.payment_method',
        'checkout.after_summary',
        'checkout.before_place_order',
        'order.confirmation',
        'account.order',
    ]) {
        const DefaultLayout = ({ children }) =>
            React.createElement(
                'main',
                null,
                marker('storefront.overlay'),
                children,
            );
        const html = renderToStaticMarkup(
            React.createElement(
                Layout,
                { DefaultLayout },
                marker(page, 'Core module content'),
            ),
        );
        once(html, 'storefront.overlay');
        once(html, page);
        assert.match(html, /Core module content/);
    }
});
