import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import type { ConflictState, Draft, SyntaxPrompt } from './types';

export type SortMode = 'recent' | 'name' | 'priority';
export type StatusFilter = 'all' | 'active' | 'inactive';
export type SaveState = 'idle' | 'saving' | 'saved' | 'error';

interface LayoutState {
	explorerWidth: number;
	propertiesWidth: number;
	explorerOpen: boolean;
	propertiesOpen: boolean;
	sort: SortMode;
	typeFilter: string;
	statusFilter: StatusFilter;
	collapsed: Record<string, boolean>;
	setExplorerWidth: (width: number) => void;
	setPropertiesWidth: (width: number) => void;
	setExplorerOpen: (open: boolean) => void;
	setPropertiesOpen: (open: boolean) => void;
	setSort: (sort: SortMode) => void;
	setTypeFilter: (type: string) => void;
	setStatusFilter: (status: StatusFilter) => void;
	toggleGroup: (key: string) => void;
}

export const useLayout = create<LayoutState>()(
	persist(
		(set) => ({
			explorerWidth: 280,
			propertiesWidth: 300,
			explorerOpen: true,
			propertiesOpen: true,
			sort: 'recent',
			typeFilter: 'all',
			statusFilter: 'all',
			collapsed: {},
			setExplorerWidth: (explorerWidth) => set({ explorerWidth }),
			setPropertiesWidth: (propertiesWidth) => set({ propertiesWidth }),
			setExplorerOpen: (explorerOpen) => set({ explorerOpen }),
			setPropertiesOpen: (propertiesOpen) => set({ propertiesOpen }),
			setSort: (sort) => set({ sort }),
			setTypeFilter: (typeFilter) => set({ typeFilter }),
			setStatusFilter: (statusFilter) => set({ statusFilter }),
			toggleGroup: (key) =>
				set((state) => ({
					collapsed: { ...state.collapsed, [key]: !state.collapsed[key] },
				})),
		}),
		{
			name: 'snippet-studio-layout',
			storage: createJSONStorage(() => localStorage),
		}
	)
);

interface PrefsState {
	fontSize: number;
	tabSize: number;
	wordWrap: boolean;
	indentWithTab: boolean;
	setFontSize: (fontSize: number) => void;
	setTabSize: (tabSize: number) => void;
	setWordWrap: (wordWrap: boolean) => void;
	setIndentWithTab: (indentWithTab: boolean) => void;
}

export const usePrefs = create<PrefsState>()(
	persist(
		(set) => ({
			fontSize: 13,
			tabSize: 4,
			wordWrap: false,
			indentWithTab: false,
			setFontSize: (fontSize) => set({ fontSize }),
			setTabSize: (tabSize) => set({ tabSize }),
			setWordWrap: (wordWrap) => set({ wordWrap }),
			setIndentWithTab: (indentWithTab) => set({ indentWithTab }),
		}),
		{
			name: 'snippet-studio-prefs',
			storage: createJSONStorage(() => localStorage),
		}
	)
);

interface CursorPosition {
	line: number;
	col: number;
}

interface SessionState {
	tabs: number[];
	activeId: number | null;
	dirty: Record<number, boolean>;
	drafts: Record<number, Draft>;
	query: string;
	saveState: SaveState;
	saveMessage: string;
	syntaxMessage: string;
	cursor: CursorPosition;
	quickOpen: boolean;
	codeSearch: boolean;
	settingsOpen: boolean;
	createOpen: boolean;
	revisionsFor: number | null;
	pendingClose: number | null;
	conflict: ConflictState | null;
	syntaxPrompt: SyntaxPrompt | null;
	openTab: (id: number) => void;
	closeTab: (id: number) => void;
	setActive: (id: number) => void;
	setDirty: (id: number, dirty: boolean) => void;
	setDraft: (id: number, draft: Partial<Draft>) => void;
	clearDraft: (id: number) => void;
	setQuery: (query: string) => void;
	setSaveStatus: (saveState: SaveState, saveMessage?: string) => void;
	setSyntaxMessage: (syntaxMessage: string) => void;
	setCursor: (cursor: CursorPosition) => void;
	setQuickOpen: (quickOpen: boolean) => void;
	setCodeSearch: (codeSearch: boolean) => void;
	setSettingsOpen: (settingsOpen: boolean) => void;
	setCreateOpen: (createOpen: boolean) => void;
	setRevisionsFor: (revisionsFor: number | null) => void;
	setPendingClose: (pendingClose: number | null) => void;
	setConflict: (conflict: ConflictState | null) => void;
	setSyntaxPrompt: (syntaxPrompt: SyntaxPrompt | null) => void;
	isDirty: (id: number) => boolean;
}

export const useSession = create<SessionState>()(
	persist(
		(set, get) => ({
			tabs: [],
			activeId: null,
			dirty: {},
			drafts: {},
			query: '',
			saveState: 'idle',
			saveMessage: 'Ready',
			syntaxMessage: '',
			cursor: { line: 1, col: 1 },
			quickOpen: false,
			codeSearch: false,
			settingsOpen: false,
			createOpen: false,
			revisionsFor: null,
			pendingClose: null,
			conflict: null,
			syntaxPrompt: null,
			openTab: (id) =>
				set((state) => ({
					tabs: state.tabs.includes(id) ? state.tabs : [...state.tabs, id],
					activeId: id,
				})),
			closeTab: (id) =>
				set((state) => {
					const tabs = state.tabs.filter((tab) => tab !== id);
					const dirty = { ...state.dirty };
					const drafts = { ...state.drafts };
					delete dirty[id];
					delete drafts[id];
					return {
						tabs,
						dirty,
						drafts,
						activeId: state.activeId === id ? (tabs[tabs.length - 1] ?? null) : state.activeId,
						revisionsFor: state.revisionsFor === id ? null : state.revisionsFor,
					};
				}),
			setActive: (activeId) => set({ activeId }),
			setDirty: (id, dirty) =>
				set((state) => {
					if (Boolean(state.dirty[id]) === dirty) {
						return state;
					}
					const next = { ...state.dirty };
					if (dirty) {
						next[id] = true;
					} else {
						delete next[id];
					}
					return { dirty: next };
				}),
			setDraft: (id, draft) =>
				set((state) => ({
					drafts: {
						...state.drafts,
						[id]: { ...state.drafts[id], ...draft },
					},
				})),
			clearDraft: (id) =>
				set((state) => {
					const drafts = { ...state.drafts };
					delete drafts[id];
					return { drafts };
				}),
			setQuery: (query) => set({ query }),
			setSaveStatus: (saveState, saveMessage = '') => set({ saveState, saveMessage }),
			setSyntaxMessage: (syntaxMessage) => set({ syntaxMessage }),
			setCursor: (cursor) =>
				set((state) =>
					state.cursor.line === cursor.line && state.cursor.col === cursor.col ? state : { cursor }
				),
			setQuickOpen: (quickOpen) => set({ quickOpen }),
			setCodeSearch: (codeSearch) => set({ codeSearch }),
			setSettingsOpen: (settingsOpen) => set({ settingsOpen }),
			setCreateOpen: (createOpen) => set({ createOpen }),
			setRevisionsFor: (revisionsFor) => set({ revisionsFor }),
			setPendingClose: (pendingClose) => set({ pendingClose }),
			setConflict: (conflict) => set({ conflict }),
			setSyntaxPrompt: (syntaxPrompt) => set({ syntaxPrompt }),
			isDirty: (id) => {
				const state = get();
				if (state.dirty[id]) {
					return true;
				}
				const draft = state.drafts[id];
				return !!draft && Object.keys(draft).length > 0;
			},
		}),
		{
			name: 'snippet-studio-session',
			storage: createJSONStorage(() => sessionStorage),
			partialize: (state) => ({ tabs: state.tabs, activeId: state.activeId }),
		}
	)
);
