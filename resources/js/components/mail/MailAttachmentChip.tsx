import { Download, File } from "lucide-react"
import type { MailAttachment } from "@/types/mail"

function formatSize(bytes: number | null | undefined): string {
	if (!bytes) {
return ""
}
	if (bytes < 1024) {
return `${bytes} B`
}
	if (bytes < 1024 * 1024) {
return `${(bytes / 1024).toFixed(1)} KB`
}
	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

type Props = {
	attachment: MailAttachment
}

export default function MailAttachmentChip({ attachment }: Props) {
	return (
		<a
			href={attachment.downloadUrl}
			className="inline-flex items-center gap-2 rounded-md border bg-muted/40 px-2.5 py-1.5 text-sm hover:bg-muted transition-colors">
			<File className="size-4 text-muted-foreground shrink-0" />
			<span className="truncate max-w-40">{attachment.filename ?? "attachment"}</span>
			<span className="text-xs text-muted-foreground shrink-0">{formatSize(attachment.size)}</span>
			<Download className="size-3.5 text-muted-foreground shrink-0" />
		</a>
	)
}
