import Axios from "axios"

const AxiosClient = Axios.create({
	baseURL: "/",
	headers: {
		"X-Requested-With": "XMLHttpRequest",
		"Cache-Control": "no-cache, no-store, must-revalidate",
		Pragma: "no-cache",
		Expires: "0",
	},
	withCredentials: true,
	withXSRFToken: true,
})

AxiosClient.interceptors.request.use((config) => {
	const raw = localStorage.getItem("sanctumToken")

	if (raw) {
		try {
			const token = JSON.parse(raw) as string
			if (token) {
				config.headers.Authorization = `Bearer ${token}`
			}
		} catch {
			// malformed storage value — skip
		}
	}

	return config
})

export default AxiosClient
