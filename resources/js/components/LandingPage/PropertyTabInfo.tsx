import type { FC } from "react"
import { ArrowRight } from "lucide-react"

import { Link } from "@/components/ui/link"

const PropertyTabInfo: FC = () => {
	return (
		<div className="flex flex-col items-center gap-3 rounded-2xl bg-primary/5 mb-2 p-6 text-center">
			<h3 className="text-xl font-semibold text-primary">Property Management</h3>
			<p className="text-foreground">Effortlessly manage every property. From anywhere.</p>
			<p className="max-w-2xl text-sm text-muted-foreground">
				One beautifully simple platform that gives you complete visibility into all
				your properties. Because managing multiple properties should feel as
				intuitive as it is powerful.
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

export default PropertyTabInfo