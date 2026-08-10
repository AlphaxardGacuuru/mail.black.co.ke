import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type TenancyBarProps = {
	dashboard: {
		units?: {
			tenantsThisYear?: { labels: string[]; data: number[] }
			vacanciesThisYear?: { labels: string[]; data: number[] }
		}
	}
}

const chartConfig = {
	tenants: { label: "Tenants This Month", color: "hsl(221 83% 53%)" },
	vacancies: {
		label: "Vacancies This Month",
		color: "hsl(221 83% 53% / 0.35)",
	},
}

const TenancyBar = ({ dashboard }: TenancyBarProps) => {
	const labels = dashboard.units?.tenantsThisYear?.labels ?? []
	const chartData = labels.map((label, index) => ({
		month: label,
		tenants: dashboard.units?.tenantsThisYear?.data?.[index] ?? 0,
		vacancies: dashboard.units?.vacanciesThisYear?.data?.[index] ?? 0,
	}))

	return (
		<div className="rounded-3xl border border-border/60 bg-background/80 p-4 shadow-sm backdrop-blur">
			<div className="h-95 w-full">
				<ChartContainer
					config={chartConfig}
					className="h-full w-full">
					<BarChart
						data={chartData}
						margin={{ top: 16, right: 8, left: 0, bottom: 0 }}>
						<CartesianGrid
							vertical={false}
							strokeDasharray="3 3"
						/>
						<XAxis
							dataKey="month"
							tickLine={false}
							axisLine={false}
							tickMargin={8}
						/>
						<YAxis
							tickLine={false}
							axisLine={false}
							width={40}
						/>
						<ChartTooltip content={<ChartTooltipContent indicator="line" />} />
						<Bar
							dataKey="tenants"
							fill="hsl(221 83% 53%)"
							radius={4}
						/>
						<Bar
							dataKey="vacancies"
							fill="hsl(221 83% 53% / 0.35)"
							radius={4}
						/>
					</BarChart>
				</ChartContainer>
			</div>
		</div>
	)
}

export default TenancyBar
