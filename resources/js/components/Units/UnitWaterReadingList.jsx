import React, { useEffect, useState } from "react"

import WaterReadingList from "@/components/Water/WaterReadingList"

const UnitWaterReadingList = ({ userUnitId = "", ...props }) => {
	const [waterReadings, setWaterReadings] = useState([])

	const [tenant, setTenant] = useState("")
	const [unit, setUnit] = useState("")
	const [startMonth, setStartMonth] = useState("")
	const [startYear, setStartYear] = useState("")
	const [endMonth, setEndMonth] = useState("")
	const [endYear, setEndYear] = useState("")

	useEffect(() => {
		// Fetch Water Readings
		props.getPaginated(
			`water-readings?
			propertyId=${props.unit?.propertyId}&
			unitId=${props.unit?.id}&
			tenant=${tenant}&
			tenantId=${props.tenant?.id}&
			unit=${unit}&
			startMonth=${startMonth}&
			endMonth=${endMonth}&
			startYear=${startYear}&
			endYear=${endYear}`,
			setWaterReadings
		)
	}, [props.selectedPropertyId, props.tenant, tenant, unit, startMonth, endMonth, startYear, endYear])

	return (
		<WaterReadingList
			{...props}
			userUnitId={userUnitId}
			waterReadings={waterReadings}
			setWaterReadings={setWaterReadings}
			setTenant={setTenant}
			setUnit={setUnit}
			setStartMonth={setStartMonth}
			setEndMonth={setEndMonth}
			setStartYear={setStartYear}
			setEndYear={setEndYear}
		/>
	)
}

export default UnitWaterReadingList