import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { bracketMatching, foldGutter, foldKeymap, indentOnInput, indentUnit } from '@codemirror/language';
import { highlightSelectionMatches, openSearchPanel, search, searchKeymap, SearchQuery, setSearchQuery } from '@codemirror/search';
import { Compartment, EditorState, type Extension } from '@codemirror/state';
import { oneDark } from '@codemirror/theme-one-dark';
import {
	drawSelection,
	dropCursor,
	EditorView,
	highlightActiveLine,
	highlightActiveLineGutter,
	keymap,
	lineNumbers,
} from '@codemirror/view';
import { useEffect, useRef } from 'react';
import { usePrefs, useSession } from '../store';
import { registerEditor } from './registry';

interface CodeMirrorPaneProps {
	id: number;
	code: string;
	type: string;
	visible: boolean;
	readOnly: boolean;
}

function fontTheme(size: number): Extension {
	return EditorView.theme({
		'&': { fontSize: `${size}px` },
		'.cm-scroller': { fontFamily: '"Cascadia Code", Consolas, ui-monospace, monospace' },
	});
}

function indentFor(tabSize: number, useTabs: boolean): Extension {
	return indentUnit.of(useTabs ? '\t' : ' '.repeat(tabSize));
}

function openReplace(view: EditorView): boolean {
	const selection = view.state.sliceDoc(view.state.selection.main.from, view.state.selection.main.to);
	openSearchPanel(view);
	view.dispatch({
		effects: setSearchQuery.of(new SearchQuery({ search: selection, replace: '' })),
	});
	return true;
}

async function languageFor(type: string): Promise<Extension> {
	if (type === 'css') {
		const { css } = await import('@codemirror/lang-css');
		return css();
	}
	if (type === 'js') {
		const { javascript } = await import('@codemirror/lang-javascript');
		return javascript();
	}
	if (type === 'html') {
		const { html } = await import('@codemirror/lang-html');
		return html();
	}
	if (type === 'php') {
		const { php } = await import('@codemirror/lang-php');
		return php({ plain: true });
	}
	if (type === 'universal') {
		const { php } = await import('@codemirror/lang-php');
		return php();
	}
	return [];
}

export function CodeMirrorPane({ id, code, type, visible, readOnly }: CodeMirrorPaneProps) {
	const parent = useRef<HTMLDivElement>(null);
	const viewRef = useRef<EditorView | null>(null);
	const initialCode = useRef(code);

	useEffect(() => {
		const host = parent.current;
		if (!host) {
			return undefined;
		}

		const savedRef = { current: initialCode.current };
		const dirtyRef = { current: false };
		const wrap = new Compartment();
		const indent = new Compartment();
		const tabSize = new Compartment();
		const font = new Compartment();
		const language = new Compartment();
		let cursorFrame = 0;

		const publishCursor = (state: EditorState) => {
			cancelAnimationFrame(cursorFrame);
			cursorFrame = requestAnimationFrame(() => {
				const pos = state.selection.main.head;
				const line = state.doc.lineAt(pos);
				useSession.getState().setCursor({ line: line.number, col: pos - line.from + 1 });
			});
		};

		const prefs = usePrefs.getState();
		const view = new EditorView({
			parent: host,
			state: EditorState.create({
				doc: initialCode.current,
				extensions: [
					lineNumbers(),
					highlightActiveLineGutter(),
					highlightActiveLine(),
					foldGutter(),
					drawSelection(),
					dropCursor(),
					bracketMatching(),
					closeBrackets(),
					indentOnInput(),
					history(),
					highlightSelectionMatches(),
					oneDark,
					search({ top: true }),
					keymap.of([
						...closeBracketsKeymap,
						...defaultKeymap,
						...searchKeymap,
						...historyKeymap,
						...foldKeymap,
						indentWithTab,
						{ key: 'Mod-h', preventDefault: true, run: openReplace },
					]),
					wrap.of(prefs.wordWrap ? EditorView.lineWrapping : []),
					indent.of(indentFor(prefs.tabSize, prefs.indentWithTab)),
					tabSize.of(EditorState.tabSize.of(prefs.tabSize)),
					font.of(fontTheme(prefs.fontSize)),
					EditorView.editable.of(!readOnly),
					language.of([]),
					EditorView.updateListener.of((update) => {
						if (update.docChanged) {
							const dirty = update.state.doc.toString() !== savedRef.current;
							if (dirty !== dirtyRef.current) {
								dirtyRef.current = dirty;
								useSession.getState().setDirty(id, dirty);
							}
						}
						if ((update.selectionSet || update.docChanged) && useSession.getState().activeId === id) {
							publishCursor(update.state);
						}
					}),
				],
			}),
		});
		viewRef.current = view;

		const stopPrefs = usePrefs.subscribe((next) => {
			view.dispatch({
				effects: [
					wrap.reconfigure(next.wordWrap ? EditorView.lineWrapping : []),
					indent.reconfigure(indentFor(next.tabSize, next.indentWithTab)),
					tabSize.reconfigure(EditorState.tabSize.of(next.tabSize)),
					font.reconfigure(fontTheme(next.fontSize)),
				],
			});
		});

		let cancelled = false;
		languageFor(type)
			.then((extension) => {
				if (!cancelled) {
					view.dispatch({ effects: language.reconfigure(extension) });
				}
			})
			.catch(() => undefined);

		const unregister = registerEditor(id, {
			getCode: () => view.state.doc.toString(),
			setCode: (next) => {
				savedRef.current = next;
				dirtyRef.current = false;
				view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: next } });
				useSession.getState().setDirty(id, false);
			},
			markSaved: (next) => {
				savedRef.current = next;
				const dirty = view.state.doc.toString() !== next;
				dirtyRef.current = dirty;
				useSession.getState().setDirty(id, dirty);
			},
			focus: () => view.focus(),
			openSearch: () => {
				openSearchPanel(view);
			},
			openReplace: () => {
				openReplace(view);
			},
			revealLine: (line) => {
				const bounded = Math.max(1, Math.min(line, view.state.doc.lines));
				const target = view.state.doc.line(bounded);
				view.dispatch({ selection: { anchor: target.from }, scrollIntoView: true });
				view.focus();
			},
		});

		return () => {
			cancelled = true;
			cancelAnimationFrame(cursorFrame);
			stopPrefs();
			unregister();
			view.destroy();
			viewRef.current = null;
		};
	}, [id, readOnly, type]);

	useEffect(() => {
		if (visible) {
			viewRef.current?.requestMeasure();
		}
	}, [visible]);

	return <div ref={parent} className={visible ? 'absolute inset-0' : 'absolute inset-0 hidden'} />;
}
