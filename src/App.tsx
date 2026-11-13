import { useQuery, useQueryClient } from '@tanstack/react-query';
import { PanelLeft, PanelRight, Plus, Search, Settings } from 'lucide-react';
import { useEffect, type PointerEvent as ReactPointerEvent } from 'react';
import { api, ApiError } from './api';
import { CodeMirrorPane } from './editor/CodeMirrorPane';
import { getEditor } from './editor/registry';
import { isCopyTitle, parseTitle, typeLabel } from './group';
import { applySnippet, saveSnippet, setSnippetActive } from './save';
import { useLayout, usePrefs, useSession } from './store';
import type { Bootstrap, Snippet, Summary } from './types';
import { Explorer } from './components/Explorer';
import { Properties } from './components/Properties';
import {
	CloseDialog,
	CodeSearch,
	ConflictDialog,
	CreateDialog,
	QuickOpen,
	RevisionsDialog,
	SettingsDialog,
	SyntaxDialog,
} from './components/Overlays';

function revealLine(id: number, line: number) {
	let tries = 0;
	const timer = window.setInterval(() => {
		const editor = getEditor(id);
		tries += 1;
		if (editor) {
			editor.revealLine(line);
			window.clearInterval(timer);
		} else if (tries > 30) {
			window.clearInterval(timer);
		}
	}, 150);
}

export function App() {
	const client = useQueryClient();
	const bootstrap = useQuery({
		queryKey: ['bootstrap'],
		queryFn: () => api<Bootstrap>('/bootstrap'),
	});
	const woodyReady = bootstrap.data?.woody_active === true;
	const snippets = useQuery({
		queryKey: ['snippets'],
		queryFn: () => api<Summary[]>('/snippets'),
		enabled: woodyReady,
	});

	const tabs = useSession((state) => state.tabs);
	const activeId = useSession((state) => state.activeId);
	const dirty = useSession((state) => state.dirty);
	const drafts = useSession((state) => state.drafts);
	const openTab = useSession((state) => state.openTab);
	const closeTab = useSession((state) => state.closeTab);
	const setActive = useSession((state) => state.setActive);
	const setPendingClose = useSession((state) => state.setPendingClose);
	const quickOpen = useSession((state) => state.quickOpen);
	const codeSearch = useSession((state) => state.codeSearch);
	const settingsOpen = useSession((state) => state.settingsOpen);
	const createOpen = useSession((state) => state.createOpen);
	const saveState = useSession((state) => state.saveState);
	const saveMessage = useSession((state) => state.saveMessage);
	const syntaxMessage = useSession((state) => state.syntaxMessage);
	const cursor = useSession((state) => state.cursor);
	const setQuickOpen = useSession((state) => state.setQuickOpen);
	const setCodeSearch = useSession((state) => state.setCodeSearch);
	const setSettingsOpen = useSession((state) => state.setSettingsOpen);
	const setCreateOpen = useSession((state) => state.setCreateOpen);
	const setSyntaxMessage = useSession((state) => state.setSyntaxMessage);

	const explorerOpen = useLayout((state) => state.explorerOpen);
	const propertiesOpen = useLayout((state) => state.propertiesOpen);
	const explorerWidth = useLayout((state) => state.explorerWidth);
	const propertiesWidth = useLayout((state) => state.propertiesWidth);
	const setExplorerOpen = useLayout((state) => state.setExplorerOpen);
	const setPropertiesOpen = useLayout((state) => state.setPropertiesOpen);
	const setExplorerWidth = useLayout((state) => state.setExplorerWidth);
	const setPropertiesWidth = useLayout((state) => state.setPropertiesWidth);
	const prefs = usePrefs();

	const activeQuery = useQuery({
		queryKey: ['snippet', activeId],
		queryFn: () => api<Snippet>(`/snippets/${activeId}`),
		enabled: activeId !== null && woodyReady,
	});

	useEffect(() => {
		const onKey = (event: KeyboardEvent) => {
			const meta = event.ctrlKey || event.metaKey;
			if (!meta) {
				return;
			}
			const key = event.key.toLowerCase();
			const id = useSession.getState().activeId;
			if (key === 's') {
				event.preventDefault();
				if (id) {
					void saveSnippet(client, id);
				}
			} else if (key === 'p' && !event.shiftKey) {
				event.preventDefault();
				useSession.getState().setQuickOpen(true);
			} else if (key === 'f' && event.shiftKey) {
				event.preventDefault();
				useSession.getState().setCodeSearch(true);
			} else if (key === 'f' && id) {
				event.preventDefault();
				getEditor(id)?.openSearch();
			} else if (key === 'h' && id) {
				event.preventDefault();
				getEditor(id)?.openReplace();
			} else if (key === 'w' && id) {
				event.preventDefault();
				if (useSession.getState().isDirty(id)) {
					useSession.getState().setPendingClose(id);
				} else {
					useSession.getState().closeTab(id);
				}
			}
		};
		const onLeave = (event: BeforeUnloadEvent) => {
			const state = useSession.getState();
			if (state.tabs.some((id) => state.isDirty(id))) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener('keydown', onKey);
		window.addEventListener('beforeunload', onLeave);
		return () => {
			window.removeEventListener('keydown', onKey);
			window.removeEventListener('beforeunload', onLeave);
		};
	}, [client]);

	const canWrite = bootstrap.data?.can_write === true;
	const rows = snippets.data ?? [];
	const activeCount = rows.filter((snippet) => snippet.active).length;
	const activeSnippet = activeQuery.data;

	const openSnippet = (id: number, line?: number) => {
		openTab(id);
		if (line) {
			revealLine(id, line);
		}
	};

	const requestClose = (id: number) => {
		if (useSession.getState().isDirty(id)) {
			setPendingClose(id);
			return;
		}
		closeTab(id);
	};

	const trashSnippet = async (snippet: Summary) => {
		const copy = isCopyTitle(snippet.title);
		const message = copy
			? `Move “${parseTitle(snippet.title).name}” to the trash? It is marked as a copy. You can restore it from the WordPress trash.`
			: `Move “${parseTitle(snippet.title).name}” to the trash? You can restore it from the WordPress trash.`;
		if (!window.confirm(message)) {
			return;
		}
		try {
			await api<{ id: number; trashed: boolean }>(`/snippets/${snippet.id}`, 'DELETE');
			client.setQueryData<Summary[]>(['snippets'], (current) => current?.filter((item) => item.id !== snippet.id));
			client.removeQueries({ queryKey: ['snippet', snippet.id] });
			closeTab(snippet.id);
		} catch (error) {
			const text = error instanceof ApiError ? error.message : 'Could not move the snippet to the trash';
			useSession.getState().setSaveStatus('error', text);
		}
	};

	const duplicate = async (id: number) => {
		try {
			const created = await api<Snippet>(`/snippets/${id}/duplicate`, 'POST');
			applySnippet(client, created);
			openTab(created.id);
		} catch (error) {
			const message = error instanceof ApiError ? error.message : 'Could not duplicate the snippet';
			useSession.getState().setSaveStatus('error', message);
		}
	};

	const exportBackup = async () => {
		const data = await api<unknown>('/export');
		const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const link = document.createElement('a');
		link.href = url;
		link.download = `snippet-studio-backup.json`;
		link.click();
		URL.revokeObjectURL(url);
	};

	const checkSyntax = async () => {
		if (!activeId || !activeSnippet) {
			return;
		}
		const code = getEditor(activeId)?.getCode() ?? activeSnippet.code;
		try {
			const result = await api<{ valid: boolean; checked: boolean; message: string }>(`/snippets/${activeId}/check`, 'POST', { code });
			setSyntaxMessage(result.checked ? (result.valid ? 'No syntax errors' : result.message) : 'Syntax was not checked');
		} catch (error) {
			setSyntaxMessage(error instanceof ApiError ? error.message : 'Syntax check failed');
		}
	};

	const drag = (startWidth: number, event: ReactPointerEvent<HTMLDivElement>, direction: 1 | -1, apply: (width: number) => void, min: number) => {
		event.preventDefault();
		const startX = event.clientX;
		const move = (ev: PointerEvent) => {
			apply(Math.min(520, Math.max(min, startWidth + direction * (ev.clientX - startX))));
		};
		const up = () => {
			window.removeEventListener('pointermove', move);
			window.removeEventListener('pointerup', up);
		};
		window.addEventListener('pointermove', move);
		window.addEventListener('pointerup', up);
	};

	if (bootstrap.isLoading) {
		return <ScreenMessage title="Loading Snippet Studio" detail="Fetching the editor." />;
	}
	if (bootstrap.isError) {
		const message = bootstrap.error instanceof ApiError ? bootstrap.error.message : 'The editor could not reach WordPress.';
		return <ScreenMessage title="Snippet Studio is unavailable" detail={message} />;
	}
	if (!woodyReady) {
		return <ScreenMessage title="Woody Code Snippets is not active" detail="Install and activate Woody, then reload Snippet Studio. Nothing here changes Woody's files." />;
	}

	return (
		<div className="flex h-full min-h-0 flex-col bg-studio-bg font-sans text-studio-text">
			<header className="flex min-h-[52px] flex-wrap items-center gap-3 border-b border-studio-line bg-studio-header px-4 py-2">
				<div className="flex items-center gap-2.5">
					<div className="flex h-[26px] w-[26px] items-center justify-center rounded-md bg-studio-accent">
						<span className="font-mono text-[11px] text-white">{'</>'}</span>
					</div>
					<span className="text-[15px] font-semibold">Snippet Studio</span>
					<span className="text-studio-faint">for Woody Snippets</span>
				</div>
				<button
					type="button"
					onClick={() => setQuickOpen(true)}
					className="mx-auto flex h-8 max-w-[460px] flex-1 items-center gap-2 rounded-md border border-studio-input bg-studio-editor px-2.5 text-studio-muted"
				>
					<Search size={14} />
					<span>Go to snippet, search in code…</span>
					<span className="ml-auto rounded border border-[#3a3d47] px-1.5 font-mono text-[11px]">Ctrl P</span>
				</button>
				<div className="flex items-center gap-2">
					<a href={bootstrap.data?.classic_url} className="rounded-md px-2.5 py-1.5 text-[13px] text-studio-muted no-underline hover:text-white">
						Classic list
					</a>
					<button type="button" onClick={() => void exportBackup()} className="rounded-md px-2 py-1.5 text-[13px] text-studio-muted">
						Export
					</button>
					<button type="button" aria-label="Editor settings" onClick={() => setSettingsOpen(true)} className="rounded-md p-1.5 text-studio-muted">
						<Settings size={16} />
					</button>
					<button
						type="button"
						disabled={!canWrite}
						onClick={() => setCreateOpen(true)}
						className="flex h-8 items-center gap-1 rounded-md bg-studio-accent px-3 text-[13px] font-medium text-white disabled:opacity-40"
					>
						<Plus size={14} /> New snippet
					</button>
				</div>
			</header>

			{bootstrap.data?.file_edit_locked && (
				<div className="bg-[#3a2e22] px-4 py-1.5 text-xs text-[#f2b880]">WordPress file editing is disabled, so this screen is read-only.</div>
			)}

			<div className="flex min-h-0 flex-1">
				{explorerOpen ? (
					<aside className="flex min-h-0 shrink-0 border-r border-studio-line bg-studio-panel" style={{ width: explorerWidth }}>
						<div className="min-w-0 flex-1">
							<Explorer
								snippets={rows}
								onOpen={openSnippet}
								onDuplicate={(id) => void duplicate(id)}
								onToggleActive={(snippet) => {
									if (!canWrite) {
										return;
									}
									if (!snippet.active && !window.confirm('Activate this snippet? Woody will be able to run it.')) {
										return;
									}
									void setSnippetActive(client, snippet.id, !snippet.active).catch(() => undefined);
								}}
								onTrash={(snippet) => {
									if (canWrite) {
										void trashSnippet(snippet);
									}
								}}
							/>
						</div>
					</aside>
				) : (
					<button type="button" aria-label="Show explorer" onClick={() => setExplorerOpen(true)} className="w-8 shrink-0 border-r border-studio-line bg-studio-panel text-studio-muted">
						<PanelLeft size={16} className="mx-auto" />
					</button>
				)}
				{explorerOpen && (
					<div
						role="separator"
						aria-orientation="vertical"
						aria-label="Resize explorer"
						className="w-1 shrink-0 cursor-col-resize bg-studio-line hover:bg-studio-accent"
						onPointerDown={(event) => drag(explorerWidth, event, 1, setExplorerWidth, 220)}
						onDoubleClick={() => setExplorerOpen(false)}
					/>
				)}

				<main className="flex min-w-0 flex-1 flex-col bg-studio-editor">
					<div role="tablist" aria-label="Open snippets" className="flex min-h-[38px] overflow-x-auto border-b border-studio-line bg-studio-panel">
						{tabs.map((id) => {
							const summary = rows.find((snippet) => snippet.id === id);
							const cached = client.getQueryData<Snippet>(['snippet', id]);
							const title = summary?.title || cached?.title || `Snippet ${id}`;
							const type = summary?.type || cached?.type || 'php';
							const on = id === activeId;
							const copy = isCopyTitle(title);
							const unsaved = Boolean(dirty[id]) || Boolean(drafts[id] && Object.keys(drafts[id]).length > 0);
							return (
								<div key={id} className={`flex shrink-0 items-center border-r border-studio-line ${on ? 'bg-studio-editor text-white shadow-tab' : 'text-studio-muted'}`}>
									<button type="button" role="tab" aria-selected={on} onClick={() => setActive(id)} className="flex h-[37px] items-center gap-2 pl-3 pr-1 text-[13px]">
										<span className="rounded bg-[#262c45] px-1 font-mono text-[10px] text-[#9fb3ff]">{typeLabel(type).toLowerCase()}</span>
										<span className={`max-w-[180px] truncate ${copy ? 'text-[#f2c14b]' : ''}`}>{parseTitle(title).name}</span>
										{copy && <span className="rounded bg-[#3a3418] px-1 text-[10px] font-semibold uppercase tracking-wide text-[#f2c14b]">Copy</span>}
										{unsaved && <span className="text-[#f2b880]">●</span>}
									</button>
									<button type="button" aria-label={`Close ${title}`} onClick={() => requestClose(id)} className="h-[37px] w-7 text-studio-faint">
										×
									</button>
								</div>
							);
						})}
						<div className="ml-auto flex items-center gap-1 pr-2">
							<button type="button" aria-label={explorerOpen ? 'Hide explorer' : 'Show explorer'} onClick={() => setExplorerOpen(!explorerOpen)} className="p-1 text-studio-faint">
								<PanelLeft size={15} />
							</button>
							<button type="button" aria-label={propertiesOpen ? 'Hide properties' : 'Show properties'} onClick={() => setPropertiesOpen(!propertiesOpen)} className="p-1 text-studio-faint">
								<PanelRight size={15} />
							</button>
						</div>
					</div>

					{activeSnippet && (
						<div className="flex flex-wrap items-center gap-2 border-b border-[#24262d] px-4 py-2">
							<nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-studio-muted">
								<span>Snippets</span>
								<span>/</span>
								<span>{parseTitle(activeSnippet.title).group}</span>
								<span>/</span>
								<span className={isCopyTitle(activeSnippet.title) ? 'text-[#f2c14b]' : 'text-studio-text'}>{parseTitle(activeSnippet.title).name}</span>
								{isCopyTitle(activeSnippet.title) && <span className="rounded bg-[#3a3418] px-1 text-[10px] font-semibold uppercase tracking-wide text-[#f2c14b]">Copy</span>}
							</nav>
							<div className="ml-auto flex gap-1.5">
								<button type="button" onClick={() => useSession.getState().setRevisionsFor(activeSnippet.id)} className="h-7 rounded-md border border-studio-input bg-studio-chip px-2.5 text-xs text-[#c9cbd1]">
									Revisions
								</button>
								<button type="button" onClick={() => void checkSyntax()} className="h-7 rounded-md border border-studio-input bg-studio-chip px-2.5 text-xs text-[#c9cbd1]">
									Check syntax
								</button>
								<button
									type="button"
									disabled={!canWrite || saveState === 'saving'}
									onClick={() => activeId && void saveSnippet(client, activeId)}
									className="h-7 rounded-md bg-studio-accent px-3 text-xs font-medium text-white disabled:opacity-40"
								>
									{saveState === 'saving' ? 'Saving…' : 'Save'}
								</button>
							</div>
						</div>
					)}

					<div className="relative min-h-0 flex-1">
						{tabs.map((id) => (
							<EditorSlot key={id} id={id} visible={id === activeId} readOnly={!canWrite} />
						))}
						{tabs.length === 0 && (
							<div className="flex h-full flex-col items-center justify-center gap-2 text-studio-muted">
								<span className="text-[15px] text-studio-text">No snippet open</span>
								<span>Pick one in the Explorer, or press Ctrl P.</span>
							</div>
						)}
						{snippets.isLoading && tabs.length === 0 && <div className="absolute left-4 top-4 text-xs text-studio-muted">Loading snippets…</div>}
						{snippets.isError && <div className="absolute left-4 top-4 text-xs text-red-300">The snippet list could not be loaded.</div>}
					</div>
				</main>

				{propertiesOpen && (
					<div
						role="separator"
						aria-orientation="vertical"
						aria-label="Resize properties"
						className="w-1 shrink-0 cursor-col-resize bg-studio-line hover:bg-studio-accent"
						onPointerDown={(event) => drag(propertiesWidth, event, -1, setPropertiesWidth, 240)}
						onDoubleClick={() => setPropertiesOpen(false)}
					/>
				)}
				{propertiesOpen ? (
					<aside className="min-h-0 shrink-0 overflow-hidden border-l border-studio-line bg-studio-panel" style={{ width: propertiesWidth }}>
						{activeSnippet && bootstrap.data ? (
							<Properties
								snippet={activeSnippet}
								bootstrap={bootstrap.data}
								canWrite={canWrite}
								onDuplicate={() => void duplicate(activeSnippet.id)}
								onTrash={() => void trashSnippet(activeSnippet)}
							/>
						) : (
							<div className="p-4 text-sm text-studio-muted">Open a snippet to see its settings.</div>
						)}
					</aside>
				) : (
					<button type="button" aria-label="Show properties" onClick={() => setPropertiesOpen(true)} className="w-8 shrink-0 border-l border-studio-line bg-studio-panel text-studio-muted">
						<PanelRight size={16} className="mx-auto" />
					</button>
				)}
			</div>

			<footer className="flex h-[26px] shrink-0 items-center gap-4 overflow-hidden whitespace-nowrap bg-studio-bar px-3 text-[11.5px] text-white">
				<span>
					{saveState === 'error' ? '● Error' : saveState === 'saving' ? '● Saving' : '● Saved'}
					{saveMessage ? ` · ${saveMessage}` : ''}
				</span>
				<span>{activeSnippet ? typeLabel(activeSnippet.type) : 'No file'}</span>
				<span>
					Ln {cursor.line}, Col {cursor.col}
				</span>
				<span>{prefs.indentWithTab ? 'Tabs' : `Spaces: ${prefs.tabSize}`}</span>
				<span className="max-w-[360px] truncate">{syntaxMessage || (activeSnippet?.type === 'php' ? 'Syntax not checked' : '')}</span>
				<span className="ml-auto">
					{rows.length} snippets · {activeCount} active
				</span>
			</footer>

			{quickOpen && <QuickOpen snippets={rows} onOpen={openSnippet} />}
			{codeSearch && <CodeSearch onOpen={openSnippet} />}
			{settingsOpen && <SettingsDialog />}
			{createOpen && bootstrap.data && <CreateDialog bootstrap={bootstrap.data} />}
			<ConflictDialog />
			<SyntaxDialog />
			<CloseDialog />
			<RevisionsDialog />
		</div>
	);
}

function EditorSlot({ id, visible, readOnly }: { id: number; visible: boolean; readOnly: boolean }) {
	const query = useQuery({
		queryKey: ['snippet', id],
		queryFn: () => api<Snippet>(`/snippets/${id}`),
		staleTime: 60_000,
	});

	if (!visible && !query.data) {
		return null;
	}
	if (query.isLoading) {
		return visible ? <div className="p-4 text-sm text-studio-muted">Loading snippet…</div> : null;
	}
	if (query.isError || !query.data) {
		return visible ? <div className="p-4 text-sm text-red-300">This snippet could not be opened.</div> : null;
	}

	return <CodeMirrorPane id={id} code={query.data.code} type={query.data.type} visible={visible} readOnly={readOnly} />;
}

function ScreenMessage({ title, detail }: { title: string; detail: string }) {
	return (
		<div className="flex h-full flex-col items-center justify-center gap-2 bg-studio-bg text-studio-text">
			<h1 className="text-lg font-semibold">{title}</h1>
			<p className="max-w-md text-center text-sm text-studio-muted">{detail}</p>
		</div>
	);
}
