<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/UI/BaseButton.vue';

defineProps<{
    demoAdminEmail: string;
    demoUserEmail: string;
}>();

const adminForm = useForm({ role: 'admin' });
const userForm = useForm({ role: 'user' });

function signInAsAdmin(): void {
    adminForm.post('/login/demo');
}

function signInAsUser(): void {
    userForm.post('/login/demo');
}
</script>

<template>
    <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-white">Quick Demo Access</p>
        <p class="mt-1 text-xs text-neutral-400">
            Test operator reporting as <span class="font-mono text-neutral-200">{{ demoAdminEmail }}</span> or standard user access as
            <span class="font-mono text-neutral-200">{{ demoUserEmail }}</span>.
        </p>

        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <BaseButton
                type="button"
                class="w-full"
                :loading="adminForm.processing"
                :disabled="userForm.processing"
                @click="signInAsAdmin"
            >
                Demo Admin
            </BaseButton>
            <BaseButton
                type="button"
                variant="secondary"
                class="w-full"
                :loading="userForm.processing"
                :disabled="adminForm.processing"
                @click="signInAsUser"
            >
                Demo User
            </BaseButton>
        </div>
    </div>
</template>
