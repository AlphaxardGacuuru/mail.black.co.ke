declare global {
	interface Window {
		__SPA_PAGE__?: {
			component: string
			props: Record<string, unknown>
			url: string
		}
	}
}

export {}
