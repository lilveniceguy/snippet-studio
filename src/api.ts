import apiFetch from '@wordpress/api-fetch';

export class ApiError extends Error {
	code: string;
	status: number;
	data: Record<string, unknown>;

	constructor(error: { code?: string; message?: string; data?: Record<string, unknown> }) {
		super(error.message || 'Request failed');
		this.name = 'ApiError';
		this.code = error.code || 'unknown';
		this.status = Number(error.data?.status || 0);
		this.data = error.data || {};
	}
}

export async function api<T>(path: string, method = 'GET', data?: unknown): Promise<T> {
	try {
		return await apiFetch<T>({
			path: `/snippet-studio/v1${path}`,
			method,
			data,
		});
	} catch (error) {
		const candidate = error as { code?: string; message?: string; data?: Record<string, unknown> };
		throw new ApiError(candidate);
	}
}
