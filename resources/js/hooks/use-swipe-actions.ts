import { useEffect, useRef, useState } from "react"
import type { MouseEvent as ReactMouseEvent, PointerEvent as ReactPointerEvent } from "react"

const SWIPE_TRIGGER_PX = 64
const SWIPE_MAX_PX = 88
const AXIS_LOCK_THRESHOLD_PX = 8
const LONG_PRESS_MS = 500

type UseSwipeActionsOptions = {
	onSwipeRight?: () => void
	onSwipeLeft?: () => void
	onLongPress?: () => void
	disabled?: boolean
}

export function useSwipeActions({
	onSwipeRight,
	onSwipeLeft,
	onLongPress,
	disabled = false,
}: UseSwipeActionsOptions) {
	const [offsetX, setOffsetX] = useState(0)
	const startRef = useRef<{ x: number; y: number } | null>(null)
	const axisRef = useRef<"horizontal" | "vertical" | null>(null)
	const pointerIdRef = useRef<number | null>(null)
	const longPressTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
	const suppressClickRef = useRef(false)

	function clearLongPressTimer() {
		if (longPressTimerRef.current) {
			clearTimeout(longPressTimerRef.current)
			longPressTimerRef.current = null
		}
	}

	useEffect(() => clearLongPressTimer, [])

	function reset() {
		setOffsetX(0)
		startRef.current = null
		axisRef.current = null
		pointerIdRef.current = null
		clearLongPressTimer()
	}

	function onPointerDown(event: ReactPointerEvent<HTMLElement>) {
		if (disabled || (!onSwipeRight && !onSwipeLeft && !onLongPress)) {
			return
		}

		startRef.current = { x: event.clientX, y: event.clientY }
		axisRef.current = null
		pointerIdRef.current = event.pointerId
		event.currentTarget.setPointerCapture(event.pointerId)

		if (onLongPress) {
			longPressTimerRef.current = setTimeout(() => {
				longPressTimerRef.current = null
				reset()
				onLongPress()
			}, LONG_PRESS_MS)
		}
	}

	function onPointerMove(event: ReactPointerEvent<HTMLElement>) {
		if (!startRef.current || pointerIdRef.current !== event.pointerId) {
			return
		}

		const deltaX = event.clientX - startRef.current.x
		const deltaY = event.clientY - startRef.current.y

		if (!axisRef.current) {
			if (
				Math.abs(deltaX) < AXIS_LOCK_THRESHOLD_PX &&
				Math.abs(deltaY) < AXIS_LOCK_THRESHOLD_PX
			) {
				return
			}
			axisRef.current = Math.abs(deltaX) > Math.abs(deltaY) ? "horizontal" : "vertical"
			clearLongPressTimer()
		}

		if (axisRef.current === "vertical") {
			return
		}

		let next = deltaX
		if (next > 0 && !onSwipeRight) {
			next = 0
		}
		if (next < 0 && !onSwipeLeft) {
			next = 0
		}

		setOffsetX(Math.max(-SWIPE_MAX_PX, Math.min(SWIPE_MAX_PX, next)))
	}

	function onPointerUp(event: ReactPointerEvent<HTMLElement>) {
		if (pointerIdRef.current !== event.pointerId) {
			return
		}

		const wasHorizontal = axisRef.current === "horizontal"
		const finalOffset = offsetX

		reset()

		if (!wasHorizontal) {
			return
		}

		// A drag that moved horizontally at all (even one that snapped back
		// without crossing the trigger threshold) shouldn't also register as
		// a tap on release — callers that bind onClick to the same element
		// (e.g. a row that's both swipeable and selectable) can check this
		// via consumeSwipeSuppression().
		suppressClickRef.current = true

		if (finalOffset >= SWIPE_TRIGGER_PX) {
			onSwipeRight?.()
		} else if (finalOffset <= -SWIPE_TRIGGER_PX) {
			onSwipeLeft?.()
		}
	}

	function consumeSwipeSuppression(): boolean {
		const value = suppressClickRef.current
		suppressClickRef.current = false
		return value
	}

	return {
		offsetX,
		progress: Math.max(-1, Math.min(1, offsetX / SWIPE_TRIGGER_PX)),
		consumeSwipeSuppression,
		bind: {
			onPointerDown,
			onPointerMove,
			onPointerUp,
			onPointerCancel: onPointerUp,
			onContextMenu: (event: ReactMouseEvent) => {
				if (onLongPress) {
					event.preventDefault()
				}
			},
		},
	}
}
