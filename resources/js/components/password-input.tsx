import { Eye, EyeOff } from "lucide-react"
import type { ComponentProps, Ref } from "react"
import { useState } from "react"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"

export default function PasswordInput({
	className,
	ref,
	label,
	...props
}: Omit<ComponentProps<"input">, "type"> & {
	ref?: Ref<HTMLInputElement>
	label?: string
}) {
	const [showPassword, setShowPassword] = useState(false)

	return (
		<div className="relative w-full">
			<Input
				label={label ?? "Password"}
				type={showPassword ? "text" : "password"}
				className={cn("pr-12", className)}
				ref={ref}
				{...props}
			/>
			<button
				type="button"
				onClick={() => setShowPassword((prev) => !prev)}
				className={cn(
					"absolute top-1/2 right-3 z-10 flex -translate-y-1/2 items-center justify-center rounded-sm text-neutral-500 transition-colors hover:text-neutral-800 focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none dark:text-white/60 dark:hover:text-white cursor-pointer",
					(props.disabled ?? false) && "pointer-events-none opacity-50"
				)}
				aria-label={showPassword ? "Hide password" : "Show password"}
				disabled={props.disabled}
				tabIndex={-1}>
				{showPassword ? (
					<EyeOff className="size-4" />
				) : (
					<Eye className="size-4" />
				)}
			</button>
		</div>
	)
}
