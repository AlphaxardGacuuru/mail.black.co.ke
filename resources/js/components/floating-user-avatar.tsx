import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useApp } from '@/contexts/AppContext';

export function FloatingUserAvatar() {
    const getInitials = useInitials();
    const { auth } = useApp();

    if (!auth) {
        return null;
    }

    return (
        <div className="fixed right-4 bottom-20 z-40 md:bottom-4">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="inline-flex h-12 w-12 items-center justify-center rounded-full border border-border/50 bg-background/90 p-1 shadow-lg backdrop-blur-sm transition-transform duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Open user menu"
                    >
                        <Avatar className="h-10 w-10 overflow-hidden rounded-full">
                            <AvatarImage
                                src={auth.avatar}
                                alt={auth.name}
                            />
                            <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                {getInitials(auth.name)}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56" align="end" sideOffset={8}>
                    <UserMenuContent user={auth} />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
