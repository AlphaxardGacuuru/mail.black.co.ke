// import { Link } from "@/lib/spa"

import { Link } from "@/components/ui/link"
import AppLogoIcon from "@/components/app-logo-icon"
import { Card, CardContent } from "@/components/ui/card"
import { home } from "@/routes"
import type { AuthLayoutProps } from "@/types"

export default function AuthSimpleLayout({
	children,
	title,
	description,
}: AuthLayoutProps) {
	return (
		<div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
			{/* START: Page Backdrop Elements */}
			<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
				<div className="absolute -left-40 -top-28 h-80 w-80 rounded-full bg-primary/36 blur-3xl dark:bg-primary/28" />
				<div className="absolute -right-24 top-36 h-96 w-96 rounded-full bg-secondary/70 blur-3xl dark:bg-secondary/35" />
				<div className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-foreground/18 blur-3xl dark:bg-foreground/12" />
				<div className="bg-motion-drift bg-motion-delay-1 absolute left-[43%] top-[7%] h-44 w-44 -translate-x-1/2 rounded-[2.5rem] bg-white/58 dark:bg-white/22" />
				<div className="bg-motion-rotate bg-motion-delay-2 absolute right-[3%] top-[15%] h-36 w-36 rotate-12 rounded-3xl bg-secondary/85 dark:bg-secondary/40" />
				<div className="bg-motion-float bg-motion-delay-3 absolute bottom-[38%] left-[36%] h-48 w-48 -rotate-12 rounded-full bg-primary/46 dark:bg-primary/34" />
				<div className="bg-motion-drift absolute bottom-[12%] right-[20%] h-56 w-56 rounded-[3rem] bg-secondary/60 dark:bg-secondary/25" />
				{/* Brand Background Gradient Start */}
				<div className="absolute inset-0 bg-[radial-gradient(circle_at_12%_10%,rgba(143,255,224,0.18),transparent_35%),radial-gradient(circle_at_88%_14%,rgba(0,49,58,0.55),transparent_40%),linear-gradient(to_bottom,transparent,rgba(143,255,224,0.08),transparent)] dark:bg-[radial-gradient(circle_at_12%_10%,rgba(143,255,224,0.22),transparent_35%),radial-gradient(circle_at_88%_14%,rgba(0,49,58,0.22),transparent_40%),linear-gradient(to_bottom,transparent,rgba(143,255,224,0.12),transparent)]" />
				{/* Brand Background Gradient End */}
				<div className="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.75)_1px,transparent_1px)] bg-size-[16px_16px] opacity-[0.12] dark:opacity-[0.05]" />
				<div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(255,255,255,0.32)_0%,transparent_30%,rgba(148,163,184,0.2)_50%,transparent_70%,rgba(255,255,255,0.22)_100%)] opacity-[0.26] dark:opacity-[0.12]" />
				<div className="absolute inset-0 bg-[repeating-linear-gradient(45deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
				<div className="absolute inset-0 bg-[repeating-linear-gradient(135deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
			</div>
			{/* END: Page Backdrop Elements */}

			<div className="w-full max-w-md">
				<Card className="gap-0 border-white/55 bg-white/32 py-8 shadow-[inset_0_1px_0_rgba(255,255,255,0.55),0_20px_45px_-28px_rgba(15,23,42,0.45)] dark:border-white/20 dark:bg-slate-950/18">
					<CardContent className="flex flex-col gap-8 px-7 sm:px-8">
						<div className="flex flex-col items-center gap-4">
							<Link
								href={home().url}
								variant="unstyled"
								className="flex flex-col items-center gap-2 font-medium">
								<AppLogoIcon className="w-58 fill-current text-secondary dark:text-primary" />
								<span className="sr-only">{title}</span>
							</Link>

							<div className="space-y-2 text-center">
								<h1 className="text-4xl font-semibold tracking-tight text-primary dark:text-white">
									{title}
								</h1>
								<p className="text-lg leading-8 text-muted-foreground">
									{description}
								</p>
							</div>
						</div>
						{children}
					</CardContent>
				</Card>
			</div>
		</div>
	)
}
