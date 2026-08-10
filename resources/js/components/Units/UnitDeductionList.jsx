import React, { useEffect, useState } from "react"

import DeductionList from "@/components/Deductions/DeductionList"

const UnitDeductionList = ({ userUnitId = "", ...props }) => {
	const [deductions, setDeductions] = useState([])

	const [tenant, setTenant] = useState("")
	const [unit, setUnit] = useState("")
	const [invoiceCode, setInvoiceCode] = useState("")
	const [startMonth, setStartMonth] = useState("")
	const [startYear, setStartYear] = useState("")
	const [endMonth, setEndMonth] = useState("")
	const [endYear, setEndYear] = useState("")

	useEffect(() => {
		// Fetch Deduction
		props.getPaginated(
			`deductions?
			propertyId=${props.unit?.propertyId}&
			unitId=${props.unit?.id}&
			tenant=${tenant}&
			tenantId=${props.tenant?.id}&
			unit=${unit}&
			invoiceCode=${invoiceCode}&
			startMonth=${startMonth}&
			endMonth=${endMonth}&
			startYear=${startYear}&
			endYear=${endYear}`,
			setDeductions
		)
	}, [
		props.selectedPropertyId,
		props.tenant,
		tenant,
		unit,
		invoiceCode,
		startMonth,
		endMonth,
		startYear,
		endYear,
	])

	return (
		<div className="row">
			<div className="col-sm-12">
				{/* Deductions Tab */}
				<DeductionList
					{...props}
					unit={props.unit}
					deductions={deductions}
					setDeductions={setDeductions}
					setInvoiceCode={setInvoiceCode}
					setUnit={setUnit}
					setTenant={setTenant}
					setStartMonth={setStartMonth}
					setEndMonth={setEndMonth}
					setStartYear={setStartYear}
					setEndYear={setEndYear}
				/>
				{/* Deductions Tab End */}
			</div>
		</div>
	)
}

export default UnitDeductionList
