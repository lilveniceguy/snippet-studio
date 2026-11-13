import { ChevronsDownUp, ChevronsUpDown, ChevronRight, Folder, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { groupSnippets, isCopyTitle, parseTitle, relativeTime, shortTime, typeLabel } from '../group';
import { useLayout, useSession, type SortMode } from '../store';
import type { Summary } from '../types';

const badge: Record<string, string> = {
	php: 'bg-[#262c45] text-[#9fb3ff]',
	js: 'bg-[#3a2e22] text-[#f2b880]',
	css: 'bg-[#1d3140] text-[#7cc4fa]',
	html: 'bg-[#243024] text-[#b5df8c]',
};

interface ExplorerProps {
	snippets: Summary[];
	onOpen: (id: number) => void;
	onDuplicate: (id: number) => void;
	onToggleActive: (snippet: Summary) => void;
	onTrash: (snippet: Summary) => void;
}

export function Explorer({ snippets, onOpen, onDuplicate, onToggleActive, onTrash }: ExplorerProps) {
	const query = useSession((state) => state.query);
	const setQuery = useSession((state) => state.setQuery);
	const activeId = useSession((state) => state.activeId);
	const sort = useLayout((state) => state.sort);
	const setSort = useLayout((state) => state.setSort);
	const typeFilter = useLayout((state) => state.typeFilter);
	const setTypeFilter = useLayout((state) => state.setTypeFilter);
	const statusFilter = useLayout((state) => state.statusFilter);
	const setStatusFilter = useLayout((state) => state.setStatusFilter);
	const collapsed = useLayout((state) => state.collapsed);
	const toggleGroup = useLayout((state) => state.toggleGroup);
	const setGroupsCollapsed = useLayout((state) => state.setGroupsCollapsed);
	const [limits, setLimits] = useState<Record<string, number>>({});
	const [menu, setMenu] = useState<{ id: number; x: number; y: number } | null>(null);

	const filtered = useMemo(() => {
		const needle = query.trim().toLowerCase();
		return snippets.filter((snippet) => {
			if (typeFilter !== 'all' && snippet.type !== typeFilter) {
				return false;
			}
			if (statusFilter === 'active' && !snippet.active) {
				return false;
			}
			if (statusFilter === 'inactive' && snippet.active) {
				return false;
			}
			if (!needle) {
				return true;
			}
			return snippet.title.toLowerCase().includes(needle) || String(snippet.id).includes(needle);
		});
	}, [query, snippets, statusFilter, typeFilter]);

	const groups = useMemo(() => {
		const stamp = (value: string) => Date.parse(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);
		const sorter = (a: Summary, b: Summary) => {
			if (sort === 'name') {
				return parseTitle(a.title).name.localeCompare(parseTitle(b.title).name);
			}
			if (sort === 'priority') {
				return Number(b.priority) - Number(a.priority);
			}
			return stamp(b.modified_gmt) - stamp(a.modified_gmt);
		};
		return groupSnippets(filtered).map((group) => ({
			...group,
			items: group.items.slice().sort(sorter),
		}));
	}, [filtered, sort]);

	const recent = useMemo(() => {
		return snippets
			.slice()
			.sort((a, b) => {
			const stamp = (value: string) => Date.parse(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);
			return stamp(b.modified_gmt) - stamp(a.modified_gmt);
		})
			.slice(0, 4);
	}, [snippets]);

	const showRecent = sort === 'recent' && query.trim() === '' && typeFilter === 'all' && statusFilter === 'all';
	const sorts: Array<[SortMode, string]> = [
		['recent', 'Recent'],
		['name', 'A–Z'],
		['priority', 'Priority'],
	];

	return (
		<div className="flex h-full min-h-0 flex-col" onClick={() => setMenu(null)}>
			<div className="flex flex-col gap-2.5 px-3 pb-2 pt-3">
				<div className="flex items-center justify-between">
					<span className="text-[11px] font-semibold tracking-[0.08em] text-studio-muted">EXPLORER</span>
					<span className="text-[11px] text-studio-muted">
						{filtered.length} of {snippets.length}
					</span>
				</div>
				<label className="sr-only" htmlFor="sst-filter">
					Filter snippets
				</label>
				<div className="relative">
					<Search className="pointer-events-none absolute left-2 top-2 text-studio-muted" size={14} />
					<input
						id="sst-filter"
						type="search"
						value={query}
						onChange={(event) => setQuery(event.target.value)}
						placeholder="Filter by name…"
						className="h-[30px] w-full rounded-md border border-studio-input bg-studio-editor pl-7 pr-2 text-[13px] text-studio-text outline-none"
					/>
				</div>
				<div className="flex gap-1 rounded-md border border-studio-line bg-studio-editor p-[3px]">
					{sorts.map(([key, label]) => (
						<button
							key={key}
							type="button"
							aria-pressed={sort === key}
							onClick={() => setSort(key)}
							className={`h-6 flex-1 rounded text-xs ${sort === key ? 'bg-studio-row font-medium text-white' : 'text-studio-muted'}`}
						>
							{label}
						</button>
					))}
				</div>
				<div className="flex gap-2">
					<select
						aria-label="Filter by type"
						value={typeFilter}
						onChange={(event) => setTypeFilter(event.target.value)}
						className="h-7 flex-1 rounded-md border border-studio-line bg-studio-editor px-1 text-xs text-studio-text"
					>
						<option value="all">All types</option>
						<option value="php">PHP</option>
						<option value="js">JavaScript</option>
						<option value="css">CSS</option>
						<option value="html">HTML</option>
						<option value="universal">Universal</option>
						<option value="text">Text</option>
					</select>
					<select
						aria-label="Filter by status"
						value={statusFilter}
						onChange={(event) => setStatusFilter(event.target.value as 'all' | 'active' | 'inactive')}
						className="h-7 flex-1 rounded-md border border-studio-line bg-studio-editor px-1 text-xs text-studio-text"
					>
						<option value="all">Any status</option>
						<option value="active">Active</option>
						<option value="inactive">Inactive</option>
					</select>
				</div>
			</div>
			<div className="min-h-0 flex-1 overflow-y-auto px-1.5 pb-3">
				{showRecent && (
					<div>
						<div className="px-2 pb-1 pt-2 text-[11px] font-semibold tracking-[0.06em] text-studio-muted">RECENTLY EDITED</div>
						{recent.map((snippet) => (
							<Row key={`recent-${snippet.id}`} snippet={snippet} active={snippet.id === activeId} side={shortTime(snippet.modified_gmt)} indent={false} onOpen={onOpen} onMenu={setMenu} />
						))}
						<div className="mx-1.5 my-2 h-px bg-studio-line" />
					</div>
				)}
				{groups.length > 0 && (
					<div className="flex items-center justify-end gap-0.5 px-1 pb-1">
						<button
							type="button"
							aria-label="Collapse all groups"
							title="Collapse all"
							onClick={() => setGroupsCollapsed(groups.map((group) => group.key), true)}
							className="rounded p-1 text-studio-muted hover:bg-studio-row hover:text-white"
						>
							<ChevronsDownUp size={14} />
						</button>
						<button
							type="button"
							aria-label="Expand all groups"
							title="Expand all"
							onClick={() => setGroupsCollapsed(groups.map((group) => group.key), false)}
							className="rounded p-1 text-studio-muted hover:bg-studio-row hover:text-white"
						>
							<ChevronsUpDown size={14} />
						</button>
					</div>
				)}
				{groups.map((group) => {
					const open = query.trim() !== '' || !collapsed[group.key];
					const limit = limits[group.key] ?? 120;
					const visible = group.items.slice(0, limit);
					return (
						<div key={group.key}>
							<button
								type="button"
								aria-expanded={open}
								onClick={() => toggleGroup(group.key)}
								className="flex w-full items-center gap-1.5 px-2 py-1.5 text-left text-xs font-semibold text-[#c9cbd1]"
							>
								<ChevronRight size={14} className={`shrink-0 text-studio-muted transition-transform ${open ? 'rotate-90' : ''}`} />
								<Folder size={14} className="shrink-0 text-studio-muted" />
								<span className="min-w-0 flex-1 truncate">{group.label}</span>
								<span className="rounded-full bg-[#2a2c34] px-1.5 text-[11px] font-medium text-studio-muted">{group.items.length}</span>
							</button>
							{open &&
								visible.map((snippet) => (
									<Row
										key={snippet.id}
										snippet={snippet}
										active={snippet.id === activeId}
										side={sort === 'priority' ? snippet.priority : shortTime(snippet.modified_gmt)}
										indent
										onOpen={onOpen}
										onMenu={setMenu}
									/>
								))}
							{open && group.items.length > limit && (
								<button
									type="button"
									className="px-8 py-1 text-xs text-[#8fb0ff]"
									onClick={() => setLimits((current) => ({ ...current, [group.key]: limit + 120 }))}
								>
									Show {Math.min(120, group.items.length - limit)} more
								</button>
							)}
						</div>
					);
				})}
				{groups.length === 0 && <p className="px-3 py-6 text-center text-studio-muted">No snippets match.</p>}
			</div>
			{menu && (
				<div
					role="menu"
					className="fixed z-40 w-44 rounded-md border border-studio-line bg-studio-header py-1 shadow-lg"
					style={{ left: menu.x, top: menu.y }}
				>
					<button type="button" className="block w-full px-3 py-1.5 text-left text-xs hover:bg-studio-row" onClick={() => onOpen(menu.id)}>
						Open
					</button>
					<button
						type="button"
						className="block w-full px-3 py-1.5 text-left text-xs hover:bg-studio-row"
						onClick={() => onDuplicate(menu.id)}
					>
						Duplicate
					</button>
					<button
						type="button"
						className="block w-full px-3 py-1.5 text-left text-xs hover:bg-studio-row"
						onClick={() => {
							const snippet = snippets.find((item) => item.id === menu.id);
							if (snippet) {
								onToggleActive(snippet);
							}
						}}
					>
						{snippets.find((item) => item.id === menu.id)?.active ? 'Deactivate' : 'Activate'}
					</button>
					<button
						type="button"
						className="block w-full px-3 py-1.5 text-left text-xs text-[#f2c14b] hover:bg-studio-row"
						onClick={() => {
							const snippet = snippets.find((item) => item.id === menu.id);
							if (snippet) {
								onTrash(snippet);
							}
						}}
					>
						Move to trash
					</button>
				</div>
			)}
		</div>
	);
}

function Row({
	snippet,
	active,
	side,
	indent,
	onOpen,
	onMenu,
}: {
	snippet: Summary;
	active: boolean;
	side: string;
	indent: boolean;
	onOpen: (id: number) => void;
	onMenu: (menu: { id: number; x: number; y: number }) => void;
}) {
	const parsed = parseTitle(snippet.title);
	const copy = isCopyTitle(snippet.title);
	return (
		<button
			type="button"
			onClick={() => onOpen(snippet.id)}
			onContextMenu={(event) => {
				event.preventDefault();
				onMenu({ id: snippet.id, x: event.clientX, y: event.clientY });
			}}
			className={`flex w-full items-center gap-2 rounded px-2 py-1 text-left text-[13px] ${active ? 'bg-studio-row text-white' : 'text-[#c9cbd1]'} ${indent ? 'pl-7' : ''}`}
		>
			<span
				className={`h-2 w-2 shrink-0 rounded-full ${snippet.active ? 'bg-[#4ade80]' : 'border border-studio-faint'}`}
				aria-label={snippet.active ? 'Active' : 'Inactive'}
			/>
			<span className={`rounded px-1 font-mono text-[10px] ${badge[snippet.type] ?? 'bg-[#2a2c34] text-studio-muted'}`}>{typeLabel(snippet.type)}</span>
			<span className={`min-w-0 flex-1 truncate ${copy ? 'text-[#f2c14b]' : ''}`}>{parsed.name}</span>
			{copy && <span className="shrink-0 rounded bg-[#3a3418] px-1 text-[10px] font-semibold uppercase tracking-wide text-[#f2c14b]">Copy</span>}
			<span className="shrink-0 text-[11px] text-studio-faint">{side || relativeTime(snippet.modified_gmt)}</span>
		</button>
	);
}
