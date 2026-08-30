let audioContext: AudioContext | null = null

function getAudioContext(): AudioContext | null {
	if (typeof window === "undefined") {
		return null
	}

	const AudioContextClass =
		window.AudioContext ||
		(window as typeof window & { webkitAudioContext?: typeof AudioContext })
			.webkitAudioContext

	if (!AudioContextClass) {
		return null
	}

	if (!audioContext) {
		audioContext = new AudioContextClass()
	}

	return audioContext
}

/**
 * Plays a short two-tone chime to signal an incoming realtime mail event.
 */
export function playIncomingMailChime(): void {
	try {
		const context = getAudioContext()
		if (!context) {
			return
		}

		if (context.state === "suspended") {
			void context.resume()
		}

		const now = context.currentTime
		const notes: Array<{ frequency: number; start: number; duration: number }> =
			[
				{ frequency: 880, start: 0, duration: 0.12 },
				{ frequency: 1318.5, start: 0.1, duration: 0.18 },
			]

		notes.forEach(({ frequency, start, duration }) => {
			const oscillator = context.createOscillator()
			const gain = context.createGain()

			oscillator.type = "sine"
			oscillator.frequency.setValueAtTime(frequency, now + start)

			gain.gain.setValueAtTime(0, now + start)
			gain.gain.linearRampToValueAtTime(0.15, now + start + 0.02)
			gain.gain.exponentialRampToValueAtTime(0.0001, now + start + duration)

			oscillator.connect(gain)
			gain.connect(context.destination)

			oscillator.start(now + start)
			oscillator.stop(now + start + duration + 0.02)
		})
	} catch {
		// Autoplay restrictions or unsupported browsers should never break the UI.
	}
}
