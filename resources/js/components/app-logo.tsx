import AppLogoIcon from "@/components/app-logo-icon"

type AppLogoProps = {
	variant?: "lockup" | "full"
	className?: string
}

export default function AppLogo({
	variant = "lockup",
	className,
}: AppLogoProps) {
	if (variant === "full") {
		return (
			<img
				src="/default-monochrome-black.svg"
				alt="Black Tree Logo"
				className={className ?? "h-5 w-auto dark:invert"}
			/>
		)
	}

	return (
		<div className="flex justify-center w-full text-sidebar-primary-foreground">
			<AppLogoIcon className="width-10 fill-current text-primary dark:text-white" />
		</div>
	)
}
