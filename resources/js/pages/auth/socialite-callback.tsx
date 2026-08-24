import { useEffect, useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import toast from "@/lib/toast"
import { useApp } from "@/contexts/AppContext"
import { invalidateAuth } from "@/middleware/auth"
import { CheckCircle2, Circle, Loader2 } from "lucide-react"

type StepStatus = "pending" | "active" | "done"

const STEPS = [
	"Verifying your identity",
	"Setting up your account",
	"Redirecting to dashboard",
]

const PROVIDER_ICONS: Record<string, React.ReactNode> = {
	google: (
		<svg aria-hidden="true" className="size-6" viewBox="0 0 24 24">
			<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.44a5.5 5.5 0 0 1-2.39 3.61v3h3.86c2.25-2.07 3.58-5.12 3.58-8.64Z" />
			<path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.95-2.92l-3.86-3c-1.08.72-2.46 1.14-4.09 1.14-3.14 0-5.8-2.12-6.75-4.97H1.26v3.1A12 12 0 0 0 12 24Z" />
			<path fill="#FBBC05" d="M5.25 14.25A7.2 7.2 0 0 1 4.87 12c0-.78.14-1.53.38-2.25v-3.1H1.26A12 12 0 0 0 0 12c0 1.93.46 3.76 1.26 5.35l3.99-3.1Z" />
			<path fill="#EA4335" d="M12 4.78c1.76 0 3.34.61 4.58 1.82l3.43-3.43C17.95 1.25 15.23 0 12 0A12 12 0 0 0 1.26 6.65l3.99 3.1c.94-2.85 3.61-4.97 6.75-4.97Z" />
		</svg>
	),
}

function capitalize(str: string) {
	return str.charAt(0).toUpperCase() + str.slice(1)
}

export default function SocialiteCallback() {
	const { setLocalStorage } = useApp()
	const navigate = useNavigate()

	const params = new URLSearchParams(window.location.search)
	const token = params.get("token")
	const message = params.get("message")
	const error = params.get("error")
	const provider = params.get("provider") ?? "google"

	const [stepStatuses, setStepStatuses] = useState<StepStatus[]>(["active", "pending", "pending"])

	useEffect(() => {
		const t1 = setTimeout(() => {
			setStepStatuses(["done", "active", "pending"])
		}, 500)

		const t2 = setTimeout(() => {
			setStepStatuses(["done", "done", "active"])
		}, 1000)

		const t3 = setTimeout(() => {
			setStepStatuses(["done", "done", "done"])

			if (error) {
				toast.error(error)
				navigate({ to: "/login" })
				return
			}

			if (token) {
				setLocalStorage("sanctumToken", token)
				invalidateAuth()
				toast.success(message ?? "Logged in")
				navigate({ to: "/mail" })
				return
			}

			toast.error("Authentication failed. Please try again.")
			navigate({ to: "/login" })
		}, 1500)

		return () => {
			clearTimeout(t1)
			clearTimeout(t2)
			clearTimeout(t3)
		}
	}, [])

	return (
		<div className="flex h-[50vh] items-center justify-center bg-background rounded-2xl">
			<div className="flex w-full max-w-xs flex-col items-center justify-center gap-5 rounded-2xl bg-card p-8 shadow-sm">
				<div className="flex flex-col items-center gap-2">
					<div className="flex size-11 items-center justify-center rounded-full border bg-background shadow-sm">
						{PROVIDER_ICONS[provider] ?? (
							<div className="size-5 rounded-full bg-muted" />
						)}
					</div>
					<div className="text-center">
						<h1 className="text-base font-semibold">
							Signing in with {capitalize(provider)}
						</h1>
						<p className="text-xs text-muted-foreground">
							This will only take a moment
						</p>
					</div>
				</div>

				<div className="w-full space-y-2">
					{STEPS.map((label, i) => {
						const status = stepStatuses[i]
						return (
							<div
								key={label}
								className="flex items-center gap-2.5">
								<span className="shrink-0">
									{status === "done" && (
										<CheckCircle2 className="size-4 text-green-500" />
									)}
									{status === "active" && (
										<Loader2 className="size-4 animate-spin text-primary" />
									)}
									{status === "pending" && (
										<Circle className="size-4 text-muted-foreground/40" />
									)}
								</span>
								<span
									className={
										status === "pending"
											? "text-xs text-muted-foreground/50"
											: status === "active"
												? "text-xs font-medium text-foreground"
												: "text-xs text-muted-foreground"
									}>
									{label}
								</span>
							</div>
						)
					})}
				</div>
			</div>
		</div>
	)
}
