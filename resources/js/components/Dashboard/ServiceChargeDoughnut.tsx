import { Cell, Pie, PieChart } from "recharts"

import {
	ChartContainer,
	ChartLegend,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type ServiceChargeDoughnutProps = {
	dashboard: {
		serviceCharge?: {
			paid: number
			due: number
			total: string
			percentage: string
		}
	}
}

const chartConfig = {
	paid: { label: "Paid Service", color: "hsl(25 95% 53%)" },
	due: { label: "Due Service", color: "hsl(25 95% 53% / 0.35)" },
}

const ServiceChargeDoughnut = ({ dashboard }: ServiceChargeDoughnutProps) => {
	const chartData = [
		{
			name: "Paid Service",
			value: dashboard.serviceCharge?.paid ?? 0,
			fill: "hsl(25 95% 53%)",
		},
		{
			name: "Due Service",
			value: dashboard.serviceCharge?.due ?? 0,
			fill: "hsl(25 95% 53% / 0.35)",
		},
	]

	return (
		<div className="rounded-3xl border border-border/60 bg-background/80 p-4 shadow-sm backdrop-blur">
			<div className="h-70 w-full">
				<ChartContainer
					config={chartConfig}
					className="h-full w-full">
					<PieChart>
						<ChartTooltip content={<ChartTooltipContent />} />
						<Pie
							data={chartData}
							dataKey="value"
							nameKey="name"
							innerRadius={68}
							outerRadius={104}
							paddingAngle={3}>
							{chartData.map((entry) => (
								<Cell
									key={entry.name}
									fill={entry.fill}
								/>
							))}
						</Pie>
						<ChartLegend />
					</PieChart>
				</ChartContainer>
			</div>
			<div className="mt-4 text-center">
				<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
					Total
				</p>
				<p className="mt-1 text-2xl font-bold text-orange-600">
					KES {dashboard.serviceCharge?.total ?? "0"}
				</p>
			</div>
		</div>
	)
}

export default ServiceChargeDoughnut
