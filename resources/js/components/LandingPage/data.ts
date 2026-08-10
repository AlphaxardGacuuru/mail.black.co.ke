export type YearSeries = {
	labels: string[]
	data: number[]
}

export type LandingDashboard = {
	units: {
		totalOccupied: number
		totalUnoccupied: number
		percentage: string
		tenantsThisYear: YearSeries
		vacanciesThisYear: YearSeries
	}
	rent: {
		paid: number
		due: number
		total: string
		percentage: string
		paidThisYear: YearSeries
		unpaidThisYear: YearSeries
	}
	water: {
		paid: number
		due: number
		total: string
		percentage: string
		usageTwoMonthsAgo: number | string
		usageLastMonth: number | string
		paidThisYear: YearSeries
		unpaidThisYear: YearSeries
	}
	serviceCharge: {
		paid: number
		due: number
		total: string
		percentage: string
		paidThisYear: YearSeries
		unpaidThisYear: YearSeries
	}
}

export type LandingDashboardProperties = {
	total: number
	ids: number[]
	names: string[]
	units: number[]
}

const monthLabels = [
	"Jan",
	"Feb",
	"Mar",
	"Apr",
	"May",
	"Jun",
	"Jul",
	"Aug",
	"Sep",
	"Oct",
	"Nov",
	"Dec",
]

export const dashboardProperties: LandingDashboardProperties = {
	total: 5,
	ids: [1, 2, 3, 4, 5],
	names: [
		"Kulas Alley",
		"Nathanial Trail",
		"Bechtelar Forge",
		"Kozey Oval",
		"Pouros Center",
	],
	units: [12, 11, 11, 12, 11],
}

export const dashboard: LandingDashboard = {
	units: {
		totalOccupied: 90,
		totalUnoccupied: 10,
		percentage: "90",
		tenantsThisYear: {
			labels: monthLabels,
			data: [0, 0, 0, 14, 53, 94, 110, 110, 55, 55, 0, 0],
		},
		vacanciesThisYear: {
			labels: monthLabels,
			data: [57, 57, 57, 43, 4, 37, 53, 53, 2, 2, 0, 0],
		},
	},
	rent: {
		paid: 57952000,
		due: 6305000,
		total: "64,257,000",
		percentage: "90.2",
		paidThisYear: {
			labels: monthLabels,
			data: [
				2420000, 2420000, 2420000, 2420000, 3055000, 4840000, 7806000,
				10857000, 10857000, 10857000, 0, 0,
			],
		},
		unpaidThisYear: {
			labels: monthLabels,
			data: [
				561000, 561000, 561000, 561000, 601000, 722000, 737000, 667000,
				667000, 667000, 0, 0,
			],
		},
	},
	water: {
		paid: 69682,
		due: 7044,
		total: "76,727",
		usageTwoMonthsAgo: 5014,
		usageLastMonth: 6120,
		percentage: "90.8",
		paidThisYear: {
			labels: monthLabels,
			data: [7554, 6632, 5747, 5007, 5458, 8094, 9800, 11764, 6697, 2924, 0, 0],
		},
		unpaidThisYear: {
			labels: monthLabels,
			data: [1279, 1167, 943, 865, 808, 628, 519, 450, 265, 114, 0, 0],
		},
	},
	serviceCharge: {
		paid: 9987869,
		due: 929500,
		total: "10,917,369",
		percentage: "91.5",
		paidThisYear: {
			labels: monthLabels,
			data: [
				400070, 400070, 400070, 400070, 529214, 881447, 1374467, 1867487,
				1867487, 1867487, 0, 0,
			],
		},
		unpaidThisYear: {
			labels: monthLabels,
			data: [
				92950, 92950, 92950, 92950, 92950, 92950, 92950, 92950, 92950, 92950,
				0, 0,
			],
		},
	},
}