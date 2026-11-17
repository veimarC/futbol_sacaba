# Revisión para la defensa — 5 de noviembre de 2026

## Resultado comprobado

- `php artisan test --compact`: **121 pruebas correctas, 1.185 aserciones**.
- Sintaxis: 134 archivos PHP revisados en app, routes, database, config y tests, sin errores.
- `npm.cmd run build`: compilación correcta. Persisten avisos de obsolescencia de Sass/Bootstrap, sin impedir la compilación.
- `php artisan view:cache`: vistas compiladas correctamente.
- `php artisan migrate:status`: todas las migraciones aplicadas. No se ejecutaron migraciones ni se modificaron los datos deportivos reales.
- GD disponible en PHP CLI; pruebas de PDF con imágenes correctas.
- `public/storage` apunta a `storage/app/public`.
- Apache: respuestas HTTP 200 en inicio, ingreso, registro y detalle de los siete campeonatos publicados (5, 6, 7, 9, 15, 18 y 19).
- Los tres archivos compilados CSS/JavaScript del manifiesto responden HTTP 200.
- El reglamento PDF rechaza al visitante sin sesión con 403, conforme a la política que reserva la descarga al organizador creador.
- MySQL: cero transacciones activas al consultar; cero trabajos pendientes y cero trabajos fallidos en las tablas de cola.

## Cambio realizado

La prueba de recuperación de un campeonato inexistente esperaba la antigua clave de sesión `mensaje`. La aplicación ya usa `info` y muestra una alerta informativa. Se actualizó la prueba y se añadieron verificaciones de que el mensaje y la alerta se rendericen en el listado. Se conservaron los cambios previos del proyecto.

## Límites y puntos que debes conocer

- Las pruebas funcionales usan SQLite en memoria. Las comprobaciones HTTP públicas sí usaron Apache y la base real. No se realizó un recorrido visual interactivo completo con cada rol ni una prueba de concurrencia.
- El registro contiene un error MySQL de espera de bloqueo al guardar una sesión el 5 de noviembre. No se reprodujo en esta revisión y no había transacciones activas al consultar. La causa original no quedó determinada; no se cerraron procesos ni se alteraron sesiones.
- `MAIL_MAILER=log`: la recuperación de contraseña genera el correo en el registro, pero no lo entrega a una bandeja real. Para demostrar entrega real hace falta configurar un proveedor de correo.
- WhatsApp está habilitado en la configuración local, pero no se enviaron mensajes reales durante la revisión. Las pruebas simuladas no certifican credenciales, plantillas ni entrega de Meta.
- Los iconos Bootstrap se descargan desde un CDN. Sin internet pueden faltar iconos aunque el CSS y JavaScript principales sean locales.
- Los recordatorios programados requieren un planificador activo (`php artisan schedule:work` en una terminal durante la demostración, si se desea ejecutar los recordatorios configurados). Ese comando puede enviar mensajes reales cuando existan destinatarios habilitados.

## Ensayo recomendado antes de presentar

1. Inicia Apache y MySQL en XAMPP y abre `http://localhost/plataforma-futbol-sacaba/public/`.
2. Usa cuentas de demostración de organizador, delegado y administrador; ensaya el acceso con cada una.
3. En un campeonato de demostración, recorre creación, envío a revisión y aprobación; luego inscripción y aprobación de equipos y jugadores.
4. Genera el fixture, programa un partido, presenta y aprueba las nóminas, registra incidencias y finaliza el resultado. Comprueba la tabla o la siguiente ronda.
5. Descarga las planillas y reglamento con el rol autorizado, abre los PDF y verifica fotos y escudos en el navegador de la presentación.
6. Comprueba el resultado desde una ventana sin sesión y ensaya los casos de validación: campos vacíos, permisos insuficientes y campeonato inexistente.
7. Conserva una copia de la base de datos y de `storage/app/public` antes del ensayo. Evita hacer operaciones de demostración sobre campeonatos que necesites conservar intactos.

Estos resultados reducen el riesgo de fallos, pero no constituyen una garantía de ausencia total de errores.
