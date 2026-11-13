import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Copy } from 'lucide-react';
import { api } from '../api';
import { formatStamp, isCopyTitle, parseTitle, relativeTime } from '../group';
import { setSnippetActive } from '../save';
import { useSession } from '../store';
import type { Bootstrap, Draft, RevisionSummary, Snippet } from '../types';

const emptyDraft: Draft = {};

const field =
	'h-8 w-full rounded-md border border-studio-input bg-studio-editor px-2 text-[13px] text-studio-text outline-none focus:border-studio-accent';

interface PropertiesProps {
	snippet: Snippet;
	bootstrap: Bootstrap;
	canWrite: boolean;
	onDuplicate: () => void;
	onTrash: () => void;
}

export function Properties({ snippet, bootstrap, canWrite, onDuplicate, onTrash }: PropertiesProps) {
	const draft = useSession((state) => state.drafts[snippet.id] ?? emptyDraft);
	const setDraft = useSession((state) => state.setDraft);
	const setRevisionsFor = useSession((state) => state.setRevisionsFor);
	const client = useQueryClient();
	const revisions = useQuery({
		queryKey: ['revisions', snippet.id],
		queryFn: () => api<RevisionSummary[]>(`/snippets/${snippet.id}/revisions`),
		enabled: true,
		staleTime: 15_000,
	});

	const title = draft.title ?? snippet.title;
	const scope = draft.scope ?? snippet.scope;
	const location = draft.location ?? snippet.location;
	const locationNumber = draft.locationNumber ?? snippet.location_number;
	const priority = draft.priority ?? snippet.priority;
	const description = draft.description ?? snippet.description;
	const tags = draft.tags ?? snippet.tags;
	const linking = draft.linking ?? snippet.linking;
	const active = draft.active ?? snippet.active;
	const parsed = parseTitle(snippet.title);
	const copy = isCopyTitle(title);
	const knownScope = bootstrap.scopes.some((item) => item.value === scope);
	const locationMeta = bootstrap.locations.find((item) => item.value === location);
	const showLinking = snippet.type === 'js' || snippet.type === 'css';

	const copyShortcode = () => {
		void navigator.clipboard?.writeText(snippet.shortcode);
	};

	const toggle = () => {
		if (!canWrite) {
			return;
		}
		const next = !active;
		if (next && !window.confirm('Activate this snippet? Woody will be able to run it.')) {
			return;
		}
		void setSnippetActive(client, snippet.id, next).catch(() => undefined);
	};

	return (
		<div className="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-4">
			<div>
				<div className="text-[11px] font-semibold tracking-[0.06em] text-studio-muted">SNIPPET #{snippet.id}</div>
				<input
					aria-label="Snippet title"
					value={title}
					disabled={!canWrite}
					onChange={(event) => setDraft(snippet.id, { title: event.target.value })}
					className={`${field} mt-1 h-auto py-1 font-semibold ${copy ? 'text-[#f2c14b]' : ''}`}
				/>
				{copy ? (
					<p className="mt-1 text-[11px] text-[#f2c14b]">This title contains “copy”, so it is marked as a duplicate. Rename it here, or move it to the trash.</p>
				) : (
					<p className="mt-1 text-[11px] text-studio-faint">
						Stored title. {parsed.group === 'Ungrouped' ? 'No group prefix.' : `Grouped as ${parsed.group} because of the [prefix].`}
					</p>
				)}
			</div>

			<div className="flex items-center justify-between rounded-lg bg-studio-chip px-3 py-2.5">
				<div>
					<div className="font-medium">Status</div>
					<div className="text-xs text-studio-muted">{active ? 'Active — running on the site' : 'Inactive — not running'}</div>
				</div>
				<button
					type="button"
					role="switch"
					aria-checked={active}
					aria-label="Snippet active"
					disabled={!canWrite}
					onClick={toggle}
					className="relative h-[22px] w-10 rounded-full"
					style={{ background: active ? '#3858e9' : '#4a4d57' }}
				>
					<span className="absolute top-[3px] h-4 w-4 rounded-full bg-white" style={{ left: active ? 21 : 3 }} />
				</button>
			</div>

			<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
				Where it runs
				<select
					value={scope}
					disabled={!canWrite}
					onChange={(event) => setDraft(snippet.id, { scope: event.target.value })}
					className={field}
				>
					{scope === '' && <option value="">Not set</option>}
					{!knownScope && scope !== '' && <option value={scope}>{scope}</option>}
					{bootstrap.scopes.map((item) => (
						<option key={item.value} value={item.value}>
							{item.label}
						</option>
					))}
				</select>
			</label>

			{(scope === 'shortcode' || snippet.shortcode) && (
				<div className="flex items-center gap-1.5 rounded-md border border-dashed border-[#3a3d47] bg-studio-editor px-2 py-1.5">
					<code className="min-w-0 flex-1 truncate font-mono text-[11px] text-[#b5df8c]">{snippet.shortcode}</code>
					<button type="button" onClick={copyShortcode} className="rounded bg-[#2a2c34] px-2 py-0.5 text-[11px] text-[#c9cbd1]">
						<Copy size={12} className="inline" /> Copy
					</button>
				</div>
			)}

			{(scope === 'auto' || location !== '') && (
				<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
					Location
					<select
						value={location}
						disabled={!canWrite}
						onChange={(event) => setDraft(snippet.id, { location: event.target.value })}
						className={field}
					>
						{location !== '' && !bootstrap.locations.some((item) => item.value === location) && <option value={location}>{location}</option>}
						<option value="">Select a location</option>
						{bootstrap.locations.map((item) => (
							<option key={item.value} value={item.value}>
								{item.label}
							</option>
						))}
					</select>
				</label>
			)}

			{(locationMeta?.requires_number || locationNumber !== '') && (
				<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
					Location number
					<input
						value={locationNumber}
						disabled={!canWrite}
						onChange={(event) => setDraft(snippet.id, { locationNumber: event.target.value })}
						className={field}
					/>
				</label>
			)}

			<div className="grid grid-cols-2 gap-2.5">
				<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
					Priority
					<input
						type="number"
						value={priority}
						disabled={!canWrite}
						onChange={(event) => setDraft(snippet.id, { priority: event.target.value })}
						className={field}
					/>
				</label>
				<div className="flex flex-col gap-1.5 text-xs text-studio-muted">
					Type
					<div className="flex h-8 items-center rounded-md bg-studio-chip px-2 text-[13px] text-studio-text">{snippet.type.toUpperCase()}</div>
				</div>
			</div>

			{showLinking && (
				<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
					Asset linking
					<select
						value={linking || 'external'}
						disabled={!canWrite}
						onChange={(event) => setDraft(snippet.id, { linking: event.target.value })}
						className={field}
					>
						{linking !== '' && linking !== 'external' && linking !== 'inline' && <option value={linking}>{linking}</option>}
						<option value="external">External file</option>
						<option value="inline">Inline</option>
					</select>
				</label>
			)}

			<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
				Description
				<textarea
					value={description}
					disabled={!canWrite}
					onChange={(event) => setDraft(snippet.id, { description: event.target.value })}
					rows={3}
					className="w-full rounded-md border border-studio-input bg-studio-editor px-2 py-1.5 text-[13px] text-studio-text outline-none"
				/>
			</label>

			<label className="flex flex-col gap-1.5 text-xs text-studio-muted">
				Tags
				<input
					value={tags}
					disabled={!canWrite}
					onChange={(event) => setDraft(snippet.id, { tags: event.target.value })}
					placeholder="Comma-separated"
					className={field}
				/>
				<span className="text-[11px] text-studio-faint">Stored in wbcr_inp_snippet_tags. Woody uses these as shortcode attribute names.</span>
			</label>

			<dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 border-t border-studio-line pt-3 text-xs">
				<dt className="text-studio-muted">Last edited</dt>
				<dd className="m-0 text-right">{relativeTime(snippet.modified_gmt)}</dd>
				<dt className="text-studio-muted">Edited by</dt>
				<dd className="m-0 text-right">{snippet.editor || '—'}</dd>
				<dt className="text-studio-muted">Created</dt>
				<dd className="m-0 text-right">{formatStamp(snippet.date_gmt)}</dd>
			</dl>

			<div>
				<div className="mb-2 text-[11px] font-semibold tracking-[0.06em] text-studio-muted">REVISIONS</div>
				{(revisions.data ?? []).slice(0, 4).map((revision) => (
					<div key={`${revision.source}-${revision.id}`} className="mb-1.5 flex items-center gap-2 text-xs">
						<span className="h-1.5 w-1.5 rounded-full bg-[#6c7080]" />
						<span className="min-w-0 flex-1 truncate">
							{formatStamp(revision.created_gmt)} · {revision.author}
						</span>
					</div>
				))}
				{revisions.data && revisions.data.length === 0 && <p className="text-xs text-studio-faint">No revisions yet.</p>}
				<button type="button" onClick={() => setRevisionsFor(snippet.id)} className="mt-1 text-xs text-[#8fb0ff]">
					Open history
				</button>
			</div>

			<div className="mt-auto flex flex-col gap-2 border-t border-studio-line pt-3">
				<button type="button" disabled={!canWrite} onClick={onDuplicate} className="h-8 rounded-md border border-studio-input text-xs text-[#c9cbd1] disabled:opacity-40">
					Duplicate
				</button>
				<button type="button" disabled={!canWrite} onClick={onTrash} className="h-8 rounded-md border border-[#6b5a28] text-xs text-[#f2c14b] disabled:opacity-40">
					Move to trash
				</button>
				<a href={snippet.woody_url} className="text-center text-xs text-[#8fb0ff] no-underline hover:text-[#b8ccff]">
					Open in Woody editor
				</a>
			</div>
		</div>
	);
}
