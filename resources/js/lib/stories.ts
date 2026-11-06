// Stories live with chats; their pages sit under /chats/stories.
export const STORIES_URL = "/chats/stories"

export function storyUrl(id: string): string {
	return `${STORIES_URL}/${id}/view`
}
