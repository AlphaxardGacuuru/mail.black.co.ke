"use client"

import * as React from "react"
import { useState, useEffect, useRef } from "react"
import {
	CalendarIcon,
	ChevronDownIcon,
	ChevronLeftIcon,
	ChevronRightIcon,
} from "lucide-react"
import {
	DayPicker,
	getDefaultClassNames,
} from "react-day-picker"
import type {
	DayPickerProps,
	DayButtonProps,
	ChevronProps,
	RootProps,
	WeekNumberProps,
} from "react-day-picker"
import { format, parse, isValid } from "date-fns"
import type { VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"
import { Button, buttonVariants } from "@/components/ui/button"
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from "@/components/ui/popover"
import { InputWrapper } from "./input-wrapper"

type CalendarComponentProps = DayPickerProps & {
	buttonVariant?: VariantProps<typeof buttonVariants>["variant"]
}

type DatePickerProps = Omit<
	React.ButtonHTMLAttributes<HTMLButtonElement>,
	"value" | "onChange"
> & {
	className?: string
	label?: string
	error?: string
	helperText?: string
	value?: string | Date | null
	onChange?: (value: string) => void
}

function CalendarComponent({
	className,
	classNames,
	showOutsideDays = false,
	captionLayout = "label",
	buttonVariant = "ghost",
	formatters,
	components,
	...props
}: CalendarComponentProps) {
	const defaultClassNames = getDefaultClassNames()

	return (
		<DayPicker
			showOutsideDays={showOutsideDays}
			className={cn(
				"bg-background group/calendar p-3 [--cell-size:--spacing(8)] in-data-[slot=card-content]:bg-transparent in-data-[slot=popover-content]:bg-transparent",
				String.raw`rtl:**:[.rdp-button\_next>svg]:rotate-180`,
				String.raw`rtl:**:[.rdp-button\_previous>svg]:rotate-180`,
				className
			)}
			captionLayout={captionLayout}
			formatters={{
				formatMonthDropdown: (date: Date) =>
					date.toLocaleString("default", { month: "short" }),
				...formatters,
			}}
			classNames={{
				root: cn("w-fit", defaultClassNames.root),
				months: cn(
					"flex gap-4 flex-col md:flex-row relative",
					defaultClassNames.months
				),
				month: cn("flex flex-col w-full gap-4", defaultClassNames.month),
				nav: cn(
					"flex items-center gap-1 w-full absolute top-0 inset-x-0 justify-between",
					defaultClassNames.nav
				),
				button_previous: cn(
					buttonVariants({ variant: buttonVariant }),
					"size-(--cell-size) aria-disabled:opacity-50 p-0 select-none",
					defaultClassNames.button_previous
				),
				button_next: cn(
					buttonVariants({ variant: buttonVariant }),
					"size-(--cell-size) aria-disabled:opacity-50 p-0 select-none",
					defaultClassNames.button_next
				),
				month_caption: cn(
					"flex items-center justify-center h-(--cell-size) w-full px-(--cell-size)",
					defaultClassNames.month_caption
				),
				dropdowns: cn(
					"w-full flex items-center text-sm font-medium justify-center h-(--cell-size) gap-1.5",
					defaultClassNames.dropdowns
				),
				dropdown_root: cn(
					"relative border border-neutral-300 bg-white shadow-xs rounded-md text-neutral-900 overflow-hidden min-h-[35px] flex items-center min-w-[90px] dark:border-white/20 dark:bg-white/10 dark:backdrop-blur-xl dark:text-white",
					defaultClassNames.dropdown_root
				),
				dropdown: cn(
					"absolute inset-0 w-full h-full cursor-pointer text-neutral-900 bg-transparent appearance-none px-2 z-10 py-0 flex items-center dark:text-white",
					"[&>option]:bg-white [&>option]:text-neutral-900 dark:[&>option]:bg-gray-900 dark:[&>option]:text-white",
					defaultClassNames.dropdown
				),
				caption_label: cn(
					"select-none font-medium text-neutral-900 dark:text-white pointer-events-none",
					captionLayout === "label"
						? "text-sm"
						: "opacity-0 rounded-md pl-2 pr-1 flex items-center gap-1 text-sm h-8 [&>svg]:text-neutral-500 dark:[&>svg]:text-white/60 [&>svg]:size-3.5 [&>svg]:pointer-events-none",
					defaultClassNames.caption_label
				),
				table: "w-full border-collapse",
				weekdays: cn("flex", defaultClassNames.weekdays),
				weekday: cn(
					"text-neutral-500 dark:text-white/60 rounded-md flex-1 font-normal text-[0.8rem] select-none",
					defaultClassNames.weekday
				),
				week: cn("flex w-full mt-2", defaultClassNames.week),
				week_number_header: cn(
					"select-none w-(--cell-size)",
					defaultClassNames.week_number_header
				),
				week_number: cn(
					"text-[0.8rem] select-none text-neutral-500 dark:text-white/60",
					defaultClassNames.week_number
				),
				day: cn(
					"relative w-full h-full p-0 text-center [&:last-child[data-selected=true]_button]:rounded-r-md group/day aspect-square select-none",
					props.showWeekNumber
						? "[&:nth-child(2)[data-selected=true]_button]:rounded-l-md"
						: "[&:first-child[data-selected=true]_button]:rounded-l-md",
					defaultClassNames.day
				),
				range_start: cn(
					"rounded-l-md bg-neutral-200 dark:bg-white/20",
					defaultClassNames.range_start
				),
				range_middle: cn("rounded-none", defaultClassNames.range_middle),
				range_end: cn(
					"rounded-r-md bg-neutral-200 dark:bg-white/20",
					defaultClassNames.range_end
				),
				today: cn(
					"bg-neutral-200 text-neutral-900 dark:bg-white/20 dark:text-white rounded-md data-[selected=true]:rounded-none",
					defaultClassNames.today
				),
				outside: cn(
					"text-neutral-400 aria-selected:text-neutral-400 dark:text-white/40 dark:aria-selected:text-white/40",
					defaultClassNames.outside
				),
				disabled: cn(
					"text-neutral-300 dark:text-white/30 opacity-50",
					defaultClassNames.disabled
				),
				hidden: cn("invisible", defaultClassNames.hidden),
				...classNames,
			}}
			components={{
				Root: ({ className, rootRef, ...props }: RootProps) => {
					return (
						<div
							data-slot="calendar"
							ref={rootRef}
							className={cn(className)}
							{...props}
						/>
					)
				},
				Chevron: ({ className, orientation, ...props }: ChevronProps) => {
					if (orientation === "left") {
						return (
							<ChevronLeftIcon
								className={cn("size-4", className)}
								{...props}
							/>
						)
					}

					if (orientation === "right") {
						return (
							<ChevronRightIcon
								className={cn("size-4", className)}
								{...props}
							/>
						)
					}

					return (
						<ChevronDownIcon
							className={cn("size-4", className)}
							{...props}
						/>
					)
				},
				DayButton: CalendarDayButton,
				WeekNumber: ({ children, ...props }: WeekNumberProps) => {
					return (
						<td {...props}>
							<div className="flex size-(--cell-size) items-center justify-center text-center">
								{children}
							</div>
						</td>
					)
				},
				...components,
			}}
			{...props}
		/>
	)
}

function CalendarDayButton({ className, day, modifiers, ...props }: DayButtonProps) {
	const defaultClassNames = getDefaultClassNames()

	const ref = useRef<HTMLButtonElement>(null)
	useEffect(() => {
		if (modifiers.focused) {
			ref.current?.focus()
		}
	}, [modifiers.focused])

	return (
		<Button
			ref={ref}
			variant="ghost"
			size="icon"
			data-day={day.date.toLocaleDateString("en-GB")}
			data-selected-single={
				modifiers.selected &&
				!modifiers.range_start &&
				!modifiers.range_end &&
				!modifiers.range_middle
			}
			data-range-start={modifiers.range_start}
			data-range-end={modifiers.range_end}
			data-range-middle={modifiers.range_middle}
			className={cn(
				"data-[selected-single=true]:bg-neutral-200 data-[selected-single=true]:text-neutral-900 dark:data-[selected-single=true]:bg-white/30 dark:data-[selected-single=true]:text-white data-[range-middle=true]:bg-neutral-100 data-[range-middle=true]:text-neutral-900 dark:data-[range-middle=true]:bg-white/20 dark:data-[range-middle=true]:text-white data-[range-start=true]:bg-neutral-200 data-[range-start=true]:text-neutral-900 dark:data-[range-start=true]:bg-white/30 dark:data-[range-start=true]:text-white data-[range-end=true]:bg-neutral-200 data-[range-end=true]:text-neutral-900 dark:data-[range-end=true]:bg-white/30 dark:data-[range-end=true]:text-white group-data-[focused=true]/day:border-ring group-data-[focused=true]/day:ring-ring/50 hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-white/20 dark:hover:text-white flex aspect-square size-auto w-full min-w-(--cell-size) flex-col gap-1 leading-none font-normal group-data-[focused=true]/day:relative group-data-[focused=true]/day:z-10 group-data-[focused=true]/day:ring-[3px] data-[range-end=true]:rounded-md data-[range-end=true]:rounded-r-md data-[range-middle=true]:rounded-none data-[range-start=true]:rounded-md data-[range-start=true]:rounded-l-md [&>span]:text-xs [&>span]:opacity-70",
				defaultClassNames.day,
				className
			)}
			{...props}
		/>
	)
}

// Parse date string to local Date object (avoids timezone issues)
function parseLocalDate(dateStr: string | Date | null | undefined): Date | null {
	if (!dateStr) {
		return null
	}

	if (dateStr instanceof Date) {
		return isValid(dateStr) ? dateStr : null
	}

	// Try YYYY-MM-DD first, then ISO 8601
	const date = parse(String(dateStr).slice(0, 10), "yyyy-MM-dd", new Date())

	return isValid(date) ? date : null
}

// Format Date object to YYYY-MM-DD in local timezone
export function formatLocalDate(date: Date | null | undefined): string {
	if (!date || !isValid(date)) {
		return ""
	}

	return format(date, "yyyy-MM-dd")
}

export function DatePicker({
	className,
	label,
	error,
	helperText,
	value,
	onChange,
	...props
}: DatePickerProps) {
	const [open, setOpen] = useState(false)

	// Convert string value to Date for internal use
	const dateValue = typeof value === "string" ? parseLocalDate(value) : value

	const isActive = Boolean(value)

	return (
		<div className={cn("relative w-full", className)}>
			<Popover
				open={open}
				onOpenChange={setOpen}>
				<InputWrapper error={error}>
					{({ focused, handleFocus, handleBlur }) => {
						const isActiveWithFocus = focused || isActive

						return (
							<>
								<PopoverTrigger asChild>
									<button
										type="button"
										className={cn(
											"relative flex h-14 w-full rounded-lg border px-4 pt-5 pb-1 text-left text-neutral-900 dark:text-white font-light font-nunito text-base transition-all",
											error
												? "border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/20"
												: "border-neutral-300 focus:border-neutral-500 focus:ring-2 focus:ring-neutral-400/20 dark:border-white/20 dark:focus:border-white/40 dark:focus:ring-white/10",
											"focus:outline-none",
											"disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-neutral-200/5 dark:disabled:bg-white/5"
										)}
										onFocus={handleFocus}
										onBlur={handleBlur}
										{...props}>
										<span
											className={cn(
												"truncate",
												!value && "text-neutral-500 dark:text-white/50"
											)}>
											{dateValue
												? dateValue.toLocaleDateString("en-GB")
												: label || "Select date"}
										</span>
										<CalendarIcon className="ml-auto size-4 opacity-50" />
									</button>
								</PopoverTrigger>

								{label && (
									<label
										className={cn(
											"absolute left-4 transition-all duration-200 pointer-events-none font-light font-nunito z-10",
											isActiveWithFocus
												? "top-1.5 text-xs text-neutral-700 dark:text-white"
												: "top-4 text-base text-neutral-600 dark:text-white",
											error && isActiveWithFocus && "text-red-400"
										)}>
										{label}
									</label>
								)}
							</>
						)
					}}
				</InputWrapper>

				<PopoverContent
					className="w-auto overflow-hidden p-0 bg-white border border-neutral-300 text-neutral-900 dark:bg-white/10 dark:backdrop-blur-xl dark:border-white/20 dark:text-white"
					align="start">
					<CalendarComponent
						mode="single"
						selected={dateValue ?? undefined}
						defaultMonth={dateValue ?? undefined}
						captionLayout="dropdown"
						onSelect={(date: Date | undefined) => {
							onChange?.(formatLocalDate(date))
							setOpen(false)
						}}
					/>
				</PopoverContent>
			</Popover>

			{(error || helperText) && (
				<p
					className={cn(
						"mt-1.5 px-4 text-xs font-light font-nunito",
						error ? "text-red-400" : "text-neutral-500 dark:text-white/40"
					)}>
					{error || helperText}
				</p>
			)}
		</div>
	)
}
