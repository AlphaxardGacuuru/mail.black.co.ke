import LucideIconDisplay from "./lucide-icon-display"

type HeroIconProps = {
	icon: string
}

const HeroIcon = ({ icon }: HeroIconProps) => {
	return (
		<div className="bg-primary/10 text-primary rounded-full border border-white/50 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.55),0_14px_28px_-16px_rgba(15,23,42,0.28)] backdrop-blur-xl backdrop-saturate-150 dark:border-white/16">
			<LucideIconDisplay icon={icon} className="size-12" />
		</div>
	)
}

export default HeroIcon
