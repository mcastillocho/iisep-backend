<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const careers = [
    {
        title: 'Computación e Informática',
        category: 'Tecnología',
        icon: 'computer',
        duration: '1 a 2 Años (Modular)',
        badge: 'Certificación Oficial MINEDU',
        description:
            'Aprende gestión de sistemas, soporte y mantenimiento de equipos, redes de datos, bases de datos y desarrollo de aplicaciones para empresas.',
        features: ['Ofimática avanzada', 'Soporte de hardware y redes', 'Desarrollo web y bases de datos'],
        color: 'from-blue-600 to-indigo-700',
    },
    {
        title: 'Logística y Gestión de Almacenes',
        category: 'Gestión Empresarial',
        icon: 'truck',
        duration: '1 Año (Modular)',
        badge: 'Alta Demanda Laboral',
        description:
            'Capacítate en control de inventarios, gestión de almacenes, Kardex, compras, despachos y cadena de suministros con herramientas digitales.',
        features: ['Control de stock e inventarios', 'Operaciones de almacén y despacho', 'Software logístico y Excel'],
        color: 'from-amber-600 to-orange-700',
    },
    {
        title: 'Gastronomía y Arte Culinario',
        category: 'Alimentos y Bebidas',
        icon: 'utensils',
        duration: '1 a 2 Años (Modular)',
        badge: 'Talleres Prácticos 100%',
        description:
            'Domina las técnicas de cocina peruana, internacional, pescados y mariscos, buffet y gestión de negocios culinarios con altos estándares.',
        features: ['Cocina peruana e internacional', 'Buenas Prácticas de Manufactura (BPM)', 'Costos y gestión de restaurantes'],
        color: 'from-rose-600 to-red-700',
    },
    {
        title: 'Cosmetología y Estética Integral',
        category: 'Cuidado y Belleza',
        icon: 'sparkles',
        duration: '1 Año (Modular)',
        badge: 'Inserción o Emprendimiento',
        description:
            'Especialízate en corte y diseño capilar, colorimetría, peinados para eventos, manicure, pedicure y tratamientos estéticos modernos.',
        features: ['Colorimetría y mechas avanzadas', 'Corte dama, caballero y niños', 'Tratamientos capilares y faciales'],
        color: 'from-pink-600 to-rose-700',
    },
    {
        title: 'Instalaciones Electrotécnicas',
        category: 'Ingeniería Técnica',
        icon: 'bolt',
        duration: '1 Año (Modular)',
        badge: 'Certificación Técnica',
        description:
            'Aprende diseño, instalación y mantenimiento de tableros eléctricos residenciales y comerciales conforme al Código Nacional de Electricidad.',
        features: ['Instalaciones residenciales y comerciales', 'Tableros de distribución y pozo a tierra', 'Normas de seguridad eléctrica'],
        color: 'from-emerald-600 to-teal-800',
    },
];

const workshops = [
    { name: 'Ofimática Empresarial', duration: '2 a 3 Meses', icon: 'file-text' },
    { name: 'Barber Shop & Fade Pro', duration: '2 Meses', icon: 'scissors' },
    { name: 'Uñas Acrílicas & Polygel', duration: '2 Meses', icon: 'hand' },
    { name: 'Ensamblaje y Redes de PC', duration: '3 Meses', icon: 'cpu' },
    { name: 'Diseño Gráfico Publicitario', duration: '3 Meses', icon: 'palette' },
    { name: 'Pastelería y Repostería Fina', duration: '3 Meses', icon: 'cake' },
    { name: 'Bartender y Coctelería', duration: '2 Meses', icon: 'wine' },
];

const branches = [
    {
        name: 'Sede Comas (La Pascana)',
        address: 'Av. Túpac Amaru 3850 – La Pascana',
        reference: 'Al costado de Plaza Vea La Pascana',
        phones: ['970 518 364', '(01) 698-0498'],
        schedule: 'Lunes a Sábado: 8:00 am - 8:00 pm',
        highlights: 'Talleres de cómputo equipados, cocina profesional y salones de estética.',
    },
    {
        name: 'Sede Puente Piedra (Centro)',
        address: 'Jr. Ricardo Palma 241',
        reference: '2do piso de la oficina RENIEC',
        phones: ['987 710 344'],
        schedule: 'Lunes a Sábado: 8:00 am - 8:00 pm',
        highlights: 'Ubicación céntrica de rápido acceso con aulas climatizadas y talleres modernos.',
    },
];
</script>

<template>
    <Head title="CETPRO IISEP - Centro de Educación Técnico Productiva | Sedes Comas y Puente Piedra">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-800 antialiased selection:bg-[#2e8b57] selection:text-white">
        <!-- Barra Superior de Contacto y Autorización MINEDU -->
        <div class="bg-slate-900 text-slate-300 text-xs py-2 px-4 border-b border-slate-800">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 font-medium text-emerald-400">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        CETPRO Licenciado por MINEDU &bull; R.D. N° 0154-2015-ED
                    </span>
                    <span class="hidden md:inline text-slate-600">|</span>
                    <span class="hidden md:inline text-slate-400">DRELM - UGEL 04</span>
                </div>
                <div class="flex items-center gap-4 text-slate-300">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Comas: 970 518 364
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Puente Piedra: 987 710 344
                    </span>
                </div>
            </div>
        </div>

        <!-- Barra de Navegación Principal -->
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <!-- Marca / Logo -->
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="h-12 w-12 rounded-xl bg-[#2e8b57] text-white flex items-center justify-center font-black text-xl shadow-md shadow-emerald-900/20 group-hover:scale-105 transition-transform">
                        IISEP
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-black text-slate-900 tracking-tight leading-none group-hover:text-[#2e8b57] transition-colors">
                            CETPRO IISEP
                        </span>
                        <span class="text-xs text-slate-500 font-medium leading-tight mt-0.5">
                            Educación Técnico Productiva
                        </span>
                    </div>
                </Link>

                <!-- Menú de Enlaces Desktop -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                    <a href="#carreras" class="hover:text-[#2e8b57] transition-colors">Carreras Técnicas</a>
                    <a href="#talleres" class="hover:text-[#2e8b57] transition-colors">Cursos Cortos</a>
                    <a href="#sedes" class="hover:text-[#2e8b57] transition-colors">Sedes y Horarios</a>
                    <a href="#beneficios" class="hover:text-[#2e8b57] transition-colors">¿Por qué IISEP?</a>
                </nav>

                <!-- Acciones / Botón de Login -->
                <div class="flex items-center gap-3">
                    <template v-if="user">
                        <Link
                            href="/admin"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold shadow-md hover:bg-slate-800 transition"
                        >
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span>Panel Admin ({{ user.username }})</span>
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2e8b57] text-white text-sm font-bold shadow-md shadow-emerald-700/20 hover:bg-[#267347] active:scale-[0.98] transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>Acceso Staff / Login</span>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative bg-gradient-to-b from-emerald-950 via-slate-900 to-slate-900 text-white pt-16 pb-24 overflow-hidden">
            <!-- Patrón de fondo brillante -->
            <div class="absolute inset-0 bg-[radial-gradient(#2e8b57_1px,transparent_1px)] [background-size:24px_24px] opacity-20 pointer-events-none"></div>
            <div class="absolute top-10 right-1/4 w-96 h-96 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-10 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-semibold mb-6">
                        <span class="flex h-2 w-2 rounded-full bg-[#2e8b57] animate-ping"></span>
                        Matrículas Abiertas 2025 - 2026 | Turnos Mañana, Tarde y Noche
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.1] text-white">
                        Formación Técnica que impulsa tu <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-amber-300">éxito profesional</span>
                    </h1>

                    <p class="mt-6 text-lg sm:text-xl text-slate-300 leading-relaxed">
                        Aprende con metodología 100% práctica orientada a la rápida inserción laboral. Obtén tu
                        <strong class="text-white font-semibold">Título Oficial a Nombre de la Nación</strong> en nuestras sedes de
                        <strong class="text-emerald-300 font-semibold">Comas y Puente Piedra</strong>.
                    </p>

                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a
                            href="#carreras"
                            class="inline-flex items-center justify-center px-6 py-3.5 rounded-xl bg-[#2e8b57] text-white text-base font-bold shadow-lg shadow-emerald-900/50 hover:bg-[#267347] transition active:scale-95"
                        >
                            Ver Carreras Técnicas
                        </a>

                        <Link
                            href="/login"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 text-white text-base font-semibold border border-white/10 backdrop-blur-xs transition"
                        >
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Portal Administrativo</span>
                        </Link>
                    </div>

                    <!-- Estadísticas Rápidas -->
                    <div class="mt-14 grid grid-cols-2 sm:grid-cols-4 gap-6 pt-8 border-t border-slate-800">
                        <div>
                            <div class="text-3xl font-black text-white">+15 Años</div>
                            <div class="text-xs text-slate-400 mt-1">Formando especialistas</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-emerald-400">100% Oficial</div>
                            <div class="text-xs text-slate-400 mt-1">Certificado MINEDU</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-white">2 Sedes</div>
                            <div class="text-xs text-slate-400 mt-1">Comas y Puente Piedra</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-amber-400">90%</div>
                            <div class="text-xs text-slate-400 mt-1">Inserción laboral</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección Carreras Técnicas -->
        <section id="carreras" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#2e8b57] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                        Oferta Académica Oficial
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight mt-3">
                        Carreras Técnicas Profesionales
                    </h2>
                    <p class="mt-4 text-base text-slate-600">
                        Planes curriculares modulares aprobados por el Ministerio de Educación que te permiten certificar progresivamente y trabajar mientras estudias.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div
                        v-for="career in careers"
                        :key="career.title"
                        class="group relative bg-white rounded-2xl border border-slate-200 shadow-xs hover:shadow-xl hover:border-emerald-500/40 transition-all duration-300 flex flex-col overflow-hidden"
                    >
                        <!-- Franja Superior de Color -->
                        <div class="h-3 w-full bg-gradient-to-r" :class="career.color"></div>

                        <div class="p-6 sm:p-7 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="text-xs font-semibold text-slate-500">{{ career.category }}</span>
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ career.badge }}
                                    </span>
                                </div>

                                <h3 class="text-xl font-black text-slate-900 group-hover:text-[#2e8b57] transition-colors">
                                    {{ career.title }}
                                </h3>

                                <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Duración: {{ career.duration }}</span>
                                </div>

                                <p class="mt-4 text-sm text-slate-600 leading-relaxed">
                                    {{ career.description }}
                                </p>

                                <div class="mt-5 pt-5 border-t border-slate-100">
                                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Competencias clave:</div>
                                    <ul class="space-y-1.5">
                                        <li
                                            v-for="feature in career.features"
                                            :key="feature"
                                            class="text-xs text-slate-600 flex items-center gap-2"
                                        >
                                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ feature }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-medium">Sedes Comas & Puente Piedra</span>
                                <a
                                    href="#sedes"
                                    class="text-xs font-bold text-[#2e8b57] hover:text-[#267347] inline-flex items-center gap-1 group-hover:translate-x-1 transition-transform"
                                >
                                    <span>Consultar vacante</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección Cursos Cortos y Talleres -->
        <section id="talleres" class="py-16 bg-slate-100 border-y border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-3 py-1 rounded-full border border-amber-200">
                            Capacitación Rápida
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-2">
                            Cursos Cortos y Talleres de Especialización
                        </h2>
                        <p class="mt-2 text-sm text-slate-600 max-w-2xl">
                            Aprende habilidades puntuales y actualizadas para emprender tu propio negocio o potenciar tu CV en 2 a 3 meses.
                        </p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <span class="text-xs font-semibold text-slate-500">Incluye certificación modular &bull; Vacantes limitadas</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        v-for="w in workshops"
                        :key="w.name"
                        class="p-5 rounded-xl bg-white border border-slate-200 shadow-xs hover:border-emerald-500 hover:shadow-md transition group"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                {{ w.duration }}
                            </span>
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <h3 class="font-bold text-slate-900 text-base mt-3 group-hover:text-[#2e8b57] transition-colors">
                            {{ w.name }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Clases 100% prácticas en talleres acondicionados.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección Sedes y Horarios -->
        <section id="sedes" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#2e8b57] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                        Ubicaciones Estratégicas
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight mt-3">
                        Nuestras Sedes en Lima Norte
                    </h2>
                    <p class="mt-3 text-sm text-slate-600">
                        Instalaciones equipadas con talleres modernos, fácil acceso y seguridad para nuestros estudiantes.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div
                        v-for="branch in branches"
                        :key="branch.name"
                        class="p-8 rounded-2xl bg-gradient-to-br from-slate-50 to-emerald-50/30 border border-slate-200 shadow-sm relative overflow-hidden"
                    >
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 rounded-xl bg-[#2e8b57] text-white flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-900">{{ branch.name }}</h3>
                                <p class="text-xs text-emerald-700 font-semibold">{{ branch.reference }}</p>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm text-slate-600 mt-6">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span><strong>Dirección:</strong> {{ branch.address }}</span>
                            </div>

                            <div class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span><strong>Teléfonos de contacto:</strong> {{ branch.phones.join(' / ') }}</span>
                            </div>

                            <div class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span><strong>Horario:</strong> {{ branch.schedule }}</span>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-200 text-xs text-slate-500 font-medium">
                            {{ branch.highlights }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banner Exclusivo para Personal Administrativo / Staff -->
        <section id="portal-staff" class="py-16 bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl bg-white/5 border border-white/10 p-8 sm:p-12 flex flex-col lg:flex-row items-center justify-between gap-8 backdrop-blur-md">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold mb-4">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Plataforma Interna de Gestión Académica</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            Acceso Exclusivo para Personal Staff y Docentes
                        </h2>
                        <p class="mt-3 text-sm text-slate-300 leading-relaxed">
                            Ingresa al sistema para la gestión integral de matrículas, aperturas de cursos y horarios, control de asistencia por DNI en tiempo real y administración de sedes.
                        </p>
                    </div>

                    <div class="shrink-0 flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
                        <Link
                            href="/login"
                            class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-[#2e8b57] hover:bg-[#267347] text-white font-bold text-base shadow-xl shadow-emerald-950 transition active:scale-95"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>Iniciar Sesión en el Panel (/login)</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer Institucional -->
        <footer class="bg-slate-950 text-slate-400 text-xs py-12 border-t border-slate-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-slate-900">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-[#2e8b57] text-white flex items-center justify-center font-black text-lg">
                                IISEP
                            </div>
                            <span class="text-lg font-black text-white">CETPRO IISEP</span>
                        </div>
                        <p class="mt-3 text-slate-400 max-w-sm text-xs leading-relaxed">
                            Centro de Educación Técnico Productiva autorizado con Resolución Directoral N° 0154-2015-ED. Comprometidos con el desarrollo profesional de nuestros estudiantes.
                        </p>
                    </div>

                    <div>
                        <h4 class="font-bold text-white text-sm mb-3">Navegación</h4>
                        <ul class="space-y-2">
                            <li><a href="#carreras" class="hover:text-emerald-400 transition-colors">Carreras Técnicas</a></li>
                            <li><a href="#talleres" class="hover:text-emerald-400 transition-colors">Talleres Cortos</a></li>
                            <li><a href="#sedes" class="hover:text-emerald-400 transition-colors">Sedes y Horarios</a></li>
                            <li><Link href="/login" class="text-emerald-400 font-semibold hover:underline">Acceso Administrativo (/login)</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-bold text-white text-sm mb-3">Atención y Sedes</h4>
                        <ul class="space-y-2">
                            <li>Sede Comas: Av. Túpac Amaru 3850</li>
                            <li>Sede Puente Piedra: Jr. Ricardo Palma 241</li>
                            <li>Horario: Lun - Sáb 8:00am a 8:00pm</li>
                            <li>MINEDU / DRELM / UGEL 04</li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-slate-500">
                    <p>&copy; {{ new Date().getFullYear() }} CETPRO IISEP. Todos los derechos reservados.</p>
                    <div class="flex items-center gap-6">
                        <Link href="/login" class="hover:text-slate-300">Intranet Docente y Administrativa</Link>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
