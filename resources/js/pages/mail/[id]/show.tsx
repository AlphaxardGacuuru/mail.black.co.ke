import { useQueryClient } from "@tanstack/react-query"
import { useNavigate, useRouterState } from "@tanstack/react-router"
import { useEffect, useRef } from "react"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import MailThreadView from "@/components/mail/MailThreadView"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { invalidateAuth } from "@/middleware/auth"

export default function MailShow({ id }: { id: string }) {
	const navigate = useNavigate()
	const queryClient = useQueryClient()
	const { auth } = useApp()
	const accountId = useRouterState({
		select: (state) =>
			(state.location.search as { account?: string }).account,
	})
	const switchStarted = useRef(false)

	// Notification links carry the account that received the mail; threads
	// are scoped to the active account, so switch to it before loading.
	const needsSwitch = !!auth?.mailgunAccounts?.some(
		(account) => account.id === accountId && !account.isActive
	)

	useEffect(() => {
		if (!accountId) {
			return
		}

		// Drop the param once handled, so switching accounts later from this
		// page doesn't get undone.
		const clearParam = () =>
			navigate({ to: `/mail/${id}/show` as never, replace: true })

		if (!needsSwitch) {
			if (auth) {
				void clearParam()
			}

			return
		}

		if (switchStarted.current) {
			return
		}

		switchStarted.current = true

		Axios.post(MailgunAccountController.activate.url(accountId))
			.then(() => {
				invalidateAuth()
				queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
				queryClient.invalidateQueries({ queryKey: ["mail", "thread"] })
			})
			.catch(() => {
				toast.error("Unable to switch mail account.")
				void clearParam()
			})
	}, [accountId, auth, id, navigate, needsSwitch, queryClient])

	return (
		<>
			<Head title="Mail" />

			<div className="flex h-[calc(100vh-4rem)] flex-col rounded-lg overflow-hidden">
				{accountId && (needsSwitch || !auth) ? (
					<div className="flex flex-1 items-center justify-center">
						<Spinner className="size-6" />
					</div>
				) : (
					<MailThreadView
						threadId={id}
						variant="page"
						onBack={() => navigate({ to: "/mail" })}
					/>
				)}
			</div>
		</>
	)
}
