import { XIcon } from "lucide-react"
import * as React from "react"

import { Checkbox } from "@/components/ui/checkbox"
import { InputWrapper } from "@/components/ui/input-wrapper"
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

export type MultiSelectOption = {
	value: string
	// Shown beside the value and searched too, e.g. the county a town is in
	hint?: string
}

type MultiSelectFieldProps = {
	label: string
	options: MultiSelectOption[]
	value: string[]
	onChange: (value: string[]) => void
	max?: number
	searchPlaceholder?: string
	helperText?: string
	error?: string | boolean
}

/**
 * A searchable checklist with the same floating-label look as Input and
 * SelectField. Picked values show as removable chips; values that aren't in
 * `options` (saved before the list existed) still show and can be removed.
 */
function MultiSelectField({
	label,
	options,
	value,
	onChange,
	max,
	searchPlaceholder = "Search",
	helperText,
	error,
}: MultiSelectFieldProps) {
	const [open, setOpen] = React.useState(false)
	const [query, setQuery] = React.useState("")

	const atMax = max !== undefined && value.length >= max
	const search = query.trim().toLowerCase()
	const filtered = search
		? options.filter((option) =>
				`${option.value} ${option.hint ?? ""}`.toLowerCase().includes(search)
			)
		: options

	const toggle = (option: string) =>
		onChange(
			value.includes(option)
				? value.filter((selected) => selected !== option)
				: [...value, option]
		)

	return (
		<div className="relative w-full">
			<Popover
				open={open}
				onOpenChange={(nextOpen) => {
					setOpen(nextOpen)
					if (!nextOpen) setQuery("")
				}}>
				<InputWrapper error={error}>
					{({ focused, handleFocus, handleBlur }) => (
						<PopoverAnchor asChild>
							<div
								onClick={() => setOpen(true)}
								className={cn(
									"relative flex min-h-14 w-full cursor-pointer flex-wrap items-center gap-1.5 rounded-lg border bg-transparent px-4 pt-6 pb-2 font-nunito text-base font-light text-neutral-900 transition-all dark:text-white",
									error
										? "border-red-500 dark:border-red-500"
										: "border-neutral-300 dark:border-white/20",
									(focused || open) &&
										!error &&
										"border-neutral-500 ring-2 ring-neutral-400/20 dark:border-white/40 dark:ring-white/10"
								)}>
								<label
									className={cn(
										"pointer-events-none absolute left-4 z-10 font-nunito font-light transition-all duration-200",
										value.length > 0 || focused || open
											? "top-1.5 text-xs text-neutral-700 dark:text-white"
											: "top-4 text-base text-neutral-600 dark:text-white",
										error && "text-red-400 dark:text-red-400"
									)}>
									{label}
								</label>

								{value.map((selected) => (
									<span
										key={selected}
										className="inline-flex items-center gap-1 rounded-md bg-neutral-100 py-0.5 pr-1 pl-2 text-sm dark:bg-white/10">
										{selected}
										<button
											type="button"
											aria-label={`Remove ${selected}`}
											className="rounded p-0.5 text-neutral-500 hover:bg-neutral-200 hover:text-neutral-900 dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white"
											onClick={(event) => {
												event.stopPropagation()
												toggle(selected)
											}}>
											<XIcon className="size-3" />
										</button>
									</span>
								))}

								<PopoverTrigger
									aria-label={label}
									className="min-w-0 flex-1 text-left text-sm text-neutral-500 focus:outline-none dark:text-white/50"
									onFocus={handleFocus}
									onBlur={handleBlur}>
									{value.length > 0 && !atMax ? "Add more" : null}
								</PopoverTrigger>
							</div>
						</PopoverAnchor>
					)}
				</InputWrapper>

				<PopoverContent
					align="start"
					className="w-(--radix-popover-trigger-width) p-0"
					onOpenAutoFocus={(event) => {
						event.preventDefault()
						;(event.currentTarget as HTMLElement)
							.querySelector<HTMLInputElement>("input[type=search]")
							?.focus()
					}}>
					<div className="border-b p-2">
						<input
							type="search"
							value={query}
							placeholder={searchPlaceholder}
							onChange={(event) => setQuery(event.target.value)}
							className="w-full rounded-md bg-transparent px-2 py-1.5 text-sm focus:outline-none"
						/>
					</div>

					<div className="max-h-64 overflow-y-auto p-1">
						{filtered.length === 0 ? (
							<p className="px-2 py-6 text-center text-sm text-muted-foreground">
								No matches
							</p>
						) : (
							filtered.map((option) => {
								const checked = value.includes(option.value)
								const disabled = atMax && !checked

								return (
									<label
										key={option.value}
										className={cn(
											"flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent",
											disabled && "cursor-not-allowed opacity-50 hover:bg-transparent"
										)}>
										<Checkbox
											checked={checked}
											disabled={disabled}
											onCheckedChange={() => toggle(option.value)}
										/>
										<span className="flex-1">{option.value}</span>
										{option.hint && (
											<span className="text-xs text-muted-foreground">
												{option.hint}
											</span>
										)}
									</label>
								)
							})
						)}
					</div>

					{max !== undefined && (
						<p className="border-t px-3 py-2 text-xs text-muted-foreground">
							{value.length} of {max} selected
						</p>
					)}
				</PopoverContent>
			</Popover>

			{(error || helperText) && (
				<p
					className={cn(
						"mt-1.5 px-4 font-nunito text-xs font-light",
						error ? "text-red-400 dark:text-red-400" : "text-neutral-500 dark:text-white/40"
					)}>
					{error || helperText}
				</p>
			)}
		</div>
	)
}

export { MultiSelectField }
