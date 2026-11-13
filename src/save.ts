import type { QueryClient } from '@tanstack/react-query';
import { api, ApiError } from './api';
import { getEditor } from './editor/registry';
import { useSession } from './store';
import type { Draft, Snippet, Summary } from './types';

export function applySnippet(client: QueryClient, snippet: Snippet): void {
	client.setQueryData(['snippet', snippet.id], snippet);
	client.setQueryData<Summary[]>(['snippets'], (rows) => {
		if (!rows) {
			return rows;
		}
		const summary: Summary = {
			id: snippet.id,
			title: snippet.title,
			status: snippet.status,
			type: snippet.type,
			active: snippet.active,
			scope: snippet.scope,
			priority: snippet.priority,
			location: snippet.location,
			modified_gmt: snippet.modified_gmt,
			date_gmt: snippet.date_gmt,
			meta_token: snippet.meta_token,
			author: snippet.author,
			editor: snippet.editor,
		};
		const index = rows.findIndex((row) => row.id === snippet.id);
		if (index === -1) {
			return [summary, ...rows];
		}
		const next = rows.slice();
		next[index] = summary;
		return next;
	});
}

function changedProperties(snippet: Snippet, draft: Draft | undefined): Record<string, unknown> {
	const properties: Record<string, unknown> = {};
	if (!draft) {
		return properties;
	}
	if (draft.scope !== undefined && draft.scope !== snippet.scope) {
		properties.scope = draft.scope;
	}
	if (draft.location !== undefined && draft.location !== snippet.location) {
		properties.location = draft.location;
	}
	if (draft.locationNumber !== undefined && draft.locationNumber !== snippet.location_number) {
		properties.location_number = draft.locationNumber;
	}
	if (draft.priority !== undefined && draft.priority !== snippet.priority) {
		properties.priority = draft.priority;
	}
	if (draft.description !== undefined && draft.description !== snippet.description) {
		properties.description = draft.description;
	}
	if (draft.tags !== undefined && draft.tags !== snippet.tags) {
		properties.tags = draft.tags;
	}
	if (draft.linking !== undefined && draft.linking !== snippet.linking) {
		properties.linking = draft.linking;
	}
	if (draft.active !== undefined && draft.active !== snippet.active) {
		properties.active = draft.active;
	}
	return properties;
}

function finishSave(id: number, sentCode: string | null, saved: Snippet): void {
	const editor = getEditor(id);
	if (editor && sentCode !== null) {
		const current = editor.getCode();
		if (saved.code !== sentCode && current === sentCode) {
			editor.setCode(saved.code);
		} else {
			editor.markSaved(sentCode);
		}
	}
	useSession.getState().clearDraft(id);
	useSession.getState().setSyntaxMessage(
		saved.syntax?.checked ? (saved.syntax.valid ? 'No syntax errors' : saved.syntax.message) : ''
	);
	useSession.getState().setSaveStatus('saved', 'Saved');
	useSession.getState().setConflict(null);
	useSession.getState().setSyntaxPrompt(null);
}

async function commit(client: QueryClient, id: number, body: Record<string, unknown>): Promise<void> {
	const session = useSession.getState();
	if (session.saveState === 'saving') {
		return;
	}
	session.setSaveStatus('saving', 'Saving…');
	try {
		const saved = await api<Snippet>(`/snippets/${id}`, 'PUT', body);
		applySnippet(client, saved);
		finishSave(id, typeof body.code === 'string' ? body.code : null, saved);
	} catch (error) {
		const apiError = error instanceof ApiError ? error : new ApiError({ message: 'Save failed' });
		if (apiError.code === 'snippet_studio_conflict' && apiError.data.server) {
			useSession.getState().setConflict({
				id,
				message: apiError.message,
				server: apiError.data.server as Snippet,
				mine: getEditor(id)?.getCode() ?? (typeof body.code === 'string' ? body.code : ''),
				body,
			});
		} else if (apiError.code === 'snippet_studio_syntax') {
			useSession.getState().setSyntaxPrompt({
				id,
				message: apiError.message,
				canOverride: Boolean(apiError.data.can_override),
				body,
			});
		}
		useSession.getState().setSaveStatus('error', apiError.message);
	}
}

export async function saveSnippet(
	client: QueryClient,
	id: number,
	options?: { force?: boolean; ignoreSyntax?: boolean }
): Promise<void> {
	const snippet = client.getQueryData<Snippet>(['snippet', id]);
	if (!snippet) {
		return;
	}
	const session = useSession.getState();
	const draft = session.drafts[id];
	const code = getEditor(id)?.getCode();
	const properties = changedProperties(snippet, draft);
	const body: Record<string, unknown> = {
		base_modified: snippet.modified_gmt,
		base_code_hash: snippet.code_hash,
		base_meta_token: snippet.meta_token,
		force: Boolean(options?.force),
		ignore_syntax: Boolean(options?.ignoreSyntax),
	};
	if (draft?.title !== undefined && draft.title !== snippet.title) {
		body.title = draft.title;
	}
	if (code !== undefined && code !== snippet.code) {
		body.code = code;
	}
	if (Object.keys(properties).length > 0) {
		body.properties = properties;
	}
	if (body.title === undefined && body.code === undefined && body.properties === undefined) {
		session.setSaveStatus('saved', 'Saved');
		return;
	}
	await commit(client, id, body);
}

export async function resendSave(client: QueryClient, id: number, body: Record<string, unknown>): Promise<void> {
	await commit(client, id, body);
}

export async function setSnippetActive(client: QueryClient, id: number, active: boolean): Promise<void> {
	let snippet = client.getQueryData<Snippet>(['snippet', id]);
	if (!snippet) {
		snippet = await api<Snippet>(`/snippets/${id}`);
		client.setQueryData(['snippet', id], snippet);
	}
	const rows = client.getQueryData<Summary[]>(['snippets']);
	const summary = rows?.find((row) => row.id === id);
	useSession.getState().setSaveStatus('saving', active ? 'Activating…' : 'Deactivating…');
	try {
		const saved = await api<Snippet>(`/snippets/${id}/status`, 'POST', {
			active,
			base_modified: snippet.modified_gmt,
			base_meta_token: snippet.meta_token || summary?.meta_token || '',
			base_code_hash: snippet.code_hash,
		});
		applySnippet(client, saved);
		useSession.getState().setDraft(id, { active: undefined });
		useSession.getState().setSaveStatus('saved', saved.active ? 'Active' : 'Inactive');
	} catch (error) {
		const apiError = error instanceof ApiError ? error : new ApiError({ message: 'Could not update status' });
		useSession.getState().setSaveStatus('error', apiError.message);
		throw apiError;
	}
}
