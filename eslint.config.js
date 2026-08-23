import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import globals from 'globals';

export default [
    {
        ignores: ['vendor/**', 'public/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**', 'BSDental_MultiTenant_Development_Pack_v4/**', 'stitch_*/**'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{ts,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
        },
    },
];