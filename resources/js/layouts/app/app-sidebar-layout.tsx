import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    const { component } = usePage();
    const isInbox = component === 'inbox/index';

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn(
                    'min-w-0',
                    isInbox
                        ? 'h-svh min-h-0 overflow-hidden'
                        : 'overflow-x-clip',
                )}
            >
                <div className="border-border/70 bg-background flex h-12 shrink-0 items-center gap-2 border-b px-3 md:hidden">
                    <SidebarTrigger className="size-10" />
                    <span className="text-sm font-medium">Menu</span>
                </div>
                {isInbox ? (
                    <div className="flex min-h-0 flex-1 flex-col">
                        {children}
                    </div>
                ) : (
                    children
                )}
            </AppContent>
        </AppShell>
    );
}
