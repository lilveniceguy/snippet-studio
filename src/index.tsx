import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles.css';

const client = new QueryClient({
	defaultOptions: {
		queries: {
			retry: 1,
			refetchOnWindowFocus: false,
			staleTime: 30_000,
		},
	},
});

const root = document.getElementById('snippet-studio-app');
if (root) {
	createRoot(root).render(
		<QueryClientProvider client={client}>
			<App />
		</QueryClientProvider>
	);
}
