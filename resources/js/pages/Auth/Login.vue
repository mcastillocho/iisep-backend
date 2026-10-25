<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}

function fillCredentials(user: string, pass: string) {
    form.login = user;
    form.password = pass;
}
</script>

<template>
    <Head title="Iniciar Sesión - Portal Administrativo CETPRO IISEP" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden selection:bg-[#2e8b57] selection:text-white">
        <!-- Elementos decorativos de fondo -->
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-[#2e8b57]/15 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Enlace superior para volver -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0 mb-6">
            <Link
                href="/"
                class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-300/80 hover:text-white transition group"
            >
                <svg class="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Volver al sitio web principal</span>
            </Link>
        </div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
            <!-- Cabecera Institucional -->
            <div class="text-center mb-8">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#2e8b57] text-white font-black text-2xl shadow-xl shadow-emerald-900/40 border border-emerald-400/30 mb-4">
                    IISEP
                </div>
                <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                    Portal Institucional
                </h1>
                <p class="mt-2 text-sm text-slate-300">
                    Sistema de Gestión Académica y Control Administrativo
                </p>
                <div class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-300">
                    <span class="h-2 w-2 rounded-full bg-[#2e8b57] animate-pulse"></span>
                    CETPRO Licenciado por MINEDU &bull; Sedes Comas y Puente Piedra
                </div>
            </div>

            <!-- Tarjeta de Login -->
            <div class="bg-white/95 backdrop-blur-md py-8 px-6 shadow-2xl rounded-2xl sm:px-10 border border-slate-100">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Alerta de Error de Validación -->
                    <div
                        v-if="form.errors.login"
                        class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-800 flex items-start gap-3"
                    >
                        <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold">Error de acceso:</span>
                            <p class="mt-0.5">{{ form.errors.login }}</p>
                        </div>
                    </div>

                    <!-- Campo Usuario o Email -->
                    <div>
                        <label for="login" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Usuario o Correo Institucional
                        </label>
                        <div class="mt-1.5 relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input
                                id="login"
                                v-model="form.login"
                                type="text"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Ej: admin o storres"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-3 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-2 focus:ring-[#2e8b57]/20 focus:outline-none transition"
                            />
                        </div>
                    </div>

                    <!-- Campo Contraseña -->
                    <div>
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Contraseña
                            </label>
                        </div>
                        <div class="mt-1.5 relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-3 pr-11 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-2 focus:ring-[#2e8b57]/20 focus:outline-none transition font-mono"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                                tabindex="-1"
                            >
                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Recordar sesión -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-[#2e8b57] focus:ring-[#2e8b57] cursor-pointer"
                            />
                            <span class="text-xs font-medium text-slate-600">Mantener sesión iniciada</span>
                        </label>
                    </div>

                    <!-- Botón de Envío -->
                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-[#2e8b57] hover:bg-[#257347] py-3.5 px-4 text-sm font-bold text-white shadow-lg shadow-emerald-700/25 transition cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ form.processing ? 'Verificando credenciales...' : 'Ingresar al Sistema' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Credenciales de Prueba Rápida para Evaluación -->
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider text-center mb-3">
                        Acceso Rápido de Demostración
                    </p>
                    <div class="grid grid-cols-1 gap-2">
                        <button
                            type="button"
                            @click="fillCredentials('admin', 'admin123')"
                            class="flex items-center justify-between p-2.5 rounded-lg border border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/70 text-left transition cursor-pointer"
                        >
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">Super Administrador (Staff)</span>
                                <span class="text-[11px] font-mono text-emerald-800">admin / admin123</span>
                            </div>
                            <span class="text-xs font-bold text-[#2e8b57]">Autollenar &rarr;</span>
                        </button>
                        <button
                            type="button"
                            @click="fillCredentials('storres', 'admin123')"
                            class="flex items-center justify-between p-2.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-left transition cursor-pointer"
                        >
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">Coordinadora Sede Comas</span>
                                <span class="text-[11px] font-mono text-slate-600">storres / admin123</span>
                            </div>
                            <span class="text-xs font-bold text-slate-600">Autollenar &rarr;</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Informativo -->
            <div class="mt-8 text-center text-xs text-slate-400">
                <p>&copy; {{ new Date().getFullYear() }} CETPRO IISEP. Todos los derechos reservados.</p>
                <p class="mt-1">R.D. N° 0154-2015 &bull; Autorizado por MINEDU / DRELM</p>
            </div>
        </div>
    </div>
</template>
