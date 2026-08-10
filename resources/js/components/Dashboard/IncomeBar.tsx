import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type IncomeBarProps = {
	dashboard: {
		rent?: {
			paidThisYear?: { labels: string[]; data: number[] }
			unpaidThisYear?: { labels: string[]; data: number[] }
		}
		water?: {
			paidThisYear?: { labels: string[]; data: number[] }
			unpaidThisYear?: { labels: string[]; data: number[] }
		}
		serviceCharge?: {
			paidThisYear?: { labels: string[]; data: number[] }
			unpaidThisYear?: { labels: string[]; data: number[] }
		}
	}
}

const chartConfig = {
	paidRent: { label: "Paid Rent", color: "hsl(142 71% 45%)" },
	dueRent: { label: "Due Rent", color: "hsl(142 71% 45% / 0.35)" },
	paidWater: { label: "Paid Water Bill", color: "hsl(188 94% 43%)" },
	dueWater: { label: "Due Water Bill", color: "hsl(188 94% 43% / 0.35)" },
	paidService: { label: "Paid Service Charge", color: "hsl(25 95% 53%)" },
	dueService: { label: "Due Service Charge", color: "hsl(25 95% 53% / 0.35)" },
}

const IncomeBar = ({ dashboard }: IncomeBarProps) => {
	const labels = dashboard.rent?.paidThisYear?.labels ?? dashboard.water?.paidThisYear?.labels ?? dashboard.serviceCharge?.paidThisYear?.labels ?? []

	const chartData = labels.map((label, index) => ({
		month: label,
		paidRent: dashboard.rent?.paidThisYear?.data?.[index] ?? 0,
		dueRent: dashboard.rent?.unpaidThisYear?.data?.[index] ?? 0,
		paidWater: dashboard.water?.paidThisYear?.data?.[index] ?? 0,
		dueWater: dashboard.water?.unpaidThisYear?.data?.[index] ?? 0,
		paidService: dashboard.serviceCharge?.paidThisYear?.data?.[index] ?? 0,
		dueService: dashboard.serviceCharge?.unpaidThisYear?.data?.[index] ?? 0,
	}))

	return (
		<div className="rounded-3xl border border-border/60 bg-background/80 p-4 shadow-sm backdrop-blur">
			<div className="h-95 w-full">
				<ChartContainer config={chartConfig} className="h-full w-full">
					<BarChart data={chartData} margin={{ top: 16, right: 8, left: 0, bottom: 0 }}>
						<CartesianGrid vertical={false} strokeDasharray="3 3" />
						<XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
						<YAxis tickLine={false} axisLine={false} width={40} />
						<ChartTooltip content={<ChartTooltipContent indicator="line" />} />
						<Bar dataKey="paidRent" fill="hsl(142 71% 45%)" radius={4} />
						<Bar dataKey="dueRent" fill="hsl(142 71% 45% / 0.35)" radius={4} />
						<Bar dataKey="paidWater" fill="hsl(188 94% 43%)" radius={4} />
						<Bar dataKey="dueWater" fill="hsl(188 94% 43% / 0.35)" radius={4} />
						<Bar dataKey="paidService" fill="hsl(25 95% 53%)" radius={4} />
						<Bar dataKey="dueService" fill="hsl(25 95% 53% / 0.35)" radius={4} />
					</BarChart>
				</ChartContainer>
			</div>
		</div>
	)
}

export default IncomeBar
