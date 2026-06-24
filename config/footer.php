            </div>

            <!-- Firma del desarrollador -->
            <div class="px-6 pb-4 text-center text-[11px] text-zinc-400 dark:text-zinc-600 select-none">
                Desarrollado por <span class="font-mono font-semibold tracking-tight text-zinc-500 dark:text-zinc-400">&lt;LFRC/&gt;</span>
            </div>
        </main>
    </div>
</div>


<script>
    // Inicializar íconos de Lucide al cargar el DOM
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) lucide.createIcons();
    });

    // Reinicializar íconos cuando Alpine muestre/oculte elementos
    document.addEventListener('alpine:initialized', () => {
        if (window.lucide) lucide.createIcons();
    });

    // Por si lucide carga después que el DOMContentLoaded
    window.addEventListener('load', () => {
        if (window.lucide) lucide.createIcons();
    });
</script>

</body>
</html>
