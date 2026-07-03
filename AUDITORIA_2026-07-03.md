# Auditoría completa — Sistema Ley Studio
**Fecha:** 3 de julio de 2026 · **Alcance:** sistema/index.html, ficha.html, api/enviar-correo.php, reglas de Firebase, .htaccess

---

## 1. Corregido en esta auditoría (commit `6e869b7`)

### Seguridad
- **[CRÍTICO] Ficha pública escribible por cualquiera.** La regla de Firebase permitía a quien tuviera (o adivinara) un enlace de ficha **leer y sobrescribir todo el nodo** de la clienta, incluyendo nombre y teléfono. Nueva regla: el público solo puede escribir `ficha`, `correo` e `ig`; los datos de identidad solo los escribe el sistema. ⚠️ **Requiere acción manual — ver sección 2.**
- **[CRÍTICO] XSS almacenado.** Lo que una clienta escribe en su ficha pública (observaciones, música, snack, correo) se mostraba en el panel de administración **sin escapar HTML** — un texto malicioso podía ejecutar código con tu sesión y robar toda la base. Ahora todo dato de la ficha pública se escapa antes de mostrarse.
- **[ALTO] Cualquier cuenta Firebase podía leer los datos.** La regla era `auth != null`: bastaba con auto-registrarse en el proyecto. Ahora solo los dos correos autorizados (los mismos del envío de correo) pueden leer/escribir. ⚠️ Además hay que **deshabilitar el registro público** en Firebase (ver sección 2).
- `firebase_rules.json` y `leystudio_demo_data.json` eran descargables desde el sitio — bloqueados en `.htaccess`.

### Bugs
- **Gráfico "Métodos de pago" del dashboard roto**: llamaba a una función inexistente (`fInRng`) — lanzaba error cada vez que se abría el dashboard. Corregido a `inThisMo`.
- **Zona horaria (UTC-6)**: los movimientos del **día 1 de cada mes** se contaban en el mes anterior en el dashboard y el gráfico de 6 meses. Corregido.
- **Login pisaba datos locales**: al iniciar sesión se reemplazaba todo el estado local por el de la nube sin merge (otra vía de pérdida de datos, además de la corregida ayer). Ahora usa el merge seguro.
- **Actualización de fichas borraba tokens ajenos**: el sistema subía el espejo público completo con PUT; si otro dispositivo había creado una clienta nueva, su ficha se borraba. Ahora usa PATCH.
- **IDs con colisión**: los IDs eran consecutivos (máximo + 1); dos dispositivos creando registros sin sincronizar generaban el mismo ID y el merge descartaba uno. Ahora los IDs son únicos (timestamp).
- Función `delCustomSvc` duplicada (código muerto) — eliminada.

### Ya corregido ayer (contexto)
- Sincronización por registro con marcas de tiempo y lápidas (fin de la pérdida de datos entre dispositivos).
- Timeout de red de 15 s (fin del "guardar queda pegado").
- Cancelar/borrar cita elimina su ingreso ligado.
- Errores JS visibles en pantalla; guardia contra subir estado vacío.

---

## 2. ⚠️ Acciones manuales pendientes (10 minutos, hazlas hoy)

1. **Publicar las nuevas reglas de Firebase**: Firebase Console → Realtime Database → Rules → pegar el contenido de `firebase_rules.json` (ya actualizado en el repo) → Publish.
2. **Deshabilitar el registro público**: Firebase Console → Authentication → Sign-in method → Email/Password → desactivar "Permitir que los usuarios se registren" (o en Settings → User actions, marcar que solo administradores crean usuarios).
3. `git push` para publicar el código corregido, y que todo el equipo recargue con Cmd/Ctrl+Shift+R.

---

## 3. Riesgos conocidos que quedan (no urgentes, decidir después)

- **Usuarios locales con contraseña reversible.** La lista de usuarios de Config guarda contraseñas con codificación reversible (no es un hash) y viaja a la nube. Hoy **no se usan para autenticar** (el login real es Firebase), son solo para rol/saludo. Recomendación: eliminar el campo contraseña de esa pantalla.
- **Citas ligadas a clientas por nombre exacto.** Si renombras una clienta, sus citas viejas dejan de contar en sus estadísticas. Recomendación futura: ligar por ID.
- **Fotos en base64 dentro del estado.** Cada foto de clienta/servicio viaja completa en cada sincronización y consume la cuota de localStorage (~5 MB). Funciona hoy, pero con decenas de fotos se degradará. Recomendación futura: subir fotos a Firebase Storage y guardar solo la URL.
- **Sin respaldos automáticos.** Existe exportación manual, pero nadie la ejecuta. Ver sugerencia #1 abajo.

---

## 4. Sugerencias de mejora (priorizadas)

### Ganancias rápidas
1. **Respaldo automático semanal**: guardar un snapshot fechado en un nodo `/backups` de Firebase (se puede automatizar en el propio sistema al iniciar sesión los lunes). Ante cualquier accidente, restauras con un clic.
2. **Felicitación de cumpleaños automática**: el sistema ya sabe los cumpleaños y tiene la plantilla 🎂 — hoy hay que enviarla a mano. Sugerir el envío al abrir el sistema el día del cumpleaños (un clic para mandar).
3. **Validaciones de formularios**: impedir montos negativos, stock decimal donde no aplica, citas duplicadas (misma clienta, fecha y hora), y normalizar teléfonos a un solo formato.
4. **Crear clienta desde la cita**: si escribes un nombre nuevo al agendar, ofrecer crearla como clienta automáticamente (hoy son dos pasos separados).

### Mediano plazo
5. **Recordatorios de cita automáticos**: hoy el botón "Enviar todos" es manual. Un aviso al abrir el sistema ("hay 3 recordatorios de mañana sin enviar, ¿enviar ahora?") reduce no-shows.
6. **Reactivación automática de inactivas**: campaña mensual sugerida para clientas con +30 días sin visita (los datos ya existen, la plantilla ya existe).
7. **Explotar datos que ya capturas y no se usan**: origen de la cita (¿de dónde llegan las clientas?), duración de servicios (agenda con horas reales de ocupación), categorías de servicio (qué familia deja más ingreso), preferencias de la ficha (música/snack para experiencia VIP).
8. **Agenda con disponibilidad real**: el calendario muestra citas pero no bloques de horario ni duración — con la duración de los servicios se puede evitar sobre-agendar.

### Más adelante
9. **Auto-reserva para clientas**: una página pública (como la ficha) donde la clienta elige servicio y horario disponible, y a ti te llega como cita "Por confirmar".
10. **Fotos a Firebase Storage** (ver riesgo de base64 arriba).
11. **Cierre de caja diario**: resumen del día (ingresos por método de pago, pendientes de cobro) con un botón "cerrar día" que genera un PDF con la identidad de la marca — igual que la proforma.
12. **Roles reales**: hoy todos los usuarios ven todo; con roles, una asistente podría gestionar citas sin ver finanzas.
