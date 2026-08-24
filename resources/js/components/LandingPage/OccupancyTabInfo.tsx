import type { FC } from "react"
import { ArrowRight } from "lucide-react"

import { Link } from "@/components/ui/link"

const OccupancyTabInfo: FC = () => {
	return (
		<div className="flex flex-col items-center gap-3 rounded-2xl bg-primary/5 mb-2 p-6 text-center">
			<h3 className="text-xl font-semibold text-primary">Occupancy Management</h3>
			<p className="text-foreground">See everything. Miss nothing.</p>
			<p className="max-w-2xl text-sm text-muted-foreground">
				Instantly understand your property occupancy at a glance. Vacant units become
				opportunities, not problems.
			</p>
			<Link
				href="/mail"
				variant="solid"
				size="sm"
				className="mt-2 capitalize"
				iconFront={<ArrowRight className="size-4" />}>
				start now
			</Link>
		</div>
	)
}

export default OccupancyTabInfo