import { Cell, Pie, PieChart } from "recharts"

import {
	ChartContainer,
	ChartLegend,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type TenancyDoughnutProps = {
	dashboard: {
		units?: {
			totalOccupied: number
			totalUnoccupied: number
			percentage: string
		}
	}
	dashboardProperties: {
		units: number[]
	}
}

const chartConfig = {
	occupied: { label: "Occupied", color: "hsl(221 83% 53%)" },
	unoccupied: { label: "Unoccupied", color: "hsl(221 83% 53% / 0.35)" },
}

const TenancyDoughnut = ({ dashboard }: TenancyDoughnutProps) => {
	const chartData = [
		{
			name: "Occupied Units",
			value: dashboard.units?.totalOccupied ?? 0,
			fill: "hsl(221 83% 53%)",
		},
		{
			name: "Unoccupied Units",
			value: dashboard.units?.totalUnoccupied ?? 0,
			fill: "hsl(221 83% 53% / 0.35)",
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
							innerRadius={78}
							outerRadius={118}
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
			<div className="mt-4 grid grid-cols-2 gap-3 text-center">
				<div>
					<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
						Occupied
					</p>
					<p className="mt-1 text-2xl font-bold text-sky-600">
						{dashboard.units?.totalOccupied ?? 0}
					</p>
				</div>
				<div>
					<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
						Unoccupied
					</p>
					<p className="mt-1 text-2xl font-bold text-sky-600">
						{dashboard.units?.totalUnoccupied ?? 0}
					</p>
				</div>
			</div>
		</div>
	)
}

export default TenancyDoughnut
