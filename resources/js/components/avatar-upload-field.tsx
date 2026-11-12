import { isCancel } from "axios"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageCrop from "filepond-plugin-image-crop"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImagePreview from "filepond-plugin-image-preview"
import { Camera, ImageUp } from "lucide-react"
import { useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { FilePond, registerPlugin } from "react-filepond"
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController"
import InputError from "@/components/input-error"
import { filePondErrorMessage } from "@/lib/upload-errors"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

import "filepond/dist/filepond.min.css"
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css"

registerPlugin(
	FilePondPluginFileValidateSize,
	FilePondPluginFileValidateType,
	FilePondPluginImageCrop,
	FilePondPluginImageExifOrientation,
	FilePondPluginImagePreview
)

const MAX_AVATAR_MB = 5

/**
 * The profile picture: a round FilePond over the current picture. Choosing
 * (or dropping) an image previews it in the circle, cropped the way it will
 * show, and only replaces the picture once the user taps Save, posting
 * straight to ProfileController::updateAvatar(). Cancel drops the preview.
 */
export function AvatarUploadField({
	avatar,
	initials,
}: {
	avatar?: string
	initials: string
}) {
	const queryClient = useQueryClient()
	const pondRef = useRef<FilePond>(null)
	const [chosen, setChosen] = useState(false)
	const [saving, setSaving] = useState(false)
	const [error, setError] = useState<string>()
	// The new picture from the upload's response, shown straight away
	// rather than the old one until the auth query refetches.
	const [uploaded, setUploaded] = useState<string>()
	const current = uploaded ?? avatar
	// Falls back to the initials when the picture fails to load.
	const [brokenSrc, setBrokenSrc] = useState<string>()

	function browse() {
		setError(undefined)
		pondRef.current?.browse()
	}

	function cancel() {
		setError(undefined)
		pondRef.current?.removeFiles()
	}

	return (
		<div className="flex flex-col items-center gap-5 sm:flex-row">
			<div className="relative size-28 shrink-0">
				{/* FilePond's own panel is transparent (see .avatar-upload in
				    app.css), so this circle is the only background. */}
				<div className="avatar-upload relative size-28 overflow-hidden rounded-full bg-muted ring-1 ring-border">
					{current && current !== brokenSrc ? (
						<img
							src={current}
							alt="Your profile picture"
							onError={() => setBrokenSrc(current)}
							className="absolute inset-0 size-full object-cover"
						/>
					) : (
						<span className="absolute inset-0 flex items-center justify-center text-3xl font-medium text-muted-foreground">
							{initials}
						</span>
					)}

					{/* Avatar Start */}
					<FilePond
						ref={pondRef}
						name="avatar"
						stylePanelLayout="compact circle"
						stylePanelAspectRatio="1:1"
						styleProgressIndicatorPosition="center bottom"
						imagePreviewHeight={112}
						imageCropAspectRatio="1:1"
						instantUpload={false}
						allowPaste={false}
						acceptedFileTypes={["image/jpeg", "image/png", "image/webp"]}
						labelFileTypeNotAllowed="Use a JPG, PNG or WebP picture."
						maxFileSize={`${MAX_AVATAR_MB}MB`}
						labelMaxFileSizeExceeded={`Pictures must be ${MAX_AVATAR_MB} MB or smaller.`}
						credits={false}
						labelIdle='<span class="sr-only">Choose a new profile picture</span>'
						onaddfile={(addError, file) => {
							if (addError) {
								// The validate plugins report { main, sub }, not the typed body.
								setError(
									(addError as unknown as { main?: string }).main ??
										addError.body
								)
								pondRef.current?.removeFile(file.id)

								return
							}

							setError(undefined)
							setChosen(true)
						}}
						onremovefile={() => setChosen(false)}
						server={{
							process: (
								_fieldName,
								file,
								_metadata,
								load,
								processError,
								progress,
								abort
							) => {
								const controller = new AbortController()
								const formData = new FormData()
								formData.append("avatar", file, file.name)

								setSaving(true)
								setError(undefined)

								Axios.post(ProfileController.updateAvatar.url(), formData, {
									signal: controller.signal,
									onUploadProgress: (event) => {
										if (event.total) {
											progress(true, event.loaded, event.total)
										}
									},
								})
									.then(
										(response: {
											data: { message?: string; avatar?: string }
										}) => {
											load("avatar")
											setUploaded(response.data.avatar)
											toast.success(
												response.data.message ?? "Profile Picture Updated"
											)
											queryClient.invalidateQueries({ queryKey: ["auth"] })
											pondRef.current?.removeFiles()
										}
									)
									.catch((requestError) => {
										if (isCancel(requestError)) {
											return
										}

										const message =
											requestError?.response?.data?.errors?.avatar?.[0] ??
											filePondErrorMessage(requestError, "Upload failed")

										setError(message)
										processError(message)
									})
									.finally(() => setSaving(false))

								return {
									abort: () => {
										controller.abort()
										abort()
									},
								}
							},
							revert: null,
						}}
					/>
					{/* Avatar End */}
				</div>

				{!chosen && (
					<button
						type="button"
						onClick={browse}
						aria-label="Change profile picture"
						className="absolute right-0 bottom-0 flex size-9 items-center justify-center rounded-full border-2 border-background bg-primary text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
						<Camera className="size-4" />
					</button>
				)}
			</div>

			<div className="space-y-3 text-center sm:text-left">
				<div className="space-y-1">
					<p className="text-sm font-medium">Profile picture</p>
					<p className="text-sm text-muted-foreground">
						{chosen
							? "This is how your new picture will look. Save it to use it."
							: `JPG, PNG or WebP, up to ${MAX_AVATAR_MB} MB. A clear, square photo of your face works best.`}
					</p>
				</div>

				<div className="flex justify-center gap-2 sm:justify-start">
					{chosen ? (
						<>
							<Button
								type="button"
								size="sm"
								disabled={saving}
								onClick={() => pondRef.current?.processFiles()}>
								{saving && <Spinner className="size-4" />}
								{saving ? "Saving..." : "Save picture"}
							</Button>
							<Button
								type="button"
								size="sm"
								variant="ghost"
								disabled={saving}
								onClick={cancel}>
								Cancel
							</Button>
						</>
					) : (
						<Button
							type="button"
							size="sm"
							variant="outline"
							onClick={browse}>
							<ImageUp className="size-4" />
							Upload new picture
						</Button>
					)}
				</div>

				<InputError
					message={error}
					role="alert"
				/>
			</div>
		</div>
	)
}
