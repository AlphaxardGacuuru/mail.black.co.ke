import { Cell, Pie, PieChart } from "recharts"

import {
	ChartContainer,
	ChartLegend,
	ChartTooltip,
	ChartTooltipContent,
} from "@/components/ui/chart"

type PropertyDoughnutProps = {
	dashboardProperties: {
		total: number
		names: string[]
		units: number[]
	}
}

const chartConfig = {
	units: { label: "Units", color: "hsl(221 83% 53%)" },
	residences: { label: "Residences", color: "hsl(142 71% 45%)" },
	commercial: { label: "Commercial", color: "hsl(45 93% 47%)" },
}

const PropertyDoughnut = ({ dashboardProperties }: PropertyDoughnutProps) => {
	const chartData = dashboardProperties.names.map((name, index) => ({
		name,
		units: dashboardProperties.units[index] ?? 0,
		fill: [
			"hsl(221 83% 53%)",
			"hsl(142 71% 45%)",
			"hsl(45 93% 47%)",
			"hsl(262 83% 58%)",
			"hsl(340 82% 52%)",
		][index % 5],
	}))

	return (
		<div className="rounded-3xl border border-border/60 bg-background/80 p-4 shadow-sm backdrop-blur">
			<div className="h-80 w-full">
				<ChartContainer
					config={chartConfig}
					className="h-full w-full">
					<PieChart>
						<ChartTooltip content={<ChartTooltipContent />} />
						<Pie
							data={chartData}
							dataKey="units"
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
		</div>
	)
}

export default PropertyDoughnut
