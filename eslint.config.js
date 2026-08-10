import js from "@eslint/js"
import stylistic from "@stylistic/eslint-plugin"
import prettier from "eslint-config-prettier/flat"
import importPlugin from "eslint-plugin-import"
import react from "eslint-plugin-react"
import reactHooks from "eslint-plugin-react-hooks"
import globals from "globals"
import typescript from "typescript-eslint"



/** @type {import('eslint').Linter.Config[]} */
export default [
	js.configs.recommended,
	reactHooks.configs.flat["recommended-latest"],
	...typescript.configs.recommended,
	{
		...react.configs.flat.recommended,
		...react.configs.flat["jsx-runtime"], // Required for React 17+
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		rules: {
			"react-hooks/exhaustive-deps": "off",
			"react-hooks/set-state-in-effect": "off",
			"react/react-in-jsx-scope": "off",
			"react/prop-types": "off",
			"react/no-unescaped-entities": "off",
		},
		settings: {
			react: {
				version: "detect",
			},
		},
	},
	{
		plugins: {
			import: importPlugin,
		},
		settings: {
			"import/resolver": {
				typescript: {
					alwaysTryTypes: true,
					project: "./tsconfig.json",
				},
				node: true,
			},
		},
		rules: {
			"@typescript-eslint/no-explicit-any": "off",
			"@typescript-eslint/consistent-type-imports": [
				"error",
				{
					prefer: "type-imports",
					fixStyle: "separate-type-imports",
				},
			],
		},
	},
	{
		plugins: {
			"@stylistic": stylistic,
		},
		rules: {
			"@stylistic/brace-style": ["error", "1tbs", { allowSingleLine: false }],
		},
	},
	{
		ignores: [
			"vendor",
			"node_modules",
			"public",
			"bootstrap/ssr",
			"tailwind.config.js",
			"vite.config.ts",
			"resources/js/actions/**",
			"resources/js/components/ui/*",
			"resources/js/routes/**",
			"resources/js/wayfinder/**",
		],
	},
	prettier, // Turn off all rules that might conflict with Prettier
	{
		plugins: {
			"@stylistic": stylistic,
		},
		rules: {
			curly: ["error", "all"],
			"@stylistic/brace-style": ["error", "1tbs", { allowSingleLine: false }],
		},
	},
]
