import type { FC } from "react"
import { ArrowRight } from "lucide-react"

import { Link } from "@/components/ui/link"

const BillingTabInfo: FC = () => {
	return (
		<div className="flex flex-col items-center gap-3 rounded-2xl bg-primary/5 mb-2 p-6 text-center">
			<h3 className="text-xl font-semibold text-primary">Billing</h3>
			<p className="text-foreground">Payment made simple. Collection made automatic.</p>
			<p className="max-w-2xl text-sm text-muted-foreground">
				Invoices that send themselves. Reminders that work. Payments that flow
				effortlessly from tenant to you.
			</p>
			<Link
				href="/admin/dashboard"
				variant="solid"
				size="sm"
				className="mt-2 capitalize"
				iconFront={<ArrowRight className="size-4" />}>
				start now
			</Link>
		</div>
	)
}

export default BillingTabInfo