import Heading from "@/components/heading"
import PushNotificationSettings from "@/components/push-notification-settings"
import { Checkbox } from "@/components/ui/checkbox"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { errorMessage } from "@/queries/job-search"
import {
	useNotificationPreferences,
	useUpdateNotificationPreference,
} from "@/queries/notification-preferences"
import { edit } from "@/routes/notification-settings"
import type { NotificationCategory, NotificationChannel } from "@/types"

const CHANNELS: { key: NotificationChannel; label: string }[] = [
	{ key: "inApp", label: "In app" },
	{ key: "push", label: "Push" },
	{ key: "email", label: "Email" },
]

// Three equal channel columns beside a flexible label column
const ROW = "grid grid-cols-[1fr_repeat(3,3.5rem)] items-center gap-2"

function CategoryRow({ category }: { category: NotificationCategory }) {
	const update = useUpdateNotificationPreference()
	const blockedReasons = Object.values(category.blocked)

	return (
		<div className={`${ROW} py-3`}>
			<div className="min-w-0">
				<p className="text-sm font-medium">{category.label}</p>
				<p className="text-xs text-muted-foreground">{category.description}</p>
				{blockedReasons.map((reason) => (
					<p
						key={reason}
						className="mt-1 text-xs text-amber-600 dark:text-amber-400">
						{reason}
					</p>
				))}
			</div>
			{CHANNELS.map((channel) => (
				<div
					key={channel.key}
					className="flex justify-center">
					<Checkbox
						aria-label={`${category.label}: ${channel.label}`}
						checked={category.channels[channel.key]}
						disabled={!!category.blocked[channel.key]}
						onCheckedChange={(checked) =>
							update.mutate(
								{
									category: category.key,
									channel: channel.key,
									enabled: checked === true,
								},
								{
									onError: (error) =>
										toast.error(
											errorMessage(error, "Unable to save that setting.")
										),
								}
							)
						}
					/>
				</div>
			))}
		</div>
	)
}

export default function NotificationSettings() {
	const { data, isLoading } = useNotificationPreferences()

	// In config order, grouped (Object.groupBy is missing on older phones)
	const groups = new Map<string, NotificationCategory[]>()
	for (const category of data?.categories ?? []) {
		groups.set(category.group, [...(groups.get(category.group) ?? []), category])
	}

	return (
		<>
			<Head title="Notifications" />

			<h1 className="sr-only">Notifications</h1>

			<div className="space-y-10">
				<div className="space-y-4">
					<Heading
						variant="small"
						title="This device"
						description="Push notifications are turned on per browser or phone."
					/>
					<PushNotificationSettings />
				</div>

				<div className="space-y-4">
					<Heading
						variant="small"
						title="What to notify you about"
						description={`Choose where each notification arrives. Emails go to ${data?.email ?? "your login email"}.`}
					/>

					{isLoading ? (
						<div className="space-y-3">
							<Skeleton className="h-14 w-full" />
							<Skeleton className="h-14 w-full" />
							<Skeleton className="h-14 w-full" />
						</div>
					) : (
						[...groups].map(([group, categories]) => (
							<section
								key={group}
								className="rounded-lg border px-4">
								<div
									className={`${ROW} border-b py-2 text-xs font-medium text-muted-foreground`}>
									<h2 className="text-sm text-foreground">{group}</h2>
									{CHANNELS.map((channel) => (
										<span
											key={channel.key}
											className="text-center">
											{channel.label}
										</span>
									))}
								</div>
								<div className="divide-y">
									{categories.map((category) => (
										<CategoryRow
											key={category.key}
											category={category}
										/>
									))}
								</div>
							</section>
						))
					)}
				</div>
			</div>
		</>
	)
}

NotificationSettings.layout = {
	breadcrumbs: [
		{
			title: "Notifications",
			href: edit(),
		},
	],
}
