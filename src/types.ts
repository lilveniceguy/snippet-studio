export interface Summary {
	id: number;
	title: string;
	status: string;
	type: string;
	active: boolean;
	scope: string;
	priority: string;
	location: string;
	modified_gmt: string;
	date_gmt: string;
	meta_token: string;
	author: string;
	editor: string;
}

export interface Snippet extends Summary {
	code: string;
	code_hash: string;
	meta_token: string;
	location_number: string;
	linking: string;
	description: string;
	tags: string;
	shortcode: string;
	woody_url: string;
	syntax?: SyntaxResult;
}

export interface SyntaxResult {
	valid: boolean;
	checked: boolean;
	message: string;
}

export interface LocationOption {
	value: string;
	label: string;
	description: string;
	requires_number: boolean;
}

export interface LabeledValue {
	value: string;
	label: string;
}

export interface Bootstrap {
	version: string;
	woody_active: boolean;
	woody_version: string;
	post_type: string;
	can_write: boolean;
	file_edit_locked: boolean;
	user: { id: number; name: string };
	scopes: LabeledValue[];
	types: LabeledValue[];
	locations: LocationOption[];
	classic_url: string;
}

export interface RevisionSummary {
	id: number;
	snippet_id: number;
	source: 'studio' | 'wordpress';
	kind: string;
	created_gmt: string;
	title: string;
	note: string;
	author: string;
	user_id: number;
	has_meta?: boolean;
}

export interface RevisionDetail extends RevisionSummary {
	code: string;
	meta: Record<string, string> | null;
}

export interface SearchHit {
	id: number;
	title: string;
	type: string;
	active: boolean;
	modified_gmt: string;
	title_match: boolean;
	matches: Array<{ line: number; text: string }>;
}

export interface Draft {
	title?: string;
	scope?: string;
	location?: string;
	locationNumber?: string;
	priority?: string;
	description?: string;
	tags?: string;
	linking?: string;
	active?: boolean;
}

export interface ConflictState {
	id: number;
	message: string;
	server: Snippet;
	mine: string;
	body: Record<string, unknown>;
}

export interface SyntaxPrompt {
	id: number;
	message: string;
	canOverride: boolean;
	body: Record<string, unknown>;
}
