<script setup>
import { ref, onMounted, computed } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const showingNavigationDropdown = ref(false)

// ─────────────────────────────────────────────────────
// TASKS BADGE — count of overdue + due-today tasks
// ─────────────────────────────────────────────────────
const taskBadge = ref(0)
const contractAlertCount = computed(() => page.props.contractAlerts?.count ?? 0)

// ─────────────────────────────────────────────────────
// DARK MODE TOGGLE
// Reads saved preference from localStorage on load
// Saves preference whenever user toggles
// Applies/removes 'dark' class on <html> tag
// ─────────────────────────────────────────────────────
const isDark = ref(true) // default to dark since we built everything dark
const appName = import.meta.env.VITE_APP_NAME || 'CFOs Tools'

const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles
    if (!roles) return false
    return Object.values(roles).includes('super-admin')
})

const isAdminOrSuper = computed(() => {
    const roles = page.props.auth?.user?.roles
    if (!roles) return false
    return Object.values(roles).some(r => ['admin', 'super-admin'].includes(r))
})

onMounted(() => {
    // Fetch tasks badge count (overdue + due today)
    fetch('/tasks/badge-count', { credentials: 'include' })
        .then(r => r.json())
        .then(d => { taskBadge.value = d.count || 0 })
        .catch(() => {}) // silent fail — badge stays 0

    // Read saved preference — if none saved, default to dark
    const saved = localStorage.getItem('theme')
    if (saved === 'light') {
        isDark.value = false
        document.documentElement.classList.remove('dark')
    } else {
        // Default to dark mode
        isDark.value = true
        document.documentElement.classList.add('dark')
    }
})

function toggleDarkMode() {
    isDark.value = !isDark.value
    if (isDark.value) {
        document.documentElement.classList.add('dark')
        localStorage.setItem('theme', 'dark')
    } else {
        document.documentElement.classList.remove('dark')
        localStorage.setItem('theme', 'light')
    }
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 dark:bg-gray-950 transition-colors duration-200">
            <nav class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 transition-colors duration-200">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-[5rem] justify-between">

                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <div class="flex items-center gap-2">
                                        <!-- <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                        </div> -->
                                        <img src="/images/logo2.png" :alt="appName" class="h-[4.25rem] w-auto" />
                                        <span class="text-mp-teal font-bold text-sm hidden sm:block">{{ appName }}</span>
                                    </div>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                    class="text-white dark:text-white hover:text-white dark:hover:text-white"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    href="/portfolio-companies"
                                    :active="route().current('portfolio-companies.*')"
                                    class="text-white dark:text-white hover:text-white dark:hover:text-white"
                                >
                                    Customers
                                </NavLink>

                         <NavLink
                            v-if="isSuperAdmin"
                            href="/organizations"
                            :active="$page.url.startsWith('/organizations')"
                            class="text-white dark:text-white hover:text-white dark:hover:text-white">
                            Organizations
                        </NavLink>

                         <NavLink
                            href="/users"
                            :active="$page.url.startsWith('/users')"
                            class="text-white dark:text-white hover:text-white dark:hover:text-white"
                            v-if="isAdminOrSuper">
                            Users
                        </NavLink>


                            </div>
                        </div>

                        <!-- Right side -->
                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">

                            <!-- ── DARK MODE TOGGLE BUTTON ── -->
                            <button
                                @click="toggleDarkMode"
                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200"
                                :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                            >
                                <!-- Sun icon — shown in dark mode (click to go light) -->
                                <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                                </svg>
                                <!-- Moon icon — shown in light mode (click to go dark) -->
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>


                            <Link
                                v-if="isAdminOrSuper && contractAlertCount > 0"
                                href="/dashboard"
                                class="relative flex items-center gap-1.5 text-m font-bold text-mp-danger hover:text-white transition-colors px-3 py-1.5 rounded-lg hover:bg-gray-800"
                                :title="`${contractAlertCount} expired contract(s)`">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span class="hidden md:inline text-xs">Contracts</span>
                                <span
                                    class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold min-w-[1rem] h-4 flex items-center justify-center rounded-full px-0.5">
                                    {{ contractAlertCount > 9 ? '9+' : contractAlertCount }}
                                </span>
                            </Link>

                            <Link
                                href="/tasks"
                                class="relative flex items-center gap-1.5 text-m font-bold text-green-600 hover:text-white transition-colors px-3 py-1.5 rounded-lg hover:bg-gray-800 mr-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                My Tasks
                                <!-- Red badge for overdue/due-today count — shown when > 0 -->
                                <span
                                    v-if="taskBadge > 0"
                                    class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-4 h-4 flex items-center justify-center rounded-full"
                                >
                                    {{ taskBadge > 9 ? '9+' : taskBadge }}
                                </span>
                            </Link>

                            <!-- User Dropdown -->
                            <div class="relative ms-1">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-all duration-200"
                                            >
                                                <!-- User avatar initial -->
                                                <span class="w-5 h-5 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold">
                                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                                </span>
                                                {{ $page.props.auth.user.name }}
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger (mobile) -->
                        <div class="-me-2 flex items-center gap-2 sm:hidden">

                            <!-- Mobile dark mode toggle -->
                            <button
                                @click="toggleDarkMode"
                                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400"
                            >
                                <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>

                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Responsive Navigation Menu (mobile) -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/portfolio-companies" :active="route().current('portfolio-companies.*')">
                            Customers
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isSuperAdmin"
                            href="/organizations"
                            :active="route().current('organizations.*')">
                            Organizations
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="isAdminOrSuper"
                            href="/users"
                            :active="route().current('users.*')">
                            Users
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800 dark:text-gray-200">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading (if used) -->
            <header class="bg-white dark:bg-gray-900 shadow dark:shadow-gray-800/50" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>