# Revisión técnica — 27 de octubre de 2026

## Alcance

Revisión de rutas, autorización en controladores, registro y perfil, campeonatos,
partidos, nóminas, incidencias, suspensiones, tablas, imágenes, PDF y WhatsApp.
Se ejecutó toda la suite existente y se añadieron seis pruebas de regresión.
Se conservaron los cambios previos y las copias de código de `output/`.
Las pruebas usan SQLite en memoria; no se ejecutaron migraciones en la base real.

## Correcciones

- **Nóminas aprobadas:** el formulario impedía editarlas al delegado, pero el
  endpoint de guardado no aplicaba esa restricción. Ahora comprueba aprobación
  y estado del partido dentro de la transacción, bloqueando la fila del partido.
- **Integridad de partidos:** se impide cambiar campeonato o participantes cuando
  hay fase, nóminas o incidencias. Sigue siendo posible modificar hora y cancha.
  Esto evita mezclar la planilla o las llaves con otros equipos.
- **Hora inválida:** se valida `H:i` antes de persistir o generar notificaciones.
- **Suspensiones:** se considera la hora al ordenar encuentros del mismo día;
  anteriormente el orden de creación podía liberar o suspender incorrectamente.
- **WhatsAppChannel:** admite notificaciones que solo implementan plantilla,
  aplica valores predeterminados a sus parámetros opcionales, comprueba activación
  y consentimiento, y no falla al registrar un error de un destinatario sin `getKey()`.
- **Vista pública:** se restauraron nombre, portada, organizador, fechas y estado
  del campeonato. Los valores se imprimen escapados mediante Blade.
- **Prueba desactualizada:** la aprobación esperaba que toda edición posterior
  devolviera 403, contradiciendo la edición informativa implementada y probada
  en `ChampionshipRulesTest`. Se actualizó esa expectativa y se comprueba que
  la aprobación se conserva.

## Verificación

- Inicial: 110 pruebas, 105 correctas y 5 fallidas.
- Final: **116 pruebas correctas, 1.053 aserciones** ejecutando:
  `php -d extension=gd vendor/phpunit/phpunit/phpunit`.
- `npm.cmd run build`: correcto; advertencias de obsolescencia de Sass/Bootstrap.
- Sintaxis PHP: correcta en `app`, `routes`, `database`, `config` y `tests`.
- `php artisan route:list --except-vendor --json`: 68 rutas registradas.
- `git diff --check`: detecta espacios/finales de archivo en cambios existentes;
  no se realizó una limpieza general del trabajo previo.

## Pendientes y límites

1. **GD está desactivado en el PHP de XAMPP.** El PDF con imágenes falla sin esa
   extensión, ya declarada como requisito en Composer. La biblioteca existe en
   `C:\xampp\php\ext\php_gd.dll`. Habilitar `extension=gd` en
   `C:\xampp\php\php.ini` y reiniciar Apache permite aplicar permanentemente
   la configuración usada en las pruebas. Esta revisión no cambió configuración
   global fuera del proyecto.
2. Las advertencias Sass no impiden compilar. Su actualización requiere comprobar
   compatibilidad visual; no se sustituyeron dependencias ni se ocultaron avisos.
3. No se enviaron mensajes reales a Meta. Las pruebas de WhatsApp usan respuestas
   simuladas; no certifican credenciales, aprobación de plantillas ni entrega real.
4. No se hizo una prueba manual de navegador, de carga concurrente ni una auditoría
   de vulnerabilidades de dependencias. Los resultados no certifican ausencia total
   de errores ni reproducen las diferencias de MySQL respecto de SQLite.
5. Se conservaron migraciones históricas y generadores de formatos anteriores:
   todavía tienen referencias o sirven para instalaciones y datos existentes.
   Su antigüedad no demuestra que sean código inutilizable.
