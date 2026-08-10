import React, { useEffect, useState } from "react"

import InvoiceList from "@/components/Invoices/InvoiceList"

const UnitInvoiceList = ({ userUnitId = "", ...props }) => {
	const [invoices, setInvoices] = useState([])

	const [number, setNumber] = useState("")
	const [invoice, setInvoice] = useState("")
	const [unit, setUnit] = useState("")
	const [tenant, setTenant] = useState("")
	const [type, setType] = useState("")
	const [status, setStatus] = useState("")
	const [startMonth, setStartMonth] = useState("")
	const [startYear, setStartYear] = useState("")
	const [endMonth, setEndMonth] = useState("")
	const [endYear, setEndYear] = useState("")

	useEffect(() => {
		// Fetch Invoices
		props.getPaginated(
			`invoices?
			propertyId=${props.unit?.propertyId}&
			unitId=${props.unit?.id}&
			number=${number}&
			invoice=${invoice}&
			unit=${unit}&
			tenant=${tenant}&
			tenantId=${props.tenant?.id}&
			type=${type}&
			status=${status}&
			startMonth=${startMonth}&
			endMonth=${endMonth}&
			startYear=${startYear}&
			endYear=${endYear}`,
			setInvoices
		)
	}, [
		props.selectedPropertyId,
		props.tenant,
		number,
		invoice,
		unit,
		tenant,
		type,
		status,
		startMonth,
		endMonth,
		startYear,
		endYear,
	])

	return (
		<div className="row">
			<div className="col-sm-12">
				{/* Invoices Tab */}
				<InvoiceList
					{...props}
					invoices={invoices}
					setInvoices={setInvoices}
					setNumber={setNumber}
					setInvoice={setNumber}
					setUnit={setUnit}
					setTenant={setTenant}
					setType={setType}
					setStatus={setStatus}
					setStartMonth={setStartMonth}
					setEndMonth={setEndMonth}
					setStartYear={setStartYear}
					setEndYear={setEndYear}
				/>
				{/* Invoices Tab End */}
			</div>
		</div>
	)
}

export default UnitInvoiceList
