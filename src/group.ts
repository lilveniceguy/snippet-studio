import type { Summary } from './types';

export interface ParsedTitle {
	key: string;
	group: string;
	name: string;
}

export function parseTitle(title: string): ParsedTitle {
	const match = title.match(/^\s*\[([^\]]+)\]\s*([\s\S]*)$/);
	if (!match) {
		const name = title.trim();
		return { key: '~', group: 'Ungrouped', name: name || '(untitled)' };
	}

	const group = match[1].trim();
	const name = match[2].trim();
	return {
		key: group.toLowerCase(),
		group,
		name: name || title.trim(),
	};
}

/** Studio names duplicates by appending the word "copy". */
export function isCopyTitle(title: string): boolean {
	return /\bcopy\b/i.test(parseTitle(title).name);
}

export interface SnippetGroup {
	key: string;
	label: string;
	items: Summary[];
}

export function groupSnippets(items: Summary[]): SnippetGroup[] {
	const buckets = new Map<string, { counts: Map<string, number>; items: Summary[] }>();

	items.forEach((item) => {
		const parsed = parseTitle(item.title);
		const bucket = buckets.get(parsed.key) ?? { counts: new Map<string, number>(), items: [] };
		bucket.counts.set(parsed.group, (bucket.counts.get(parsed.group) ?? 0) + 1);
		bucket.items.push(item);
		buckets.set(parsed.key, bucket);
	});

	return Array.from(buckets.entries())
		.map(([key, bucket]) => {
			let label = 'Ungrouped';
			let best = -1;
			bucket.counts.forEach((count, name) => {
				if (count > best) {
					best = count;
					label = name;
				}
			});
			return { key, label, items: bucket.items };
		})
		.sort((a, b) => {
			if (a.key === '~') {
				return 1;
			}
			if (b.key === '~') {
				return -1;
			}
			return a.label.localeCompare(b.label);
		});
}

export function relativeTime(gmt: string): string {
	const then = Date.parse(`${gmt.replace(' ', 'T')}Z`);
	if (Number.isNaN(then)) {
		return '';
	}
	const minutes = Math.max(0, Math.round((Date.now() - then) / 60000));
	if (minutes < 1) {
		return 'just now';
	}
	if (minutes < 60) {
		return `${minutes} min ago`;
	}
	if (minutes < 1440) {
		return `${Math.round(minutes / 60)} h ago`;
	}
	if (minutes < 10080) {
		return `${Math.round(minutes / 1440)} d ago`;
	}
	if (minutes < 43200) {
		return `${Math.round(minutes / 10080)} w ago`;
	}
	return `${Math.round(minutes / 43200)} mo ago`;
}

export function shortTime(gmt: string): string {
	return relativeTime(gmt).replace(' ago', '');
}

export function formatStamp(gmt: string): string {
	const then = Date.parse(`${gmt.replace(' ', 'T')}Z`);
	if (Number.isNaN(then)) {
		return gmt;
	}
	return new Intl.DateTimeFormat(undefined, {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
	}).format(then);
}

export function typeLabel(type: string): string {
	const labels: Record<string, string> = {
		php: 'PHP',
		js: 'JS',
		css: 'CSS',
		html: 'HTML',
		universal: 'Universal',
		text: 'Text',
		advert: 'Ad',
	};
	return labels[type] ?? type.toUpperCase();
}
