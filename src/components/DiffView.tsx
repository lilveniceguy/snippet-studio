import { EditorView } from '@codemirror/view';
import { MergeView } from '@codemirror/merge';
import { oneDark } from '@codemirror/theme-one-dark';
import { useEffect, useRef } from 'react';

interface DiffViewProps {
	before: string;
	after: string;
}

export function DiffView({ before, after }: DiffViewProps) {
	const parent = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const host = parent.current;
		if (!host) {
			return undefined;
		}
		const view = new MergeView({
			parent: host,
			a: {
				doc: before,
				extensions: [oneDark, EditorView.editable.of(false), EditorStateReadOnly()],
			},
			b: {
				doc: after,
				extensions: [oneDark, EditorView.editable.of(false), EditorStateReadOnly()],
			},
			highlightChanges: true,
			gutter: true,
		});
		return () => view.destroy();
	}, [after, before]);

	return <div ref={parent} className="h-[420px] overflow-hidden rounded-md border border-studio-line" />;
}

function EditorStateReadOnly() {
	return EditorView.editorAttributes.of({ 'aria-readonly': 'true' });
}
