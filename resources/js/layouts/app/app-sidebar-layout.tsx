import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    const { component } = usePage();
    const isFlowEditor = component === 'flows/show';

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={`min-w-0 overflow-x-clip ${isFlowEditor ? 'h-screen' : ''}`}
            >
                {children}
            </AppContent>
        </AppShell>
    );
}
