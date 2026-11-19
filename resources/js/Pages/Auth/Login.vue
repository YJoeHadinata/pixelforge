<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/UI/BaseButton.vue';
import DemoReviewerLoginBox from '@/Pages/Auth/Components/DemoReviewerLoginBox.vue';

defineProps<{
    demoAdminEmail: string;
    demoUserEmail: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit(): void {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <main class="flex min-h-screen items-center justify-center bg-[#0a0a0a] px-4 py-12 text-neutral-100">
        <div class="w-full max-w-md space-y-6 rounded-2xl border border-neutral-800 bg-[#121212] p-8 shadow-2xl">
            <div class="text-center">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white font-mono text-sm font-extrabold text-neutral-950">
                    PF
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-white">Pixelforge Studio</h1>
                <p class="mt-1 text-sm text-neutral-400">Sign in to generate images and track live token billing.</p>
            </div>

            <DemoReviewerLoginBox
                :demo-admin-email="demoAdminEmail"
                :demo-user-email="demoUserEmail"
            />

            <div class="relative flex items-center py-1">
                <div class="grow border-t border-neutral-800" />
                <span class="mx-3 shrink text-xs uppercase tracking-wider text-neutral-500">or with email</span>
                <div class="grow border-t border-neutral-800" />
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="login-email" class="block text-sm font-medium text-neutral-200">Email</label>
                    <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="login-password" class="block text-sm font-medium text-neutral-200">Password</label>
                    <input
                        id="login-password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-300">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-neutral-700 bg-[#0a0a0a] accent-white focus:ring-neutral-500"
                    />
                    Keep me signed in
                </label>

                <BaseButton type="submit" variant="secondary" class="w-full" :loading="form.processing">
                    Sign in
                </BaseButton>
            </form>

            <p class="text-center text-sm text-neutral-400">
                No account yet?
                <Link href="/register" class="font-medium text-neutral-200 hover:text-white">
                    Create one (includes $2.00 starter credit)
                </Link>
            </p>
        </div>
    </main>
</template>
