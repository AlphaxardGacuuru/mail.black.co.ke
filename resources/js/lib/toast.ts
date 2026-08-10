import { toast as sonnerToast, type ExternalToast } from "sonner"
import type { CSSProperties } from "react"

const DEFAULT_TOAST_DURATION = 4000
const PROGRESS_ENABLED_VAR = "--toast-progress-enabled"
const PROGRESS_DURATION_VAR = "--toast-progress-duration"

type ToastOptions = ExternalToast

type ToastFunction = typeof sonnerToast

function hasFiniteDuration(duration: unknown): duration is number {
	return typeof duration === "number" && Number.isFinite(duration)
}

function withProgressStyle(options?: ToastOptions): ToastOptions | undefined {
	const duration = options?.duration ?? DEFAULT_TOAST_DURATION

	if (!hasFiniteDuration(duration)) {
		return options
	}

	return {
		...options,
		style: {
			...(options?.style ?? {}),
			[PROGRESS_ENABLED_VAR]: 1,
			[PROGRESS_DURATION_VAR]: `${duration}ms`,
		} as CSSProperties & Record<string, string | number>,
	}
}

const toast = new Proxy(sonnerToast, {
	apply(target, thisArg, argArray: Parameters<ToastFunction>) {
		const [message, options] = argArray

		return Reflect.apply(target, thisArg, [message, withProgressStyle(options)])
	},
	get(target, property, receiver) {
		if (property === "promise") {
			return Reflect.get(target, property, receiver)
		}

		if (
			property === "success" ||
			property === "info" ||
			property === "warning" ||
			property === "error" ||
			property === "message" ||
			property === "custom"
		) {
			return (...args: unknown[]) => {
				const [content, options] = args as [unknown, ToastOptions | undefined]
				const method = Reflect.get(target, property, receiver) as (
					...methodArgs: unknown[]
				) => unknown

				return method.call(target, content, withProgressStyle(options))
			}
		}

		return Reflect.get(target, property, receiver)
	},
}) as ToastFunction

export { toast }
export default toast
