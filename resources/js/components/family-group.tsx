import { cn } from "@/lib/utils"

export default function FamilyGroup({
	title,
	className,
	children,
}: {
	title: string
	className?: string
	children: React.ReactNode
}) {
	return (
		<div
			className={cn(
				"relative flex w-fit min-w-80 min-h-60 justify-center gap-6 rounded-full border border-gray-200 px-12 py-4 shadow-lg backdrop-blur-[1px] dark:border-white/10 dark:border-b-white/20",
				className
			)}>
			<h1 className="absolute top-0 left-1/2 z-10 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary px-4 py-1 text-sm font-semibold whitespace-nowrap text-white">
				{title}
			</h1>
			{children}
		</div>
	)
}
