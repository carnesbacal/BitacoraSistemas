# 📋 Bitácora de Sistemas — Carnes Bacal

Sistema integral de gestión de incidencias, equipos, mantenimientos y comunicación interna para el departamento de Sistemas de **Carnes Bacal** (Tijuana).

---

## 🎯 ¿Qué hace?

Esta es la plataforma central donde el equipo de Sistemas registra y da seguimiento a todo lo que pasa con la infraestructura tecnológica de las sucursales:

- **Incidencias** — fallas, solicitudes, instalaciones, configuraciones
- **Equipos** — inventario completo con depreciación, mantenimientos, fotos y transferencias
- **Mantenimientos preventivos** — calendarizados y recurrentes
- **Comunicación interna** — anuncios, recordatorios, menciones, reacciones
- **Reportes** — KPIs, tendencias, análisis por sucursal/categoría/severidad
- **Mapa físico** — ubicación de equipos sobre planos de cada planta

---

## 🚀 Stack técnico

| Capa | Tecnología |
|---|---|
| **Backend** | PHP 8.1+ (sin frameworks pesados) |
| **Base de datos** | MySQL 5.7+ / MariaDB 10.4+ |
| **Frontend** | Tailwind CSS (vía CDN) + Alpine.js 3 + Chart.js |
| **Iconos** | Lucide |
| **Tipografías** | Inter + Bricolage Grotesque |
| **Servidor local** | XAMPP (Apache + MySQL + PHP) |
| **Acceso remoto** | Tailscale (red privada zero-config) |

---

## 📂 Estructura del proyecto

```
BitacoraSistemas/
├── api/                    # Endpoints AJAX (JSON)
├── admin/                  # Páginas administrativas
├── backups/                # Backups automáticos de BD (protegido)
├── config/                 # Configuración, helpers, header/footer compartidos
├── cron/                   # Scripts programados
├── uploads/                # Archivos subidos
│   ├── avatares/           # Fotos de usuarios
│   ├── adjuntos/           # Archivos de incidencias
│   ├── equipo_fotos/       # Galería de equipos
│   └── planos/             # Planos de plantas
├── dashboard.php           # Página principal
├── bitacora.php            # Lista de incidencias
├── incidencia_nueva.php    # Crear incidencia
├── incidencia_ver.php      # Ver detalle (con comentarios, reacciones)
├── equipos.php             # Inventario
├── mantenimientos.php      # Calendario de mantenimientos
├── mapa_sucursal.php       # Mapa físico multi-planta
├── reportes.php            # Reportes con gráficas
├── kb.php                  # Base de conocimiento
└── ...
```

---

## ⚙️ Setup inicial

### 1. Requisitos

- **XAMPP** con PHP 8.1 o superior
- **MySQL** o MariaDB
- 1 GB de disco para archivos subidos (mínimo)
- Recomendado: SSD para mejor rendimiento

### 2. Instalación

1. **Copia el proyecto** a `C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\`

2. **Crea la BD** en phpMyAdmin: `CREATE DATABASE carnes_bacal CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;`

3. **Ejecuta los SQL** en orden (todos los `carnes_bacal_faseN.sql` que se hayan acumulado)

4. **Configura conexión** en `config/db.php` si tus credenciales de MySQL son distintas a `root` sin contraseña

5. **Crea las carpetas de uploads** con permisos de escritura:
   ```
   uploads/avatares/
   uploads/adjuntos/
   uploads/equipo_fotos/
   uploads/planos/
   backups/
   ```
   En cada una pon un `.htaccess`:
   ```apache
   <FilesMatch "\.(php|phtml|phar)$">
       Require all denied
   </FilesMatch>
   Options -Indexes
   ```

6. **Configura PHP** en `C:\xampp\php\php.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   max_file_uploads = 20
   ```
   Reinicia Apache.

7. **Detecta mysqldump** (opcional, para backups):
   Verifica que existe `C:\xampp\mysql\bin\mysqldump.exe`

### 3. Primer login

Usuario inicial:
- Login: `admin`
- Contraseña: `admin123`

**Cámbiala inmediatamente** en "Mi perfil → Cambiar contraseña".

### 4. Configurar tareas programadas (Windows Task Scheduler)

Crea 3 tareas:

| Tarea | Frecuencia | Argumentos |
|---|---|---|
| Backup diario | Diaria 2:00 AM | `"...\cron\backup_diario.php"` |
| Notificar mantenimientos | Diaria 8:00 AM | `"...\cron\notificar_mantenimientos.php"` |
| Archivar antiguas | Diaria 3:00 AM | `"...\cron\archivar_automatico.php"` |
| Enviar recordatorios | Cada 5 minutos | `"...\cron\enviar_recordatorios.php"` |

Programa: `C:\xampp\php\php.exe`

---

## 🔑 Roles y permisos

| Rol | Puede |
|---|---|
| **admin** | Todo |
| **ingeniero** | Ver/crear/resolver incidencias, gestionar equipos, mantenimientos, reportes |
| **gerente** | Ver todas las sucursales, crear solicitudes, ver reportes |
| **jefe_area** | Ver su sucursal, crear solicitudes, reportar fallas |

---

## ⌨️ Atajos de teclado

| Atajo | Acción |
|---|---|
| `Ctrl + K` | Búsqueda global |
| `Ctrl + N` | Nueva incidencia |
| `/` | Búsqueda (alternativa) |
| `?` | Mostrar todos los atajos |
| `g d` | Ir al Dashboard |
| `g b` | Ir a Bitácora |
| `g m` | Ir a Mantenimientos |
| `g e` | Ir a Equipos |
| `Esc` | Cerrar modal abierto |

---

## 🎨 Personalización

### Modo oscuro
Click en avatar (esquina superior derecha) → Tema: Auto / Claro / Oscuro
- **Auto** sigue la configuración del SO
- Se persiste en BD y se sincroniza entre dispositivos

### Branding
- Rojo corporativo: `#C8102E`
- Dorado: `#E8B923`
- Configurable en `config/header.php` dentro de `tailwind.config`

---

## 🛡️ Seguridad

- **CSRF tokens** en todos los formularios POST
- **Password hash** con `password_hash()` (PHP nativo)
- **Sesiones** con tracking de IP, navegador, dispositivo
- **Cierre remoto** de sesiones desde Mi Perfil
- **Auditoría** completa de acciones administrativas
- **Backups** automáticos diarios cifrados a nivel de carpeta

### Sesiones inválidas
Si después de actualizar el código las sesiones existentes se vuelven raras, incrementa `SESSION_VERSION` en `config/auth.php`. Eso fuerza un re-login limpio.

---

## 🔧 Mantenimiento

### Backups
- Automáticos diarios en `backups/`
- Manual desde Admin → Backups
- Restauración: importa el `.sql` desde phpMyAdmin

### Limpieza
- Incidencias resueltas hace >1 año se archivan automáticamente (no se eliminan)
- Logs de auditoría se mantienen indefinidamente
- Las sesiones cerradas no se purgan automáticamente (hacerlo manualmente si crecen mucho)

### Logs de errores
- PHP: `C:\xampp\php\logs\php_error_log`
- Apache: `C:\xampp\apache\logs\error.log`

---

## 🌐 Acceso remoto

Configurado con **Tailscale**: cada PC autorizada en la tailnet puede acceder al sistema vía la IP Tailscale de la PC anfitriona.

Para que las URLs en notificaciones y enlaces funcionen desde cualquier nodo de la tailnet, el sistema usa **rutas relativas** internamente. El navegador construye la URL absoluta con el host actual.

---

## 📊 Módulos principales

### Bitácora de incidencias
- Listado con filtros avanzados (búsqueda, fechas, severidad, estado, asignado, equipo, SLA, archivadas)
- Vista tabla / kanban
- Crear con sugerencias en vivo (plantillas, soluciones de KB, técnico menos cargado, categoría)
- Auto-asignación por reglas configurables
- Comentarios con menciones `@usuario` (autocompletado + notificación)
- Reacciones a comentarios con 6 emojis
- Adjuntos
- Reincidencias detectadas automáticamente
- Archivado automático >1 año

### Equipos
- Inventario completo
- 5 tabs por equipo: info+depreciación / fotos / mantenimientos / transferencias / historial
- Galería con hasta 20 fotos por equipo
- Transferencias entre sucursales con historial
- Estados: en uso, en mantenimiento, baja
- Depreciación lineal con valor de rescate 10%
- Indicador de "equipo problemático" (umbrales 2/3/4 fallas en 30 días, 6 en 90 días)

### Mantenimientos
- Calendario mensual
- Tipos: preventivo, correctivo, calibración, limpieza, inspección
- Recurrencia configurable (días/semanas/meses/años)
- Generación automática del siguiente recurrente al completar
- Conversión a incidencia con un click
- Notificaciones de próximos (7d, 1d) y vencidos

### Comunicación
- Tablero de anuncios con vigencia y audiencia segmentada (sucursal/rol)
- Recordatorios personales programados con cron de envío
- Menciones `@usuario` en comentarios con notificación
- Reacciones con emojis: 👍 ✓ 🔧 ❤️ 😄 👀

### Mapa físico
- Multi-planta por sucursal (planta baja, piso 1, bodega, etc.)
- Subir plano por planta (imagen)
- Drag & drop de equipos sobre el plano (admin)
- Pins coloreados según estado y problemas
- Tooltip al hover con info rápida

### Reportes
- 5 reportes con gráficas Chart.js
- Imprimibles a PDF
- KPIs con sparklines de tendencia 7 días en dashboard

### Inteligencia
- Sugerencias de plantillas, soluciones y técnicos al crear incidencia
- Sugerencia automática de categoría según palabras clave
- Auto-asignación por reglas (palabras clave → técnico)
- Detección de equipos problemáticos

### Administración
- Catálogos (sucursales, áreas, categorías, tipos, severidades, estados, orígenes)
- Usuarios con avatar y sesiones activas
- Plantillas de incidencias
- Base de conocimiento (artículos con búsqueda)
- Importación masiva CSV (usuarios, equipos, incidencias)
- Backups y descarga
- Auditoría completa
- Anuncios, reglas, organización (archivado + palabras clave)

---

## 💡 Búsqueda global

`Ctrl + K` busca simultáneamente en:
- Incidencias (folio, título, descripción)
- Equipos (código, nombre, número de serie)
- Usuarios (login, nombre, email) — solo admin
- Base de conocimiento (título, resumen, contenido)

Resultados agrupados, navegables con teclado.

---

## 🐛 Solución de problemas comunes

### "ERR_CONNECTION_REFUSED" desde otra PC en la red
- Verifica que Tailscale esté corriendo en ambas PCs
- Usa la IP Tailscale, no `localhost`
- Confirma que el firewall de Windows permite Apache en el puerto 80

### Notificaciones llevan a 404
Las notificaciones viejas tenían URL absoluta con `localhost`. Se solucionó con `url_relativa()`. Si quedan algunas viejas, ejecuta el script `fix_urls_localhost.sql`.

### El modo oscuro no se aplica en algún componente
El sistema usa overrides CSS con `!important` para mapear clases de Tailwind. Si un componente nuevo usa colores hardcodeados, agregar el override en `config/header.php`.

### Apache no permite subir archivos grandes
Ajusta `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
```

### Las sesiones se cierran al actualizar código
Si cambiamos la estructura de `$_SESSION['usuario']`, incrementa `SESSION_VERSION` en `config/auth.php`. Esto fuerza re-login con la nueva estructura.

---

## 📞 Soporte

Para reportar problemas, sugerir mejoras o consultar dudas técnicas, contactar al administrador del sistema.

---

## 📝 Licencia

Sistema interno propietario de **Carnes Bacal**. Uso restringido al personal autorizado.

---

_Última actualización: Fase 18 (UX Polish) — 2026_
