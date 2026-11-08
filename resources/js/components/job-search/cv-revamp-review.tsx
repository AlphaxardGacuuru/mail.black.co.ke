import {
	Check,
	Download,
	ExternalLink,
	LoaderCircle,
	Pencil,
	RotateCcw,
	Sparkles,
} from "lucide-react"
import { useState } from "react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { Switch } from "@/components/ui/switch"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	useStartCvRevamp,
	useStopUsingCvRevamp,
	useUpdateCvRevamp,
	useUpdateCvTailoring,
	useUseCvRevamp,
} from "@/queries/cv-revamp"
import type { CvRevampChanges } from "@/queries/cv-revamp"
import { errorMessage } from "@/queries/job-search"
import type {
	CvEntry,
	CvRevamp,
	CvRevampQuestion,
	CvRevampSection,
	CvRevampState,
} from "@/types"

const TEXTAREA =
	"w-full rounded-lg border border-neutral-300 bg-transparent p-3 font-nunito text-sm font-light focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-400/20 dark:border-white/20"

/** Shows the toast for a finished change, or what went wrong. */
function notify<T extends { message: string }>(
	request: Promise<T>,
	failure: string
): Promise<T | void> {
	return request
		.then((data) => {
			toast.success(data.message)

			return data
		})
		.catch((error) => {
			toast.error(errorMessage(error, failure))
		})
}

/**
 * The revamped CV for review: what to send and download, the AI's
 * questions, tailoring to each job, and each section next to what the CV
 * said, to keep either and edit the rewrite.
 */
export function CvRevampReview({
	state,
	revamp,
}: {
	state: CvRevampState
	revamp: CvRevamp
}) {
	const update = useUpdateCvRevamp()
	const groups = revamp.sections.reduce<[string, CvRevampSection[]][]>(
		(all, section) => {
			const last = all[all.length - 1]

			if (last && last[0] === section.heading) {
				last[1].push(section)
			} else {
				all.push([section.heading, [section]])
			}

			return all
		},
		[]
	)

	const save = (changes: CvRevampChanges) =>
		notify(update.mutateAsync(changes), "Unable to save your changes.")

	return (
		<div className="space-y-6">
			<UseCard revamp={revamp} />

			{revamp.questions.length > 0 && (
				<QuestionsCard
					// A new rewrite brings new questions; start their answers over.
					key={revamp.questions
						.map((question) => `${question.key}:${question.used}`)
						.join()}
					revamp={revamp}
					onSave={save}
				/>
			)}

			<TailoringCard
				state={state}
				revamp={revamp}
			/>

			<div className="space-y-1">
				<h2 className="text-lg font-semibold">{revamp.name}</h2>
				{revamp.headline && (
					<p className="text-sm text-muted-foreground">{revamp.headline}</p>
				)}
				{revamp.contact.length > 0 && (
					<p className="text-xs text-muted-foreground">
						{revamp.contact.join(" · ")}
					</p>
				)}
			</div>

			{groups.map(([heading, sections]) => (
				<section
					key={`${heading}-${sections[0].key}`}
					className="space-y-3">
					<h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
						{heading}
					</h3>
					{sections.map((section) => (
						<SectionCard
							key={section.key}
							section={section}
							questions={revamp.questions.filter(
								(question) => question.section === section.key && !question.used
							)}
							saving={update.isPending}
							onSave={(change) => save({ sections: { [section.key]: change } })}
						/>
					))}
				</section>
			))}
		</div>
	)
}

function UseCard({ revamp }: { revamp: CvRevamp }) {
	const use = useUseCvRevamp()
	const stop = useStopUsingCvRevamp()
	const start = useStartCvRevamp()
	const busy = use.isPending || stop.isPending || start.isPending
	const changedSinceUse =
		revamp.inUse &&
		!!revamp.usedAt &&
		new Date(revamp.updatedAt) > new Date(revamp.usedAt)

	return (
		<GlassCard className="space-y-4 p-4">
			<div className="flex flex-wrap items-center gap-2 text-sm">
				{revamp.inUse ? (
					<Badge className="border-transparent bg-lime-600 text-white">
						<Check />
						Sent with your applications
					</Badge>
				) : (
					<Badge variant="secondary">Not used yet</Badge>
				)}
				<span className="text-muted-foreground">
					{revamp.inUse
						? changedSinceUse
							? "You've made changes since. Update it so employers get them."
							: "Employers get this CV instead of the one you uploaded."
						: "Review each section, then use it to send it to employers instead of the one you uploaded."}
				</span>
			</div>

			<div className="flex flex-wrap items-center gap-2">
				{revamp.downloads && (
					<>
						<Link
							href={revamp.downloads.pdf}
							target="_blank"
							rel="noopener noreferrer"
							variant="outline"
							size="sm"
							className="normal-case">
							Preview PDF
							<ExternalLink />
						</Link>
						<Link
							href={revamp.downloads.docx}
							variant="outline"
							size="sm"
							className="normal-case">
							Word file
							<Download />
						</Link>
					</>
				)}
				<Button
					type="button"
					variant="secondary"
					size="sm"
					className="ml-auto"
					disabled={busy}
					onClick={() =>
						notify(start.mutateAsync(undefined), "Unable to revamp your CV.")
					}>
					<RotateCcw />
					Start over
				</Button>
				{revamp.inUse && (
					<Button
						type="button"
						variant="secondary"
						size="sm"
						disabled={busy}
						onClick={() =>
							notify(stop.mutateAsync(undefined), "Unable to switch back.")
						}>
						Send my own CV
					</Button>
				)}
				{(!revamp.inUse || changedSinceUse) && (
					<Button
						type="button"
						size="sm"
						disabled={busy}
						onClick={() =>
							notify(use.mutateAsync(undefined), "Unable to use this CV.")
						}>
						{use.isPending && <LoaderCircle className="animate-spin" />}
						{revamp.inUse ? "Update the CV I send" : "Use this CV"}
					</Button>
				)}
			</div>
		</GlassCard>
	)
}

function QuestionsCard({
	revamp,
	onSave,
}: {
	revamp: CvRevamp
	onSave: (changes: CvRevampChanges) => Promise<unknown>
}) {
	const start = useStartCvRevamp()
	const [answers, setAnswers] = useState<Record<string, string>>(() =>
		Object.fromEntries(
			revamp.questions.map((question) => [question.key, question.answer])
		)
	)
	const changed = Object.fromEntries(
		revamp.questions
			.filter((question) => answers[question.key] !== question.answer)
			.map((question) => [question.key, answers[question.key]])
	)
	// Answers the rewrite doesn't use yet.
	const fresh = revamp.questions.some(
		(question) => !question.used && answers[question.key]?.trim()
	)

	return (
		<GlassCard className="space-y-4 p-4">
			<div className="space-y-1">
				<div className="flex items-center gap-2 font-medium">
					<Sparkles className="size-4 text-primary" />
					Make it stronger
				</div>
				<p className="text-sm text-muted-foreground">
					Numbers get interviews, and the AI never makes them up. Answer what
					you can, then revamp again to work them in.
				</p>
			</div>

			<ol className="space-y-3">
				{revamp.questions.map((question) => (
					<QuestionRow
						key={question.key}
						question={question}
						value={answers[question.key] ?? ""}
						onChange={(value) =>
							setAnswers((current) => ({ ...current, [question.key]: value }))
						}
					/>
				))}
			</ol>

			<div className="flex flex-wrap justify-end gap-2">
				{Object.keys(changed).length > 0 && (
					<Button
						type="button"
						variant="secondary"
						size="sm"
						onClick={() => onSave({ answers: changed })}>
						Save answers
					</Button>
				)}
				<Button
					type="button"
					size="sm"
					disabled={!fresh || start.isPending}
					onClick={() =>
						onSave({ answers: changed }).then(() =>
							notify(start.mutateAsync(undefined), "Unable to revamp your CV.")
						)
					}>
					{start.isPending && <LoaderCircle className="animate-spin" />}
					Revamp again with my answers
				</Button>
			</div>
		</GlassCard>
	)
}

function QuestionRow({
	question,
	value,
	onChange,
}: {
	question: CvRevampQuestion
	value: string
	onChange: (value: string) => void
}) {
	return (
		<li className="space-y-1.5">
			<div className="flex items-start justify-between gap-2 text-sm">
				<span>{question.question}</span>
				{question.used && (
					<Badge
						variant="secondary"
						className="shrink-0">
						In your CV
					</Badge>
				)}
			</div>
			{/* Answer Start */}
			<Input
				aria-label={question.question}
				value={value}
				maxLength={500}
				onChange={(event) => onChange(event.target.value)}
			/>
			{/* Answer End */}
		</li>
	)
}

function TailoringCard({
	state,
	revamp,
}: {
	state: CvRevampState
	revamp: CvRevamp
}) {
	const tailoring = useUpdateCvTailoring()

	return (
		<GlassCard className="space-y-2 p-4">
			{/* Tailor CV Start */}
			<label className="flex items-center justify-between gap-4 text-sm font-medium">
				Tailor my CV to each job
				<Switch
					checked={state.tailorCv}
					disabled={!revamp.inUse || tailoring.isPending}
					onCheckedChange={(tailorCv) =>
						notify(
							tailoring.mutateAsync(tailorCv),
							"Unable to save the setting."
						)
					}
				/>
			</label>
			{/* Tailor CV End */}
			<p className="text-sm text-muted-foreground">
				{revamp.inUse
					? "Each application gets a copy of this CV that puts what the employer asks for first. Nothing new is added."
					: "Use this CV first: each job's copy is made from it."}{" "}
				You can also tailor it to a single job from its card.
			</p>
		</GlassCard>
	)
}

function SectionCard({
	section,
	questions,
	saving,
	onSave,
}: {
	section: CvRevampSection
	questions: CvRevampQuestion[]
	saving: boolean
	onSave: (change: {
		keep?: "revamped" | "original"
		text?: string
		bullets?: string[]
	}) => Promise<unknown>
}) {
	const [editing, setEditing] = useState(false)
	const keepsOriginal = section.keep === "original" && !!section.original

	return (
		<GlassCard className="space-y-4 p-4">
			<div className="grid gap-4 md:grid-cols-2">
				<div
					className={cn(
						"space-y-2 rounded-lg border p-3 transition-opacity",
						keepsOriginal ? "border-primary" : "border-border/60 opacity-70"
					)}>
					<div className="text-xs font-medium text-muted-foreground uppercase">
						Your CV
					</div>
					{section.original ? (
						<EntryView entry={section.original} />
					) : (
						<p className="text-sm text-muted-foreground">
							Not in your CV: the AI added this from what it says.
						</p>
					)}
				</div>
				<div
					className={cn(
						"space-y-2 rounded-lg border p-3",
						keepsOriginal ? "border-border/60 opacity-70" : "border-primary"
					)}>
					<div className="flex items-center gap-2 text-xs font-medium text-muted-foreground uppercase">
						Revamped
						{section.edited && <Badge variant="secondary">Edited</Badge>}
					</div>
					{editing ? (
						<EntryEditor
							entry={section.revamped}
							saving={saving}
							onCancel={() => setEditing(false)}
							onSave={(change) => onSave(change).then(() => setEditing(false))}
						/>
					) : (
						<EntryView entry={section.revamped} />
					)}
				</div>
			</div>

			{section.why && (
				<p className="text-sm text-muted-foreground">{section.why}</p>
			)}

			{questions.map((question) => (
				<p
					key={question.key}
					className="text-sm text-muted-foreground">
					<Sparkles className="mr-1 inline size-3.5 text-primary" />
					{question.question} Answer it above to add it.
				</p>
			))}

			<div className="flex flex-wrap items-center justify-end gap-2">
				{!editing && (
					<Button
						type="button"
						variant="secondary"
						size="sm"
						onClick={() => setEditing(true)}>
						<Pencil />
						Edit
					</Button>
				)}
				{section.original && (
					<Button
						type="button"
						variant={keepsOriginal ? "secondary" : "default"}
						size="sm"
						disabled={saving}
						onClick={() =>
							onSave({ keep: keepsOriginal ? "revamped" : "original" })
						}>
						{keepsOriginal ? "Use the revamped version" : "Keep my version"}
					</Button>
				)}
			</div>
		</GlassCard>
	)
}

function EntryView({ entry }: { entry: CvEntry }) {
	return (
		<div className="space-y-1.5 text-sm">
			{(entry.title || entry.period) && (
				<div className="flex flex-wrap items-baseline justify-between gap-x-3">
					<span className="font-medium">{entry.title}</span>
					<span className="text-xs text-muted-foreground">{entry.period}</span>
				</div>
			)}
			{entry.subtitle && (
				<div className="text-muted-foreground">{entry.subtitle}</div>
			)}
			{entry.text && <p className="whitespace-pre-line">{entry.text}</p>}
			{entry.bullets.length > 0 && (
				<ul className="list-disc space-y-1 pl-5">
					{entry.bullets.map((bullet, index) => (
						<li key={index}>{bullet}</li>
					))}
				</ul>
			)}
		</div>
	)
}

function EntryEditor({
	entry,
	saving,
	onCancel,
	onSave,
}: {
	entry: CvEntry
	saving: boolean
	onCancel: () => void
	onSave: (change: { text?: string; bullets?: string[] }) => void
}) {
	const [text, setText] = useState(entry.text)
	const [bullets, setBullets] = useState(entry.bullets.join("\n"))

	return (
		<div className="space-y-3">
			{(entry.text || entry.bullets.length === 0) && (
				<>
					{/* Text Start */}
					<textarea
						aria-label="Text"
						value={text}
						rows={4}
						maxLength={1500}
						onChange={(event) => setText(event.target.value)}
						className={TEXTAREA}
					/>
					{/* Text End */}
				</>
			)}
			{entry.bullets.length > 0 && (
				<div className="space-y-1">
					{/* Bullets Start */}
					<textarea
						aria-label="Points, one per line"
						value={bullets}
						rows={Math.max(4, entry.bullets.length + 1)}
						onChange={(event) => setBullets(event.target.value)}
						className={TEXTAREA}
					/>
					{/* Bullets End */}
					<p className="text-xs text-muted-foreground">One point per line.</p>
				</div>
			)}
			<div className="flex justify-end gap-2">
				<Button
					type="button"
					variant="secondary"
					size="sm"
					onClick={onCancel}>
					Cancel
				</Button>
				<Button
					type="button"
					size="sm"
					disabled={saving}
					onClick={() =>
						onSave({
							text,
							bullets: bullets
								.split("\n")
								.map((line) => line.trim())
								.filter(Boolean),
						})
					}>
					Save
				</Button>
			</div>
		</div>
	)
}
