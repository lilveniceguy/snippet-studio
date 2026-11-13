import { useEffect, useRef, type ReactNode } from 'react';

interface DialogProps {
	title: string;
	onClose: () => void;
	children: ReactNode;
	wide?: boolean;
}

export function Dialog({ title, onClose, children, wide = false }: DialogProps) {
	const panel = useRef<HTMLDivElement>(null);
	const closeRef = useRef(onClose);
	closeRef.current = onClose;

	useEffect(() => {
		const previous = document.activeElement as HTMLElement | null;
		panel.current?.focus();
		const onKey = (event: KeyboardEvent) => {
			if (event.key === 'Escape') {
				event.stopPropagation();
				closeRef.current();
			}
		};
		window.addEventListener('keydown', onKey, true);
		return () => {
			window.removeEventListener('keydown', onKey, true);
			previous?.focus();
		};
	}, []);

	return (
		<div className="absolute inset-0 z-30 flex items-center justify-center bg-black/55 p-4" onMouseDown={() => closeRef.current()}>
			<div
				ref={panel}
				role="dialog"
				aria-modal="true"
				aria-label={title}
				tabIndex={-1}
				onMouseDown={(event) => event.stopPropagation()}
				className={`max-h-full overflow-auto rounded-lg border border-studio-line bg-studio-panel p-4 text-studio-text shadow-xl outline-none ${wide ? 'w-full max-w-5xl' : 'w-full max-w-lg'}`}
			>
				<div className="mb-3 flex items-center justify-between gap-3">
					<h2 className="text-sm font-semibold">{title}</h2>
					<button type="button" aria-label="Close dialog" onClick={() => closeRef.current()} className="text-lg leading-none text-studio-muted">
						×
					</button>
				</div>
				{children}
			</div>
		</div>
	);
}
