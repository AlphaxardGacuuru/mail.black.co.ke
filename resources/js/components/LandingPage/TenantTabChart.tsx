import type { FC } from "react"

import TenancyDoughnut from "@/components/Dashboard/TenancyDoughnut"
import { Card, CardContent } from "@/components/ui/card"

import { dashboard, dashboardProperties } from "./data"

const TenantTabChart: FC = () => {
	return (
		<Card className="border-border/60 bg-card/90 shadow-sm">
			<CardContent className="p-4">
				<TenancyDoughnut
					dashboard={dashboard}
					dashboardProperties={dashboardProperties}
				/>
			</CardContent>
		</Card>
	)
}

export default TenantTabChart
