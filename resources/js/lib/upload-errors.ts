import { isAxiosError } from "axios"

/** FilePond's own `error()` callback only shows whatever string we pass it,
 *  so a request that fails before reaching our .catch() (a dropped
 *  connection, a proxy rejecting an oversized body before Laravel ever
 *  sees it) needs to be told apart from a real 422 validation message,
 *  otherwise every failure looks identical and undiagnosable. */
export function filePondErrorMessage(error: unknown, fallback: string): string {
	if (
		!isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(
			error
		)
	) {
		return error instanceof Error ? error.message : fallback
	}

	if (!error.response) {
		return "Couldn't reach the server. Check your connection and try again."
	}

	if (error.response.status === 413) {
		return "That file is too large to upload."
	}

	const errors = error.response.data.errors

	return (
		(errors ? Object.values(errors)[0]?.[0] : undefined) ??
		error.response.data.message ??
		`${fallback} (error ${error.response.status})`
	)
}
