import * as React from "react"
import * as RechartsPrimitive from "recharts"

import { cn } from "@/lib/utils"

function useContainerSize(ref: React.RefObject<HTMLDivElement | null>) {
	const [size, setSize] = React.useState<{ width: number; height: number } | null>(null)

	React.useLayoutEffect(() => {
		const el = ref.current
		if (!el) return

		const observer = new ResizeObserver(([entry]) => {
			if (entry) {
				const { width, height } = entry.contentRect
				if (width > 0 && height > 0) setSize({ width, height })
			}
		})

		observer.observe(el)
		return () => observer.disconnect()
	}, [ref])

	return size
}

type ChartConfig = {
	[key: string]: {
		label?: string
		icon?: React.ComponentType<React.SVGProps<SVGSVGElement>>
		color?: string
	}
}

type ChartContainerProps = React.ComponentProps<"div"> & {
	config: ChartConfig
	children: React.ReactNode
}

const ChartContext = React.createContext<ChartConfig | null>(null)

const ChartContainer = React.forwardRef<HTMLDivElement, ChartContainerProps>(
	({ config, children, className, ...props }, forwardedRef) => {
		const innerRef = React.useRef<HTMLDivElement>(null)
		const size = useContainerSize(innerRef)

		const setRef = React.useCallback(
			(node: HTMLDivElement | null) => {
				;(innerRef as React.MutableRefObject<HTMLDivElement | null>).current = node
				if (typeof forwardedRef === "function") forwardedRef(node)
				else if (forwardedRef) forwardedRef.current = node
			},
			[forwardedRef]
		)

		const style = Object.entries(config).reduce<React.CSSProperties>(
			(acc, [key, value], index) => {
				if (value.color) {
					;(acc as Record<string, string>)[`--color-${key}`] = value.color
				}

				if (!value.color) {
					const fallbackPalette = [
						"hsl(var(--chart-1))",
						"hsl(var(--chart-2))",
						"hsl(var(--chart-3))",
						"hsl(var(--chart-4))",
						"hsl(var(--chart-5))",
					]

					;(acc as Record<string, string>)[`--color-${key}`] =
						fallbackPalette[index % fallbackPalette.length]
				}

				return acc
			},
			{}
		)

		return (
			<ChartContext.Provider value={config}>
				<div
					ref={setRef}
					className={cn("h-full w-full", className)}
					style={style}
					{...props}>
					{size
						? React.cloneElement(
								children as React.ReactElement<{ width?: number; height?: number }>,
								{ width: size.width, height: size.height }
							)
						: null}
				</div>
			</ChartContext.Provider>
		)
	}
)
ChartContainer.displayName = "ChartContainer"

type ChartTooltipContentProps = {
	active?: boolean
	payload?: Array<{
		dataKey?: string | number
		name?: string | number
		value?: unknown
		color?: string
	}>
	label?: React.ReactNode
	formatter?: (
		value: unknown,
		name: string | number | undefined,
		item: {
			dataKey?: string | number
			name?: string | number
			value?: unknown
			color?: string
		},
		payload: Array<{
			dataKey?: string | number
			name?: string | number
			value?: unknown
			color?: string
		}>
	) => React.ReactNode
	labelFormatter?: (
		label: React.ReactNode,
		payload: Array<{
			dataKey?: string | number
			name?: string | number
			value?: unknown
			color?: string
		}>
	) => React.ReactNode
	indicator?: "dot" | "line"
}

const ChartTooltipContent = React.forwardRef<
	HTMLDivElement,
	ChartTooltipContentProps
>(
	(
		{ active, payload, label, formatter, labelFormatter, indicator = "dot" },
		ref
	) => {
		const config = React.useContext(ChartContext)

		if (!active || !payload?.length) {
			return null
		}

		return (
			<div
				ref={ref}
				className="rounded-lg border border-border/70 bg-background px-3 py-2 text-sm shadow-xl backdrop-blur">
				{label ? (
					<div className="mb-2 font-medium text-foreground">
						{labelFormatter ? labelFormatter(label, payload) : label}
					</div>
				) : null}
				<div className="space-y-1.5">
					{payload.map((item) => {
						const key = String(item.dataKey ?? item.name ?? "value")
						const entry = config?.[key]
						const color = item.color ?? entry?.color ?? "hsl(var(--chart-1))"
						const displayName = entry?.label ?? String(item.name ?? key)
						const value = formatter
							? formatter(item.value, item.name, item, payload)
							: item.value

						return (
							<div
								key={`${key}-${String(item.value)}`}
								className="flex items-center gap-2">
								<span
									className={cn(
										"inline-flex size-2.5 shrink-0 rounded-full",
										indicator === "line" && "h-0.5 w-4 rounded-full"
									)}
									style={{ backgroundColor: color }}
								/>
								<span className="text-muted-foreground">{displayName}</span>
								<span className="ml-auto font-medium text-foreground">
									{String(value)}
								</span>
							</div>
						)
					})}
				</div>
			</div>
		)
	}
)
ChartTooltipContent.displayName = "ChartTooltipContent"

const ChartTooltip = RechartsPrimitive.Tooltip
const ChartLegend = RechartsPrimitive.Legend

export {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
	ChartLegend,
}

export type { ChartConfig }
