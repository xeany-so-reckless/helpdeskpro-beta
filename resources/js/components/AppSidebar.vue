<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartColumn,
    CircleCheck,
    CirclePlus,
    ClipboardList,
    Inbox,
    LayoutGrid,
    Tags,
    Ticket,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as reportsIndex } from '@/routes/reports';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import { index as categoriesIndex } from '@/routes/categories';
import { create as ticketsCreate, index as ticketsIndex } from '@/routes/tickets';
import type { NavItem } from '@/types';

const page = usePage();

// Peran dibaca dari data user yang dibagikan Inertia. Ini hanya untuk
// menentukan menu yang tampil; keamanan tetap dijaga policy di backend.
const role = computed(
    () => (page.props.auth.user as unknown as { role?: string }).role,
);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];

    if (role.value === 'admin') {
        items.push(
            { title: 'All Tickets', href: ticketsIndex(), icon: Ticket },
            { title: 'Categories', href: categoriesIndex(), icon: Tags },
            { title: 'Reports', href: reportsIndex(), icon: ChartColumn },
        );
    } else if (role.value === 'it_support') {
        items.push(
            {
                title: 'Assigned Tickets',
                href: ticketsIndex({ query: { scope: 'assigned' } }),
                icon: ClipboardList,
            },
            {
                title: 'Ticket Queue',
                href: ticketsIndex({ query: { scope: 'queue' } }),
                icon: Inbox,
            },
            {
                title: 'Resolved Tickets',
                href: ticketsIndex({ query: { scope: 'resolved' } }),
                icon: CircleCheck,
            },
        );
    } else {
        items.push(
            { title: 'My Tickets', href: ticketsIndex(), icon: Ticket },
            { title: 'Create Ticket', href: ticketsCreate(), icon: CirclePlus },
        );
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
