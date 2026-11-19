<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/UI/BaseButton.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Create Account" />

    <main class="flex min-h-screen items-center justify-center bg-[#0a0a0a] px-4 py-12 text-neutral-100">
        <div class="w-full max-w-md space-y-6 rounded-2xl border border-neutral-800 bg-[#121212] p-8 shadow-2xl">
            <div class="text-center">
                <h1 class="text-2xl font-bold tracking-tight text-white">Create Pixelforge Account</h1>
                <p class="mt-1 text-sm text-neutral-400">Includes $2.00 simulated starter credits.</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="reg-name" class="block text-sm font-medium text-neutral-200">Name</label>
                    <input
                        id="reg-name"
                        v-model="form.name"
                        type="text"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="reg-email" class="block text-sm font-medium text-neutral-200">Email</label>
                    <input
                        id="reg-email"
                        v-model="form.email"
                        type="email"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="reg-password" class="block text-sm font-medium text-neutral-200">Password</label>
                    <input
                        id="reg-password"
                        v-model="form.password"
                        type="password"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="reg-password-confirm" class="block text-sm font-medium text-neutral-200">
                        Confirm Password
                    </label>
                    <input
                        id="reg-password-confirm"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                </div>

                <BaseButton type="submit" class="w-full" :loading="form.processing">
                    Register & Start Generating
                </BaseButton>
            </form>

            <p class="text-center text-sm text-neutral-400">
                Already registered?
                <Link href="/login" class="font-medium text-neutral-200 hover:text-white">Sign in</Link>
            </p>
        </div>
    </main>
</template>
