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
