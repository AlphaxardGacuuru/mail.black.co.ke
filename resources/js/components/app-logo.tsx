import AppLogoIcon from "@/components/app-logo-icon"
import { cn } from "@/lib/utils"

type AppLogoProps = {
	variant?: "lockup" | "full" | "icon"
	className?: string
}

export default function AppLogo({ variant = "lockup", className }: AppLogoProps) {
	if (variant === "icon") {
		return (
			<div className="flex justify-center w-full text-sidebar-primary-foreground">
				<img
					src="/android-chrome-512x512.png"
					alt={import.meta.env.VITE_APP_NAME}
					className={cn(className, "w-auto")}
				/>
			</div>
		)
	}

	return (
		<div className="flex justify-center w-full text-sidebar-primary-foreground">
			<AppLogoIcon className={cn(className, "text-secondary dark:text-primary")} />
		</div>
	)
}
