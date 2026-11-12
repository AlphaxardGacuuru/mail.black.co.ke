import { isAxiosError } from "axios"
import { Sparkles, Wand2, X } from "lucide-react"
import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import toast from "@/lib/toast"
import { useDraftMail, useRevampMail } from "@/queries/mail"
import type { MailRevampStyle } from "@/queries/mail"

const REVAMP_STYLES: { style: MailRevampStyle; label: string }[] = [
	{ style: "polish", label: "Polish" },
	{ style: "formal", label: "More formal" },
	{ style: "friendly", label: "Friendlier" },
	{ style: "shorter", label: "Shorter" },
]

// Below this, there's too little written to be worth rewriting.
const MIN_REVAMP_CHARS = 20

/** The text in the editor's HTML, without tags. */
export function plainText(html: string): string {
	return (
		new DOMParser()
			.parseFromString(html, "text/html")
			.body.textContent?.trim() ?? ""
	)
}

function errorMessage(error: unknown): string | undefined {
	return isAxiosError(error)
		? (error.response?.data as { message?: string } | undefined)?.message
		: undefined
}

type DraftPromptProps = {
	subject: string
	to: string[]
	hasBody: boolean
	onDraft: (html: string) => void
	onClose: () => void
}

/**
 * Asks what the email should say, and writes it with AI from that, the
 * subject and the recipients.
 */
export function MailDraftPrompt({
	subject,
	to,
	hasBody,
	onDraft,
	onClose,
}: DraftPromptProps) {
	const [instruction, setInstruction] = useState("")
	const draftMail = useDraftMail()

	function generate() {
		if (!instruction.trim()) {
			toast.error("Say what the email should be about")
			return
		}

		if (hasBody && !window.confirm("Replace your message with an AI draft?")) {
			return
		}

		draftMail
			.mutateAsync({
				instruction,
				...(subject.trim() ? { subject } : {}),
				...(to.length > 0 ? { to } : {}),
			})
			.then((draft) => {
				onDraft(draft.data.html)
				onClose()
			})
			.catch((error) =>
				toast.error(errorMessage(error) ?? "Couldn't Write a Draft")
			)
	}

	return (
		<div className="flex flex-col gap-2 rounded-lg border border-primary/30 bg-primary/5 p-2">
			<div className="flex items-center gap-2">
				<Wand2 className="size-4 shrink-0 text-primary" />

				{/* Draft Instruction Start */}
				<input
					type="text"
					autoFocus
					value={instruction}
					onChange={(event) => setInstruction(event.target.value)}
					onKeyDown={(event) => {
						// Enter writes the draft instead of sending the email.
						if (event.key === "Enter") {
							event.preventDefault()
							generate()
						}
					}}
					maxLength={1000}
					placeholder="What should this email say? e.g. ask Jane for the Q3 report by Friday"
					className="min-w-0 flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-muted-foreground"
				/>
				{/* Draft Instruction End */}

				<Button
					type="button"
					variant="ghost"
					size="icon"
					onClick={onClose}
					aria-label="Close">
					<X className="size-4" />
				</Button>
			</div>

			<div className="flex justify-end">
				<Button
					type="button"
					size="sm"
					onClick={generate}
					disabled={draftMail.isPending}
					className="gap-2">
					{draftMail.isPending ? (
						<Spinner className="size-4" />
					) : (
						<Sparkles className="size-4" />
					)}
					Write Draft
				</Button>
			</div>
		</div>
	)
}

type RevampProps = {
	body: string
	subject: string
	onUse: (html: string) => void
}

/**
 * Once something's written, offers AI rewrites of it, shown for the user
 * to use or discard rather than replacing their text outright.
 */
export function MailRevampSuggestions({ body, subject, onUse }: RevampProps) {
	const revampMail = useRevampMail()
	const [pendingStyle, setPendingStyle] = useState<MailRevampStyle | null>(null)
	const [preview, setPreview] = useState<{
		label: string
		html: string
	} | null>(null)

	if (plainText(body).length < MIN_REVAMP_CHARS) {
		return null
	}

	function revamp(style: MailRevampStyle, label: string) {
		setPendingStyle(style)

		revampMail
			.mutateAsync({
				bodyHtml: body,
				style,
				...(subject.trim() ? { subject } : {}),
			})
			.then((rewrite) => setPreview({ label, html: rewrite.data.html }))
			.catch((error) =>
				toast.error(errorMessage(error) ?? "Couldn't Rewrite Your Message")
			)
			.finally(() => setPendingStyle(null))
	}

	return (
		<div className="flex flex-col gap-2">
			<div className="flex flex-wrap items-center gap-1.5 px-1">
				<span className="flex items-center gap-1 text-xs text-muted-foreground">
					<Sparkles className="size-3.5 text-primary" />
					Revamp with AI:
				</span>

				{REVAMP_STYLES.map(({ style, label }) => (
					<Button
						key={style}
						type="button"
						size="sm"
						variant="outline"
						onClick={() => revamp(style, label)}
						disabled={revampMail.isPending}
						className="h-7 gap-1.5 rounded-full px-3 text-xs">
						{pendingStyle === style && <Spinner className="size-3" />}
						{label}
					</Button>
				))}
			</div>

			{preview && (
				<div className="flex flex-col gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
					<p className="text-xs font-medium text-primary">
						Suggested rewrite · {preview.label}
					</p>

					<div
						className="text-sm leading-6 [&_p]:mb-3 [&_p:last-child]:mb-0"
						dangerouslySetInnerHTML={{ __html: preview.html }}
					/>

					<div className="flex justify-end gap-2">
						<Button
							type="button"
							size="sm"
							variant="ghost"
							onClick={() => setPreview(null)}>
							Discard
						</Button>
						<Button
							type="button"
							size="sm"
							onClick={() => {
								onUse(preview.html)
								setPreview(null)
							}}>
							Use This
						</Button>
					</div>
				</div>
			)}
		</div>
	)
}
