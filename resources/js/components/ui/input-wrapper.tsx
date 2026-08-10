import * as React from "react"
import { useState, forwardRef } from "react"
import { cn } from "@/lib/utils"

type InputWrapperRenderProps = {
	focused: boolean
	handleFocus: React.FocusEventHandler<HTMLElement>
	handleBlur: React.FocusEventHandler<HTMLElement>
}

type InputWrapperProps = Omit<
	React.HTMLAttributes<HTMLDivElement>,
	"children"
> & {
	children:
		| React.ReactNode
		| ((props: InputWrapperRenderProps) => React.ReactNode)
	error?: string | boolean
	showBorder?: boolean
}

const InputWrapper = forwardRef<HTMLDivElement, InputWrapperProps>(
	({ className, children, error, showBorder = true, ...props }, ref) => {
		const [focused, setFocused] = useState(false)
		const [shimmerAnimation, setShimmerAnimation] = useState<
			"idle" | "focus-spin" | "blur-spin"
		>("idle")
		const [animationTick, setAnimationTick] = useState(0)

		const handleFocus: React.FocusEventHandler<HTMLElement> = () => {
			setFocused(true)
			setShimmerAnimation("focus-spin")
			setAnimationTick((previousTick) => previousTick + 1)
		}

		const handleBlur: React.FocusEventHandler<HTMLElement> = () => {
			setFocused(false)
			setShimmerAnimation("blur-spin")
			setAnimationTick((previousTick) => previousTick + 1)
		}

		return (
			<div
				className={cn("relative", className)}
				ref={ref}
				{...props}>
				{/* Shimmer border effect */}
				{showBorder && !error && (
					<div className="absolute -inset-0.5 rounded-lg overflow-hidden">
						<div
							key={`${shimmerAnimation}-${animationTick}`}
							className="input-wrapper-shimmer absolute inset-0"
							style={{
								background: `conic-gradient(from 0deg, 
                                        transparent 0deg, 
                                        var(--input-shimmer-soft) 60deg,
                                        var(--input-shimmer-strong) 90deg, 
                                        var(--input-shimmer-soft) 120deg,
                                        transparent 180deg)`,
								animation:
									shimmerAnimation === "focus-spin"
										? "input-wrapper-focus-spin 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards"
										: shimmerAnimation === "blur-spin"
											? "input-wrapper-blur-spin 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards"
											: undefined,
								transform:
									shimmerAnimation === "idle"
										? `rotate(${focused ? "0deg" : "180deg"})`
										: undefined,
							}}
							onAnimationEnd={() => {
								setShimmerAnimation("idle")
							}}
						/>
						<div className="absolute inset-0.5 rounded-lg bg-white dark:bg-[#0a0a0a]" />
					</div>
				)}

				{typeof children === "function"
					? children({ focused, handleFocus, handleBlur })
					: children}

				<style
					dangerouslySetInnerHTML={{
						__html: `
						.input-wrapper-shimmer {
								--input-shimmer-soft: color-mix(in oklch, var(--color-primary) 35%, transparent);
								--input-shimmer-strong: var(--color-primary);
						}

						.dark .input-wrapper-shimmer {
							--input-shimmer-soft: rgba(255, 255, 255, 0.1);
							--input-shimmer-strong: rgba(255, 255, 255, 0.8);
						}

						@keyframes input-wrapper-focus-spin {
							from {
								transform: rotate(180deg);
							}
							to {
								transform: rotate(720deg);
							}
						}

						@keyframes input-wrapper-blur-spin {
							from {
								transform: rotate(0deg);
							}
							to {
								transform: rotate(540deg);
							}
						}
                `,
					}}
				/>
			</div>
		)
	}
)

InputWrapper.displayName = "InputWrapper"

export { InputWrapper }
