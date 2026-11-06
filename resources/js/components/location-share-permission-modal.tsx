import { MapPin } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Spinner } from "@/components/ui/spinner"

type Props = {
	open: boolean
	onOpenChange: (open: boolean) => void
	onConfirm: () => void
	processing?: boolean
}

// Action-triggered (opened when the user picks a share duration), not part
// of the app-load onboarding sequence like permissions-onboarding-modal.tsx —
// there's no "onboarded" flag to persist, it's fine to show this again next
// time if the user denies and later retries.
export default function LocationSharePermissionModal({
	open,
	onOpenChange,
	onConfirm,
	processing,
}: Props) {
	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent className="sm:max-w-sm">
				<div className="flex flex-col items-center gap-4 pt-2 text-center">
					<div className="flex size-16 items-center justify-center rounded-full bg-primary/10">
						<MapPin className="size-8 text-primary" />
					</div>
					<DialogHeader className="items-center gap-2">
						<DialogTitle>Share your live location</DialogTitle>
						<DialogDescription>
							The other person will be able to see your live location on a
							map until you turn sharing off again.
						</DialogDescription>
					</DialogHeader>
				</div>
				<DialogFooter className="sm:justify-center">
					<Button
						type="button"
						variant="outline"
						disabled={processing}
						onClick={() => onOpenChange(false)}>
						Not now
					</Button>
					<Button
						type="button"
						disabled={processing}
						onClick={onConfirm}>
						{processing && <Spinner className="size-4" />}
						Share location
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
