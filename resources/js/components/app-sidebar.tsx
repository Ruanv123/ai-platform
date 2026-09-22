import { Link, usePage } from '@inertiajs/react';
import {
    Bot,
    Brain,
    LayoutGrid,
    MessageSquare,
    Plug,
    Settings,
    Workflow,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as collections } from '@/routes/collections';
import { index as agents } from '@/routes/agents';
import { index as channels } from '@/routes/channels';
import { index as integrations } from '@/routes/integrations';
import { index as flows } from '@/routes/flows';
import { edit as profile } from '@/routes/profile';
import { index as inbox } from '@/routes/inbox';
import { useInboxRealtime } from '@/hooks/use-inbox-realtime';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const dashboardUrl = dashboard();
    const { isMobile, state } = useSidebar();
    const isCollapsed = !isMobile && state === 'collapsed';
    const { props } = usePage<{
        currentTeam?: { id: number } | null;
        pendingHumanCount?: number;
    }>();
    const teamId = props.currentTeam?.id ?? null;
    useInboxRealtime(teamId, ['pendingHumanCount']);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Agentes',
            href: agents(),
            icon: Bot,
        },
        {
            title: 'Atendimento',
            href: inbox(),
            icon: MessageSquare,
            badge: props.pendingHumanCount,
        },
        {
            title: 'Fluxos',
            href: flows(),
            icon: Workflow,
        },
        {
            title: 'Base de conhecimento',
            href: collections(),
            icon: Brain,
        },
        {
            title: 'Canais',
            href: channels(),
            icon: MessageSquare,
        },
        {
            title: 'Integrações',
            href: integrations(),
            icon: Plug,
        },
        {
            title: 'Configurações',
            href: profile(),
            icon: Settings,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1 group-data-[collapsible=icon]:gap-0">
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="ease-ui-move min-w-0 flex-1 transition-[width,height,padding,opacity] duration-250 group-data-[collapsible=icon]:pointer-events-none group-data-[collapsible=icon]:w-0! group-data-[collapsible=icon]:opacity-0 motion-reduce:transition-none"
                        >
                            <Link
                                href={dashboardUrl}
                                prefetch
                                aria-hidden={isCollapsed}
                                tabIndex={isCollapsed ? -1 : undefined}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <SidebarTrigger className="size-8 shrink-0" />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}
