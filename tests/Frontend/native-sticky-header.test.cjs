const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const React = require('react');
const ts = require('typescript');

function header(settings, sections) {
    const context = {
        exports: {},
        require(name) {
            if (name === '@inertiajs/react')
                return {
                    usePage: () => ({
                        props: {
                            auth: { user: null },
                            storefront: {
                                theme: { settings },
                                themeSections: { header: sections },
                                categories: [],
                            },
                        },
                    }),
                };
            if (name === '@/hooks/use-compact-on-scroll')
                return { useCompactOnScroll: () => true };
            if (name === '@/lib/utils')
                return { cn: (...values) => values.filter(Boolean).join(' ') };
            if (name.startsWith('@/') || name === 'lucide-react') return {};
            return require(name);
        },
    };
    const file = path.resolve(
        __dirname,
        '../../resources/js/components/storefront/site-header.tsx',
    );
    vm.runInNewContext(
        ts.transpileModule(fs.readFileSync(file, 'utf8'), {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                jsx: ts.JsxEmit.ReactJSX,
            },
        }).outputText,
        context,
    );
    return context.exports.SiteHeader({ children: 'Original theme header' });
}

test('native sticky headers retain their own chrome when enabled or disabled', () => {
    for (const sticky of [true, false]) {
        for (const legacy of [true, false, undefined]) {
            const result = header({ sticky_header: legacy }, [
                { type: 'header', settings: { sticky } },
            ]);
            assert.equal(result.type, React.Fragment);
            assert.equal(result.props.children, 'Original theme header');
        }
    }
});

test('existing themes keep the shared compact header behavior', () => {
    const result = header({ sticky_header: true }, [
        { type: 'header', settings: {} },
    ]);
    const wrapper = React.Children.toArray(result.props.children).find(
        (child) => child.type === 'div',
    );
    assert.match(wrapper.props.className, /sticky top-0/);
    assert.equal(wrapper.props['data-compact'], '');
});

test('sticky settings on other section types do not change header behavior', () => {
    const result = header({ sticky_header: true }, [
        { type: 'promo-strip', settings: { sticky: true } },
    ]);
    assert.ok(
        React.Children.toArray(result.props.children).some(
            (child) => child.type === 'div',
        ),
    );
});
