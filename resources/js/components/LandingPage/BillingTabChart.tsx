import type { FC } from "react"

import RentDoughnut from "@/components/Dashboard/RentDoughnut"
import ServiceChargeDoughnut from "@/components/Dashboard/ServiceChargeDoughnut"
import WaterDoughnut from "@/components/Dashboard/WaterDoughnut"
import { Card, CardContent } from "@/components/ui/card"

import { dashboard } from "./data"

const BillingTabChart: FC = () => {
	return (
		<Card className="border-border/60 bg-card/90 shadow-sm">
			<CardContent className="grid gap-4 p-4 md:grid-cols-3">
				<RentDoughnut dashboard={dashboard} />
				<WaterDoughnut dashboard={dashboard} />
				<ServiceChargeDoughnut dashboard={dashboard} />
			</CardContent>
		</Card>
	)
}

export default BillingTabChart