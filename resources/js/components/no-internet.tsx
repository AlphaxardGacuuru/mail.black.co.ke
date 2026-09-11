import { WifiOff } from "lucide-react"

export default function NoInternet() {
	return (
		<div className="relative isolate flex min-h-[60vh] flex-1 flex-col items-center justify-center overflow-hidden rounded-2xl px-6 text-center">
			<div
				aria-hidden="true"
				className="pointer-events-none absolute -top-24 left-1/2 -translate-x-1/2 size-100 rounded-full bg-primary/10 blur-[100px]"
			/>

			<div className="relative z-10 flex flex-col items-center gap-6">
				<div className="rounded-full border border-border bg-card p-0.5 shadow-sm">
					<div className="rounded-full border border-border bg-muted p-4">
						<WifiOff className="size-8 text-foreground" />
					</div>
				</div>

				<div className="max-w-sm space-y-2">
					<h2 className="text-xl font-semibold tracking-tight">
						No internet connection
					</h2>
					<p className="text-sm leading-6 text-muted-foreground">
						Check your connection. We'll bring this back automatically once
						you're back online.
					</p>
				</div>

				<button
					type="button"
					onClick={() => window.location.reload()}
					className="inline-flex items-center gap-2 rounded-md border border-border bg-background px-5 py-2.5 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
					Try again
				</button>
			</div>
		</div>
	)
}
