import { Link } from '@inertiajs/react';
import {
    Clock3,
    LayoutGrid,
    Library,
    MessageSquare,
    ScanLine,
} from 'lucide-react';
import { index as deckChat } from '@/actions/App/Http/Controllers/DeckChatController';
import PendingScanController from '@/actions/App/Http/Controllers/PendingScanController';
import ScannerController from '@/actions/App/Http/Controllers/ScannerController';
import CollectionController from '@/actions/App/Http/Controllers/CollectionController';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Chat Magic',
        href: deckChat(),
        icon: MessageSquare,
    },
    {
        title: 'Início',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Adicionar cartas',
        href: ScannerController.index(),
        icon: ScanLine,
    },
    {
        title: 'Minha coleção',
        href: CollectionController.index(),
        icon: Library,
    },
    {
        title: 'Scans pendentes',
        href: PendingScanController.index(),
        icon: Clock3,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <div className="text-sidebar-foreground/60 mt-auto flex items-center gap-3 px-4 py-3 text-sm">
                    <Library className="size-4" /> Meus decks{' '}
                    <span className="ml-auto text-xs">Em breve</span>
                </div>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
