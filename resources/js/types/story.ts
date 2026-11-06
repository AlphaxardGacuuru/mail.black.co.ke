// A picture (or video) per segment; a news segment also carries the
// article it's about.
export type StoryMediaItem = {
	image?: string
	video?: string
	url?: string
	source?: string
	title?: string
}

export type Story = {
	id: string
	name: string
	avatar: string | null
	verified: boolean
	media: StoryMediaItem[]
	text: string | null
	hasSeen: boolean
	createdAt: string
}
