import { useCurrentUrl } from "@/hooks/use-current-url"
import { detectPortal } from "@/lib/portal-context"

type HeroHeadingProps = {
	heading: string
	data?: number | string
}

const HeroHeading = ({ heading, data }: HeroHeadingProps) => {
	const { currentUrl } = useCurrentUrl()
	const portal = detectPortal(currentUrl)

	const colorClass =
		portal === "admin"
			? "text-primary"
			: portal === "tenant"
				? "text-emerald-600 dark:text-emerald-400"
				: "text-amber-600 dark:text-amber-400"

	return (
		<div>
			<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
				{heading}
			</p>
			<p className={`mt-1 text-3xl font-semibold ${colorClass}`}>
				{data ?? 0}
			</p>
		</div>
	)
}

export default HeroHeading
