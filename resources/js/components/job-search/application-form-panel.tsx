import {
	CircleCheck,
	Copy,
	ExternalLink,
	LoaderCircle,
	RotateCcw,
	ScanSearch,
} from "lucide-react"
import { useState } from "react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { SelectField, SelectItem } from "@/components/ui/select"
import { useClipboard } from "@/hooks/use-clipboard"
import toast from "@/lib/toast"
import {
	errorMessage,
	usePrepareApplicationForm,
	useSaveApplicationFormAnswers,
	useSubmitApplicationForm,
} from "@/queries/job-search"
import type {
	ApplicationForm,
	ApplicationFormEffort,
	ApplicationFormField,
	JobApplication,
} from "@/types"

const TICKED = /^(yes|true|on|1)$/i

const EFFORTS: Record<
	ApplicationFormEffort,
	{ label: string; className: string }
> = {
	filled: {
		label: "Filled",
		className: "border-transparent bg-lime-600 text-white",
	},
	check: {
		label: "Check",
		className: "border-transparent bg-yellow-500 text-white",
	},
	needs_you: {
		label: "Needs you",
		className: "border-transparent bg-destructive text-white",
	},
	optional: {
		label: "Optional",
		className: "border-transparent bg-secondary text-secondary-foreground",
	},
}

const INPUT_TYPES = ["email", "tel", "url", "number", "date"]

/**
 * Applying on the employer's own form, inside an "apply yourself" card:
 * the form's questions with suggested answers, how much is left to do,
 * and a screenshot of the form, for the user to review and submit.
 */
export function ApplicationFormPanel({
	application,
}: {
	application: JobApplication
}) {
	const prepare = usePrepareApplicationForm()
	const { form } = application

	const read = () =>
		prepare
			.mutateAsync(application.id)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to read the form."))
			)

	if (!form) {
		return (
			<div className="space-y-3 rounded-lg border border-border/60 p-4 text-sm">
				<div className="flex gap-3">
					<ScanSearch className="size-5 shrink-0 text-muted-foreground" />
					<span className="min-w-0">
						Read this job's application form and get a suggested answer to every
						question, with how long the rest will take you.
					</span>
				</div>
				<div className="flex justify-end">
					<Button
						type="button"
						size="sm"
						disabled={prepare.isPending}
						onClick={read}>
						{prepare.isPending && <LoaderCircle className="animate-spin" />}
						Prepare application
					</Button>
				</div>
			</div>
		)
	}

	if (form.status === "preparing" || form.status === "submitting") {
		return (
			<div className="flex items-center gap-3 rounded-lg border border-border/60 p-4 text-sm text-muted-foreground">
				<LoaderCircle className="size-5 shrink-0 animate-spin" />
				{form.status === "preparing"
					? "Reading the form and drafting answers. This takes a minute or two."
					: "Filling in the form and submitting it. This takes a minute or two."}
			</div>
		)
	}

	if (form.status === "submitted") {
		return (
			<div className="space-y-3 rounded-lg border border-border/60 p-4 text-sm">
				<div className="flex items-center gap-2 font-medium">
					<CircleCheck className="size-5 text-lime-600" />
					Application submitted
				</div>
				<Screenshot
					src={form.screenshots.confirmation}
					label="What the site showed after submitting"
				/>
			</div>
		)
	}

	if (form.status === "blocked" || form.status === "failed") {
		return (
			<div className="space-y-3 rounded-lg border border-border/60 p-4 text-sm">
				<p>{form.error}</p>
				{form.status === "failed" && (
					<div className="flex justify-end">
						<Button
							type="button"
							variant="secondary"
							size="sm"
							disabled={prepare.isPending}
							onClick={read}>
							<RotateCcw />
							Try again
						</Button>
					</div>
				)}
			</div>
		)
	}

	return (
		<FormReview
			// Reading the form again starts over from the new suggestions.
			key={form.preparedAt}
			application={application}
			form={form}
			onReadAgain={read}
			reading={prepare.isPending}
		/>
	)
}

function FormReview({
	application,
	form,
	onReadAgain,
	reading,
}: {
	application: JobApplication
	form: ApplicationForm
	onReadAgain: () => void
	reading: boolean
}) {
	const save = useSaveApplicationFormAnswers()
	const submit = useSubmitApplicationForm()
	const [answers, setAnswers] = useState<Record<string, string>>(() =>
		Object.fromEntries(form.fields.map((field) => [field.key, field.answer]))
	)

	const changed = Object.fromEntries(
		form.fields
			.filter((field) => answers[field.key] !== field.answer)
			.map((field) => [field.key, answers[field.key]])
	)
	const unanswered = form.fields.filter(
		(field) =>
			field.required &&
			!field.group &&
			field.type !== "file" &&
			!answers[field.key]?.trim()
	)
	const counts = form.fields.reduce(
		(total, field) => ({ ...total, [field.effort]: total[field.effort] + 1 }),
		{ filled: 0, check: 0, needs_you: 0, optional: 0 }
	)
	const captchaStops = !!form.captcha && !form.captchaSolved
	const busy = save.isPending || submit.isPending || reading

	return (
		<div className="space-y-4 rounded-lg border border-border/60 p-4">
			<div className="flex flex-wrap items-center gap-2 text-sm">
				<Badge className={EFFORTS.filled.className}>
					{counts.filled} filled
				</Badge>
				<Badge className={EFFORTS.check.className}>
					{counts.check} to check
				</Badge>
				<Badge className={EFFORTS.needs_you.className}>
					{counts.needs_you} need you
				</Badge>
				<span className="text-muted-foreground">
					{form.minutesLeft > 0
						? `About ${form.minutesLeft} min left`
						: "Nothing left to do"}
				</span>
			</div>

			{form.status === "submit_failed" && (
				<div className="space-y-2 rounded-lg bg-destructive/10 p-3 text-sm">
					<p>{form.error}</p>
					<Screenshot
						src={form.screenshots.confirmation ?? form.screenshots.filled}
						label="What the site showed"
					/>
				</div>
			)}

			{form.captcha && (
				<p className="text-sm text-muted-foreground">
					{captchaStops
						? "This form has a CAPTCHA, which only you can fill in. Open the form and copy each answer across."
						: "This form has a CAPTCHA, which is solved automatically when you submit. This is an experiment and may be turned away by the site."}
				</p>
			)}

			<Screenshot
				src={form.screenshots.form}
				label="The form as it looks on the site"
			/>

			<ol className="space-y-5">
				{form.fields.map((field) => (
					<FieldRow
						key={field.key}
						field={field}
						value={answers[field.key] ?? ""}
						onChange={(value) =>
							setAnswers((current) => ({ ...current, [field.key]: value }))
						}
					/>
				))}
			</ol>

			<div className="flex flex-wrap items-center justify-end gap-2">
				{form.url && (
					<Link
						href={form.url}
						target="_blank"
						rel="noopener noreferrer"
						variant="outline"
						size="sm"
						className="mr-auto normal-case">
						Open the form
						<ExternalLink />
					</Link>
				)}
				<Button
					type="button"
					variant="secondary"
					size="sm"
					disabled={busy}
					onClick={onReadAgain}>
					<RotateCcw />
					Read again
				</Button>
				{Object.keys(changed).length > 0 && (
					<Button
						type="button"
						variant="secondary"
						size="sm"
						disabled={busy}
						onClick={() =>
							save
								.mutateAsync({ id: application.id, answers: changed })
								.then((data) => toast.success(data.message))
								.catch((error) =>
									toast.error(
										errorMessage(error, "Unable to save the answers.")
									)
								)
						}>
						Save answers
					</Button>
				)}
				<Button
					type="button"
					size="sm"
					disabled={busy || captchaStops || unanswered.length > 0}
					title={
						unanswered.length > 0
							? `Answer these first: ${unanswered.map((field) => field.label).join("; ")}`
							: undefined
					}
					onClick={() =>
						submit
							.mutateAsync({ id: application.id, answers: changed })
							.then((data) => toast.success(data.message))
							.catch((error) =>
								toast.error(errorMessage(error, "Unable to submit the form."))
							)
					}>
					{submit.isPending && <LoaderCircle className="animate-spin" />}
					Submit application
				</Button>
			</div>
		</div>
	)
}

function FieldRow({
	field,
	value,
	onChange,
}: {
	field: ApplicationFormField
	value: string
	onChange: (value: string) => void
}) {
	const [, copy] = useClipboard()
	const answered = value.trim() !== ""
	const effort =
		field.effort === "needs_you" && answered ? "filled" : field.effort
	const label = field.group
		? `${field.group}: ${field.label}`
		: field.label || "Unlabelled question"

	return (
		<li className="space-y-2">
			<div className="flex items-start justify-between gap-3">
				<div className="min-w-0 text-sm font-medium">
					{label}
					{field.required && <span className="text-destructive"> *</span>}
				</div>
				<div className="flex shrink-0 items-center gap-1">
					<Badge className={EFFORTS[effort].className}>
						{EFFORTS[effort].label}
					</Badge>
					{answered && field.type !== "file" && (
						<Button
							type="button"
							variant="secondary"
							size="icon"
							className="size-7"
							aria-label={`Copy the answer to ${label}`}
							onClick={() =>
								copy(value).then(
									(copied) => copied && toast.success("Answer Copied")
								)
							}>
							<Copy className="size-3.5" />
						</Button>
					)}
				</div>
			</div>

			{/* Answer Start */}
			<FieldInput
				field={field}
				label={label}
				value={value}
				onChange={onChange}
			/>
			{/* Answer End */}

			{field.note && (
				<p className="text-xs text-muted-foreground">{field.note}</p>
			)}
		</li>
	)
}

function FieldInput({
	field,
	label,
	value,
	onChange,
}: {
	field: ApplicationFormField
	label: string
	value: string
	onChange: (value: string) => void
}) {
	if (field.type === "file") {
		return (
			<p className="text-sm text-muted-foreground">
				{value === "cv"
					? "Your CV is uploaded here."
					: "Nothing is uploaded here."}
			</p>
		)
	}

	if (field.type === "checkbox") {
		return (
			<label className="flex items-center gap-2 text-sm">
				<Checkbox
					checked={TICKED.test(value)}
					onCheckedChange={(checked) => onChange(checked ? "Yes" : "")}
				/>
				Tick
			</label>
		)
	}

	if (field.options.length > 0) {
		return (
			<SelectField
				label="Choose an answer"
				value={value || undefined}
				onValueChange={onChange}>
				{field.options.map((option) => (
					<SelectItem
						key={option}
						value={option}>
						{option}
					</SelectItem>
				))}
			</SelectField>
		)
	}

	if (field.type === "textarea") {
		return (
			<textarea
				aria-label={label}
				value={value}
				rows={5}
				onChange={(event) => onChange(event.target.value)}
				className="w-full rounded-lg border border-neutral-300 bg-transparent p-4 font-nunito text-sm font-light focus:border-neutral-500 focus:ring-2 focus:ring-neutral-400/20 focus:outline-none dark:border-white/20"
			/>
		)
	}

	return (
		<Input
			aria-label={label}
			type={INPUT_TYPES.includes(field.type) ? field.type : "text"}
			value={value}
			onChange={(event) => onChange(event.target.value)}
		/>
	)
}

function Screenshot({ src, label }: { src: string | null; label: string }) {
	if (!src) {
		return null
	}

	return (
		<a
			href={src}
			target="_blank"
			rel="noopener noreferrer"
			className="block w-full max-w-xs overflow-hidden rounded-lg border border-border/60">
			<img
				src={src}
				alt={label}
				loading="lazy"
				className="max-h-48 w-full object-cover object-top"
			/>
			<span className="block px-3 py-1.5 text-xs text-muted-foreground">
				{label}
			</span>
		</a>
	)
}
