const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const ts = require('typescript');
const context = { exports: {} };
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname,'../../resources/js/lib/navigation-tree.ts'),'utf8'), {compilerOptions:{module:ts.ModuleKind.CommonJS}}).outputText,context);
const { reparentTreeItem, getTreeItem, removeTreeItem, updateTreeItem, treeCount } = context.exports;
const link = (label,children=[]) => ({label,url:'/search',children});
const plain = value => JSON.parse(JSON.stringify(value));

test('indent keeps the selected subtree and preserves its siblings without mutating input', () => {
    const input=[link('Shop',[link('Existing')]),link('Men',[link('Tops')]),link('Help')];
    const original=JSON.stringify(input);
    const next=reparentTreeItem(input,[1],'indent');
    assert.deepEqual(plain(next.path),[0,1]);
    assert.equal(getTreeItem(next.items,[0,1,0]).label,'Tops');
    assert.equal(next.items[1].label,'Help');
    assert.equal(JSON.stringify(input),original);
    assert.equal(treeCount(next.items),5);
});
test('outdent inserts after its parent and retains the complete descendant branch', () => {
    const input=[link('Shop',[link('Women',[link('Dresses')]),link('Men')]),link('Help')];
    const next=reparentTreeItem(input,[0,0],'outdent');
    assert.deepEqual(plain(next.path),[1]);
    assert.equal(next.items[0].children[0].label,'Men');
    assert.equal(next.items[1].children[0].label,'Dresses');
    assert.equal(next.items[2].label,'Help');
});
test('a subtree cannot be indented past four levels', () => {
    const input=[link('One',[link('Two',[link('Previous'),link('Three',[link('Four')])])])];
    const next=reparentTreeItem(input,[0,0,1],'indent');
    assert.equal(next.items,input);
    assert.deepEqual(plain(next.path),[0,0,1]);
});
test('removing a branch cascades only through that branch', () => {
    const input=[link('Shop',[link('Men',[link('Shirts')]),link('Women')]),link('Help')];
    const next=removeTreeItem(input,[0,0]);
    assert.equal(treeCount(next),3);
    assert.equal(next[0].children[0].label,'Women');
    assert.equal(input[0].children.length,2);
});
test('editing a nested destination preserves descendants and neighbouring links', () => {
    const input=[link('Shop',[link('Men',[link('Shirts')]),link('Women')])];
    const next=updateTreeItem(input,[0,0],{url:'/c/men',type:'category',category_id:7});
    assert.equal(getTreeItem(next,[0,0]).category_id,7);
    assert.equal(getTreeItem(next,[0,0,0]).label,'Shirts');
    assert.equal(getTreeItem(input,[0,0]).url,'/search');
});
