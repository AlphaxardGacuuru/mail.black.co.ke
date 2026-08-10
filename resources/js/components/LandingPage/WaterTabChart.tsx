import type { FC } from "react"

import WaterUsagePie from "@/components/Dashboard/WaterUsagePie"
import { Card, CardContent } from "@/components/ui/card"

import { dashboard } from "./data"

const WaterTabChart: FC = () => {
	return (
		<Card className="border-border/60 bg-card/90 shadow-sm">
			<CardContent className="p-4">
				<WaterUsagePie dashboard={dashboard} />
			</CardContent>
		</Card>
	)
}

export default WaterTabChart
