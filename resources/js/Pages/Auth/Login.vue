<template>

	<Head :title="`Sign In — ${appName}`" />
	<div class="min-h-screen bg-mp-page flex">
		<!-- ═══════════════════════════════════════
         LEFT SIDE — Branding panel
    ════════════════════════════════════════ -->
		<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-mp-card flex-col justify-between p-12">
			<!-- Background geometric pattern -->
			<div class="absolute inset-0 overflow-hidden">
				<!-- Large circle top right -->
				<div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-mp-teal/10 border border-mp-teal/20">
				</div>
				<!-- Medium circle bottom left -->
				<div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full bg-mp-teal/8 border border-mp-teal/15">
				</div>
				<!-- Small accent circles -->
				<div class="absolute top-1/3 left-1/4 w-32 h-32 rounded-full bg-mp-teal/5 border border-mp-teal/10">
				</div>
				<div class="absolute bottom-1/3 right-1/4 w-20 h-20 rounded-full bg-mp-teal/5"></div>
				<!-- Diagonal grid lines -->
				<svg class="absolute inset-0 w-full h-full opacity-5" xmlns="http://www.w3.org/2000/svg">
					<defs>
						<pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
							<path d="M 40 0 L 0 0 0 40" fill="none" stroke="#00b4c8" stroke-width="0.5" />
						</pattern>
					</defs>
					<rect width="100%" height="100%" fill="url(#grid)" />
				</svg>
			</div>
			<!-- Logo -->
			<div class="relative z-10">
				<div class="flex items-center gap-3">
					<div class="w-10 h-10 bg-mp-teal rounded-xl flex items-center justify-center">
						<!-- Chart/investment icon -->
						<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
						</svg>
					</div>
					<span class="text-white text-xl font-bold tracking-tight">{{ appName }}</span>
				</div>
			</div>
			<!-- Center content -->
			<div class="relative z-10 space-y-8">
				<!-- Main headline -->
				<div>
					<h1 class="text-4xl font-bold text-white leading-tight mb-4"> Financial Planning<br />
						<span class="text-white">Budgeting &amp; Analysis</span>
					</h1>
					<p class="text-white text-lg leading-relaxed"> Plan budgets, analyze variances, and forecast with
						confidence — one platform built for finance and consulting teams. </p>
				</div>
				<!-- Feature highlights -->
				<div class="space-y-4">
					<div class="flex items-start gap-3">
						<div
							class="w-8 h-8 bg-mp-teal/20 border border-mp-teal/30 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
							<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
							</svg>
						</div>
						<div>
							<p class="text-white text-sm font-medium">Budgeting &amp; Planning</p>
							<p class="text-white text-xs mt-0.5">Build, track, and revise budgets across clients and
								fiscal periods</p>
						</div>
					</div>
					<div class="flex items-start gap-3">
						<div
							class="w-8 h-8 bg-mp-teal/20 border border-mp-teal/30 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
							<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
							</svg>
						</div>
						<div>
							<p class="text-white text-sm font-medium">Variance Analysis</p>
							<p class="text-white text-xs mt-0.5">Compare actuals vs plan with automated alerts on key
								deviations</p>
						</div>
					</div>
					<div class="flex items-start gap-3">
						<div
							class="w-8 h-8 bg-mp-teal/20 border border-mp-teal/30 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
							<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
							</svg>
						</div>
						<div>
							<p class="text-white text-sm font-medium">Financial Studies &amp; Forecasting</p>
							<p class="text-white text-xs mt-0.5">Run studies, cash forecasts, and scenario planning in
								one place</p>
						</div>
					</div>
				</div>
				<!-- Stats row -->
				<div class="grid grid-cols-3 gap-4 pt-4 border-t border-mp-border">
					<div>
						<p class="text-2xl font-bold text-white">100%</p>
						<p class="text-white text-xs mt-0.5">Secure & Private</p>
					</div>
					<div>
						<p class="text-2xl font-bold text-white">EGP</p>
						<p class="text-white text-xs mt-0.5">Multi-currency</p>
					</div>
					<div>
						<p class="text-2xl font-bold text-white">Live</p>
						<p class="text-white text-xs mt-0.5">Real-time data</p>
					</div>
				</div>
			</div>
			<!-- Footer -->
			<div class="relative z-10">
				<p class="text-white text-xs">© {{ new Date().getFullYear() }} {{ appName }} · Built by SQUAD</p>
			</div>
		</div>
		<!-- ═══════════════════════════════════════
         RIGHT SIDE — Login form
    ════════════════════════════════════════ -->
		<div class="w-full lg:w-1/2 flex flex-col min-h-screen px-6 pt-6 lg:pt-8 pb-8">
			<div class="w-full max-w-md mx-auto">
				<div class="flex justify-center mb-4 mt-4 ">
					<img src="/images/logo13.png" :alt="appName"
						class="w-full max-w-[14rem] sm:max-w-[15rem] lg:max-w-[17rem] h-auto object-contain" />
				</div>
				<div class="mb-5">
					<h2 class="text-2xl font-bold text-white">Welcome back</h2>
					<p class="text-white text-sm mt-1">Sign in to your account to continue</p>
				</div>
				<!-- Login Form -->
				<form @submit.prevent="submit" class="space-y-5">
					<!-- Email -->
					<div>
						<label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2"> Email
							Address </label>
						<input v-model="form.email" type="email" placeholder="your@email.com" autocomplete="username"
							class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
						<p v-if="form.errors.email" class="text-mp-danger text-xs mt-1">{{ form.errors.email }}</p>
					</div>
					<!-- Password -->
					<div>
						<div class="flex items-center justify-between mb-2">
							<label class="block text-xs font-semibold text-white uppercase tracking-widest"> Password
							</label>
							<Link v-if="canResetPassword" :href="route('password.request')"
								class="text-xs text-white hover:text-white transition-colors"> Forgot password? </Link>
						</div>
						<div class="relative">
							<input v-model="form.password" :type="showPassword ? 'text' : 'password'"
								placeholder="••••••••" autocomplete="current-password"
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
					<!-- Remember me -->
					<div class="flex items-center gap-3">
						<button type="button" @click="form.remember = !form.remember"
							:class="form.remember ? 'bg-mp-teal border-mp-teal' : 'bg-transparent border-mp-border'"
							class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors flex-shrink-0">
							<svg v-if="form.remember" class="w-3 h-3 text-white" fill="none" stroke="currentColor"
								viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
									d="M5 13l4 4L19 7" />
							</svg>
						</button>
						<span class="text-white text-sm">Remember me for 30 days</span>
					</div>
					<!-- General error (wrong credentials) -->
					<div v-if="status"
						class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
						{{ status }}
					</div>
					<!-- Submit -->
					<button type="submit" :disabled="form.processing"
						class="w-full flex items-center justify-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg transition-colors mt-2">
						<svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
						</svg>
						{{ form.processing ? 'Signing in...' : 'Sign In' }}
					</button>
				</form>
				<!-- Footer note -->
				<p class="text-center text-white text-xs mt-8"> Access is by invitation only. Contact your administrator
					if you need access. </p>
			</div>
		</div>
	</div>
</template>
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
	canResetPassword: Boolean,
	status: String,
})

const appName = import.meta.env.VITE_APP_NAME || 'CFOs Tools'

const showPassword = ref(false)

const form = useForm({
	email: '',
	password: '',
	remember: false,
})

function submit() {
	form.post(route('login'), {
		onFinish: () => form.reset('password'),
	})
}
</script>
