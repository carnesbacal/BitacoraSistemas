<?php
/**
 * ============================================================================
 * config/header.php - Layout superior reutilizable
 * ============================================================================
 * Incluye este archivo al inicio de cada página protegida.
 * Variables esperadas:
 *   $titulo_pagina (string) - título mostrado en <title> y en la cabecera
 *   $pagina_activa (string) - identificador de la sección activa para el sidebar
 * ============================================================================
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

requerir_login();

// Headers anti-cache para que el navegador no sirva versiones viejas de páginas dinámicas
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = usuario_actual();
$titulo_pagina = $titulo_pagina ?? 'Inicio';
$pagina_activa = $pagina_activa ?? '';
$mensajes_flash = flash_get();

// Conteo de notificaciones no leídas (con protección por si la tabla falla)
$notif_count = 0;
try {
    $row = db_one(
        "SELECT COUNT(*) c FROM notificaciones WHERE usuario_id = :uid AND leida = 0",
        ['uid' => $u['id']]
    );
    $notif_count = (int) ($row['c'] ?? 0);
} catch (Throwable $e) {
    // Silenciar: no debe romper la página
    $notif_count = 0;
}
?><!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo_pagina) ?> · <?= e(APP_NAME) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= url('favicon.png') ?>?v=1">
    <link rel="shortcut icon" href="<?= url('favicon.ico') ?>?v=1">
    <link rel="apple-touch-icon" href="<?= url('favicon.png') ?>?v=1">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fuentes: Bricolage Grotesque para títulos, Inter para UI -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Alpine.js (debe cargar ANTES que lucide para que el DOM dinámico funcione) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Aplicar modo oscuro ANTES de renderizar para evitar flash blanco -->
    <script>
        (function() {
            const pref = localStorage.getItem('tema_preferido') || '<?= e($u['tema_preferido'] ?? 'auto') ?>';
            const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const aplicar = pref === 'oscuro' || (pref === 'auto' && sysDark);
            if (aplicar) document.documentElement.classList.add('dark');
        })();
    </script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['"Bricolage Grotesque"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        bacal: {
                        /*
                            50:  '#FEF2F2',
                            100: '#FEE2E2',
                            200: '#FECACA',
                            300: '#FCA5A5',
                            400: '#F87171',
                            500: '#EF4444',
                            600: '#DC2626',
                            700: '#C8102E',  // rojo corporativo
                            800: '#991B1B',
                            900: '#7F1D1D',
                            */

                            50:  '#F4F5F6',
                            100: '#E8E9EB',
                            200: '#D1D3D8',
                            300: '#B0B5BC',
                            400: '#8E959F',
                            500: '#6B7380',
                            600: '#545D68',
                            700: '#36454F',   // Gris Antracita principal
                            800: '#2C3538',
                            900: '#1B1F24',

                        },
                        gold: {
                            400: '#F2C94C',
                            500: '#E8B923',
                            600: '#D4A017',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Bricolage Grotesque', sans-serif; letter-spacing: -0.02em; }

        /* ===================================================================
           MODO OSCURO - Overrides globales
           Se aplican cuando <html class="dark">
           Mapea automáticamente clases de Tailwind comunes en el sistema
           =================================================================== */
        html.dark {
            color-scheme: dark;
        }

        /* Body */
        html.dark body { background-color: #09090b; color: #e4e4e7; }

        /* Backgrounds blancos → oscuros */
        html.dark .bg-white { background-color: #18181b !important; }
        html.dark .bg-zinc-50 { background-color: #1f1f23 !important; }
        html.dark .bg-zinc-100 { background-color: #27272a !important; }
        html.dark .bg-zinc-200 { background-color: #3f3f46 !important; }

        /* Estados hover */
        html.dark .hover\:bg-zinc-50:hover { background-color: #27272a !important; }
        html.dark .hover\:bg-zinc-100:hover { background-color: #3f3f46 !important; }
        html.dark .hover\:bg-white:hover { background-color: #27272a !important; }

        /* Bordes */
        html.dark .border-zinc-100 { border-color: #27272a !important; }
        html.dark .border-zinc-200 { border-color: #3f3f46 !important; }
        html.dark .border-zinc-300 { border-color: #52525b !important; }

        /* Texto principal */
        html.dark .text-zinc-900 { color: #f4f4f5 !important; }
        html.dark .text-zinc-800 { color: #e4e4e7 !important; }
        html.dark .text-zinc-700 { color: #d4d4d8 !important; }
        html.dark .text-zinc-600 { color: #a1a1aa !important; }
        html.dark .text-zinc-500 { color: #71717a !important; }
        html.dark .text-zinc-400 { color: #52525b !important; }

        /* Inputs */
        html.dark input, html.dark textarea, html.dark select {
            background-color: #27272a !important;
            border-color: #52525b !important;
            color: #f4f4f5 !important;
        }
        html.dark input::placeholder, html.dark textarea::placeholder {
            color: #71717a !important;
        }
        html.dark input:focus, html.dark textarea:focus, html.dark select:focus {
            border-color: #36454F !important;
        }

        /* Backgrounds de colores tenues (50) → variantes oscuras tenues */
        html.dark .bg-bacal-50 { background-color: rgba(54, 69, 79, 0.1) !important; }
        html.dark .bg-emerald-50 { background-color: rgba(16, 185, 129, 0.1) !important; }
        html.dark .bg-blue-50 { background-color: rgba(59, 130, 246, 0.1) !important; }
        html.dark .bg-amber-50 { background-color: rgba(217, 119, 6, 0.1) !important; }
        html.dark .bg-purple-50 { background-color: rgba(147, 51, 234, 0.1) !important; }
        html.dark .bg-blue-100 { background-color: rgba(59, 130, 246, 0.15) !important; }
        html.dark .bg-emerald-100 { background-color: rgba(16, 185, 129, 0.15) !important; }
        html.dark .bg-amber-100 { background-color: rgba(217, 119, 6, 0.15) !important; }
        html.dark .bg-purple-100 { background-color: rgba(147, 51, 234, 0.15) !important; }
        html.dark .bg-bacal-100 { background-color: rgba(54, 69, 79, 0.15) !important; }

        /* Bordes tenues */
        html.dark .border-bacal-200 { border-color: rgba(54, 69, 79, 0.3) !important; }
        html.dark .border-emerald-200 { border-color: rgba(16, 185, 129, 0.3) !important; }
        html.dark .border-blue-200 { border-color: rgba(59, 130, 246, 0.3) !important; }
        html.dark .border-amber-200 { border-color: rgba(217, 119, 6, 0.3) !important; }
        html.dark .border-purple-200 { border-color: rgba(147, 51, 234, 0.3) !important; }

        /* Sombras más sutiles en oscuro */
        html.dark .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important; }
        html.dark .shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.4) !important; }
        html.dark .shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4) !important; }
        html.dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; }
        html.dark .shadow-2xl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important; }

        /* Gradientes en headers de pestañas */
        html.dark .bg-gradient-to-br { background-image: linear-gradient(to bottom right, #1f1f23, #18181b) !important; }

        /* Scrollbar oscuro */
        html.dark ::-webkit-scrollbar { background: #18181b; }
        html.dark ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 6px; }
        html.dark ::-webkit-scrollbar-thumb:hover { background: #52525b; }

        /* Oculta elementos con x-cloak hasta que Alpine los procese */
        [x-cloak] { display: none !important; }

        /* Scrollbar discreto */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1aa; }

        /* Item activo del sidebar */
        .nav-item-active {
            background: linear-gradient(90deg, rgba(54,69,79,0.08) 0%, rgba(54,69,79,0.02) 100%);
            color: #36454F;
            border-left: 3px solid #36454F;
        }
        .nav-item-active svg { color: #36454F; }

        /* Transición sutil al hover */
        .nav-item {
            border-left: 3px solid transparent;
            transition: all 0.15s ease;
        }
        .nav-item:hover {
            background: rgba(0,0,0,0.03);
        }

        /* ===================================================================
           MEJORAS MÓVILES Y TOUCH
           =================================================================== */
        @media (max-width: 1023px) {
            /* Tablas con scroll horizontal automático */
            .overflow-x-auto { -webkit-overflow-scrolling: touch; }

            /* Inputs más cómodos en móvil (evitan zoom de iOS si font >= 16px) */
            input[type="text"], input[type="email"], input[type="password"],
            input[type="number"], input[type="tel"], input[type="date"],
            input[type="datetime-local"], input[type="search"], textarea, select {
                font-size: 16px !important;
            }

            /* Headers más compactos */
            .font-display.text-3xl { font-size: 1.5rem !important; }
            .font-display.text-2xl { font-size: 1.25rem !important; }

            /* Reducir gaps en grids */
            .gap-6 { gap: 1rem !important; }
        }

        /* ===================================================================
           ANIMACIONES SUAVES
           =================================================================== */
        button, a, input, textarea, select {
            transition: background-color 0.15s ease, border-color 0.15s ease,
                        color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        /* Hover lift sutil en cards interactivas */
        .hover\:shadow-md:hover,
        .hover\:shadow-lg:hover {
            transform: translateY(-1px);
        }

        /* Pulse suave para badges importantes */
        @keyframes pulseSuave {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .animate-pulse-suave {
            animation: pulseSuave 2s ease-in-out infinite;
        }

        /* ===================================================================
           SKELETON SCREENS (Loading states)
           =================================================================== */
        .skeleton {
            background: linear-gradient(90deg, #f4f4f5 25%, #e4e4e7 50%, #f4f4f5 75%);
            background-size: 200% 100%;
            animation: skeletonPulse 1.5s infinite;
            border-radius: 6px;
        }
        html.dark .skeleton {
            background: linear-gradient(90deg, #27272a 25%, #3f3f46 50%, #27272a 75%);
            background-size: 200% 100%;
        }
        @keyframes skeletonPulse {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* ===================================================================
           TOASTS (notificaciones flash mejoradas)
           =================================================================== */
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up {
            animation: toastIn 0.3s ease-out;
        }

        /* ===================================================================
           SCROLL SUAVE Y MOTION REDUCIDO
           =================================================================== */
        html { scroll-behavior: smooth; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            html { scroll-behavior: auto; }
        }

        /* ===================================================================
           PRINT
           =================================================================== */
        @media print {
            aside, header, .no-print { display: none !important; }
            main { overflow: visible !important; }
        }
    </style>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased"
      x-data="busquedaGlobal()"
      @keydown.window="manejarTeclas($event)">

<!-- ============================================================
     MODAL: Búsqueda global (Ctrl+K)
     ============================================================ -->
<div x-show="abierto" x-cloak
     class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-start justify-center pt-4 md:pt-20 px-2 md:px-4"
     @click.self="cerrar()"
     x-transition.opacity>
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col"
         x-show="abierto" x-transition>
        <!-- Campo de búsqueda -->
        <div class="px-4 py-3 border-b border-zinc-200 flex items-center gap-3">
            <i data-lucide="search" class="w-5 h-5 text-zinc-400 flex-shrink-0"></i>
            <input type="text" x-ref="inputBusqueda"
                   x-model="query"
                   @input.debounce.250ms="buscar()"
                   @keydown.down.prevent="moverSeleccion(1)"
                   @keydown.up.prevent="moverSeleccion(-1)"
                   @keydown.enter.prevent="abrirSeleccionado()"
                   @keydown.escape.prevent="cerrar()"
                   placeholder="Buscar incidencias, equipos, usuarios…"
                   class="flex-1 text-base focus:outline-none bg-transparent text-zinc-900 placeholder:text-zinc-400">
            <kbd class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500 border border-zinc-200">ESC</kbd>
        </div>

        <!-- Resultados -->
        <div class="max-h-[60vh] overflow-y-auto">
            <!-- Estado: vacío inicial -->
            <template x-if="query.length < 2 && !cargando">
                <div class="px-6 py-12 text-center">
                    <i data-lucide="search" class="w-10 h-10 mx-auto text-zinc-300 mb-2"></i>
                    <p class="text-sm text-zinc-500">Escribe al menos 2 caracteres para buscar</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-[10px] text-zinc-400">
                        <span>Atajos:</span>
                        <kbd class="font-mono px-1.5 py-0.5 rounded bg-zinc-100 border border-zinc-200">↑ ↓</kbd> Navegar
                        <kbd class="font-mono px-1.5 py-0.5 rounded bg-zinc-100 border border-zinc-200">↵</kbd> Abrir
                        <kbd class="font-mono px-1.5 py-0.5 rounded bg-zinc-100 border border-zinc-200">ESC</kbd> Cerrar
                    </div>
                </div>
            </template>

            <!-- Cargando -->
            <template x-if="cargando">
                <div class="px-6 py-8 text-center">
                    <i data-lucide="loader-2" class="w-6 h-6 mx-auto text-zinc-400 animate-spin"></i>
                </div>
            </template>

            <!-- Sin resultados -->
            <template x-if="!cargando && query.length >= 2 && grupos.length === 0">
                <div class="px-6 py-12 text-center">
                    <i data-lucide="search-x" class="w-10 h-10 mx-auto text-zinc-300 mb-2"></i>
                    <p class="text-sm font-semibold text-zinc-700">Sin resultados</p>
                    <p class="text-xs text-zinc-500">No encontramos nada para "<span x-text="query"></span>"</p>
                </div>
            </template>

            <!-- Grupos de resultados -->
            <template x-for="(grupo, idxG) in grupos" :key="grupo.nombre">
                <div class="border-b border-zinc-100 last:border-b-0">
                    <div class="px-4 py-1.5 bg-zinc-50/80 flex items-center gap-1.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider sticky top-0">
                        <i :data-lucide="grupo.icono" class="w-3 h-3"></i>
                        <span x-text="grupo.nombre"></span>
                        <span class="font-normal text-zinc-400">·</span>
                        <span class="font-normal text-zinc-400" x-text="grupo.items.length"></span>
                    </div>
                    <template x-for="(item, idxI) in grupo.items" :key="item.url">
                        <a :href="urlAbsoluta(item.url)"
                           @mouseenter="indiceSeleccionado = indicePlano(idxG, idxI)"
                           :class="indicePlano(idxG, idxI) === indiceSeleccionado ? 'bg-bacal-50' : 'hover:bg-zinc-50'"
                           class="flex items-center gap-3 px-4 py-2.5 text-sm border-l-2 border-transparent"
                           :style="indicePlano(idxG, idxI) === indiceSeleccionado ? 'border-left-color: #36454F' : ''">
                            <i :data-lucide="item.icono" class="w-4 h-4 text-zinc-400 flex-shrink-0"></i>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-zinc-900 truncate" x-text="item.titulo"></div>
                                <div class="text-[11px] text-zinc-500 truncate" x-text="item.subtitulo"></div>
                            </div>
                            <template x-if="item.badge">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase whitespace-nowrap"
                                      :style="`color: ${item.badge_color}; background-color: ${item.badge_color}15`"
                                      x-text="item.badge"></span>
                            </template>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        <!-- Footer con atajos -->
        <div x-show="grupos.length > 0" x-cloak
             class="px-4 py-2 border-t border-zinc-100 bg-zinc-50/80 flex items-center gap-3 text-[10px] text-zinc-500">
            <span><kbd class="font-mono px-1 py-0.5 rounded bg-white border border-zinc-200">↑ ↓</kbd> navegar</span>
            <span><kbd class="font-mono px-1 py-0.5 rounded bg-white border border-zinc-200">↵</kbd> abrir</span>
            <span class="ml-auto" x-text="totalItems + ' resultado(s)'"></span>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Atajos de teclado (?)
     ============================================================ -->
<div x-show="modalAtajos" x-cloak
     class="fixed inset-0 z-[99] bg-black/60 flex items-center justify-center p-4"
     @click.self="modalAtajos = false"
     x-transition.opacity>
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6" x-show="modalAtajos" x-transition>
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display text-lg font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="keyboard" class="w-5 h-5"></i> Atajos de teclado
            </h3>
            <button @click="modalAtajos = false" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Búsqueda global</span>
                <span class="flex gap-1">
                    <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">Ctrl</kbd>
                    <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">K</kbd>
                </span>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Búsqueda rápida (alternativa)</span>
                <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">/</kbd>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Nueva incidencia</span>
                <span class="flex gap-1">
                    <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">Ctrl</kbd>
                    <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">N</kbd>
                </span>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Ir al Dashboard</span>
                <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">g d</kbd>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Ir a Bitácora</span>
                <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">g b</kbd>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Mostrar este menú</span>
                <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">?</kbd>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-zinc-700">Cerrar modales</span>
                <kbd class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200">ESC</kbd>
            </div>
        </div>
    </div>
</div>

<script>
function busquedaGlobal() {
    return {
        abierto: false,
        modalAtajos: false,
        query: '',
        cargando: false,
        grupos: [],
        indiceSeleccionado: 0,
        timerBuscar: null,
        ultimaTecla: '',
        ultimaTeclaTime: 0,

        get totalItems() {
            return this.grupos.reduce((acc, g) => acc + g.items.length, 0);
        },

        indicePlano(idxGrupo, idxItem) {
            let acc = 0;
            for (let i = 0; i < idxGrupo; i++) acc += this.grupos[i].items.length;
            return acc + idxItem;
        },

        itemPorIndicePlano(idx) {
            let acc = 0;
            for (const g of this.grupos) {
                if (idx < acc + g.items.length) return g.items[idx - acc];
                acc += g.items.length;
            }
            return null;
        },

        urlAbsoluta(rutaRelativa) {
            // Si ya viene con http, dejarla; si empieza con / agregar origin
            if (rutaRelativa.startsWith('http')) return rutaRelativa;
            return window.location.origin + rutaRelativa;
        },

        abrir() {
            this.abierto = true;
            this.$nextTick(() => {
                this.$refs.inputBusqueda?.focus();
            });
        },

        cerrar() {
            this.abierto = false;
            // No limpiar query para mantener última búsqueda si reabre
        },

        async buscar() {
            clearTimeout(this.timerBuscar);
            if (this.query.length < 2) {
                this.grupos = [];
                this.cargando = false;
                return;
            }
            this.cargando = true;
            try {
                const resp = await fetch('<?= url('api/buscar_global.php') ?>?q=' + encodeURIComponent(this.query), {
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                this.grupos = data.grupos || [];
                this.indiceSeleccionado = 0;
            } catch (e) {
                console.error('Error búsqueda:', e);
                this.grupos = [];
            }
            this.cargando = false;
            // Recargar lucide para iconos nuevos
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        moverSeleccion(delta) {
            const total = this.totalItems;
            if (total === 0) return;
            this.indiceSeleccionado = (this.indiceSeleccionado + delta + total) % total;
            // Scroll into view
            this.$nextTick(() => {
                const items = document.querySelectorAll('[x-data="busquedaGlobal()"] a[href]');
                items[this.indiceSeleccionado]?.scrollIntoView({ block: 'nearest' });
            });
        },

        abrirSeleccionado() {
            const item = this.itemPorIndicePlano(this.indiceSeleccionado);
            if (item) window.location.href = this.urlAbsoluta(item.url);
        },

        manejarTeclas(e) {
            // No interferir si estamos en un input/textarea (excepto Escape global)
            const enInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)
                         || e.target.isContentEditable;

            // Ctrl+K / Cmd+K → Búsqueda
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.abrir();
                return;
            }

            // Ctrl+N → Nueva incidencia
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'n' && !e.shiftKey) {
                e.preventDefault();
                window.location.href = '<?= url('incidencia_nueva.php') ?>';
                return;
            }

            // Si estamos en un input, no procesar atajos de letras
            if (enInput) return;

            // / → Búsqueda (alternativa)
            if (e.key === '/' && !this.abierto) {
                e.preventDefault();
                this.abrir();
                return;
            }

            // ? → Mostrar atajos
            if (e.key === '?' && !this.abierto) {
                e.preventDefault();
                this.modalAtajos = true;
                return;
            }

            // Escape → cerrar modal de atajos si está abierto
            if (e.key === 'Escape' && this.modalAtajos) {
                this.modalAtajos = false;
                return;
            }

            // Atajos de 2 letras (g d, g b)
            const ahora = Date.now();
            if (e.key === 'g') {
                this.ultimaTecla = 'g';
                this.ultimaTeclaTime = ahora;
                return;
            }

            // Segunda letra dentro de 1 segundo de la 'g'
            if (this.ultimaTecla === 'g' && (ahora - this.ultimaTeclaTime) < 1000) {
                if (e.key === 'd') {
                    e.preventDefault();
                    window.location.href = '<?= url('dashboard.php') ?>';
                } else if (e.key === 'b') {
                    e.preventDefault();
                    window.location.href = '<?= url('bitacora.php') ?>';
                } else if (e.key === 'm') {
                    e.preventDefault();
                    window.location.href = '<?= url('mantenimientos.php') ?>';
                } else if (e.key === 'e') {
                    e.preventDefault();
                    window.location.href = '<?= url('equipos.php') ?>';
                }
                this.ultimaTecla = '';
            }
        }
    }
}
</script>

<div class="flex h-screen overflow-hidden"
     x-data="{
        esMobile: window.innerWidth < 1024,
        sidebarAbierto: window.innerWidth >= 1024,
        menuUsuario: false,
        temaActual: localStorage.getItem('tema_preferido') || '<?= e($u['tema_preferido'] ?? 'auto') ?>',
        cambiarTema(nuevo) {
            this.temaActual = nuevo;
            localStorage.setItem('tema_preferido', nuevo);

            const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const aplicarOscuro = nuevo === 'oscuro' || (nuevo === 'auto' && sysDark);
            document.documentElement.classList.toggle('dark', aplicarOscuro);

            fetch('<?= url('api/cambiar_tema.php') ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=<?= e(csrf_token()) ?>&tema=' + encodeURIComponent(nuevo)
            }).catch(() => {});
        }
     }"
     x-init="
        window.addEventListener('resize', () => {
            const eraMobile = esMobile;
            esMobile = window.innerWidth < 1024;
            // Si pasa de móvil a desktop, abrir sidebar
            if (eraMobile && !esMobile) sidebarAbierto = true;
            // Si pasa a móvil, cerrar
            if (!eraMobile && esMobile) sidebarAbierto = false;
        });
     ">

    <!-- ============================================================ -->
    <!-- SIDEBAR -->
    <!-- ============================================================ -->
    <!-- Backdrop para móvil cuando sidebar está abierto -->
    <div x-show="sidebarAbierto && esMobile" x-cloak
         class="fixed inset-0 bg-black/50 z-30 lg:hidden"
         @click="sidebarAbierto = false"
         x-transition.opacity></div>

    <!-- Sidebar -->
    <aside class="bg-white border-r border-zinc-200 flex-shrink-0 transition-all duration-300 flex flex-col z-40
                  lg:relative fixed inset-y-0 left-0"
           :class="esMobile
                   ? (sidebarAbierto ? 'w-64 translate-x-0' : 'w-64 -translate-x-full')
                   : (sidebarAbierto ? 'w-64' : 'w-16')">

        <!-- Logo / Marca -->
        <div class="h-16 flex items-center border-b border-zinc-200 px-4 flex-shrink-0">
            <a href="<?= url('dashboard.php') ?>" class="flex items-center gap-2.5 overflow-hidden">
                <div class="w-9 h-9 flex-shrink-0 rounded-lg bg-bacal-700 flex items-center justify-center text-white font-display font-bold text-lg shadow-sm">
                    B
                </div>
                <div x-show="sidebarAbierto" x-transition.opacity class="overflow-hidden">
                    <div class="font-display font-bold text-zinc-900 text-base leading-tight">Carnes Bacal</div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Bitácora · Sistemas</div>
                </div>
            </a>
        </div>

        <!-- Navegación principal -->
        <nav class="flex-1 overflow-y-auto py-4">

            <div class="px-3 mb-2" x-show="sidebarAbierto" x-transition.opacity>
                <div class="text-[10px] uppercase tracking-wider font-bold text-zinc-400 px-3">Principal</div>
            </div>

            <a href="<?= url('dashboard.php') ?>"
               class="nav-item <?= $pagina_activa === 'dashboard' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="layout-dashboard" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Dashboard</span>
            </a>

            <a href="<?= url('proyectos.php') ?>"
             class="nav-item <?= $pagina_activa === 'proyectos' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
             <i data-lucide="folder-kanban" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
            <span x-show="sidebarAbierto" x-transition.opacity>Proyectos</span>
            </a>

            <a href="<?= url('bitacora.php') ?>"
               class="nav-item <?= $pagina_activa === 'bitacora' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="book-text" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Bitácora</span>
            </a>

            <?php if (tiene_permiso('crear_solicitud')): ?>
            <a href="<?= url('incidencia_nueva.php') ?>"
               class="nav-item <?= $pagina_activa === 'nueva' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="plus-circle" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Nueva solicitud</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('proveedores.php') ?>"
               class="nav-item <?= $pagina_activa === 'proveedores' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="truck" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Proveedores</span>
            </a>

            <a href="<?= url('mantenimientos.php') ?>"
               class="nav-item <?= $pagina_activa === 'mantenimientos' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="wrench" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Mantenimientos</span>
            </a>

            <a href="<?= url('mapa_sucursal.php') ?>"
               class="nav-item <?= $pagina_activa === 'mapa' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="map" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Mapa</span>
            </a>

            <a href="<?= url('recordatorios.php') ?>"
               class="nav-item <?= $pagina_activa === 'recordatorios' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="bell" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Recordatorios</span>
            </a>

            <a href="<?= url('vault.php') ?>"
               class="nav-item <?= $pagina_activa === 'vault' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="shield" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Bóveda</span>
            </a>

            <?php if (tiene_permiso('ver_reportes')): ?>
            <div class="px-3 mt-6 mb-2" x-show="sidebarAbierto" x-transition.opacity>
                <div class="text-[10px] uppercase tracking-wider font-bold text-zinc-400 px-3">Análisis</div>
            </div>

            <a href="<?= url('reportes/reportes.php') ?>"
               class="nav-item <?= $pagina_activa === 'reportes' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="bar-chart-3" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Reportes</span>
            </a>

            <a href="<?= url('base_conocimiento.php') ?>"
               class="nav-item <?= $pagina_activa === 'base_conocimiento' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="book-open" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Base de conocimiento</span>
            </a>
            <?php endif; ?>

            <?php if (tiene_permiso('administrar')): ?>
            <div class="px-3 mt-6 mb-2" x-show="sidebarAbierto" x-transition.opacity>
                <div class="text-[10px] uppercase tracking-wider font-bold text-zinc-400 px-3">Administración</div>
            </div>

            <a href="<?= url('admin/usuarios.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_usuarios' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="users" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Usuarios</span>
            </a>

            <a href="<?= url('admin/sucursales.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_sucursales' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="store" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Sucursales</span>
            </a>

            <a href="<?= url('admin/areas.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_areas' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="layers" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Áreas</span>
            </a>

            <a href="<?= url('admin/equipos.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_equipos' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="monitor" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Equipos</span>
            </a>

            <a href="<?= url('estaciones.php') ?>"
               class="nav-item <?= $pagina_activa === 'estaciones' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="layout-grid" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Estaciones</span>
            </a>

            <a href="<?= url('admin/catalogos.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_catalogos' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="tags" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Catálogos</span>
            </a>

            <a href="<?= url('admin/plantillas.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_plantillas' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="layout-template" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Plantillas</span>
            </a>

            <a href="<?= url('admin/auditoria.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_auditoria' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="shield-check" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Auditoría</span>
            </a>

            <a href="<?= url('admin/reglas_asignacion.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_reglas' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="settings-2" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Auto-asignación</span>
            </a>

            <a href="<?= url('admin/archivar.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_archivar' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="archive" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Organización</span>
            </a>

            <a href="<?= url('admin/importar.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_importar' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="upload-cloud" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Importar</span>
            </a>

            <a href="<?= url('admin/notificaciones_config.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_notificaciones_config' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="bell-ring" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Notificaciones</span>
            </a>

            <a href="<?= url('admin/anuncios.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_anuncios' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="megaphone" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Anuncios</span>
            </a>

            <a href="<?= url('admin/backups.php') ?>"
               class="nav-item <?= $pagina_activa === 'admin_backups' ? 'nav-item-active' : 'text-zinc-700' ?> flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
                <i data-lucide="database-backup" class="w-5 h-5 flex-shrink-0 text-zinc-500"></i>
                <span x-show="sidebarAbierto" x-transition.opacity>Backups</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Footer del sidebar: solo botón colapsar -->
        <div class="border-t border-zinc-200 flex-shrink-0 p-2">
            <button @click="sidebarAbierto = !sidebarAbierto"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-md text-zinc-500 hover:bg-zinc-100 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-show="sidebarAbierto">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"/>
                </svg>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-show="!sidebarAbierto" x-cloak>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
                <span x-show="sidebarAbierto" x-transition.opacity class="text-xs">Colapsar</span>
            </button>
        </div>
    </aside>

    <!-- ============================================================ -->
    <!-- ÁREA PRINCIPAL -->
    <!-- ============================================================ -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-zinc-200 flex items-center justify-between px-4 md:px-6 flex-shrink-0">
            <div class="flex items-center gap-3">
                <!-- Botón hamburguesa móvil -->
                <button @click="sidebarAbierto = !sidebarAbierto"
                        class="lg:hidden p-2 -ml-2 rounded-md hover:bg-zinc-100 text-zinc-600"
                        aria-label="Menú">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="font-display font-bold text-lg md:text-xl text-zinc-900 truncate"><?= e($titulo_pagina) ?></h1>
            </div>

            <div class="flex items-center gap-2">

                <!-- Búsqueda global (Ctrl+K) -->
                <button @click="abrir()"
                        class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-50 text-zinc-500 text-sm transition-colors"
                        title="Buscar (Ctrl+K)">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    <span class="text-xs">Buscar…</span>
                    <kbd class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500 border border-zinc-200 ml-3">Ctrl K</kbd>
                </button>

                <!-- Versión móvil: solo icono -->
                <button @click="abrir()"
                        class="md:hidden p-2 rounded-md hover:bg-zinc-100 text-zinc-600"
                        title="Buscar">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </button>

                <!-- Notificaciones (dropdown) -->
                <div class="relative" x-data="notifDropdown()" @click.outside="abierto = false">
                    <button @click="abrir()"
                            class="relative p-2 rounded-md hover:bg-zinc-100 text-zinc-600">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span x-show="conteo > 0" x-cloak
                              class="absolute top-1 right-1 min-w-[16px] h-4 bg-bacal-700 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1"
                              x-text="conteo > 9 ? '9+' : conteo"></span>
                    </button>

                    <!-- Panel desplegable -->
                    <div x-show="abierto" x-cloak x-transition.opacity
                         class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-zinc-200 shadow-lg overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-zinc-100 flex items-center justify-between">
                            <h3 class="font-display font-bold text-sm text-zinc-900">Notificaciones</h3>
                            <a href="<?= url('notificaciones.php') ?>" class="text-[11px] font-semibold text-bacal-700 hover:underline">Ver todas</a>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="cargando">
                                <div class="px-4 py-8 text-center text-xs text-zinc-400">Cargando…</div>
                            </template>
                            <template x-if="!cargando && recientes.length === 0">
                                <div class="px-4 py-8 text-center">
                                    <i data-lucide="bell-off" class="w-8 h-8 mx-auto text-zinc-300 mb-2"></i>
                                    <p class="text-xs text-zinc-500">Sin notificaciones</p>
                                </div>
                            </template>
                            <template x-for="n in recientes" :key="n.id">
                                <a :href="n.url || '#'"
                                   @click="marcarLeida(n.id)"
                                   class="flex items-start gap-2.5 px-4 py-3 transition-colors border-b border-zinc-50 last:border-b-0"
                                   :class="n.leida ? 'hover:bg-zinc-50' : 'bg-bacal-50/30 hover:bg-bacal-50/60'">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                         :style="`background-color: ${n.color}15`">
                                        <i :data-lucide="n.icono" class="w-4 h-4" :style="`color: ${n.color}`"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-1">
                                            <div class="font-semibold text-xs text-zinc-900 leading-tight" x-text="n.titulo"></div>
                                            <span x-show="!n.leida" class="w-1.5 h-1.5 rounded-full bg-bacal-700 flex-shrink-0 mt-1"></span>
                                        </div>
                                        <div class="text-[11px] text-zinc-600 line-clamp-2 mt-0.5" x-text="n.mensaje"></div>
                                        <div class="text-[10px] text-zinc-400 mt-1" x-text="n.tiempo_relativo"></div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Menú de usuario -->
                <div class="relative" @click.outside="menuUsuario = false">
                    <button @click="menuUsuario = !menuUsuario"
                            class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-md hover:bg-zinc-100 transition-colors">
                        <?= render_avatar($u, 'w-8 h-8') ?>
                        <div class="text-left hidden md:block">
                            <div class="text-sm font-semibold text-zinc-900 leading-tight"><?= e($u['nombre']) ?></div>
                            <div class="text-[11px] text-zinc-500"><?= e($u['rol_nombre']) ?></div>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-400"></i>
                    </button>

                    <div x-show="menuUsuario" x-transition x-cloak
                         class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-zinc-200 py-2 z-50">

                        <div class="px-4 py-3 border-b border-zinc-100">
                            <div class="font-semibold text-sm text-zinc-900"><?= e($u['nombre']) ?></div>
                            <div class="text-xs text-zinc-500 mt-0.5"><?= e($u['email'] ?? '') ?></div>
                            <div class="mt-2">
                                <?= badge($u['rol_nombre'], '#36454F') ?>
                            </div>
                        </div>

                        <a href="<?= url('mi_perfil.php') ?>"
                           @click.stop
                           class="flex items-center gap-2.5 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="user" class="w-4 h-4 text-zinc-400"></i>
                            Mi perfil
                        </a>

                        <a href="<?= url('cambiar_password.php') ?>"
                           @click.stop
                           class="flex items-center gap-2.5 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="key" class="w-4 h-4 text-zinc-400"></i>
                            Cambiar contraseña
                        </a>

                        <div class="border-t border-zinc-100 my-1"></div>

                        <!-- Selector de tema (modo claro/oscuro/auto) -->
                        <div class="px-4 py-2" @click.stop>
                            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1.5">Tema</div>
                            <div class="grid grid-cols-3 gap-1">
                                <button type="button" @click="cambiarTema('auto')"
                                        :class="temaActual === 'auto' ? 'bg-bacal-700 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                                        class="px-2 py-1.5 rounded text-[10px] font-semibold flex flex-col items-center gap-0.5 transition-colors">
                                    <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                                    Auto
                                </button>
                                <button type="button" @click="cambiarTema('claro')"
                                        :class="temaActual === 'claro' ? 'bg-bacal-700 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                                        class="px-2 py-1.5 rounded text-[10px] font-semibold flex flex-col items-center gap-0.5 transition-colors">
                                    <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                                    Claro
                                </button>
                                <button type="button" @click="cambiarTema('oscuro')"
                                        :class="temaActual === 'oscuro' ? 'bg-bacal-700 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                                        class="px-2 py-1.5 rounded text-[10px] font-semibold flex flex-col items-center gap-0.5 transition-colors">
                                    <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                                    Oscuro
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-zinc-100 my-1"></div>

                        <a href="<?= url('logout.php') ?>"
                           @click.stop
                           class="flex items-center gap-2.5 px-4 py-2 text-sm text-bacal-700 hover:bg-bacal-50 font-semibold">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Cerrar sesión
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <script>
        function notifDropdown() {
            return {
                abierto: false,
                conteo: <?= (int) $notif_count ?>,
                cargando: false,
                recientes: [],

                async cargar() {
                    this.cargando = true;
                    try {
                        const resp = await fetch('<?= url('api/notificaciones_no_leidas.php') ?>', { credentials: 'same-origin' });
                        if (resp.ok) {
                            const data = await resp.json();
                            this.conteo = data.no_leidas;
                            this.recientes = data.recientes;
                        }
                    } catch (e) { console.error(e); }
                    this.cargando = false;
                    // Re-renderizar íconos lucide
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                },

                async abrir() {
                    this.abierto = !this.abierto;
                    if (this.abierto) await this.cargar();
                },

                async marcarLeida(id) {
                    const fd = new FormData();
                    fd.append('_csrf', '<?= e(csrf_token()) ?>');
                    fd.append('id', id);
                    try {
                        const resp = await fetch('<?= url('api/notificacion_marcar_leida.php') ?>', {
                            method: 'POST', body: fd, credentials: 'same-origin'
                        });
                        if (resp.ok) {
                            const data = await resp.json();
                            this.conteo = data.no_leidas_restantes;
                        }
                    } catch (e) { console.error(e); }
                },

                init() {
                    // Auto-refresh cada 60 segundos
                    setInterval(() => { if (!this.abierto) this.cargar(); }, 60000);
                }
            }
        }
        </script>

        <!-- Contenido scrolleable -->
        <main class="flex-1 overflow-y-auto">

            <!-- Mensajes flash como toasts flotantes -->
            <?php if (!empty($mensajes_flash)): ?>
            <div class="fixed top-4 right-4 z-[90] space-y-2 w-full max-w-sm pointer-events-none no-print">
                <?php foreach ($mensajes_flash as $idx => $f):
                    $estilos = [
                        'success' => 'bg-white border-emerald-300 text-emerald-800',
                        'error'   => 'bg-white border-bacal-300 text-bacal-800',
                        'warning' => 'bg-white border-amber-300 text-amber-800',
                        'info'    => 'bg-white border-blue-300 text-blue-800',
                    ];
                    $estilo = $estilos[$f['tipo']] ?? $estilos['info'];
                    $iconos = ['success' => 'check-circle-2', 'error' => 'alert-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
                    $icono = $iconos[$f['tipo']] ?? 'info';
                    $colores_icono = [
                        'success' => 'text-emerald-600',
                        'error'   => 'text-bacal-600',
                        'warning' => 'text-amber-600',
                        'info'    => 'text-blue-600',
                    ];
                    $color_icono = $colores_icono[$f['tipo']] ?? 'text-blue-600';
                ?>
                <div class="border-l-4 rounded-lg shadow-lg px-4 py-3 flex items-start gap-3 animate-slide-up pointer-events-auto <?= $estilo ?>"
                     x-data="{ visible: true }"
                     x-show="visible"
                     x-init="setTimeout(() => visible = false, 5000)"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 translate-x-0"
                     x-transition:leave-end="opacity-0 translate-x-4">
                    <i data-lucide="<?= $icono ?>" class="w-5 h-5 flex-shrink-0 mt-0.5 <?= $color_icono ?>"></i>
                    <div class="text-sm flex-1 text-zinc-800 font-medium"><?= e($f['mensaje']) ?></div>
                    <button @click="visible = false" class="text-zinc-400 hover:text-zinc-700 p-0.5">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Aquí va el contenido de cada página -->
            <div class="p-4 md:p-6">
