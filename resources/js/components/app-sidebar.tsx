import { Link } from '@inertiajs/react';
import {
    Bot,
    Brain,
    LayoutGrid,
    MessageSquare,
    Plug,
    Settings,
    Users,
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as collections } from '@/routes/collections';
import { index as agents } from '@/routes/agents';
import { index as channels } from '@/routes/channels';
import { index as integrations } from '@/routes/integrations';
import { edit as profile } from '@/routes/profile';
import { edit as team } from '@/routes/teams';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const dashboardUrl = dashboard();

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
            title: 'Equipe',
            href: team(),
            icon: Users,
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
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
