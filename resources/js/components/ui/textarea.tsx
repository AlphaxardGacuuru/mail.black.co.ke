import * as React from "react"
import { useState, forwardRef } from "react"
import { cn } from "@/lib/utils"
import { InputWrapper } from "./input-wrapper"

type TextareaProps = React.ComponentProps<"textarea"> & {
	label?: string
	error?: string | boolean
	helperText?: string
}

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
	({ className, label, error, helperText, ...props }, ref) => {
		const [hasValue, setHasValue] = useState(false)

		const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
			setHasValue(e.target.value !== "")
			props.onChange?.(e)
		}

		const hasControlledValue =
			props.value !== undefined && String(props.value).length > 0
		const hasDefaultValue =
			props.defaultValue !== undefined && String(props.defaultValue).length > 0
		const isActive = hasValue || hasControlledValue || hasDefaultValue

		return (
			<div className="relative w-full">
				<InputWrapper error={error}>
					{({ focused, handleFocus, handleBlur }) => {
						const isActiveWithFocus = focused || isActive

						return (
							<>
								<textarea
									className={cn(
										"relative flex min-h-32 w-full rounded-lg border bg-transparent px-4 pt-5 pb-1 font-light font-nunito text-base transition-all placeholder:text-neutral-500 dark:placeholder:text-white/50 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-neutral-200/5 dark:disabled:bg-white/5 text-neutral-900 dark:text-white",
										error
											? "border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-500 dark:focus:border-red-500 dark:focus:ring-red-500/30"
											: "border-neutral-300 focus:border-neutral-500 focus:ring-2 focus:ring-neutral-400/20 dark:border-white/20 dark:focus:border-white/40 dark:focus:ring-white/10",
										className
									)}
									ref={ref}
									onFocus={(e) => {
										handleFocus(e)
										props.onFocus?.(e)
									}}
									onBlur={(e) => {
										handleBlur(e)
										setHasValue(e.target.value !== "")
										props.onBlur?.(e)
									}}
									onChange={handleChange}
									placeholder={props.placeholder}
									{...props}
								/>
								{label && (
									<label
										className={cn(
											"absolute left-4 transition-all duration-200 pointer-events-none font-light font-nunito z-10",
											isActiveWithFocus
												? "top-1.5 text-xs text-neutral-700 dark:text-white"
												: "top-4 text-base text-neutral-600 dark:text-white",
											error &&
												isActiveWithFocus &&
												"text-red-400 dark:text-red-400"
										)}>
										{label}
									</label>
								)}
							</>
						)
					}}
				</InputWrapper>

				{(error || helperText) && (
					<p
						className={cn(
							"mt-1.5 px-4 text-xs font-light font-nunito",
							error
								? "text-red-400 dark:text-red-400"
								: "text-neutral-500 dark:text-white/40"
						)}>
						{error || helperText}
					</p>
				)}
			</div>
		)
	}
)

Textarea.displayName = "Textarea"

export { Textarea }
