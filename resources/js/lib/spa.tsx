import type React from "react"
import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useState,
} from "react"

type Page = {
	component: string
	props: Record<string, unknown>
	url: string
}

type PageContextValue = {
	page: Page
	layoutProps: Record<string, unknown>
	setLayoutProps: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
}

const PageContext = createContext<PageContextValue | null>(null)
let setLayoutPropsRef: React.Dispatch<
	React.SetStateAction<Record<string, unknown>>
> | null = null

export function AppPageProvider({
	page,
	children,
}: {
	page: Page
	children: React.ReactNode
}) {
	const [layoutProps, setLayoutPropsState] = useState<Record<string, unknown>>(
		{}
	)

	useEffect(() => {
		setLayoutPropsRef = setLayoutPropsState

		return () => {
			setLayoutPropsRef = null
		}
	}, [])

	useEffect(() => {
		setLayoutPropsState({})
	}, [page.component])

	const value = useMemo<PageContextValue>(
		() => ({
			page,
			layoutProps,
			setLayoutProps: setLayoutPropsState,
		}),
		[layoutProps, page]
	)

	return <PageContext.Provider value={value}>{children}</PageContext.Provider>
}

export function usePage<
	TPageProps extends Record<string, unknown> = Record<string, any>,
>() {
	const context = useContext(PageContext)

	if (!context) {
		throw new Error("usePage must be used within AppPageProvider")
	}

	return {
		component: context.page.component,
		props: context.page.props as TPageProps,
		url: context.page.url,
	}
}

export function useLayoutProps(): Record<string, unknown> {
	const context = useContext(PageContext)

	if (!context) {
		return {}
	}

	return context.layoutProps
}

export function setLayoutProps(props: Record<string, unknown>): void {
	if (!setLayoutPropsRef) {
		return
	}

	setLayoutPropsRef((current) => ({ ...current, ...props }))
}

export function Head({ title }: { title: string }) {
	useEffect(() => {
		document.title = title
	}, [title])

	return null
}
