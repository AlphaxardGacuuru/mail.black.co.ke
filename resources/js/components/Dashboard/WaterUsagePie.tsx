import { Cell, Pie, PieChart } from "recharts"

import {
	ChartContainer,
	ChartLegend,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type WaterUsagePieProps = {
	dashboard: {
		water?: {
			usageTwoMonthsAgo: string | number
			usageLastMonth: string | number
		}
	}
}

const chartConfig = {
	previous: { label: "Previous Month", color: "hsl(188 94% 43%)" },
	current: { label: "Current Month", color: "hsl(340 82% 52%)" },
}

const WaterUsagePie = ({ dashboard }: WaterUsagePieProps) => {
	const chartData = [
		{
			name: "Previous Month",
			value: Number(dashboard.water?.usageTwoMonthsAgo ?? 0),
			fill: "hsl(188 94% 43%)",
		},
		{
			name: "Current Month",
			value: Number(dashboard.water?.usageLastMonth ?? 0),
			fill: "hsl(340 82% 52%)",
		},
	]

	return (
		<div className="rounded-3xl border border-border/60 bg-background/80 p-4 shadow-sm backdrop-blur">
			<div className="h-70 w-full">
				<ChartContainer config={chartConfig} className="h-full w-full">
					<PieChart>
						<ChartTooltip content={<ChartTooltipContent />} />
						<Pie
							data={chartData}
							dataKey="value"
							nameKey="name"
							outerRadius={104}
							paddingAngle={2}
							startAngle={90}
							endAngle={-270}>
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
					Current usage
				</p>
				<p className="mt-1 text-2xl font-bold text-cyan-600">
					{Number(dashboard.water?.usageLastMonth ?? 0).toLocaleString("en-US")}L
				</p>
			</div>
		</div>
	)
}

export default WaterUsagePie
