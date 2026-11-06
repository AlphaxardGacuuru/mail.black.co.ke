import { isCancel } from "axios"
import type { FilePondFile } from "filepond"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import { Paperclip, Send, X } from "lucide-react"
import { forwardRef, useRef, useState } from "react"
import type { KeyboardEvent } from "react"
import { FilePond, registerPlugin } from "react-filepond"
import { useQueryClient } from "@tanstack/react-query"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import ShareLocationMenu from "@/components/chat/ShareLocationMenu"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import type { useConversationChannel } from "@/hooks/use-conversation-channel"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { useSendMessage } from "@/queries/chat"
import type { ChatLocationShare, ChatMessage } from "@/types/chat"

import "filepond/dist/filepond.min.css"

registerPlugin(FilePondPluginFileValidateSize)

type Props = {
	conversationId: string
	onTyping?: () => void
	replyingTo?: ChatMessage | null
	onCancelReply?: () => void
	initialBody?: string
	myLocationShare: ChatLocationShare | null
	channel: ReturnType<typeof useConversationChannel>["channel"]
}

const MessageComposer = forwardRef<HTMLDivElement, Props>(
	function MessageComposer(
		{
			conversationId,
			onTyping,
			replyingTo,
			onCancelReply,
			initialBody,
			myLocationShare,
			channel,
		},
		ref
	) {
		const [body, setBody] = useState(initialBody ?? "")
		const [attachmentIds, setAttachmentIds] = useState<Record<string, number>>(
			{}
		)
		const [pendingUploads, setPendingUploads] = useState(0)
		const [showAttachments, setShowAttachments] = useState(false)
		const pondRef = useRef<FilePond>(null)
		const sendMessage = useSendMessage(conversationId)
		const queryClient = useQueryClient()

		const isUploading = pendingUploads > 0
		const canSend =
			(body.trim() !== "" || Object.keys(attachmentIds).length > 0) &&
			!isUploading

		function resetForm() {
			setBody("")
			setAttachmentIds({})
			setShowAttachments(false)
			pondRef.current?.removeFiles()
		}

		function handleSend() {
			if (!canSend || sendMessage.isPending) {
				return
			}

			sendMessage.mutate(
				{
					body: body.trim() || undefined,
					temporaryUploadIds: Object.values(attachmentIds),
					replyToId: replyingTo?.id,
				},
				{
					onSuccess: () => {
						resetForm()
						onCancelReply?.()
						queryClient.refetchQueries({
							queryKey: ["chat", "conversation", conversationId],
						})
					},
					onError: () => toast.error("Couldn't send the message"),
				}
			)
		}

		function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
			if (event.key === "Enter" && !event.shiftKey) {
				event.preventDefault()
				handleSend()
			}
		}

		return (
			<div
				ref={ref}
				className="pointer-events-none fixed inset-x-0 bottom-0 z-10 px-3 pb-3 md:absolute">
				<div className="bg-card pointer-events-auto flex flex-col gap-2 rounded-4xl border p-1 shadow-lg backdrop-blur supports-backdrop-filter:bg-card/95">
					{replyingTo && (
						<div className="flex items-center gap-2 rounded-3xl bg-muted px-3 py-1.5">
							<div className="min-w-0 flex-1 border-l-2 border-primary pl-2">
								<p className="text-xs font-medium">Replying to</p>
								<p className="truncate text-xs text-muted-foreground">
									{replyingTo.body ??
										(replyingTo.attachments.length > 0 ? "Attachment" : "")}
								</p>
							</div>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								className="size-6 shrink-0"
								aria-label="Cancel reply"
								title="Cancel reply"
								onClick={onCancelReply}>
								<X className="size-3.5" />
							</Button>
						</div>
					)}

					{showAttachments && (
						<div className="mt-2 mx-2">
							<FilePond
								ref={pondRef}
								name="filepond-chat-attachments"
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
											FilePondController.storeChatAttachment.url(),
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
											FilePondController.destroyChatAttachment.url(uniqueFileId)
										)
											.then(() => load())
											.catch(() => error("Could not remove attachment"))
									},
								}}
								onprocessfilestart={() =>
									setPendingUploads((count) => count + 1)
								}
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
						</div>
					)}

					<div className="flex items-center gap-1">
						<Button
							type="button"
							variant="ghost"
							size="icon"
							aria-label="Attach files"
							title="Attach files"
							className="rounded-full p-0"
							onClick={() => setShowAttachments((value) => !value)}>
							<Paperclip className="size-6" />
						</Button>

						<ShareLocationMenu
							conversationId={conversationId}
							myLocationShare={myLocationShare}
							channel={channel}
						/>

						<textarea
							value={body}
							onChange={(event) => {
								setBody(event.target.value)
								onTyping?.()
							}}
							onKeyDown={handleKeyDown}
							placeholder="Type a message"
							rows={1}
							className="max-h-32 flex-1 resize-none rounded-full p-3 text-sm outline-none focus:bg-background"
						/>

						<Button
							type="button"
							size="icon"
							aria-label="Send"
							title="Send"
							className="rounded-full p-6"
							disabled={!canSend || sendMessage.isPending}
							onClick={handleSend}>
							{sendMessage.isPending ? (
								<Spinner className="size-6" />
							) : (
								<Send className="size-6 rotate-315" />
							)}
						</Button>
					</div>
				</div>
			</div>
		)
	}
)

export default MessageComposer
