import { Cell, Pie, PieChart } from "recharts"

import {
	ChartContainer,
	ChartLegend,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type RentDoughnutProps = {
	dashboard: {
		rent?: {
			paid: number
			due: number
			total: string
			percentage: string
		}
	}
}

const chartConfig = {
	paid: { label: "Paid Rent", color: "hsl(142 71% 45%)" },
	due: { label: "Due Rent", color: "hsl(142 71% 45% / 0.35)" },
}

const RentDoughnut = ({ dashboard }: RentDoughnutProps) => {
	const chartData = [
		{
			name: "Paid Rent",
			value: dashboard.rent?.paid ?? 0,
			fill: "hsl(142 71% 45%)",
		},
		{
			name: "Due Rent",
			value: dashboard.rent?.due ?? 0,
			fill: "hsl(142 71% 45% / 0.35)",
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
				<p className="mt-1 text-2xl font-bold text-emerald-600">
					KES {dashboard.rent?.total ?? "0"}
				</p>
			</div>
		</div>
	)
}

export default RentDoughnut
