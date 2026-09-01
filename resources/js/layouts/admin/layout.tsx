import type { PropsWithChildren } from "react"
import Heading from "@/components/heading"

export default function AdminLayout({ children }: PropsWithChildren) {
	return (
		<div className="space-y-6 px-4 py-6">
			<Heading
				title="Admin"
				description="App metrics and incoming webhook activity"
			/>

			{children}
		</div>
	)
}
