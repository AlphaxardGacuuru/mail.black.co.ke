// Routes where the fixed mobile bottom nav gets in the way of a full-screen,
// task-focused view (composing a message, or an open thread's own reply box,
// sits at the exact same screen edge) and should be hidden instead of
// overlapping. Both /mail/[id]/show and /mail/sent/[id] render MailThreadView
// in its "page" variant on mobile — see resources/js/pages/mail. An open
// conversation's composer sits at the same edge.
const HIDDEN_ON = [
	/^\/mail\/compose/,
	/^\/mail\/[^/]+\/show/,
	/^\/mail\/sent\/[^/]+/,
	/^\/chats\/new/,
	/^\/chats\/[^/]+\/show/,
]

export function shouldHideBottomNav(pathname: string): boolean {
	return HIDDEN_ON.some((pattern) => pattern.test(pathname))
}

// An open thread renders its own header (back button, subject, reply/forward/
// star actions — see MailThreadView's "page" variant), so the generic app
// header is redundant there.
const MAIL_THREAD_SHOW_PATTERNS = [
	/^\/mail\/[^/]+\/show/,
	/^\/mail\/sent\/[^/]+$/,
]

export function isMailThreadShowRoute(pathname: string): boolean {
	return MAIL_THREAD_SHOW_PATTERNS.some((pattern) => pattern.test(pathname))
}

// An open conversation renders its own sticky user-info header (avatar,
// name, presence), so the generic app header is redundant there.
const CONVERSATION_SHOW_PATTERN = /^\/chats\/[^/]+\/show/

export function isConversationShowRoute(pathname: string): boolean {
	return CONVERSATION_SHOW_PATTERN.test(pathname)
}
