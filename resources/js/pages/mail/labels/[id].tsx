import MailFolderPage from "@/components/mail/MailFolderPage"

export default function MailLabel({ id }: { id: string }) {
	return (
		<MailFolderPage
			folder="inbox"
			labelId={id}
		/>
	)
}
