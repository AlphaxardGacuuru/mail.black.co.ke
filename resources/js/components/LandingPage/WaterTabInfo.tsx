import type { FC } from "react"
import { ArrowRight } from "lucide-react"

import { Link } from "@/components/ui/link"

const WaterTabInfo: FC = () => {
	return (
		<div className="flex flex-col items-center gap-3 rounded-2xl bg-primary/5 mb-2 p-6 text-center">
			<h3 className="text-xl font-semibold text-primary">Water Management</h3>
			<p className="text-foreground">Every drop. Perfectly tracked.</p>
			<p className="max-w-2xl text-sm text-muted-foreground">
				Water usage becomes crystal clear. From readings to billing,
				transparency flows through every interaction.
			</p>
			<Link
				href="/admin/dashboard"
				variant="solid"
				size="sm"
				className="mt-2 capitalize"
				iconFront={<ArrowRight className="size-4" />}>
				start now
			</Link>
		</div>
	)
}

export default WaterTabInfo