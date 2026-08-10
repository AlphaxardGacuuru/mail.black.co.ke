import * as React from "react"
import { Link as RouterLink } from "@tanstack/react-router"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"
import { buildClientRoutePathSet } from "@/router/page-routes"

const linkVariants = cva(
	"inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[color,box-shadow,background-color,border-color] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] no-underline capitalize",
	{
		variants: {
			variant: {
				default:
					"bg-light/10 text-light hover:bg-light/15",
				solid:
					"bg-primary text-primary-foreground shadow-xs hover:bg-primary/90",
				outline:
					"border border-primary text-primary shadow-xs hover:bg-accent hover:bg-primary hover:text-white",
				ghost: "text-foreground hover:bg-accent hover:text-accent-foreground",
				muted:
					"text-muted-foreground hover:text-foreground hover:underline underline-offset-4",
				text: "text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500",
				unstyled: "",
			},
			size: {
				default: "h-9 px-4 py-2 has-[>svg]:px-3",
				sm: "h-8 rounded-md px-3 has-[>svg]:px-2.5",
				lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
				icon: "size-9",
				none: "",
			},
		},
		defaultVariants: {
			variant: "default",
			size: "default",
		},
	}
)

type LinkProps = Omit<React.ComponentPropsWithoutRef<"a">, "href"> &
	VariantProps<typeof linkVariants> & {
		href: string
		linkStyle?: React.CSSProperties
		icon?: React.ReactNode
		iconFront?: React.ReactNode
		text?: string
	}

const clientRoutePaths = buildClientRoutePathSet(
	Object.keys(import.meta.glob("../../pages/**/*.tsx", { eager: false })),
	"../../pages/"
)

const isExternalHref = (href: string): boolean => {
	return (
		/^(?:[a-z][a-z\d+\-.]*:|\/\/)/i.test(href) ||
		href.startsWith("#")
	)
}

const pathnameFromHref = (href: string): string => {
	try {
		return new URL(href, window.location.origin).pathname
	} catch {
		return href.split("?")[0]?.split("#")[0] ?? href
	}
}

const Link = ({
	href,
	linkStyle,
	className,
	icon,
	iconFront,
	text,
	variant = "default",
	size = "default",
	children,
	...props
}: LinkProps) => {
	const routePathname = pathnameFromHref(href)
	const canUseClientNavigation =
		!isExternalHref(href) &&
		props.target !== "_blank" &&
		clientRoutePaths.has(routePathname)

	const content = (
		<>
			{icon && <span className="text-inherit">{icon}</span>}

			{text ? (
				<span className="mx-0.5 text-inherit text-nowrap">{text}</span>
			) : (
				children
			)}

			{iconFront && <span className="text-inherit">{iconFront}</span>}
		</>
	)

	const linkClassName =
		variant === "unstyled"
			? className
			: cn(linkVariants({ variant, size, className }))

	if (canUseClientNavigation) {
		return (
			<RouterLink
				to={routePathname as never}
				style={linkStyle}
				className={linkClassName}
				{...props}>
				{content}
			</RouterLink>
		)
	}

	return (
		<a
			href={href}
			style={linkStyle}
			className={linkClassName}
			{...props}>
			{content}
		</a>
	)
}

export { Link, linkVariants }
export default Link
