<?php
/**
 * ============================================================================
 * vault.php - Página principal de la Bóveda
 * ============================================================================
 * Listado agrupado por familia y categoría, con filtros, búsqueda y favoritos.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/vault_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = vault_puede_administrar();

// Filtros
$f_busqueda = trim((string) input('q', ''));
$f_categoria = (int) input('categoria_id', 0);
$f_familia = (string) input('familia', '');
$f_favoritos = (int) input('favoritos', 0);

$filtros = [
    'busqueda' => $f_busqueda ?: null,
    'categoria_id' => $f_categoria ?: null,
    'familia' => $f_familia ?: null,
    'solo_favoritos' => $f_favoritos === 1,
];

// Datos
$entradas = vault_listar_entradas($u, $filtros);
$stats = vault_stats($u);
$categorias_agrupadas = vault_categorias_agrupadas();

// Agrupar entradas por familia para mostrar en secciones
$entradas_por_familia = [];
foreach ($entradas as $e) {
    $f = $e['familia'];
    if (!isset($entradas_por_familia[$f])) {
        $entradas_por_familia[$f] = [];
    }
    $entradas_por_familia[$f][] = $e;
}

$hay_filtros = !empty($f_busqueda) || $f_categoria || !empty($f_familia) || $f_favoritos;

$titulo_pagina = 'Bóveda';
$pagina_activa = 'vault';
require_once __DIR__ . '/config/header.php';
?>

<div class="animate-fade-in space-y-4"
     x-data="vaultListado()">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 flex items-center gap-2">
                <i data-lucide="shield" class="w-6 h-6 text-bacal-700"></i>
                Bóveda
            </h2>
            <p class="text-xs text-zinc-500 mt-0.5">
                Credenciales, accesos, instaladores, manuales y configuraciones del depto. de Sistemas.
            </p>
        </div>

        <?php if ($es_admin): ?>
        <a href="<?= url('vault_entrada.php?accion=nuevo') ?>"
           class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nueva entrada
        </a>
        <?php endif; ?>
    </div>

    <!-- KPIs rápidos -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Total entradas</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) $stats['total'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-amber-700 uppercase tracking-wider font-bold mb-1">Favoritos</div>
            <div class="font-display text-2xl font-extrabold text-amber-700"><?= (int) $stats['favoritos'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Familias</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($categorias_agrupadas) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Categorías</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900">
                <?= array_sum(array_map(fn($f) => count($f['categorias']), $categorias_agrupadas)) ?>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-3">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <!-- Búsqueda -->
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
                <input type="text" name="q" value="<?= e($f_busqueda) ?>"
                       placeholder="Buscar por nombre, usuario, tag, notas…"
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <!-- Familia -->
            <select name="familia" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                <option value="">Todas las familias</option>
                <?php foreach ($categorias_agrupadas as $fam): ?>
                <option value="<?= e($fam['familia']) ?>" <?= $f_familia === $fam['familia'] ? 'selected' : '' ?>>
                    <?= e($fam['familia']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Favoritos -->
            <label class="px-3 py-2 rounded-lg border text-sm font-medium cursor-pointer flex items-center gap-1.5 transition-colors
                          <?= $f_favoritos ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' ?>">
                <input type="checkbox" name="favoritos" value="1" <?= $f_favoritos ? 'checked' : '' ?>
                       onchange="this.form.submit()" class="hidden">
                <i data-lucide="star" class="w-4 h-4 <?= $f_favoritos ? 'fill-amber-500 text-amber-500' : '' ?>"></i>
                Favoritos
            </label>

            <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                Buscar
            </button>

            <?php if ($hay_filtros): ?>
            <a href="<?= url('vault.php') ?>" class="px-3 py-2 text-sm text-zinc-600 hover:text-bacal-700 font-medium">
                Limpiar
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Sidebar de categorías (en desktop) -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        <!-- Panel de categorías -->
        <aside class="lg:col-span-1 space-y-3 order-2 lg:order-1">
            <?php foreach ($stats['por_categoria'] as $i => $cat):
                $key_fam = $cat['familia'];
                $first_of_fam = ($i === 0 || $stats['por_categoria'][$i-1]['familia'] !== $key_fam);
            ?>
                <?php if ($first_of_fam): ?>
                <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-zinc-50 border-b border-zinc-100">
                        <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?= e($key_fam) ?></div>
                    </div>
                    <div>
                <?php endif; ?>

                <a href="<?= url('vault.php?categoria_id=' . $cat['id']) ?>"
                   class="flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-zinc-50 border-b border-zinc-100 last:border-b-0
                          <?= $f_categoria === (int) $cat['id'] ? 'bg-bacal-50 border-l-2 border-l-bacal-700' : '' ?>">
                    <i data-lucide="<?= e($cat['icono']) ?>" class="w-3.5 h-3.5 flex-shrink-0" style="color: <?= e($cat['color']) ?>"></i>
                    <span class="flex-1 text-zinc-700 truncate"><?= e($cat['nombre']) ?></span>
                    <?php if ((int) $cat['total'] > 0): ?>
                    <span class="text-[10px] font-bold text-zinc-500 bg-white px-1.5 py-0.5 rounded"><?= (int) $cat['total'] ?></span>
                    <?php endif; ?>
                </a>

                <?php $last_of_fam = ($i === count($stats['por_categoria']) - 1 || $stats['por_categoria'][$i+1]['familia'] !== $key_fam); ?>
                <?php if ($last_of_fam): ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </aside>

        <!-- Listado principal -->
        <div class="lg:col-span-3 order-1 lg:order-2">
            <?php if (empty($entradas)): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-12 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    <i data-lucide="shield" class="w-8 h-8 text-zinc-400"></i>
                </div>
                <?php if ($hay_filtros): ?>
                <p class="text-sm font-semibold text-zinc-700 mb-1">No se encontraron resultados</p>
                <p class="text-xs text-zinc-500 mb-4">Ajusta los filtros o limpia la búsqueda.</p>
                <a href="<?= url('vault.php') ?>" class="text-xs font-semibold text-bacal-700 hover:underline">Limpiar filtros</a>
                <?php else: ?>
                <p class="text-sm font-semibold text-zinc-700 mb-1">La bóveda está vacía</p>
                <?php if ($es_admin): ?>
                <p class="text-xs text-zinc-500 mb-4">Crea la primera entrada con tus credenciales, manuales o configuraciones.</p>
                <a href="<?= url('vault_entrada.php?accion=nuevo') ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    <i data-lucide="plus" class="w-4 h-4"></i> Crear primera entrada
                </a>
                <?php else: ?>
                <p class="text-xs text-zinc-500">Aún no hay entradas que puedas ver.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <?php foreach ($entradas_por_familia as $familia => $items): ?>
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-2 px-1">
                        <h3 class="font-display text-sm font-bold text-zinc-900 uppercase tracking-wider"><?= e($familia) ?></h3>
                        <span class="text-[10px] font-normal text-zinc-500">· <?= count($items) ?></span>
                    </div>

                    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden divide-y divide-zinc-100">
                        <?php foreach ($items as $e):
                            $sens_color = match ($e['sensibilidad']) {
                                'critica' => '#DC2626',
                                'alta' => '#F59E0B',
                                default => null,
                            };
                            $sens_label = match ($e['sensibilidad']) {
                                'critica' => 'CRÍTICA',
                                'alta' => 'ALTA',
                                default => null,
                            };
                            $venc_proximo = false;
                            $venc_pasado = false;
                            if (!empty($e['vencimiento'])) {
                                $ts_v = strtotime($e['vencimiento']);
                                if ($ts_v < time()) $venc_pasado = true;
                                elseif ($ts_v < time() + (30 * 86400)) $venc_proximo = true;
                            }
                        ?>
                        <a href="<?= url('vault_entrada.php?id=' . $e['id']) ?>"
                           class="flex items-center gap-3 px-4 py-3 hover:bg-zinc-50 group transition-colors">
                            <!-- Icono de categoría -->
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                 style="background-color: <?= e($e['categoria_color']) ?>15">
                                <i data-lucide="<?= e($e['categoria_icono']) ?>" class="w-4 h-4" style="color: <?= e($e['categoria_color']) ?>"></i>
                            </div>

                            <!-- Info principal -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                    <span class="font-semibold text-sm text-zinc-900 truncate"><?= e($e['nombre']) ?></span>
                                    <?php if ((int) $e['es_favorito'] === 1): ?>
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-amber-400 text-amber-400 flex-shrink-0"></i>
                                    <?php endif; ?>
                                    <?php if ($sens_label): ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                                          style="color: <?= e($sens_color) ?>; background-color: <?= e($sens_color) ?>15">
                                        <?= e($sens_label) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ((int) $e['tiene_password'] === 1): ?>
                                    <i data-lucide="key-round" class="w-3 h-3 text-zinc-400" title="Tiene contraseña"></i>
                                    <?php endif; ?>
                                    <?php if ($venc_pasado): ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase bg-bacal-100 text-bacal-700">VENCIDO</span>
                                    <?php elseif ($venc_proximo): ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase bg-amber-100 text-amber-800">PRÓX. A VENCER</span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-x-3 gap-y-1 text-[11px] text-zinc-500 flex-wrap">
                                    <span class="font-medium text-zinc-600"><?= e($e['categoria_nombre']) ?></span>
                                    <?php if (!empty($e['usuario'])): ?>
                                    <span><i data-lucide="user" class="w-3 h-3 inline -mt-0.5"></i> <?= e($e['usuario']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($e['sucursal_codigo'])): ?>
                                    <span><i data-lucide="map-pin" class="w-3 h-3 inline -mt-0.5"></i> <?= e($e['sucursal_codigo']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($e['version_build'])): ?>
                                    <span><i data-lucide="tag" class="w-3 h-3 inline -mt-0.5"></i> v<?= e($e['version_build']) ?></span>
                                    <?php endif; ?>
                                    <span class="text-zinc-400">· actualizado <?= e(fmt_tiempo_relativo($e['actualizado_en'])) ?></span>
                                </div>
                            </div>

                            <!-- Quick favorito -->
                            <button @click.prevent="toggleFavorito(<?= (int) $e['id'] ?>, $event)"
                                    class="p-2 rounded hover:bg-zinc-100 text-zinc-400 hover:text-amber-500 flex-shrink-0"
                                    title="Marcar favorito">
                                <i data-lucide="star" class="w-4 h-4 <?= (int) $e['es_favorito'] === 1 ? 'fill-amber-400 text-amber-400' : '' ?>"></i>
                            </button>

                            <i data-lucide="chevron-right" class="w-4 h-4 text-zinc-300 flex-shrink-0 group-hover:text-zinc-500"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function vaultListado() {
    return {
        async toggleFavorito(entradaId, ev) {
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('entrada_id', entradaId);
                const resp = await fetch('<?= url('api/vault_favorito.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    // Recargar para reflejar el cambio en el estado del icono
                    location.reload();
                }
            } catch (e) { console.error(e); }
        }
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
