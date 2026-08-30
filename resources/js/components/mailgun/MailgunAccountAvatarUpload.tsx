import { Camera } from "lucide-react"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageCrop from "filepond-plugin-image-crop"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImageTransform from "filepond-plugin-image-transform"
import { useRef } from "react"
import { FilePond, registerPlugin } from "react-filepond"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

import "filepond/dist/filepond.min.css"

registerPlugin(
	FilePondPluginFileValidateType,
	FilePondPluginImageExifOrientation,
	FilePondPluginImageCrop,
	FilePondPluginImageTransform
)

// How long to leave FilePond's own complete/error indicator on screen before
// clearing the item and reverting to the plain avatar view.
const CLEAR_AFTER_SUCCESS_MS = 900
const CLEAR_AFTER_ERROR_MS = 2500

type Props = {
	accountId: string
	avatar: string | null
	label: string
	onUploaded: (avatar: string) => void
	size?: number
}

export default function MailgunAccountAvatarUpload({
	accountId,
	avatar,
	label,
	onUploaded,
	size = 56,
}: Props) {
	const pondRef = useRef<FilePond>(null)

	return (
		<div
			className="mailgun-account-avatar-input group relative shrink-0 overflow-hidden rounded-full"
			style={{ width: size, height: size }}>
			{/* react-filepond applies `className` to the hidden raw <input>, not the rendered UI —
			    style the actual FilePond root (a sibling it inserts) so it covers this circle and stays clickable. */}
			<style>{`
				.mailgun-account-avatar-input .filepond--wrapper,
				.mailgun-account-avatar-input .filepond--root {
					position: absolute;
					inset: 0;
					width: 100%;
					height: 100%;
					margin: 0;
				}
				.mailgun-account-avatar-input .filepond--root {
					cursor: pointer;
				}
				/* At rest (no file picked yet) stay invisible so the avatar underneath
				   is the only thing shown and remains the click target. */
				.mailgun-account-avatar-input .filepond--root:not(:has(.filepond--item)) {
					opacity: 0;
				}
				/* Once a file is active, keep FilePond's own progress ring / checkmark /
				   error indicator but drop the filename/status text and action buttons —
				   there's no room for them inside a small circular avatar. */
				.mailgun-account-avatar-input .filepond--drop-label,
				.mailgun-account-avatar-input .filepond--file-info,
				.mailgun-account-avatar-input .filepond--file-status,
				.mailgun-account-avatar-input .filepond--file-action-button,
				.mailgun-account-avatar-input .filepond--credits {
					display: none;
				}
			`}</style>

			<Avatar
				className="size-full"
				style={{ width: size, height: size }}>
				<AvatarImage
					src={avatar ?? undefined}
					alt={label}
				/>
				<AvatarFallback>{label.slice(0, 2).toUpperCase()}</AvatarFallback>
			</Avatar>

			<div className="pointer-events-none absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:bg-black/40 group-hover:opacity-100 cursor-pointer">
				<Camera className="size-4 text-white" />
			</div>

			<FilePond
				ref={pondRef}
				name="filepond-mailgun-account-avatar"
				allowMultiple={false}
				acceptedFileTypes={["image/*"]}
				imageCropAspectRatio="1:1"
				credits={false}
				labelIdle="Update profile photo"
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
							FilePondController.updateMailgunAccountAvatar.url(accountId),
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
							.then((response) => {
								const uploadedAvatar = response.data.avatar as string
								onUploaded(uploadedAvatar)
								load(uploadedAvatar)
								setTimeout(
									() => pondRef.current?.removeFile(),
									CLEAR_AFTER_SUCCESS_MS
								)
							})
							.catch(() => {
								error("Upload failed")
								setTimeout(
									() => pondRef.current?.removeFile(),
									CLEAR_AFTER_ERROR_MS
								)
							})

						return {
							abort: () => {
								controller.abort()
								abort()
							},
						}
					},
				}}
				onerror={() => toast.error("Unable to update the profile picture.")}
			/>
		</div>
	)
}
