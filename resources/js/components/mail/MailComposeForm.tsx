import { isCancel } from "axios"
import type { FilePondFile } from "filepond"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import { Copy, EyeOff, Paperclip, Send } from "lucide-react"
import { useRef, useState } from "react"
import type { FormEvent } from "react"
import { FilePond, registerPlugin } from "react-filepond"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import MailRecipientInput from "@/components/mail/MailRecipientInput"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import { Textarea } from "@/components/ui/textarea"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { useReplyMail, useSendMail } from "@/queries/mail"
import type { MailComposeMode } from "@/types/mail"

import "filepond/dist/filepond.min.css"

registerPlugin(FilePondPluginFileValidateSize)

type Props = {
	mode: MailComposeMode
	parentMessageId?: string
	initialTo?: string[]
	initialCc?: string[]
	initialSubject?: string
	onSent?: (result: { threadId?: string }) => void
	onCancel?: () => void
}

export default function MailComposeForm({
	mode,
	parentMessageId,
	initialTo = [],
	initialCc = [],
	initialSubject = "",
	onSent,
	onCancel,
}: Props) {
	const [to, setTo] = useState<string[]>(initialTo)
	const [cc, setCc] = useState<string[]>(initialCc)
	const [showCc, setShowCc] = useState(initialCc.length > 0)
	const [bcc, setBcc] = useState<string[]>([])
	const [showBcc, setShowBcc] = useState(false)
	const [subject, setSubject] = useState(initialSubject)
	const [body, setBody] = useState("")
	const [attachmentIds, setAttachmentIds] = useState<Record<string, number>>({})
	const [pendingUploads, setPendingUploads] = useState(0)
	const [showAttachments, setShowAttachments] = useState(false)
	const pondRef = useRef<FilePond>(null)

	const sendMail = useSendMail()
	const replyMail = useReplyMail(
		mode === "new" ? "reply" : mode,
		parentMessageId ?? ""
	)

	const mutation = mode === "new" ? sendMail : replyMail
	const showToField = mode === "new" || mode === "forward"
	const isUploading = pendingUploads > 0

	function removeCc() {
		setShowCc(false)
		setCc([])
	}

	function removeBcc() {
		setShowBcc(false)
		setBcc([])
	}

	function resetForm() {
		setTo([])
		setCc([])
		setShowCc(false)
		setBcc([])
		setShowBcc(false)
		setSubject("")
		setBody("")
		setAttachmentIds({})
		setShowAttachments(false)
		pondRef.current?.removeFiles()
	}

	function handleSubmit(event: FormEvent) {
		event.preventDefault()

		if (isUploading) {
			toast.error("Wait for attachments to finish uploading")
			return
		}

		if (showToField && to.length === 0) {
			toast.error("Add at least one recipient")
			return
		}

		if (!subject.trim() && mode === "new") {
			toast.error("Add a subject")
			return
		}

		const payload = {
			...(showToField ? { to } : {}),
			...(cc.length > 0 ? { cc } : {}),
			...(bcc.length > 0 ? { bcc } : {}),
			subject,
			bodyHtml: body.replace(/\n/g, "<br>"),
			temporaryUploadIds: Object.values(attachmentIds),
		}

		mutation
			.mutateAsync(payload)
			.then((response) => {
				const threadId = (
					response.data?.data as { threadId?: string } | undefined
				)?.threadId

				toast.success(mode === "new" ? "Message sent" : "Reply sent")
				resetForm()
				onSent?.({ threadId })
			})
			.catch(() => {
				toast.error("Failed to send message")
			})
	}

	return (
		<form
			onSubmit={handleSubmit}
			className="flex flex-col gap-2 rounded-lg bg-background p-2">
			{showToField && (
				<div className="flex flex-col gap-1">
					<MailRecipientInput
						label="To"
						value={to}
						onChange={setTo}
						placeholder="Recipients"
					/>
				</div>
			)}

			{showCc && (
				<MailRecipientInput
					label="Cc"
					value={cc}
					onChange={setCc}
					placeholder="Carbon copy"
					onRemove={removeCc}
				/>
			)}

			{showBcc && (
				<MailRecipientInput
					label="Bcc"
					value={bcc}
					onChange={setBcc}
					placeholder="Blind carbon copy"
					onRemove={removeBcc}
				/>
			)}

			{showToField && (!showCc || !showBcc) && (
				<div className="flex items-center gap-3 px-1">
					{!showCc && (
						<Button
							type="button"
							size="sm"
							variant="ghost"
							onClick={() => setShowCc(true)}
							className="text-xs text-muted-foreground hover:text-foreground">
							Cc
						</Button>
					)}
					{!showBcc && (
						<Button
							type="button"
							size="sm"
							variant="ghost"
							onClick={() => setShowBcc(true)}
							className="text-xs text-muted-foreground hover:text-foreground">
							Bcc
						</Button>
					)}
				</div>
			)}

			{mode === "new" && (
				<input
					type="text"
					value={subject}
					onChange={(event) => setSubject(event.target.value)}
					placeholder="Subject"
					className="border-b py-1.5 text-sm outline-none bg-transparent placeholder:text-muted-foreground"
				/>
			)}

			<Textarea
				value={body}
				onChange={(event) => setBody(event.target.value)}
				label="Write your message"
				rows={mode === "new" ? 8 : 5}
				className="resize-none border-none shadow-none focus-visible:ring-0"
			/>

			{/* Attachments Start */}
			{showAttachments && (
				<FilePond
					ref={pondRef}
					name="filepond-mail-attachments"
					allowMultiple
					maxFileSize="25MB"
					credits={false}
					labelIdle='<span class="filepond--label-action">Attach files</span> or drag and drop'
					server={{
						process: (
							fieldName,
							file,
							_metadata,
							load,
							error,
							progress,
							abort
						) => {
							const controller = new AbortController()
							const formData = new FormData()
							formData.append(fieldName, file, file.name)

							Axios.post(
								FilePondController.storeMailAttachment.url(),
								formData,
								{
									signal: controller.signal,
									onUploadProgress: (event) => {
										if (event.total) {
											progress(true, event.loaded, event.total)
										}
									},
								}
							)
								.then((response) => load(String(response.data)))
								.catch((requestError) => {
									if (isCancel(requestError)) {
										return
									}
									error("Upload failed")
								})

							return {
								abort: () => {
									controller.abort()
									abort()
								},
							}
						},
						revert: (uniqueFileId, load, error) => {
							Axios.delete(
								FilePondController.destroyMailAttachment.url(uniqueFileId)
							)
								.then(() => load())
								.catch(() => error("Could not remove attachment"))
						},
					}}
					onprocessfilestart={() => setPendingUploads((count) => count + 1)}
					onprocessfile={(err, file: FilePondFile) => {
						setPendingUploads((count) => Math.max(0, count - 1))
						if (!err) {
							setAttachmentIds((prev) => ({
								...prev,
								[file.id]: Number(file.serverId),
							}))
						}
					}}
					onprocessfileabort={() =>
						setPendingUploads((count) => Math.max(0, count - 1))
					}
					onremovefile={(_err, file: FilePondFile) => {
						setAttachmentIds((prev) => {
							const next = { ...prev }
							delete next[file.id]
							return next
						})
					}}
				/>
			)}
			{/* Attachments End */}

			<div className="flex items-center justify-between pt-1">
				{/* Cancel Button Start */}
				{onCancel && (
					<Button
						type="button"
						variant="ghost"
						onClick={onCancel}>
						Cancel
					</Button>
				)}
				{/* Cancel Button End */}

				<div className="flex items-center gap-2">
					{/* Attachment Start */}
					<Button
						type="button"
						variant="ghost"
						size="icon"
						onClick={() => setShowAttachments(true)}>
						<Paperclip className="size-4" />
					</Button>
					{/* Attachment End */}

					{/* Send  Button Start */}
					<Button
						type="submit"
						disabled={mutation.isPending || isUploading}
						className="gap-2">
						{mutation.isPending ? (
							<Spinner className="size-4" />
						) : (
							<Send className="size-4" />
						)}
						{mode === "new"
							? "Send"
							: mode === "forward"
								? "Forward"
								: "Send Reply"}
					</Button>
					{/* Send  Button End */}
				</div>
			</div>
		</form>
	)
}
