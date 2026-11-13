import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import { api, ApiError } from '../api';
import { getEditor } from '../editor/registry';
import { formatStamp, parseTitle, typeLabel } from '../group';
import { applySnippet, resendSave } from '../save';
import { usePrefs, useSession } from '../store';
import type { Bootstrap, SearchHit, Snippet, Summary } from '../types';
import { Dialog } from './Dialog';

const DiffView = lazy(() => import('./DiffView').then((module) => ({ default: module.DiffView })));

export function QuickOpen({ snippets, onOpen }: { snippets: Summary[]; onOpen: (id: number) => void }) {
	const setQuickOpen = useSession((state) => state.setQuickOpen);
	const [query, setQuery] = useState('');
	const [index, setIndex] = useState(0);
	const results = useMemo(() => {
		const needle = query.trim().toLowerCase();
		const ranked = snippets.filter((snippet) => {
			if (!needle) {
				return true;
			}
			return snippet.title.toLowerCase().includes(needle) || String(snippet.id).includes(needle);
		});
		return ranked.slice(0, 30);
	}, [query, snippets]);

	useEffect(() => {
		setIndex(0);
	}, [query]);

	return (
		<Dialog title="Quick open" onClose={() => setQuickOpen(false)}>
			<input
				autoFocus
				value={query}
				onChange={(event) => setQuery(event.target.value)}
				onKeyDown={(event) => {
					if (event.key === 'ArrowDown') {
						event.preventDefault();
						setIndex((current) => Math.min(results.length - 1, current + 1));
					} else if (event.key === 'ArrowUp') {
						event.preventDefault();
						setIndex((current) => Math.max(0, current - 1));
					} else if (event.key === 'Enter' && results[index]) {
						onOpen(results[index].id);
						setQuickOpen(false);
					}
				}}
				placeholder="Snippet name or ID"
				className="mb-2 h-8 w-full rounded-md border border-studio-input bg-studio-editor px-2 text-sm outline-none"
			/>
			<ul className="max-h-80 overflow-auto" role="listbox">
				{results.map((snippet, itemIndex) => (
					<li key={snippet.id}>
						<button
							type="button"
							role="option"
							aria-selected={itemIndex === index}
							onMouseEnter={() => setIndex(itemIndex)}
							onClick={() => {
								onOpen(snippet.id);
								setQuickOpen(false);
							}}
							className={`flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm ${itemIndex === index ? 'bg-studio-row' : ''}`}
						>
							<span className="font-mono text-[10px] text-[#9fb3ff]">{typeLabel(snippet.type)}</span>
							<span className="min-w-0 flex-1 truncate">{parseTitle(snippet.title).name}</span>
							<span className="text-xs text-studio-faint">#{snippet.id}</span>
						</button>
					</li>
				))}
			</ul>
		</Dialog>
	);
}

export function CodeSearch({ onOpen }: { onOpen: (id: number, line?: number) => void }) {
	const setCodeSearch = useSession((state) => state.setCodeSearch);
	const [query, setQuery] = useState('');
	const [debounced, setDebounced] = useState('');

	useEffect(() => {
		const timer = window.setTimeout(() => setDebounced(query.trim()), 300);
		return () => window.clearTimeout(timer);
	}, [query]);

	const results = useQuery({
		queryKey: ['search', debounced],
		queryFn: () => api<SearchHit[]>(`/search?q=${encodeURIComponent(debounced)}`),
		enabled: debounced.length >= 2,
	});

	return (
		<Dialog title="Search snippets" onClose={() => setCodeSearch(false)}>
			<input
				autoFocus
				value={query}
				onChange={(event) => setQuery(event.target.value)}
				placeholder="Search titles and code"
				className="mb-2 h-8 w-full rounded-md border border-studio-input bg-studio-editor px-2 text-sm outline-none"
			/>
			<div className="max-h-96 overflow-auto">
				{results.isFetching && <p className="text-xs text-studio-muted">Searching…</p>}
				{results.data?.map((hit) => (
					<div key={hit.id} className="mb-2 rounded-md border border-studio-line">
						<button
							type="button"
							onClick={() => {
								onOpen(hit.id, hit.matches[0]?.line);
								setCodeSearch(false);
							}}
							className="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm hover:bg-studio-row"
						>
							<span className="min-w-0 flex-1 truncate">{hit.title || `(snippet ${hit.id})`}</span>
							<span className="text-xs text-studio-faint">#{hit.id}</span>
						</button>
						{hit.matches.map((match) => (
							<button
								key={`${hit.id}-${match.line}`}
								type="button"
								onClick={() => {
									onOpen(hit.id, match.line);
									setCodeSearch(false);
								}}
								className="block w-full truncate px-2 py-1 text-left font-mono text-[11px] text-studio-muted hover:bg-studio-editor"
							>
								{match.line}: {match.text}
							</button>
						))}
					</div>
				))}
				{results.data && results.data.length === 0 && <p className="text-xs text-studio-muted">No matches.</p>}
			</div>
		</Dialog>
	);
}

export function SettingsDialog() {
	const setSettingsOpen = useSession((state) => state.setSettingsOpen);
	const prefs = usePrefs();
	return (
		<Dialog title="Editor settings" onClose={() => setSettingsOpen(false)}>
			<div className="grid gap-3 text-sm">
				<label className="flex items-center justify-between gap-3">
					Font size
					<input
						type="number"
						min={11}
						max={22}
						value={prefs.fontSize}
						onChange={(event) => prefs.setFontSize(Number(event.target.value) || 13)}
						className="h-8 w-20 rounded-md border border-studio-input bg-studio-editor px-2"
					/>
				</label>
				<label className="flex items-center justify-between gap-3">
					Indent size
					<input
						type="number"
						min={1}
						max={8}
						value={prefs.tabSize}
						onChange={(event) => prefs.setTabSize(Number(event.target.value) || 4)}
						className="h-8 w-20 rounded-md border border-studio-input bg-studio-editor px-2"
					/>
				</label>
				<label className="flex items-center justify-between gap-3">
					Word wrap
					<input type="checkbox" checked={prefs.wordWrap} onChange={(event) => prefs.setWordWrap(event.target.checked)} />
				</label>
				<label className="flex items-center justify-between gap-3">
					Indent with tabs
					<input type="checkbox" checked={prefs.indentWithTab} onChange={(event) => prefs.setIndentWithTab(event.target.checked)} />
				</label>
				<p className="text-xs text-studio-muted">Ctrl+S save · Ctrl+P quick open · Ctrl+F find · Ctrl+H replace · Ctrl+Shift+F search code · Ctrl+W close tab</p>
			</div>
		</Dialog>
	);
}

export function CreateDialog({ bootstrap }: { bootstrap: Bootstrap }) {
	const setCreateOpen = useSession((state) => state.setCreateOpen);
	const openTab = useSession((state) => state.openTab);
	const client = useQueryClient();
	const [title, setTitle] = useState('');
	const [type, setType] = useState('php');
	const [scope, setScope] = useState('shortcode');
	const [error, setError] = useState('');
	const [pending, setPending] = useState(false);

	const submit = async () => {
		setPending(true);
		setError('');
		try {
			const created = await api<Snippet>('/snippets', 'POST', { title, type, scope });
			client.setQueryData<Snippet>(['snippet', created.id], created);
			await client.invalidateQueries({ queryKey: ['snippets'] });
			openTab(created.id);
			setCreateOpen(false);
		} catch (caught) {
			setError(caught instanceof ApiError ? caught.message : 'Could not create the snippet');
		} finally {
			setPending(false);
		}
	};

	return (
		<Dialog title="New snippet" onClose={() => setCreateOpen(false)}>
			<div className="grid gap-3">
				<label className="grid gap-1 text-xs text-studio-muted">
					Title
					<input value={title} onChange={(event) => setTitle(event.target.value)} className="h-8 rounded-md border border-studio-input bg-studio-editor px-2 text-sm text-studio-text" />
				</label>
				<label className="grid gap-1 text-xs text-studio-muted">
					Type
					<select value={type} onChange={(event) => setType(event.target.value)} className="h-8 rounded-md border border-studio-input bg-studio-editor px-2 text-sm text-studio-text">
						{bootstrap.types.filter((item) => item.value !== 'advert').map((item) => (
							<option key={item.value} value={item.value}>
								{item.label}
							</option>
						))}
					</select>
				</label>
				<label className="grid gap-1 text-xs text-studio-muted">
					Where it runs
					<select value={scope} onChange={(event) => setScope(event.target.value)} className="h-8 rounded-md border border-studio-input bg-studio-editor px-2 text-sm text-studio-text">
						{bootstrap.scopes.map((item) => (
							<option key={item.value} value={item.value}>
								{item.label}
							</option>
						))}
					</select>
				</label>
				<p className="text-xs text-studio-faint">New snippets are inactive until you turn them on.</p>
				{error && <p className="text-xs text-red-300">{error}</p>}
				<button type="button" disabled={pending || title.trim() === ''} onClick={() => void submit()} className="h-8 rounded-md bg-studio-accent text-sm text-white disabled:opacity-40">
					{pending ? 'Creating…' : 'Create snippet'}
				</button>
			</div>
		</Dialog>
	);
}

export function ConflictDialog() {
	const conflict = useSession((state) => state.conflict);
	const setConflict = useSession((state) => state.setConflict);
	const client = useQueryClient();
	if (!conflict) {
		return null;
	}

	const overwrite = () => {
		const latest = getEditor(conflict.id)?.getCode();
		const body: Record<string, unknown> = { ...conflict.body, force: true };
		if (latest !== undefined) {
			body.code = latest;
		}
		void resendSave(client, conflict.id, body);
	};

	const useServer = () => {
		applySnippet(client, conflict.server);
		getEditor(conflict.id)?.setCode(conflict.server.code);
		useSession.getState().clearDraft(conflict.id);
		useSession.getState().setSaveStatus('saved', 'Loaded the saved version');
		setConflict(null);
	};

	return (
		<Dialog title="Snippet changed elsewhere" onClose={() => setConflict(null)} wide>
			<p className="mb-3 text-sm text-studio-muted">{conflict.message}</p>
			<Suspense fallback={<p className="text-sm text-studio-muted">Loading diff…</p>}>
				<DiffView before={conflict.server.code} after={conflict.mine} />
			</Suspense>
			<p className="mt-2 text-xs text-studio-faint">Left: saved on the server. Right: your editor.</p>
			<div className="mt-3 flex justify-end gap-2">
				<button type="button" onClick={() => setConflict(null)} className="h-8 rounded-md border border-studio-input px-3 text-xs">
					Cancel
				</button>
				<button type="button" onClick={useServer} className="h-8 rounded-md border border-studio-input px-3 text-xs">
					Use saved version
				</button>
				<button type="button" onClick={overwrite} className="h-8 rounded-md bg-studio-accent px-3 text-xs text-white">
					Overwrite with mine
				</button>
			</div>
		</Dialog>
	);
}

export function SyntaxDialog() {
	const prompt = useSession((state) => state.syntaxPrompt);
	const setSyntaxPrompt = useSession((state) => state.setSyntaxPrompt);
	const client = useQueryClient();
	if (!prompt) {
		return null;
	}
	return (
		<Dialog title="Syntax check" onClose={() => setSyntaxPrompt(null)}>
			<p className="text-sm text-red-300">{prompt.message}</p>
			<p className="mt-2 text-xs text-studio-muted">
				{prompt.canOverride
					? 'This snippet is inactive, so you can save it and fix it later.'
					: 'Active PHP is not saved while it has a syntax error. Deactivate it or fix the code.'}
			</p>
			<div className="mt-3 flex justify-end gap-2">
				<button type="button" onClick={() => setSyntaxPrompt(null)} className="h-8 rounded-md border border-studio-input px-3 text-xs">
					Back to editor
				</button>
				{prompt.canOverride && (
					<button
						type="button"
						onClick={() => void resendSave(client, prompt.id, { ...prompt.body, ignore_syntax: true })}
						className="h-8 rounded-md bg-studio-accent px-3 text-xs text-white"
					>
						Save anyway
					</button>
				)}
			</div>
		</Dialog>
	);
}

export function CloseDialog() {
	const pending = useSession((state) => state.pendingClose);
	const setPendingClose = useSession((state) => state.setPendingClose);
	const closeTab = useSession((state) => state.closeTab);
	const client = useQueryClient();
	if (!pending) {
		return null;
	}
	return (
		<Dialog title="Unsaved changes" onClose={() => setPendingClose(null)}>
			<p className="text-sm text-studio-muted">This tab has changes that are not saved.</p>
			<div className="mt-3 flex justify-end gap-2">
				<button type="button" onClick={() => setPendingClose(null)} className="h-8 rounded-md border border-studio-input px-3 text-xs">
					Keep editing
				</button>
				<button
					type="button"
					onClick={() => {
						closeTab(pending);
						setPendingClose(null);
					}}
					className="h-8 rounded-md border border-studio-input px-3 text-xs"
				>
					Discard
				</button>
				<button
					type="button"
					onClick={() => {
						void import('../save').then(({ saveSnippet }) => saveSnippet(client, pending));
						setPendingClose(null);
					}}
					className="h-8 rounded-md bg-studio-accent px-3 text-xs text-white"
				>
					Save
				</button>
			</div>
		</Dialog>
	);
}

export function RevisionsDialog() {
	const snippetId = useSession((state) => state.revisionsFor);
	const setRevisionsFor = useSession((state) => state.setRevisionsFor);
	const client = useQueryClient();
	const [selected, setSelected] = useState<string>('');
	const [confirming, setConfirming] = useState(false);
	const [error, setError] = useState('');

	const list = useQuery({
		queryKey: ['revisions', snippetId],
		queryFn: () => api<import('../types').RevisionSummary[]>(`/snippets/${snippetId}/revisions`),
		enabled: snippetId !== null,
	});

	const detail = useQuery({
		queryKey: ['revision', selected],
		queryFn: () => {
			const [source, id] = selected.split(':');
			return api<import('../types').RevisionDetail>(`/revisions/${source}/${id}`);
		},
		enabled: selected !== '',
	});

	if (snippetId === null) {
		return null;
	}

	const current = client.getQueryData<Snippet>(['snippet', snippetId]);
	const currentCode = (snippetId !== null && getEditor(snippetId)?.getCode()) || current?.code || '';

	const restore = async () => {
		if (!current || !detail.data) {
			return;
		}
		setError('');
		try {
			const saved = await api<Snippet>(`/revisions/${detail.data.source}/${detail.data.id}/restore`, 'POST', {
				base_modified: current.modified_gmt,
				base_code_hash: current.code_hash,
				base_meta_token: current.meta_token,
			});
			applySnippet(client, saved);
			getEditor(snippetId)?.setCode(saved.code);
			useSession.getState().clearDraft(snippetId);
			await client.invalidateQueries({ queryKey: ['revisions', snippetId] });
			setConfirming(false);
			setRevisionsFor(null);
		} catch (caught) {
			setError(caught instanceof ApiError ? caught.message : 'Restore failed');
		}
	};

	return (
		<Dialog title="Revision history" onClose={() => setRevisionsFor(null)} wide>
			<div className="grid gap-3 md:grid-cols-[240px_1fr]">
				<div className="max-h-[460px] overflow-auto">
					{(list.data ?? []).map((revision) => {
						const key = `${revision.source}:${revision.id}`;
						return (
							<button
								key={key}
								type="button"
								onClick={() => {
									setSelected(key);
									setConfirming(false);
								}}
								className={`mb-1 block w-full rounded px-2 py-1.5 text-left text-xs ${selected === key ? 'bg-studio-row' : 'hover:bg-studio-editor'}`}
							>
								<div>{formatStamp(revision.created_gmt)}</div>
								<div className="text-studio-faint">
									{revision.author} · {revision.source === 'wordpress' ? 'WordPress' : revision.kind}
								</div>
							</button>
						);
					})}
					{list.data && list.data.length === 0 && <p className="text-xs text-studio-muted">No stored revisions.</p>}
				</div>
				<div>
					{detail.data && current ? (
						<>
							<Suspense fallback={<p className="text-sm">Loading diff…</p>}>
								<DiffView before={detail.data.code} after={currentCode} />
							</Suspense>
							<p className="mt-2 text-xs text-studio-faint">Left: this revision. Right: the current editor copy on the server.</p>
							{detail.data.note && <p className="mt-1 text-xs text-studio-muted">{detail.data.note}</p>}
							{error && <p className="mt-2 text-xs text-red-300">{error}</p>}
							<div className="mt-3 flex justify-end">
								{confirming ? (
									<button type="button" onClick={() => void restore()} className="h-8 rounded-md bg-studio-accent px-3 text-xs text-white">
										Confirm restore
									</button>
								) : (
									<button type="button" onClick={() => setConfirming(true)} className="h-8 rounded-md border border-studio-input px-3 text-xs">
										Restore this revision
									</button>
								)}
							</div>
						</>
					) : (
						<p className="text-sm text-studio-muted">Choose a revision to compare it with the current snippet.</p>
					)}
				</div>
			</div>
		</Dialog>
	);
}
