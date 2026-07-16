<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-mp-teal dark:border-mp-teal text-sm font-medium leading-5 text-white dark:text-white focus:outline-none focus:border-mp-teal transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-white dark:text-white hover:text-white dark:hover:text-white hover:border-mp-border dark:hover:border-mp-border focus:outline-none focus:text-white dark:focus:text-white focus:border-mp-border dark:focus:border-mp-border transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
