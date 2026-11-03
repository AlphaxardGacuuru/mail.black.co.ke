import { Navigate } from "@tanstack/react-router"
import {
	isJobApplicationTab,
	JOB_APPLICATIONS_URL,
} from "@/lib/job-application-tabs"
import JobApplications from "@/pages/job-applications/index"

export default function JobApplicationsTab({ tab }: { tab: string }) {
	if (!isJobApplicationTab(tab)) {
		return (
			<Navigate
				to={JOB_APPLICATIONS_URL as never}
				replace
			/>
		)
	}

	// Keyed so switching tabs starts again on the first page.
	return (
		<JobApplications
			key={tab}
			tab={tab}
		/>
	)
}

JobApplicationsTab.layout = JobApplications.layout
