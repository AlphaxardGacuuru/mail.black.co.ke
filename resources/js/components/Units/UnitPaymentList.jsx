import React, { useEffect, useState } from "react"

import PaymentList from "@/components/Payments/PaymentList"

const UnitPaymentList = (props) => {
	const [payments, setPayments] = useState([])

	const [tenant, setTenant] = useState("")
	const [unit, setUnit] = useState("")
	const [startMonth, setStartMonth] = useState("")
	const [startYear, setStartYear] = useState("")
	const [endMonth, setEndMonth] = useState("")
	const [endYear, setEndYear] = useState("")

	useEffect(() => {
		// Fetch Payments
		props.getPaginated(
			`payments?
			propertyId=${props.unit?.propertyId}&
			unitId=${props.unit?.id}&
			tenant=${tenant}&
			tenantId=${props.tenant?.id}&
			unit=${unit}&
			startMonth=${startMonth}&
			endMonth=${endMonth}&
			startYear=${startYear}&
			endYear=${endYear}`,
			setPayments
		)
	}, [
		props.selectedPropertyId,
		props.tenant,
		tenant,
		unit,
		startMonth,
		endMonth,
		startYear,
		endYear,
	])

	return (
		<div className="row">
			<div className="col-sm-12">
				{/* Payments Tab */}
				<PaymentList
					{...props}
					unit={props.unit}
					payments={payments}
					setPayments={setPayments}
					setUnit={setUnit}
					setTenant={setTenant}
					setStartMonth={setStartMonth}
					setEndMonth={setEndMonth}
					setStartYear={setStartYear}
					setEndYear={setEndYear}
				/>
				{/* Payments Tab End */}
			</div>
		</div>
	)
}

export default UnitPaymentList
