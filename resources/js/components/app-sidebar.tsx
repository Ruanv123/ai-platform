import { Link } from '@inertiajs/react';
import {
    Bot,
    BookOpen,
    Brain,
    FolderGit2,
    LayoutGrid,
    MessageSquare,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import type { NavItem } from '@/types';

export function AppSidebar() {
    const dashboardUrl = dashboard();

    const mainNavItems: NavItem[] = [
        {
            title: 'Painel',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Base de conhecimento',
            href: collections(),
            icon: Brain,
        },
        {
            title: 'Agentes',
            href: agents(),
            icon: Bot,
        },
        {
            title: 'Canais',
            href: channels(),
            icon: MessageSquare,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repositório',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentação',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
