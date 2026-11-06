import { useEffect, useState } from "react"
import { MapPin } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import LocationSharePermissionModal from "@/components/location-share-permission-modal"
import type { useConversationChannel } from "@/hooks/use-conversation-channel"
import { useLocationSharing } from "@/hooks/use-location-sharing"
import toast from "@/lib/toast"
import {
	useStartLocationShare,
	useStopLocationShare,
	useUpdateLocationShare,
} from "@/queries/chat"
import type { ChatLocationShare } from "@/types/chat"

type Props = {
	conversationId: string
	myLocationShare: ChatLocationShare | null
	channel: ReturnType<typeof useConversationChannel>["channel"]
}

// A persistent opt-in toggle, not a timed share: sharing stays on until the
// user turns it off again, so this is a single on/off button rather than a
// duration picker.
export default function ShareLocationMenu({
	conversationId,
	myLocationShare,
	channel,
}: Props) {
	const [permissionModalOpen, setPermissionModalOpen] = useState(false)

	const { isSupported, permission, start, stop } = useLocationSharing()
	const startShare = useStartLocationShare(conversationId)
	const stopShare = useStopLocationShare(conversationId)
	const updateShare = useUpdateLocationShare(conversationId)

	// The server may have already stopped this share from elsewhere (another
	// tab, another device) — stop this tab's own watchPosition() the moment
	// that reflects back through the conversation query.
	useEffect(() => {
		if (!myLocationShare) {
			stop()
		}
	}, [myLocationShare, stop])

	async function confirmAndStart() {
		const granted = await start((tick) => {
			updateShare.mutate(tick)
			channel()?.whisper("location-update", tick)
		})

		if (!granted) {
			toast.error("Location permission denied", {
				description:
					"Allow location access for this site in your browser settings.",
			})
			return
		}

		startShare.mutate(undefined, {
			onError: () => {
				toast.error("Couldn't start sharing your location")
				stop()
			},
		})
	}

	function handleToggle() {
		if (myLocationShare) {
			stop()
			stopShare.mutate(undefined, {
				onError: () => toast.error("Couldn't stop sharing your location"),
			})
			return
		}

		if (!isSupported) {
			toast.error("Location isn't supported in this browser")
			return
		}

		if (permission !== "granted") {
			setPermissionModalOpen(true)
			return
		}

		void confirmAndStart()
	}

	function handleModalConfirm() {
		setPermissionModalOpen(false)
		void confirmAndStart()
	}

	return (
		<>
			<Button
				type="button"
				variant="ghost"
				size="icon"
				aria-label={
					myLocationShare ? "Stop sharing location" : "Share location"
				}
				title={myLocationShare ? "Stop sharing location" : "Share location"}
				className="rounded-full"
				disabled={startShare.isPending || stopShare.isPending}
				onClick={handleToggle}>
				{startShare.isPending || stopShare.isPending ? (
					<Spinner className="size-6" />
				) : (
					<MapPin
						className={
							myLocationShare ? "size-6 animate-pulse text-primary" : "size-6"
						}
					/>
				)}
			</Button>

			<LocationSharePermissionModal
				open={permissionModalOpen}
				onOpenChange={setPermissionModalOpen}
				onConfirm={handleModalConfirm}
				processing={startShare.isPending}
			/>
		</>
	)
}
