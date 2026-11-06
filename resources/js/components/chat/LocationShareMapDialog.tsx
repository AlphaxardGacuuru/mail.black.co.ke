import { useEffect, useState } from "react"
import L from "leaflet"
import { MapContainer, Marker, TileLayer, useMap } from "react-leaflet"
import { useEcho } from "@laravel/echo-react"
import {
	Dialog,
	DialogContent,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import type { useConversationChannel } from "@/hooks/use-conversation-channel"
import toast from "@/lib/toast"
import type { ChatLocationShare } from "@/types/chat"

import "leaflet/dist/leaflet.css"

// A plain divIcon instead of Leaflet's default marker images: those ship as
// separate PNG assets whose relative paths break under Vite bundling unless
// manually reconfigured, and a small dot is all this needs.
const locationIcon = L.divIcon({
	className: "",
	html: '<div style="width:16px;height:16px;border-radius:9999px;background:#2563eb;border:3px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.45)"></div>',
	iconSize: [16, 16],
	iconAnchor: [8, 8],
})

function Recenter({ position }: { position: [number, number] }) {
	const map = useMap()

	useEffect(() => {
		map.setView(position)
	}, [position, map])

	return null
}

function formatRelativeTime(value: string): string {
	const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000)

	if (seconds < 60) {
		return "just now"
	}

	return `${Math.floor(seconds / 60)}m ago`
}

type Props = {
	open: boolean
	onOpenChange: (open: boolean) => void
	conversationId: string
	share: ChatLocationShare | null
	senderName: string
	channel: ReturnType<typeof useConversationChannel>["channel"]
}

export default function LocationShareMapDialog({
	open,
	onOpenChange,
	conversationId,
	share,
	senderName,
	channel,
}: Props) {
	const [position, setPosition] = useState<[number, number] | null>(
		share?.latitude !== null &&
			share?.latitude !== undefined &&
			share?.longitude !== null &&
			share?.longitude !== undefined
			? [share.latitude, share.longitude]
			: null
	)
	const [lastUpdatedAt, setLastUpdatedAt] = useState(share?.lastUpdatedAt ?? null)

	useEffect(() => {
		if (share?.latitude !== null && share?.longitude !== null && share) {
			setPosition([share.latitude as number, share.longitude as number])
		}
		setLastUpdatedAt(share?.lastUpdatedAt ?? null)
	}, [share])

	useEffect(() => {
		if (!open) {
			return
		}

		const presenceChannel = channel()
		if (!presenceChannel) {
			return
		}

		presenceChannel.listenForWhisper(
			"location-update",
			(payload: { latitude: number; longitude: number }) => {
				setPosition([payload.latitude, payload.longitude])
				setLastUpdatedAt(new Date().toISOString())
			}
		)
	}, [open, channel])

	useEcho(
		`chat-conversation.${conversationId}`,
		"LocationShareStoppedEvent",
		(event: { senderId: string }) => {
			if (!share || event.senderId !== share.senderId) {
				return
			}
			toast("Location sharing ended")
			onOpenChange(false)
		},
		[conversationId, share?.senderId],
		"presence"
	)

	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent className="sm:max-w-md">
				<DialogHeader>
					<DialogTitle>{senderName}&apos;s live location</DialogTitle>
				</DialogHeader>
				<div className="h-80 w-full overflow-hidden rounded-lg">
					{position ? (
						<MapContainer
							center={position}
							zoom={15}
							className="h-full w-full">
							<TileLayer
								attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
								url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
							/>
							<Marker
								position={position}
								icon={locationIcon}
							/>
							<Recenter position={position} />
						</MapContainer>
					) : (
						<div className="flex h-full items-center justify-center text-sm text-muted-foreground">
							Waiting for location…
						</div>
					)}
				</div>
				{lastUpdatedAt && (
					<p className="text-xs text-muted-foreground">
						Updated {formatRelativeTime(lastUpdatedAt)}
					</p>
				)}
			</DialogContent>
		</Dialog>
	)
}
