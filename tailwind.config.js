/** @type {import('tailwindcss').Config} */
module.exports = {
	important: '#snippet-studio-app',
	corePlugins: {
		preflight: false,
	},
	content: ['./src/**/*.{ts,tsx}'],
	theme: {
		extend: {
			colors: {
				studio: {
					bg: '#1b1c21',
					header: '#202127',
					panel: '#1f2026',
					editor: '#16171b',
					line: '#2c2e36',
					input: '#33363f',
					text: '#e6e7ea',
					muted: '#a1a4ad',
					faint: '#8a8e98',
					accent: '#3858e9',
					row: '#2d3142',
					chip: '#24262d',
					bar: '#2b3bb0',
				},
			},
			fontFamily: {
				sans: ['Segoe UI', 'system-ui', 'sans-serif'],
				mono: ['Cascadia Code', 'Consolas', 'ui-monospace', 'monospace'],
			},
			boxShadow: {
				tab: 'inset 0 2px 0 #6c8cff',
			},
		},
	},
	plugins: [],
};
