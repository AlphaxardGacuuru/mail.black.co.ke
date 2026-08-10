import type { FC } from "react"

import PropertyDoughnut from "@/components/Dashboard/PropertyDoughnut"
import { Card, CardContent } from "@/components/ui/card"

import { dashboardProperties } from "./data"

const PropertyTabChart: FC = () => {
	return (
		<Card className="border-border/60 bg-card/90 shadow-sm">
			<CardContent className="p-4">
				<PropertyDoughnut dashboardProperties={dashboardProperties} />
			</CardContent>
		</Card>
	)
}

export default PropertyTabChart