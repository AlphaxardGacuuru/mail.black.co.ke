type HeroHeadingProps = {
	heading: string
	data?: number | string
}

const HeroHeading = ({ heading, data }: HeroHeadingProps) => {
	return (
		<div>
			<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
				{heading}
			</p>
			<p className="mt-1 text-3xl font-semibold text-primary">
				{data ?? 0}
			</p>
		</div>
	)
}

export default HeroHeading
