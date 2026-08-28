import { Camera } from "lucide-react"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageCrop from "filepond-plugin-image-crop"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImageTransform from "filepond-plugin-image-transform"
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
					opacity: 0;
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
							})
							.catch(() => error("Upload failed"))

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
