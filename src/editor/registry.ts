export interface EditorController {
	getCode: () => string;
	setCode: (code: string) => void;
	markSaved: (code: string) => void;
	focus: () => void;
	openSearch: () => void;
	openReplace: () => void;
	revealLine: (line: number) => void;
}

const editors = new Map<number, EditorController>();

export function registerEditor(id: number, controller: EditorController): () => void {
	editors.set(id, controller);
	return () => {
		if (editors.get(id) === controller) {
			editors.delete(id);
		}
	};
}

export function getEditor(id: number): EditorController | undefined {
	return editors.get(id);
}
