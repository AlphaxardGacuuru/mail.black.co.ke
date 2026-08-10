import { useEffect, useState } from "react"
import type { FormEvent } from "react"
import {
	ArrowLeft,
	Building2,
	Calculator,
	Droplets,
	FileText,
	Receipt,
} from "lucide-react"
import type { LucideIcon } from "lucide-react"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { show as showPropertyRoute, update as updatePropertyRoute } from "@/routes/properties"
import { Button } from "@/components/ui/button"
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { Separator } from "@/components/ui/separator"
import { Spinner } from "@/components/ui/spinner"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"

function SectionHeader({ icon: Icon, title }: { icon: LucideIcon; title: string }) {
	return (
		<div className="flex items-center gap-2">
			<div className="rounded-lg bg-primary/10 p-1.5 text-primary">
				<Icon className="size-4" />
			</div>
			<h3 className="text-sm font-semibold text-foreground">{title}</h3>
		</div>
	)
}

type ServiceCharge = {
	service: string
	electricity: string
	garbage: string
	security: string
	internet: string
	cleaning: string
	parking: string
}

type WaterBillRate = {
	council: string
	borehole: string
	tanker: string
}

type PropertyRecord = {
	name: string
	location: string
	depositFormula: string
	serviceCharge: Record<keyof ServiceCharge, number | string>
	waterBillRateCouncil: number | string
	waterBillRateBorehole: number | string
	waterBillRateTanker: number | string
	invoiceDate: string
	invoiceReminderDuration: number | string
	contractTerms: string | null
	email: boolean
	sms: boolean
}

const emptyServiceCharge: ServiceCharge = {
	service: "0",
	electricity: "0",
	garbage: "0",
	security: "0",
	internet: "0",
	cleaning: "0",
	parking: "0",
}

const emptyWaterBillRate: WaterBillRate = {
	council: "",
	borehole: "",
	tanker: "",
}

export default function PropertiesEditPage({ id }: { id: string }) {
	const props = useApp()

	const [loaded, setLoaded] = useState(false)
	const [name, setName] = useState("")
	const [location, setLocation] = useState("")
	const [rentMultiple, setRentMultiple] = useState("")
	const [additionalCharges, setAdditionalCharges] = useState("0")
	const [serviceCharge, setServiceCharge] = useState<ServiceCharge>(
		emptyServiceCharge
	)
	const [waterBillRate, setWaterBillRate] =
		useState<WaterBillRate>(emptyWaterBillRate)
	const [invoiceDate, setInvoiceDate] = useState("")
	const [invoiceReminderDuration, setInvoiceReminderDuration] = useState("")
	const [contractTerms, setContractTerms] = useState("")
	const [email, setEmail] = useState(true)
	const [sms, setSms] = useState(false)
	const [loading, setLoading] = useState(false)

	useEffect(() => {
		setLoaded(false)

		Axios.get(showPropertyRoute.url(id))
			.then((response) => {
				const data = response.data.data as PropertyRecord
				const [, rentMultipleValue, additionalChargesValue] =
					data.depositFormula.match(/^r\*([\d.]+)\+(-?\d+)$/) ?? []

				setName(data.name)
				setLocation(data.location)
				setRentMultiple(rentMultipleValue ?? "")
				setAdditionalCharges(
					Number(additionalChargesValue ?? 0).toLocaleString()
				)
				setServiceCharge({
					service: Number(data.serviceCharge?.service ?? 0).toLocaleString(),
					electricity: Number(
						data.serviceCharge?.electricity ?? 0
					).toLocaleString(),
					garbage: Number(data.serviceCharge?.garbage ?? 0).toLocaleString(),
					security: Number(data.serviceCharge?.security ?? 0).toLocaleString(),
					internet: Number(data.serviceCharge?.internet ?? 0).toLocaleString(),
					cleaning: Number(data.serviceCharge?.cleaning ?? 0).toLocaleString(),
					parking: Number(data.serviceCharge?.parking ?? 0).toLocaleString(),
				})
				setWaterBillRate({
					council: String(data.waterBillRateCouncil ?? ""),
					borehole: String(data.waterBillRateBorehole ?? ""),
					tanker: String(data.waterBillRateTanker ?? ""),
				})
				setInvoiceDate(String(parseInt(data.invoiceDate, 10)))
				setInvoiceReminderDuration(String(data.invoiceReminderDuration))
				setContractTerms(data.contractTerms ?? "")
				setEmail(data.email)
				setSms(data.sms)
				setLoaded(true)
			})
			.catch(() => {
				toast.error("Failed to fetch property")
			})
	}, [id])

	const fieldError = (field: string) =>
		props.getFieldError(
			props.formErrors.find((error) => error.field === field)?.message
		)

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault()
		setLoading(true)

		Axios.put(updatePropertyRoute.url(id), {
			name,
			location,
			depositFormula: `r*${rentMultiple}+${additionalCharges}`,
			serviceCharge,
			waterBillRate,
			invoiceDate: Number(invoiceDate),
			invoiceReminderDuration: Number(invoiceReminderDuration),
			contractTerms,
			email,
			sms,
		})
			.then((response) => {
				toast.success(response.data.message)
			})
			.catch((error) => {
				props.getErrors(error)
				toast.error("Failed to update property")
			})
			.finally(() => {
				setLoading(false)
			})
	}

	return (
		<div className="space-y-6">
			<Card className="overflow-hidden">
				<CardHeader className="pb-4">
					<div className="flex flex-wrap items-center justify-between gap-3">
						<div>
							<CardTitle>Edit Property</CardTitle>
							<CardDescription className="mt-1">
								Update charges, water rates, and invoicing preferences for
								this property.
							</CardDescription>
						</div>
						<Link
							href="/admin/properties"
							variant="outline"
							size="sm"
							icon={<ArrowLeft className="size-4" />}
							text="Back to properties"
						/>
					</div>
				</CardHeader>

				<CardContent>
					{!loaded ? (
						<div className="flex items-center justify-center py-16 text-muted-foreground">
							<Spinner className="size-6" />
						</div>
					) : (
						<form
							onSubmit={onSubmit}
							className="space-y-8">
							{/* Property Details Start */}
							<div className="space-y-4">
								<SectionHeader
									icon={Building2}
									title="Property Details"
								/>
								<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
									<Input
										label="Name"
										defaultValue={name}
										onChange={(event) => setName(event.target.value)}
										error={fieldError("name")}
										required
									/>
									<Input
										label="Location"
										defaultValue={location}
										onChange={(event) => setLocation(event.target.value)}
										error={fieldError("location")}
										required
									/>
								</div>
							</div>
							{/* Property Details End */}

							<Separator />

							{/* Deposit Calculation Start */}
							<div className="space-y-4">
								<SectionHeader
									icon={Calculator}
									title="Deposit Calculation"
								/>
								<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
									<Input
										type="number"
										label="Rent Multiple"
										min="0"
										step="0.1"
										defaultValue={rentMultiple}
										onChange={(event) => setRentMultiple(event.target.value)}
										error={fieldError("depositFormula")}
										required
									/>
									<Input
										label="Additional Charges to Deposit"
										defaultValue={additionalCharges}
										onChange={(event) =>
											setAdditionalCharges(props.formatToCommas(event))
										}
									/>
								</div>
								<p className="text-xs text-muted-foreground">
									Deposit = rent &times; {rentMultiple || "…"} + KES{" "}
									{additionalCharges || "0"}
								</p>
							</div>
							{/* Deposit Calculation End */}

							<Separator />

							{/* Service Charges Start */}
							<div className="space-y-4">
								<SectionHeader
									icon={Receipt}
									title="Service Charges"
								/>
								<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
									<Input
										label="Service Charge"
										defaultValue={serviceCharge.service}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												service: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Electricity"
										defaultValue={serviceCharge.electricity}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												electricity: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Garbage"
										defaultValue={serviceCharge.garbage}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												garbage: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Security"
										defaultValue={serviceCharge.security}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												security: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Internet"
										defaultValue={serviceCharge.internet}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												internet: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Cleaning"
										defaultValue={serviceCharge.cleaning}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												cleaning: props.formatToCommas(event),
											}))
										}
									/>
									<Input
										label="Parking"
										defaultValue={serviceCharge.parking}
										onChange={(event) =>
											setServiceCharge((previous) => ({
												...previous,
												parking: props.formatToCommas(event),
											}))
										}
									/>
								</div>
							</div>
							{/* Service Charges End */}

							<Separator />

							{/* Water Bill Rate Start */}
							<div className="space-y-4">
								<SectionHeader
									icon={Droplets}
									title="Water Bill Rate"
								/>
								<div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
									<Input
										type="number"
										label="Council"
										min="0"
										step="0.1"
										defaultValue={waterBillRate.council}
										onChange={(event) =>
											setWaterBillRate((previous) => ({
												...previous,
												council: event.target.value,
											}))
										}
										required
									/>
									<Input
										type="number"
										label="Borehole"
										min="0"
										step="0.1"
										defaultValue={waterBillRate.borehole}
										onChange={(event) =>
											setWaterBillRate((previous) => ({
												...previous,
												borehole: event.target.value,
											}))
										}
										required
									/>
									<Input
										type="number"
										label="Tanker"
										min="0"
										step="0.1"
										defaultValue={waterBillRate.tanker}
										onChange={(event) =>
											setWaterBillRate((previous) => ({
												...previous,
												tanker: event.target.value,
											}))
										}
										required
									/>
								</div>
							</div>
							{/* Water Bill Rate End */}

							<Separator />

							{/* Invoicing Start */}
							<div className="space-y-4">
								<SectionHeader
									icon={FileText}
									title="Invoicing"
								/>
								<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
									<Input
										type="number"
										label="Invoice Date"
										min="1"
										max="30"
										step="1"
										defaultValue={invoiceDate}
										onChange={(event) => setInvoiceDate(event.target.value)}
										error={fieldError("invoiceDate")}
										required
									/>
									<Input
										type="number"
										label="Invoice Date Reminder"
										min="1"
										max="30"
										step="1"
										defaultValue={invoiceReminderDuration}
										onChange={(event) =>
											setInvoiceReminderDuration(event.target.value)
										}
										error={fieldError("invoiceReminderDuration")}
										required
									/>
								</div>

								<Textarea
									label="Default Contract Terms"
									value={contractTerms}
									onChange={(event) => setContractTerms(event.target.value)}
									error={fieldError("contractTerms")}
								/>

								<div className="flex items-center gap-6">
									<div className="flex items-center gap-2">
										<Switch
											checked={email}
											disabled
											aria-label="Email"
										/>
										<span className="text-sm text-muted-foreground">Email</span>
									</div>
									<div className="flex items-center gap-2">
										<Switch
											checked={sms}
											disabled
											aria-label="SMS"
										/>
										<span className="text-sm text-muted-foreground">SMS</span>
									</div>
								</div>
							</div>
							{/* Invoicing End */}

							<div className="flex justify-end">
								<Button
									type="submit"
									disabled={loading}>
									{loading ? "Updating property..." : "Update property"}
									{loading && <Spinner />}
								</Button>
							</div>
						</form>
					)}
				</CardContent>
			</Card>
		</div>
	)
}

PropertiesEditPage.layout = {
	breadcrumbs: [
		{ title: "Dashboard", href: "/admin" },
		{ title: "Properties", href: "/admin/properties" },
		{ title: "Edit Property", href: "" },
	],
}
