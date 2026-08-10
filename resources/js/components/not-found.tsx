import { Link } from "@/components/ui/link"
import { ArrowLeft, Home } from "lucide-react"

export default function NotFound() {

	return (
		<div className="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-6 text-foreground">
			{/* Background blobs */}
			<div
				aria-hidden="true"
				className="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 size-150 rounded-full bg-primary/10 blur-[120px]"
			/>
			<div
				aria-hidden="true"
				className="pointer-events-none absolute -bottom-40 right-1/4 size-100 rounded-full bg-primary/8 blur-[100px]"
			/>

			<div className="relative z-10 flex flex-col items-center gap-6 text-center">
				{/* 404 number */}
				<div className="relative select-none">
					<span className="text-[10rem] font-black leading-none tracking-tighter text-transparent [-webkit-text-stroke:2px_oklch(0.59_0.18_254/0.25)] sm:text-[14rem]">
						404
					</span>
					<span
						aria-hidden="true"
						className="absolute inset-0 bg-linear-to-br from-primary via-primary/70 to-primary/30 bg-clip-text text-[10rem] font-black leading-none tracking-tighter text-transparent sm:text-[14rem]">
						404
					</span>
				</div>

				{/* Copy */}
				<div className="max-w-md space-y-3">
					<h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
						Page not found
					</h1>
					<p className="text-base leading-7 text-muted-foreground">
						The page you're looking for doesn't exist or has been moved. Check
						the URL, or head back to somewhere familiar.
					</p>
				</div>

				{/* CTAs */}
				<div className="flex flex-wrap items-center justify-center gap-3">
					<Link
						href="/"
						variant="solid"
						size="lg"
						className="gap-2">
						<Home className="size-4" />
						Go home
					</Link>
					<button
						onClick={() => window.history.back()}
						className="inline-flex items-center gap-2 rounded-md border border-border bg-background px-5 py-2.5 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
						<ArrowLeft className="size-4" />
						Go back
					</button>
				</div>
			</div>
		</div>
	)
}
