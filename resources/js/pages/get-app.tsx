import { Download, Sparkles } from "lucide-react"
import { Head } from "@/lib/spa"
import { Button } from "@/components/ui/button"
import { usePwaInstall } from "@/hooks/use-pwa-install"

export default function GetApp() {
	const { canInstall, install, isInstalled } = usePwaInstall()

	return (
		<>
			<Head title="Get the app" />
			<div className="relative isolate flex min-h-[calc(100vh-8rem)] items-center justify-center overflow-hidden rounded-2xl border bg-secondary px-6 py-16 text-secondary-foreground shadow-sm sm:px-10">
				<div className="absolute inset-0 -z-10 opacity-20 bg-[linear-gradient(135deg,transparent_25%,currentColor_25%,currentColor_26%,transparent_26%,transparent_50%,currentColor_50%,currentColor_51%,transparent_51%)] bg-size-[2rem_2rem]" />
				<div className="relative z-10 max-w-xl text-center">
					<img
						src="/android-chrome-512x512.png"
						alt="Black Mail"
						className="mx-auto mb-6 size-20 rounded-3xl shadow-lg shadow-black/20"
					/>
					<div className="mb-3 flex items-center justify-center gap-2 text-sm font-medium text-primary">
						<Sparkles className="size-4" />
						Black Mail, ready when you are
					</div>
					<h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">
						Get Black Mail on your device
					</h1>
					<p className="mx-auto mt-5 max-w-lg text-base leading-7 text-secondary-foreground/75">
						Install a focused mail experience with quick access from your home
						screen and a window of its own.
					</p>

					{isInstalled ? (
						<div className="mt-8 rounded-xl border border-primary/30 bg-primary/10 p-4 text-sm text-primary-foreground">
							Black Mail is already installed on this device.
						</div>
					) : (
						<Button
							size="lg"
							className="mt-8 gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
							disabled={!canInstall}
							onClick={() => void install()}>
							<Download className="size-5" />
							Install Black Mail
						</Button>
					)}
				</div>
			</div>
		</>
	)
}
