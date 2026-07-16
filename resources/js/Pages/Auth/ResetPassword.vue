<template>

	<Head :title="`Reset Password — ${appName}`" />
	<div class="min-h-screen bg-mp-page flex">
		<!-- Left side — Branding (same as Login) -->
		<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-mp-card flex-col justify-between p-12">
			<div class="absolute inset-0 overflow-hidden">
				<div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-mp-teal/10 border border-mp-teal/20">
				</div>
				<div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full bg-mp-teal/8 border border-mp-teal/15">
				</div>
				<div class="absolute top-1/3 left-1/4 w-32 h-32 rounded-full bg-mp-teal/5 border border-mp-teal/10">
				</div>
				<div class="absolute bottom-1/3 right-1/4 w-20 h-20 rounded-full bg-mp-teal/5"></div>
				<svg class="absolute inset-0 w-full h-full opacity-5" xmlns="http://www.w3.org/2000/svg">
					<defs>
						<pattern id="grid-reset" width="40" height="40" patternUnits="userSpaceOnUse">
							<path d="M 40 0 L 0 0 0 40" fill="none" stroke="#00b4c8" stroke-width="0.5" />
						</pattern>
					</defs>
					<rect width="100%" height="100%" fill="url(#grid-reset)" />
				</svg>
			</div>
			<div class="relative z-10">
				<div class="flex items-center gap-3">
					<div class="w-10 h-10 bg-mp-teal rounded-xl flex items-center justify-center">
						<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
						</svg>
					</div>
					<span class="text-white text-xl font-bold tracking-tight">{{ appName }}</span>
				</div>
			</div>
			<div class="relative z-10 space-y-8">
				<div>
					<h1 class="text-4xl font-bold text-white leading-tight mb-4"> Set a new<br />
						<span class="text-white">password</span>
					</h1>
					<p class="text-white text-lg leading-relaxed"> Choose a strong password. This link expires in 60
						minutes. </p>
				</div>
			</div>
			<div class="relative z-10">
				<p class="text-white text-xs">© {{ new Date().getFullYear() }} {{ appName }} · Built by SQUAD</p>
			</div>
		</div>
		<!-- Right side — Form -->
		<div class="w-full lg:w-1/2 flex flex-col min-h-screen px-6 pt-6 lg:pt-8 pb-8">
			<div class="w-full max-w-md mx-auto">
				<div class="flex justify-center mb-4 mt-4 ">
					<img src="/images/logo13.png" :alt="appName"
						class="w-full max-w-[14rem] sm:max-w-[15rem] lg:max-w-[17rem] h-auto object-contain" />
				</div>
				<div class="mb-5">
					<h2 class="text-2xl font-bold text-white">Reset password</h2>
					<p class="text-white text-sm mt-1">Enter your new password below</p>
				</div>
				<form @submit.prevent="submit" class="space-y-5">
					<div>
						<label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2"> Email
						</label>
						<input v-model="form.email" type="email" readonly
							class="w-full bg-mp-card-hover/50 border border-mp-border rounded-lg px-4 py-3 text-white cursor-not-allowed" />
						<p v-if="form.errors.email" class="text-mp-danger text-xs mt-1">{{ form.errors.email }}</p>
					</div>
					<div>
						<label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2"> New
							password </label>
						<div class="relative">
							<input v-model="form.password" :type="showPassword ? 'text' : 'password'"
								placeholder="••••••••" autocomplete="new-password"
								class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition pr-12" />
							<button type="button" @click="showPassword = !showPassword"
								class="absolute right-3 top-3.5 text-white hover:text-white transition-colors">
								<svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor"
									viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
								</svg>
								<svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
								</svg>
							</button>
						</div>
						<p v-if="form.errors.password" class="text-mp-danger text-xs mt-1">{{ form.errors.password }}
						</p>
					</div>
					<div>
						<label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2"> Confirm
							password </label>
						<input v-model="form.password_confirmation" type="password" placeholder="••••••••"
							autocomplete="new-password"
							class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
						<p v-if="form.errors.password_confirmation" class="text-mp-danger text-xs mt-1">
							{{ form.errors.password_confirmation }}</p>
					</div>
					<button type="submit" :disabled="form.processing"
						class="w-full flex items-center justify-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg transition-colors mt-2">
						<svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
						</svg>
						{{ form.processing ? 'Resetting...' : 'Reset password' }}
					</button>
				</form>
				<p class="text-center mt-6">
					<Link :href="route('login')" class="text-sm text-white hover:text-white transition-colors"> Back to
						sign in </Link>
				</p>
			</div>
		</div>
	</div>
</template>
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
	email: { type: String, required: true },
	token: { type: String, required: true },
})

const appName = import.meta.env.VITE_APP_NAME || 'CFOs Tools'

const showPassword = ref(false)

const form = useForm({
	token: props.token,
	email: props.email,
	password: '',
	password_confirmation: '',
})

function submit() {
	form.post(route('password.store'), {
		onFinish: () => form.reset('password', 'password_confirmation'),
	})
}
</script>
