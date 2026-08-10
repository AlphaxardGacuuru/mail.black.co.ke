import { icons } from "lucide-react"
import type { LucideIcon } from "lucide-react"
import { ChevronLeft, ChevronRight, ChevronDown } from "lucide-react"
import { createElement, useMemo, useState } from "react"
import { Button } from "@/components/ui/button"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { InputWrapper } from "@/components/ui/input-wrapper"
import { cn } from "@/lib/utils"

const ICON_NAMES = Object.keys(icons).sort((left, right) =>
	left.localeCompare(right)
)
const ICONS_PER_PAGE = 36

type LucideIconPickerProps = {
	id?: string
	name: string
	defaultValue?: string
	placeholder?: string
	required?: boolean
	onChange?: (value: string) => void
	label?: string
	error?: string | boolean
	helperText?: string
}

function resolveIcon(icon?: string): LucideIcon | null {
	if (!icon) {
		return null
	}

	return (icons[icon as keyof typeof icons] as LucideIcon | undefined) ?? null
}

function fuzzyMatch(term: string, query: string): boolean {
	if (!query) {
		return true
	}

	const normalizedTerm = term.toLowerCase()
	const normalizedQuery = query.toLowerCase().trim()

	if (!normalizedQuery) {
		return true
	}

	if (normalizedTerm.includes(normalizedQuery)) {
		return true
	}

	// Subsequence fuzzy match: "ac" matches "AlarmClock".
	let queryIndex = 0

	for (const character of normalizedTerm) {
		if (character === normalizedQuery[queryIndex]) {
			queryIndex += 1
		}

		if (queryIndex === normalizedQuery.length) {
			return true
		}
	}

	return false
}

export default function LucideIconPicker({
	id,
	name,
	defaultValue,
	placeholder = "Select an icon",
	required,
	onChange,
	label,
	error,
	helperText,
}: LucideIconPickerProps) {
	const [open, setOpen] = useState(false)
	const [value, setValue] = useState(defaultValue ?? "")
	const [query, setQuery] = useState("")
	const [page, setPage] = useState(1)
	const selectedIcon = resolveIcon(value)

	const filteredIconNames = useMemo(
		() => ICON_NAMES.filter((iconName) => fuzzyMatch(iconName, query)),
		[query]
	)
	const totalPages = Math.max(
		1,
		Math.ceil(filteredIconNames.length / ICONS_PER_PAGE)
	)

	const currentPageIcons = useMemo(() => {
		const start = (page - 1) * ICONS_PER_PAGE

		return filteredIconNames.slice(start, start + ICONS_PER_PAGE)
	}, [filteredIconNames, page])

	function onOpenChange(nextOpen: boolean) {
		setOpen(nextOpen)

		if (!nextOpen || !value) {
			return
		}

		const selectedIndex = ICON_NAMES.indexOf(value)

		if (selectedIndex < 0) {
			return
		}

		setPage(Math.floor(selectedIndex / ICONS_PER_PAGE) + 1)
	}

	function onSelectIcon(iconName: string) {
		setValue(iconName)
		onChange?.(iconName)
		setOpen(false)
	}

	function onSearchChange(searchValue: string) {
		setQuery(searchValue)
		setPage(1)
	}

	return (
		<div className="relative w-full">
			<input
				type="hidden"
				name={name}
				value={value}
				required={required}
			/>

			<InputWrapper error={error}>
				{({ focused, handleFocus, handleBlur }) => {
					const isActive = focused || value !== ""

					return (
						<>
							<DropdownMenu
								open={open}
								onOpenChange={onOpenChange}>
								<DropdownMenuTrigger asChild>
									<button
										id={id}
										type="button"
										onFocus={handleFocus}
										onBlur={handleBlur}
										className={cn(
											"font-nunito relative flex h-14 w-full items-center justify-between rounded-lg border bg-transparent px-4 pt-5 pb-1 text-left text-base font-light text-white transition-all focus:outline-none disabled:cursor-not-allowed disabled:bg-white/5 disabled:opacity-50",
											error
												? "border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/20"
												: "border-white/20 focus:border-white/40 focus:ring-2 focus:ring-white/10"
										)}>
										{selectedIcon ? (
											<span className="flex min-w-0 items-center gap-2 truncate">
												{createElement(selectedIcon, {
													className: "size-4 shrink-0",
													"aria-hidden": "true",
												})}
												<span className="truncate">{value}</span>
											</span>
										) : (
											<span className="text-transparent">
												{label ?? placeholder}
											</span>
										)}

										<ChevronDown
											className={cn(
												"absolute right-4 shrink-0 opacity-50 transition-all duration-200 size-4"
											)}
										/>
									</button>
								</DropdownMenuTrigger>

								<DropdownMenuContent
									align="start"
									sideOffset={6}
									className="w-88 p-3">
									<Input
										type="search"
										value={query}
										onChange={(event) => onSearchChange(event.target.value)}
										placeholder="Search Icon"
										className="mb-3"
									/>

									<div className="mb-3 flex items-center justify-between gap-2">
										<p className="text-xs text-muted-foreground">
											{filteredIconNames.length} result
											{filteredIconNames.length === 1 ? "" : "s"} • Page {page}{" "}
											of {totalPages}
										</p>
										<div className="flex items-center gap-1">
											<Button
												type="button"
												variant="outline"
												size="sm"
												onClick={() => setPage((prev) => Math.max(1, prev - 1))}
												disabled={page === 1}
												aria-label="Previous icon page">
												<ChevronLeft className="size-4" />
											</Button>
											<Button
												type="button"
												variant="outline"
												size="sm"
												onClick={() =>
													setPage((prev) => Math.min(totalPages, prev + 1))
												}
												disabled={page === totalPages}
												aria-label="Next icon page">
												<ChevronRight className="size-4" />
											</Button>
										</div>
									</div>

									<div className="grid max-h-80 grid-cols-6 gap-2 overflow-y-auto pr-1">
										{currentPageIcons.map((iconName) => {
											const iconComponent = resolveIcon(iconName)

											if (!iconComponent) {
												return null
											}

											return (
												<button
													key={iconName}
													type="button"
													onClick={() => onSelectIcon(iconName)}
													title={iconName}
													className={cn(
														"flex h-10 w-full items-center justify-center rounded-md border border-input bg-background transition-colors outline-none hover:bg-accent hover:text-accent-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
														value === iconName
															? "border-primary bg-primary/10 text-primary"
															: null
													)}>
													{createElement(iconComponent, {
														className: "size-4",
														"aria-hidden": "true",
													})}
												</button>
											)
										})}

										{currentPageIcons.length === 0 ? (
											<p className="col-span-6 py-6 text-center text-xs text-muted-foreground">
												No icons match your search.
											</p>
										) : null}
									</div>

									<p className="mt-2 text-xs text-muted-foreground">
										Selected: {value || "None"}
									</p>
								</DropdownMenuContent>
							</DropdownMenu>

							{label ? (
								<label
									className={cn(
										"font-nunito pointer-events-none absolute left-4 z-10 font-light transition-all duration-200",
										isActive
											? "top-1.5 text-xs text-white"
											: "top-4 text-base text-white",
										error && isActive ? "text-red-400" : null
									)}>
									{label}
								</label>
							) : null}
						</>
					)
				}}
			</InputWrapper>

			{error || helperText ? (
				<p
					className={cn(
						"font-nunito mt-1.5 px-4 text-xs font-light",
						error ? "text-red-400" : "text-white/40"
					)}>
					{error || helperText}
				</p>
			) : null}
		</div>
	)
}
