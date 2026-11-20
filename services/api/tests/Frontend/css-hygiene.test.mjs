import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import postcss from 'postcss';

const stylesheetPath = new URL('../../resources/css/app.css', import.meta.url);

function atRuleContext(node) {
    const context = [];

    for (let parent = node.parent; parent && parent.type !== 'root'; parent = parent.parent) {
        if (parent.type === 'atrule') {
            context.unshift(`@${parent.name} ${parent.params}`);
        }
    }

    return context.join(' > ');
}

test('app stylesheet has no declarations superseded by an identical later rule', () => {
    const root = postcss.parse(fs.readFileSync(stylesheetPath, 'utf8'));
    const ruleGroups = new Map();

    root.walkRules((rule) => {
        const key = `${atRuleContext(rule)}||${rule.selector}`;
        const group = ruleGroups.get(key) ?? [];
        group.push(rule);
        ruleGroups.set(key, group);
    });

    const superseded = [];

    for (const rules of ruleGroups.values()) {
        const laterDeclarations = new Map();

        for (let index = rules.length - 1; index >= 0; index -= 1) {
            for (const declaration of [...rules[index].nodes].reverse()) {
                if (declaration.type !== 'decl') continue;

                const later = laterDeclarations.get(declaration.prop);

                if (later && (!declaration.important || later.important)) {
                    superseded.push(`${rules[index].selector} -> ${declaration.prop}`);
                    continue;
                }

                if (!later || (declaration.important && !later.important)) {
                    laterDeclarations.set(declaration.prop, declaration);
                }
            }
        }
    }

    assert.deepEqual(superseded, []);
});

test('app stylesheet contains no empty rules or duplicate properties within one rule', () => {
    const root = postcss.parse(fs.readFileSync(stylesheetPath, 'utf8'));
    const emptyNodes = [];
    const duplicateProperties = [];

    root.walk((node) => {
        if ((node.type === 'rule' || node.type === 'atrule') && node.nodes && node.nodes.length === 0) {
            emptyNodes.push(`${node.type}:${node.selector ?? `@${node.name} ${node.params}`}`);
        }
    });

    root.walkRules((rule) => {
        const properties = new Set();

        for (const declaration of rule.nodes.filter((node) => node.type === 'decl')) {
            if (properties.has(declaration.prop)) {
                duplicateProperties.push(`${rule.selector} -> ${declaration.prop}`);
            }

            properties.add(declaration.prop);
        }
    });

    assert.deepEqual(emptyNodes, []);
    assert.deepEqual(duplicateProperties, []);
});
