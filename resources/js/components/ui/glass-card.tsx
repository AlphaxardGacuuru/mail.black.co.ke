import * as React from "react"
import { cn } from "@/lib/utils"

function GlassCard({ className, ...props }: React.ComponentProps<"div">) {
	return (
		<div
			className={cn(
				"rounded-3xl border border-white/40 bg-white/34 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20",
				className
			)}
			{...props}
		/>
	)
}

function GlassInner({ className, ...props }: React.ComponentProps<"div">) {
	return (
		<div
			className={cn(
				"rounded-2xl border border-white/30 bg-white/28 backdrop-blur-lg dark:border-white/10 dark:bg-slate-950/16",
				className
			)}
			{...props}
		/>
	)
}

export { GlassCard, GlassInner }
