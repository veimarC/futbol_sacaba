# Guía de estudio — Plataforma Fútbol Sacaba

Esta guía explica cómo está organizado el proyecto y qué responsabilidad tiene cada parte. El proyecto usa **PHP 8.2**, **Laravel 12**, **Blade**, **Bootstrap**, JavaScript y una base de datos relacional.

## 1. La idea principal de Laravel

Laravel organiza la aplicación principalmente con el patrón MVC:

```text
Navegador
   ↓ petición HTTP
Ruta (routes/web.php)
   ↓ elige el método
Controlador (app/Http/Controllers)
   ↓ consulta o modifica
Modelo (app/Models) ↔ Base de datos
   ↓ entrega datos
Vista Blade (resources/views)
   ↓ genera HTML
Navegador
```

- **Modelo:** representa datos, relaciones y algunas reglas del dominio.
- **Vista:** presenta la información al usuario.
- **Controlador:** coordina la petición, validación, permisos, modelos y respuesta.
- **Ruta:** decide qué controlador atiende cada URL.

## 2. Cómo leer una funcionalidad completa

Para estudiar cualquier acción sigue este orden:

1. Busca su URL en `routes/web.php`.
2. Abre el método del controlador indicado por la ruta.
3. Revisa los modelos utilizados por ese método.
4. Abre la vista retornada mediante `view(...)`.
5. Busca su prueba en `tests/Feature/RecoveredWorkflowTest.php`.

Ejemplo para la postulación de un árbitro:

```text
POST /arbitro/campeonatos/{campeonato}/postular
  → CampeonatoArbitroController::solicitar()
  → relación Campeonato::arbitros()
  → tabla arbitro_campeonato
  → NotificacionPlataforma para el organizador
  → redirección a arbitros.panel
```

## 3. Rutas

### `routes/web.php`

Contiene las páginas web y acciones principales:

- portal público;
- autenticación;
- campeonatos;
- postulaciones de árbitros;
- equipos y solicitudes de inscripción;
- jugadores y aprobaciones;
- partidos y resultados;
- incidencias (goles y tarjetas);
- notificaciones.

Conceptos importantes:

```php
Route::get('/home', [HomeController::class, 'index'])->name('home');
```

- `get` es el método HTTP.
- `/home` es la URL.
- `HomeController::class` es el controlador.
- `index` es el método ejecutado.
- `home` es el nombre usado con `route('home')`.

Un `middleware` protege rutas antes de llegar al controlador. Por ejemplo, `role:arbitro` permite entrar solamente al árbitro.

### `routes/console.php`

Se reserva para comandos ejecutados desde la terminal. Actualmente conserva la estructura básica de Laravel.

## 4. Controladores propios

Los controladores reciben peticiones. No deberían encargarse del diseño visual.

### `CampeonatoArbitroController.php`

Gestiona el ciclo completo del árbitro:

- `explorar`: busca campeonatos abiertos.
- `panel`: muestra postulaciones y partidos asignados.
- `solicitar`: registra una postulación única.
- `gestionar`: permite al organizador aprobarla o rechazarla.
- `opciones`: devuelve árbitros aprobados en JSON para el formulario de partidos.
- `autorizarArbitro` y `autorizarOrganizador`: protegen las acciones sensibles.

### `CampeonatoController.php`

Gestiona el campeonato para usuarios autenticados:

- listado, creación, edición, detalle y eliminación;
- validación de reglas deportivas;
- autorización del propietario;
- generación del fixture según el formato;
- portada opcional y estado de inscripciones.

### `CampeonatoEliminatoriaController.php`

Toma los clasificados de los grupos terminados y crea los primeros cruces de eliminación.

### `EquipoController.php`

Gestiona equipos, escudos, pertenencia del delegado y solicitudes para entrar a campeonatos. También permite al organizador aprobar, rechazar o retirar un equipo sin borrar su historial.

### `JugadorController.php`

Gestiona jugadores y sus permisos. Valida:

- máximo de plantilla;
- CI no repetida dentro del mismo campeonato;
- edad permitida;
- sexo permitido;
- propiedad del equipo;
- aprobación o rechazo por el organizador.

### `PartidoController.php`

Programa y edita partidos, asigna árbitros aprobados y registra resultados. Comprueba que ambos equipos pertenezcan al campeonato y que solamente el organizador o árbitro autorizado pueda cargar el marcador.

### `IncidenciaController.php`

Registra hechos ocurridos en un partido: goles, tarjetas amarillas y tarjetas rojas. Esos registros alimentan goleadores y disciplina.

### `PortalPublicoController.php`

Construye las páginas que no necesitan iniciar sesión:

- buscador de campeonatos;
- información deportiva pública;
- partidos y tabla;
- goleadores y tarjetas;
- plantillas públicas;
- grupos y llaves eliminatorias;
- campeón y tercer puesto.

### `HomeController.php`

Prepara el panel inicial según el rol y muestra contadores o acciones pendientes.

### `NotificacionController.php`

Lista notificaciones, abre una notificación individual y permite marcar todas como leídas. Verifica que cada aviso pertenezca al usuario autenticado.

### `Auth/*`

Son los controladores de autenticación proporcionados por Laravel UI: registro, inicio de sesión, verificación y recuperación de contraseña.

## 5. Modelos y relaciones

Los modelos están en `app/Models` y representan tablas.

### `User`

Representa usuarios y define los roles:

- desarrollador;
- organizador;
- delegado;
- árbitro.

Relaciones importantes:

- campeonatos donde se postuló como árbitro;
- partidos que tiene asignados.

### `Campeonato`

Guarda fechas, formato, reglas de plantilla, modalidad, nivel, categoría, sexo y portada.

Relaciones:

- muchos equipos mediante `campeonato_equipo`;
- muchas fases;
- muchos partidos;
- muchos árbitros mediante `arbitro_campeonato`.

### `Equipo`

Guarda nombre, escudo, ubicación y delegado. Se relaciona con jugadores y campeonatos.

### `Jugador`

Pertenece a un equipo y contiene datos personales y deportivos. Su estado puede ser pendiente, aprobado o rechazado.

### `Partido`

Pertenece a un campeonato y opcionalmente a una fase. Relaciona equipo local, visitante y árbitro. Guarda fecha, cancha, estado y marcador.

### `Fase`

Agrupa partidos por etapa: liga, grupo o eliminación. El campo `metadata` conserva configuración específica en formato JSON.

### `Incidencia`

Relaciona un jugador con un partido y un evento deportivo: gol, amarilla o roja.

## 6. Tablas intermedias

Una relación muchos-a-muchos necesita una tabla intermedia:

```text
campeonatos ← campeonato_equipo → equipos
campeonatos ← arbitro_campeonato → users
```

`campeonato_equipo` no solo une IDs: guarda aprobación, retiro, motivo y responsable.

`arbitro_campeonato` guarda estado de postulación, mensajes, respuesta del organizador y fechas de gestión.

En Eloquent, los datos adicionales de estas tablas se consultan con `pivot`:

```php
$campeonato->pivot->estado
```

## 7. Servicios

Los servicios contienen lógica que no conviene mezclar con el controlador.

### `TablaPosicionesService.php`

Calcula partidos jugados, victorias, empates, derrotas, goles, diferencia y puntos. Luego ordena la tabla.

### `FormatGenerators/FormatGeneratorInterface.php`

Define el contrato que deben cumplir los generadores de fixture.

### `LigaGenerator.php`

Genera un todos-contra-todos de una vuelta.

### `LigaIdaVueltaGenerator.php`

Genera los mismos cruces con localías invertidas para disputar ida y vuelta.

### `EliminacionDirectaGenerator.php`

Crea el tamaño inicial de la llave, mezcla equipos, empareja participantes y contempla pases libres cuando la cantidad no es potencia de dos.

### `GruposEliminacionGenerator.php`

Distribuye equipos en grupos, genera sus partidos y deja preparada la transición hacia eliminación.

## 8. Seguridad y permisos

### `CheckRole.php`

Middleware que comprueba si el rol actual está permitido en una ruta.

### `CampeonatoPolicy.php`

Centraliza permisos relacionados con campeonatos, como editar, eliminar o generar fixture.

### `EquipoPolicy.php`

Decide quién puede ver o modificar cada equipo.

Regla práctica: ocultar un botón en Blade mejora la interfaz, pero **no ofrece seguridad**. La autorización real siempre debe comprobarse en middleware, policy o controlador.

## 9. Notificaciones

### `NotificacionPlataforma.php`

Define el formato común de los avisos: título, mensaje, enlace, icono y color. Laravel los guarda en la tabla `notifications`.

Ejemplos:

- nueva solicitud de equipo;
- nueva postulación de árbitro;
- solicitud aprobada o rechazada;
- partido asignado;
- resultado registrado.

## 10. Vistas Blade

Blade combina HTML con directivas de Laravel:

```blade
@if($condicion)
    ...
@endif

@foreach($equipos as $equipo)
    {{ $equipo->nombre }}
@endforeach
```

`{{ ... }}` escapa texto para evitar inyección HTML. `@csrf` protege formularios contra peticiones falsas.

### `layouts/app.blade.php`

Es la plantilla principal: cabecera, navegación, mensajes, notificaciones, fondo y espacio donde cada vista inserta contenido.

### `auth/*`

Formularios para ingresar, registrarse, confirmar correo y recuperar contraseña.

### `campeonatos/*`

- `index`: campeonatos administrados.
- `create`: asistente de creación.
- `edit`: edición y reglas deportivas.
- `show`: panel interno del campeonato.
- `descubrir`: buscador para delegados.

### `arbitros/*`

- `explorar`: campeonatos disponibles para postularse.
- `panel`: postulaciones y partidos asignados.

### `equipos/*`, `jugadores/*` y `partidos/*`

Contienen formularios, listados, fichas y pantallas de resultado correspondientes a cada módulo.

### `publico/*`

- `index`: portada y buscador público.
- `show`: módulos públicos del campeonato.
- `partials/bracket-node`: tarjeta reutilizable de una serie eliminatoria.
- `partials/tabla-posiciones`: tabla reutilizable de clasificación.

### `components/team-crest.blade.php`

Componente reutilizable que muestra el escudo real o un icono sustituto.

## 11. CSS y JavaScript

### `resources/css/app.css`

Contiene estilos generales: colores, navegación, formularios, tarjetas, responsive y animaciones.

### JavaScript dentro de las vistas

Se usa para comportamientos muy ligados a una pantalla:

- pasos del formulario de campeonato;
- valores sugeridos por modalidad;
- carga de equipos y árbitros con `fetch`;
- menú por módulos;
- dibujo responsive de conexiones en las llaves.

### `resources/js/app.js` y `bootstrap.js`

Son los puntos de entrada para Vite y la configuración JavaScript compartida.

## 12. Base de datos

### Migraciones

Los archivos de `database/migrations` son el historial de estructura de la base de datos. Se ejecutan en orden por la fecha incluida en el nombre.

Principales grupos:

- usuarios y roles;
- campeonatos y reglas deportivas;
- equipos, escudos e inscripciones;
- jugadores y estados;
- partidos, árbitros y fases;
- incidencias;
- notificaciones;
- portadas.

No se debe editar una migración ya utilizada en producción para cambiar datos existentes; normalmente se crea otra migración.

### Seeders

- `DatabaseSeeder`: punto de entrada.
- `DeveloperUserSeeder`: crea el usuario de desarrollo.
- `DemoCampeonatoSeeder`: genera campeonatos, equipos, jugadores, partidos, resultados, grupos, llaves y tercer puesto para pruebas manuales.

### Factory

`UserFactory.php` crea usuarios falsos para pruebas automatizadas.

## 13. Pruebas

### `tests/Feature/RecoveredWorkflowTest.php`

Prueba los flujos reales de la plataforma:

- mensajes en español;
- creación de campeonatos;
- reglas de jugadores;
- inscripción y retiro de equipos;
- permisos del organizador;
- postulaciones de árbitros;
- asignación y resultados;
- portal público;
- llaves, campeón y tercer puesto;
- portada.

Ejecutar todo:

```bash
php artisan test
```

Ejecutar una prueba concreta:

```bash
php artisan test --filter="referee"
```

## 14. Métodos y expresiones que verás con frecuencia

```php
$request->validate([...]);
```

Valida los datos y regresa automáticamente al formulario si existe un error.

```php
abort_unless($condicion, 403);
```

Detiene la petición si no se cumple una autorización.

```php
Model::with('relacion')->get();
```

Consulta modelos junto con relaciones para evitar el problema de muchas consultas repetidas.

```php
return view('carpeta.vista', compact('dato'));
```

Abre una vista y le entrega variables.

```php
return redirect()->route('ruta')->with('mensaje', 'Listo');
```

Redirige y guarda un mensaje temporal en la sesión.

## 15. Orden recomendado para estudiar

1. `routes/web.php`.
2. `CampeonatoArbitroController.php`, porque es un flujo pequeño y completo.
3. `User.php`, `Campeonato.php` y sus relaciones.
4. `arbitros/explorar.blade.php` y `arbitros/panel.blade.php`.
5. Las pruebas de árbitros en `RecoveredWorkflowTest.php`.
6. `EquipoController.php` y `JugadorController.php`.
7. `PartidoController.php` e `IncidenciaController.php`.
8. Generadores de formatos y tabla de posiciones.
9. `PortalPublicoController.php` y sus vistas.

## 16. Regla para escribir buenos comentarios

Un comentario útil explica **por qué** existe una decisión o qué regla protege:

```php
// Un árbitro solo puede postularse una vez en el mismo campeonato.
```

Un comentario poco útil repite exactamente el código:

```php
// Asignar 1 a contador.
$contador = 1;
```

La meta es que el código siga siendo legible y que los comentarios ayuden a comprender reglas, permisos y decisiones difíciles.
