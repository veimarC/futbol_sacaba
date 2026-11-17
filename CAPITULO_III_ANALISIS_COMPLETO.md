# CAPÍTULO III: INGENIERÍA DEL PROYECTO

**Proyecto:** Desarrollo de una plataforma web para la gestión administrativa, control estadístico y difusión de información de campeonatos de fútbol amateur del municipio de Sacaba.  
**Fecha de revisión:** 23 de septiembre de 2026.  
**Versión esencial:** se priorizan los elementos más importantes, conforme a la última indicación del usuario.

## Alcance de la revisión

Se analizaron rutas, nueve modelos, controladores, políticas, middleware, servicios, las 34 migraciones, seeders, vistas, recursos de presentación, configuración y pruebas del código local. El esquema se reconstruyó desde migraciones, sin consultar ni modificar la base de datos. Las dependencias se identificaron por manifiestos y archivos de bloqueo; no se declara una auditoría de todo vendor/node_modules.

**IMPLEMENTADO** significa que existe un flujo reconocible en el código, no que se haya verificado su ejecución en este análisis. **PARCIALMENTE IMPLEMENTADO** identifica circuitos incompletos. **NO IMPLEMENTADO** significa que no se encontró el mecanismo en el código propio revisado. **CÓDIGO SIN USO / LEGACY** identifica recursos desconectados o de compatibilidad.

No se ejecutaron pruebas, migraciones, seeders ni servicios externos. No se reproducen secretos del entorno. Para mantener la lectura breve, los diagramas completos se entregan en archivos independientes dentro de `documentacion_capitulo_3/`.

## 3.1 REQUERIMIENTOS GENERALES DEL SISTEMA

### 3.1.1 Requerimientos Funcionales Generales

| Código | Requerimiento | Descripción | Actor |
|---|---|---|---|
| RF-01 | Acceso y roles | Registrar delegado, organizador o árbitro; iniciar/cerrar sesión y redirigir al área correspondiente. | Visitante / usuario |
| RF-02 | Campeonatos | Crear y mantener torneos con fechas, formato, categoría, sexo y límites de jugadores. | Organizador propietario |
| RF-03 | Equipos e inscripción | Crear equipo propio y solicitar su participación en campeonatos abiertos. | Delegado |
| RF-04 | Aprobación y retiro de equipos | Resolver solicitudes y registrar retiros con motivo sin eliminar su historial. | Organizador propietario |
| RF-05 | Jugadores | Registrar y editar fichas, con CI contextual, edad, sexo, máximo de plantilla y fotografía. | Delegado propietario |
| RF-06 | Revisión de jugadores | Consultar, aprobar o rechazar jugadores autorizados; flujo inicial parcial porque el alta usa aprobado. | Organizador |
| RF-07 | Arbitraje | Postular al campeonato, aprobar postulaciones y asignar solo árbitros aprobados. | Árbitro / organizador |
| RF-08 | Fixture y programación | Generar Liga, grupos o ronda inicial eliminatoria; asignar fecha, hora y cancha. | Organizador propietario |
| RF-09 | Nóminas | Seleccionar jugadores elegibles y dorsales; aprobar o devolver las listas de cada partido. | Delegado / Mesa de control |
| RF-10 | Incidencias y resultado | Registrar goles y tarjetas, calcular marcador y cerrar el partido con dos nóminas aprobadas. | Árbitro asignado / organizador |
| RF-11 | Disciplina | Consultar suspensión por roja o doble amarilla al preparar la siguiente nómina. | Sistema |
| RF-12 | Difusión y estadísticas | Mostrar campeonatos, plantillas, calendario, posiciones, goleadores, tarjetas y llaves. | Visitante |
| RF-13 | Imágenes y avisos | Gestionar imágenes mediante Cloudinary y avisos internos/WhatsApp con consentimiento. | Usuario autorizado / sistema |

El desarrollador puede intervenir en numerosas operaciones administrativas mediante las políticas, pero **no sustituye todos los roles**: los filtros role son exactos y, por ejemplo, aprobar jugadores está reservado al organizador. La recuperación de contraseña, el perfil y las notificaciones propias también tienen rutas y vistas; se tratan como funciones de apoyo.

### 3.1.2 Requerimientos No Funcionales Generales

| Código | Requerimiento | Sustento y límite |
|---|---|---|
| RNF-01 | Control de acceso | Sesión web, middleware auth/role, políticas y comprobaciones de propiedad. No equivale a una auditoría de seguridad aprobada. |
| RNF-02 | Validación de entradas | Validadores Laravel para fechas, identidad, archivos, límites y relaciones deportivas; existen diferencias entre algunos endpoints. |
| RNF-03 | Integridad de operaciones críticas | Transacciones en generación de fixture, nóminas e incidencias; no todas las operaciones del sistema son atómicas. |
| RNF-04 | Mantenibilidad | Separación en controladores, servicios, modelos, vistas y componentes reutilizables. |
| RNF-05 | Presentación adaptable | Blade, Bootstrap, CSS propio y controles de navegación; no se midió accesibilidad ni compatibilidad completa. |
| RNF-06 | Protección de datos en consultas públicas | El portal omite CI y teléfonos; el detalle autenticado de equipos tiene una limitación de privacidad identificada. |
| RNF-07 | Uso eficiente de imágenes | Cloudinary genera URLs optimizadas con dimensiones, formato y calidad automáticos; entrega externa no comprobada aquí. |

No se proponen como cumplidos tiempos de respuesta, disponibilidad, usuarios simultáneos ni porcentajes de cobertura: no existen mediciones obtenidas en esta revisión.

### 3.1.3 Identificación y descripción de actores

| Actor | Funciones principales | Restricciones reales |
|---|---|---|
| Visitante | Buscar torneos y consultar información deportiva pública | Sin operaciones administrativas. No requiere cuenta ni rol publico. |
| Organizador | Crear campeonatos, gestionar inscripciones y árbitros, fixture y Mesa de control | Mutaciones limitadas a sus torneos, salvo reglas explícitas; no accede al panel privado /equipos de delegados. |
| Delegado | Administrar sus equipos, jugadores, inscripciones y nóminas | Propiedad del equipo y reglas del torneo; no aprueba sus solicitudes. |
| Árbitro | Postular, consultar asignaciones y registrar incidencias/resultados | Debe estar aprobado para ser asignado y asignado al encuentro para operarlo. |
| Desarrollador | Administración general permitida por las políticas | Rol interno; no se ofrece en registro público. Los filtros role pueden excluirlo. |
| Sistemas externos | Cloudinary procesa imágenes y Meta recibe solicitudes de WhatsApp | Dependen de configuración y disponibilidad externas. |

**Mesa de control** es la función del organizador propietario o desarrollador, no un sexto rol almacenado. El valor **publico** permanece en código/esquema heredado, pero no se acepta en el registro actual. El registro público sí permite crear organizadores, sin aprobación previa.

### 3.1.4 Diagrama de casos de uso general

**FIGURA 1. Casos de uso de la plataforma.**  
Fuente: Elaboración propia.

[Código PlantUML completo](documentacion_capitulo_3/01_casos_uso_general.puml).

El diagrama agrupa acceso, administración del torneo, participantes, arbitraje, control del partido y difusión. Sus asociaciones expresan intervenciones permitidas; deben interpretarse junto con la propiedad y el estado exigidos por los controladores. Las funciones auxiliares se mantienen en el diagrama para mostrar las interacciones del sistema, sin desarrollar un catálogo extenso en el texto.

### 3.1.5 Diagrama de clases general

**FIGURA 2. Modelos y relaciones principales.**  
Fuente: Elaboración propia.

[Código PlantUML completo](documentacion_capitulo_3/02_diagrama_clases.puml).

| Modelo | Responsabilidad y relaciones destacadas |
|---|---|
| User | Cuenta, rol y consentimiento; organizador de campeonatos, delegado de equipos y árbitro relacionado mediante pivote. |
| Campeonato | Reglas y formato; pertenece a organizador; tiene fases y partidos; equipos y árbitros mediante pivotes. |
| Equipo | Pertenece a delegado; tiene jugadores; participa en múltiples campeonatos mediante campeonato_equipo. Conserva campeonato_id legacy. |
| Jugador | Ficha perteneciente a equipo; tiene incidencias y participaciones en nóminas. |
| Fase | Pertenece a campeonato y agrupa partidos; metadata contiene configuración e integrantes de grupos. |
| Partido | Pertenece a campeonato, fase opcional, equipos local/visita y árbitro opcional; tiene incidencias y nóminas. |
| Incidencia | Relaciona partido y jugador para gol o tarjeta; conserva dorsal, minuto y creador como campos. |
| NominaPartido | Cabecera por partido/equipo, estado, observación y responsable de aprobación. |
| NominaPartidoJugador | Detalle que asocia nómina y jugador con dorsal y titularidad. |

Los atributos del diagrama se obtuvieron de los modelos. Los campos fillable delimitan asignación masiva; no constituyen por sí mismos una autorización. Los casts convierten fechas, booleanos y configuraciones JSON; los accessors calculan edad, estado actual o URLs de imágenes sin crear columnas adicionales.

### 3.1.6 Diseño de base de datos general

#### 3.1.6.1 Modelo Relacional

Se encontraron **19 tablas declaradas por las migraciones propias**, incluidas las de infraestructura. La tabla técnica migrations la administra Laravel y no se agrega como tabla de negocio.

| Tabla principal | Propósito | PK | Referencias principales |
|---|---|---|---|
| users | Cuentas y roles | id | Sin FK propia de negocio |
| campeonatos | Configuración del torneo | id | usuario_creador → users |
| equipos | Equipo y delegado | id | delegado_id → users; campeonato_id legacy |
| campeonato_equipo | Inscripción, estado y retiro | id | campeonato_id, equipo_id, retirado_por |
| jugadores | Fichas deportivas | id | equipo_id |
| fases | Etapas del torneo | id | campeonato_id |
| partidos | Programación y resultado | id | campeonato_id, fase_id, equipos local/visita, arbitro_id |
| incidencias | Goles y tarjetas | id | Referencias lógicas partido_id, jugador_id y creado_por; sin FK físicas declaradas |
| arbitro_campeonato | Postulación del árbitro | id | campeonato_id, arbitro_id |
| nominas_partido | Nómina por encuentro/equipo | id | partido_id, equipo_id, aprobado_por |
| nomina_partido_jugadores | Convocados y dorsales | id | nomina_partido_id, jugador_id |

Complementan el esquema notifications, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches y failed_jobs.

**FIGURA 3. Modelo entidad-relación.**  
Fuente: Elaboración propia.

[Código Mermaid completo](documentacion_capitulo_3/03_modelo_relacional.mmd).

El diagrama incluye columnas y asociaciones de las 19 tablas. Las líneas discontinuas identifican asociaciones lógicas sin restricción FK, como incidencias, sessions.user_id y el destinatario polimórfico de notifications; no debe interpretarse que la base de datos protege todas las relaciones dibujadas.

#### 3.1.6.2 Normalización

- **1FN:** jugadores y convocados se almacenan en filas separadas; no dentro de una columna del equipo. Sin embargo, fases.metadata y formato_config utilizan JSON; sus listas impiden afirmar normalización relacional estricta de todo el esquema.
- **2FN:** campeonato_equipo separa los atributos de una inscripción de los del equipo. Las parejas únicas campeonato/equipo y nómina/jugador representan identidades de asociaciones; no se repiten nombres personales en cada detalle.
- **3FN:** nombres de organizadores y árbitros se obtienen de users. Hay redundancias deliberadas: equipos.campeonato_id coexiste con el pivote y goles_local/goles_visita son agregados de incidencias. Por ello no se afirma cumplimiento absoluto de 3FN.

El CI no es único globalmente en la base de datos: la prohibición de duplicarlo en un mismo campeonato se comprueba en la aplicación. La aprobación del jugador es global a su registro, no por cada inscripción del equipo.

#### 3.1.6.3 Campos más importantes

Se resume el diccionario a las columnas que explican los procesos centrales. El modelo relacional adjunto conserva el conjunto de campos.

##### campeonatos

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| id | bigint unsigned | PK, autoincremental, NOT NULL | Identificador |
| nombre | varchar(255) | NOT NULL | Nombre del torneo |
| fecha_inicio | date | NOT NULL | Inicio previsto |
| fecha_fin | date | NULL | Fin previsto |
| usuario_creador | bigint unsigned | NOT NULL, FK users.id, CASCADE | Organizador |
| formato | varchar(255) | NULL | Liga, Copa o eliminación; admite valores legado en datos |
| formato_config | json | NULL | Opciones calculadas de competencia |
| jugadores_cancha | smallint unsigned | NOT NULL, default 11 | Mínimo aplicado al aprobar nóminas |
| min_jugadores_equipo | smallint unsigned | NOT NULL, default 15 | Mínimo configurado de plantilla |
| max_jugadores_equipo | smallint unsigned | NOT NULL, default 25 | Máximo configurado de plantilla |

##### campeonato_equipo

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| campeonato_id | bigint unsigned | NOT NULL, FK campeonatos.id, CASCADE | Campeonato |
| equipo_id | bigint unsigned | NOT NULL, FK equipos.id, CASCADE | Equipo |
| estado | varchar(20) | NOT NULL, default pendiente | Pendiente, aprobado, rechazado o retirado en aplicación |
| motivo_retiro | text | NULL | Justificación |
| retirado_at | timestamp | NULL | Momento del retiro |
| retirado_por | bigint unsigned | NULL, FK users.id, SET NULL | Responsable del retiro |

##### jugadores

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| id | bigint unsigned | PK, autoincremental, NOT NULL | Identificador |
| dni | varchar(255) | NULL, sin UNIQUE global final | CI en interfaz; validación contextual en aplicación |
| fecha_nacimiento | date | NULL | Fecha para edad |
| sexo | varchar(20) | NULL en esquema | Obligatorio en formulario actual |
| foto_cloudinary_id | varchar(255) | NULL | Identificador externo |
| estado | varchar(20) | NOT NULL, default aprobado | Estado global del jugador |
| equipo_id | bigint unsigned | NOT NULL, FK equipos.id, CASCADE | Equipo actual |

##### partidos

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| id | bigint unsigned | PK, autoincremental, NOT NULL | Identificador |
| campeonato_id | bigint unsigned | NOT NULL, FK campeonatos.id, CASCADE | Torneo |
| fase_id | bigint unsigned | NULL, FK fases.id, SET NULL | Fase opcional |
| equipo_local_id | bigint unsigned | NOT NULL, FK equipos.id, CASCADE | Equipo local |
| equipo_visita_id | bigint unsigned | NOT NULL, FK equipos.id, CASCADE | Equipo visitante |
| fecha | date | NULL tras migración de restauración | Fixture puede quedar sin programar |
| hora | time | NULL tras migración de restauración | Hora del encuentro |
| arbitro_id | bigint unsigned | NULL, FK users.id, SET NULL | Árbitro asignado |
| goles_local | int | NULL, default NULL | Contador sincronizado |
| goles_visita | int | NULL, default NULL | Contador sincronizado |
| estado | enum(programado,jugado) | NOT NULL, default programado | Estado operativo |

##### incidencias

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| partido_id | bigint unsigned | NULL, INDEX, SIN FK | Partido; store actual lo exige |
| jugador_id | bigint unsigned | NOT NULL, INDEX, SIN FK | Jugador relacionado por Eloquent |
| dorsal | tinyint unsigned | NULL | Número copiado de nómina |
| tipo | varchar(255) | NOT NULL | gol, amarilla, doble_amarilla o roja |

##### nominas_partido

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| partido_id | bigint unsigned | NOT NULL, FK partidos.id, CASCADE | Encuentro |
| equipo_id | bigint unsigned | NOT NULL, FK equipos.id, CASCADE | Equipo |
| estado | varchar(20) | NOT NULL, default pendiente | pendiente, aprobada o rechazada en aplicación |
| aprobado_por | bigint unsigned | NULL, FK users.id, SET NULL | Revisor |

##### nomina_partido_jugadores

| Campo | Tipo | Restricción | Descripción |
|---|---|---|---|
| nomina_partido_id | bigint unsigned | NOT NULL, FK nominas_partido.id, CASCADE | Cabecera |
| jugador_id | bigint unsigned | NOT NULL, FK jugadores.id, CASCADE | Jugador |
| dorsal | tinyint unsigned | NOT NULL | Número por partido; aplicación limita 1–99 |

### 3.1.7 Arquitectura del sistema

#### 3.1.7.1 Capa de Presentación

Blade renderiza páginas y componentes desde resources/views. El layout integra Bootstrap, Sass y CSS propio. JavaScript controla el asistente de campeonato, navegación entre módulos, previsualización de imágenes y carga de equipos/árbitros según el torneo. Es una aplicación renderizada en servidor, no una SPA independiente. El archivo Tailwind resources/css/app.css no se identificó como entrada activa del layout/Vite.

#### 3.1.7.2 Capa de Lógica

Los controladores reciben peticiones, validan, autorizan y coordinan persistencia. Los servicios concentran algoritmos: ConfiguracionFormatoService, generadores de fixture, TablaPosicionesService, MarcadorPartidoService, SuspensionJugadorService, CloudinaryImageService y mensajería.

Reglas decisivas:

- Solo los equipos aprobados participan en el fixture. No se genera mientras haya solicitudes pendientes ni si ya existen fases/partidos.
- Liga usa dos vueltas hasta diez equipos y una con más de diez. Copa calcula grupos y permite configurar clasificados antes del inicio deportivo.
- El CI no puede repetirse dentro del mismo equipo o de campeonatos compartidos con inscripción activa. Edad y sexo se contrastan con reglas del torneo.
- El dorsal es por partido, de 1 a 99 y sin repetición dentro de la nómina.
- Los goles se obtienen de incidencias; no se introduce un marcador manual independiente.
- La tabla usa partidos jugados: tres puntos por victoria y uno por empate; desempata por puntos, diferencia, goles a favor y nombre en el servicio.
- Roja y doble amarilla producen indisponibilidad calculada para un encuentro posterior; no existe un módulo de sanciones disciplinarias generales.

#### 3.1.7.3 Capa de Acceso a Datos

Los nueve modelos Eloquent gestionan consultas y relaciones. Las migraciones describen evolución del esquema y restricciones. El entorno local declara MySQL; PHPUnit configura SQLite en memoria. No se comprobó el estado real de ninguna de esas bases.

#### 3.1.7.4 Servicios externos

**Cloudinary:** subida, reemplazo, eliminación y URLs optimizadas de portadas, escudos y fotos. Los public_id se almacenan en columnas propias y existe compatibilidad con imágenes locales.

**WhatsApp mediante Meta:** solicitudes HTTP de texto o plantilla, normalización de teléfonos bolivianos y consentimiento. El comando de recordatorios se programa cada diez minutos y selecciona encuentros próximos. La entrega real depende de configuración y ejecución del scheduler, no demostradas en esta revisión. Las notificaciones utilizan Queueable, pero no implementan ShouldQueue: no se presentan como envíos asíncronos.

La recuperación de contraseña dispone de rutas y formularios; MAIL_MAILER=log no demuestra entrega de correo al usuario.

#### 3.1.7.5 Comunicación entre capas

Usuario → ruta web → sesión/rol/política → controlador → servicio cuando corresponde → Eloquent → base de datos → controlador → Blade → usuario. Los endpoints JSON auxiliares permanecen en routes/web.php, protegidos por sesión y autorización.

**FIGURA 4. Arquitectura de la plataforma.**  
Fuente: Elaboración propia.

[Código PlantUML completo](documentacion_capitulo_3/04_arquitectura.puml).

## Hallazgos que deben constar en el documento académico

| Estado | Hallazgo importante |
|---|---|
| PARCIALMENTE IMPLEMENTADO | Eliminatorias: se generan cruces iniciales, pero no existe avance automático de rondas ni resolución integral de empates/pases libres. La generación desde grupos tiene endpoint, sin botón Blade identificado. |
| PARCIALMENTE IMPLEMENTADO | Jugadores: la migración establece aprobado como valor inicial, aunque la notificación habla de revisión pendiente. |
| PARCIALMENTE IMPLEMENTADO | Nóminas: el GET bloquea edición del delegado después de aprobar, pero guardar por PUT no replica la restricción. gestionar tampoco comprueba partido jugado. |
| LIMITACIÓN DE ACCESO | EquipoPolicy::view permite consulta a usuarios autenticados y equipos/show muestra CI; restringir jugadores.show no elimina esta exposición. |
| LIMITACIÓN DE INTEGRIDAD | incidencias no tiene FK físicas; eliminar un partido programado no verifica sus incidencias. No se valida integralmente la correspondencia fase/campeonato al cambiar de torneo. |
| LIMITACIÓN DE REGLAS | El mínimo configurado de plantilla no se exige integralmente al aprobar equipos/generar fixture. Se permiten goles de un jugador ya expulsado. |
| PARCIALMENTE IMPLEMENTADO | Un envío WhatsApp exitoso puede marcar el recordatorio global aunque otros destinatarios fallen; no hay seguimiento por destinatario. |
| NO IMPLEMENTADO | Exportación PDF/Excel/CSV, administración completa de usuarios, pagos y estadísticas mediante actualización en tiempo real. |
| CÓDIGO SIN USO / LEGACY | welcome, partidos/index y verificación de correo no tienen recorrido activo correspondiente. Series e ida/vuelta conservan compatibilidad interna, pero no son nuevos formatos del formulario. |

## 3.2 APLICACIÓN DE SCRUM

### 3.2.1 Roles Scrum

Para un proyecto individual, el estudiante puede desempeñar desarrollo y facilitación del proceso; la priorización debe contrastarse con el tutor y representantes de usuarios. Product Owner representa necesidades y prioridades, Scrum Master facilita el proceso y el equipo construye el incremento. **No se encontró evidencia suficiente para afirmar reuniones, fechas o sprints históricos:** lo siguiente es una organización académica propuesta del código existente.

### 3.2.2 Product Backlog

| ID | Historia principal | Prioridad | Estimación orientativa | Estado observado |
|---|---|---|---|---|
| HU-01 | Como usuario, quiero acceder según mi rol para utilizar mis funciones autorizadas. | Alta | 5 puntos | IMPLEMENTADO |
| HU-02 | Como organizador, quiero configurar campeonatos y resolver inscripciones para definir participantes. | Alta | 8 puntos | IMPLEMENTADO |
| HU-03 | Como delegado, quiero registrar jugadores elegibles para mantener mi plantilla. | Alta | 8 puntos | IMPLEMENTADO; revisión inicial parcial |
| HU-04 | Como organizador, quiero generar y programar encuentros con árbitros aprobados para organizar la competencia. | Alta | 13 puntos | IMPLEMENTADO; ciclo eliminatorio parcial |
| HU-05 | Como responsable del encuentro, quiero aprobar nóminas y registrar incidencias para obtener un resultado trazable. | Alta | 13 puntos | IMPLEMENTADO con restricciones incompletas |
| HU-06 | Como visitante, quiero consultar resultados y estadísticas para seguir el campeonato. | Alta | 8 puntos | IMPLEMENTADO; llaves dependientes de datos existentes |

Las historias se presentan como épicas resumidas, no como seis tareas pequeñas. Las estimaciones no representan horas trabajadas ni velocidad medida.

### 3.2.3 Planificación de Sprints

| Sprint | Entrega principal | Dependencia |
|---|---|---|
| 1 | Identidad, acceso y avisos propios | Base del acceso administrativo |
| 2 | Campeonatos, equipos e inscripciones | Usuarios y roles |
| 3 | Jugadores, elegibilidad e imágenes | Equipos y reglas de campeonato |
| 4 | Arbitraje, fixture y programación | Participantes aprobados |
| 5 | Nóminas, incidencias y resultados | Encuentros programados y jugadores |
| 6 | Consulta pública y estadísticas | Datos administrativos y deportivos |

### 3.2.4 Implementación de los Sprints

Se desarrolla **un caso principal por sprint** para priorizar lo importante. Los diagramas adjuntos conservan las interacciones relacionadas, mientras que las pruebas citadas se limitan a evidencia existente y no ejecutada.

#### SPRINT 1: Identidad, acceso y comunicación interna

**Objetivo e historia:** Establecer cuentas operativas, acceso por rol, perfil y lectura de avisos propios. Corresponde a HU-01.

**Requisitos y actores:** RF-01, RF-13. Visitante; los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Validar registro y excluir roles privilegiados | Alta | Implementado; límites señalados en el capítulo |
| Conectar login/logout y redirección por rol | Alta | Implementado; límites señalados en el capítulo |
| Disponer formularios del broker y confirmación | Alta | Implementado; límites señalados en el capítulo |
| Actualizar perfil y consentimiento con validaciones | Alta | Implementado; límites señalados en el capítulo |
| Listar y marcar avisos propios | Alta | Implementado; límites señalados en el capítulo |

**Diseño:** Guard web de sesión y traits de Laravel UI; User y broker; PerfilController verifica contraseña actual; NotificacionController limita destinatario. AppServiceProvider configura políticas y alias role.

**Caso de uso principal CU-01: Registrarse.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Visitante |
| Descripción | Permite obtener una cuenta operativa. |
| Precondiciones | No tener sesión iniciada. |
| Flujo principal | Abrir /register; introducir nombre, correo, contraseña confirmada y rol delegado, organizador o árbitro; validar datos; guardar User con Hash::make; iniciar sesión mediante RegistersUsers y redirigir a /home. |
| Flujos alternativos | Correo duplicado, contraseña menor a ocho caracteres, teléfono inválido o rol publico/desarrollador: devolver errores. No existe aprobación previa para el alta de organizadores. |
| Postcondiciones | Cuenta creada con el rol seleccionado y consentimiento opcional de WhatsApp. |

**FIGURA 5. Casos de uso del sprint 1.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_01_casos_uso.puml).

**FIGURA 6. Secuencia del proceso principal del sprint 1.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_01_secuencia.puml).

**FIGURA 7. Actividades del proceso principal del sprint 1.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_01_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [auth/register](resources/views/auth/register.blade.php) | GET /register | Auth/RegisterController::showRegistrationForm (trait) |
| [auth/login](resources/views/auth/login.blade.php) | GET /login | Auth/LoginController::showLoginForm (trait) |
| [perfil/edit](resources/views/perfil/edit.blade.php) | GET /perfil | PerfilController::edit |

**Pruebas existentes — NO EJECUTADAS:**

- [test_public_registration_allows_operational_roles_but_not_developer](tests/Feature/RecoveredWorkflowTest.php#L141).
- [test_public_role_is_not_available_for_new_registrations](tests/Feature/RecoveredWorkflowTest.php#L1225).
- [test_whatsapp_field_rejects_an_invalid_bolivian_cellphone](tests/Feature/RecoveredWorkflowTest.php#L1802).

**Límite:** La recuperación externa requiere correo de entrega; no se encuentra una prueba específica del circuito de token, login inválido/logout o marcado global de avisos.

#### SPRINT 2: Campeonatos, equipos e inscripciones

**Objetivo e historia:** Definir torneos y participantes con gestión de inscripciones y conservación del historial al retirar equipos. Corresponde a HU-02.

**Requisitos y actores:** RF-02, RF-03, RF-04. Organizador propietario / Administrador general; los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Construir asistente y reglas del campeonato | Alta | Implementado; límites señalados en el capítulo |
| Buscar torneos abiertos y solicitar participación | Alta | Implementado; límites señalados en el capítulo |
| Mantener equipos con propiedad y bloqueos de borrado | Alta | Implementado; límites señalados en el capítulo |
| Aprobar o rechazar inscripciones del torneo propio | Alta | Implementado; límites señalados en el capítulo |
| Retirar equipo registrando motivo y responsable | Alta | Implementado; límites señalados en el capítulo |

**Diseño:** CampeonatoPolicy y EquipoPolicy autorizan recursos; Campeonato/Equipo se asocian mediante pivote con estado. EquipoController coordina solicitud, respuesta y aviso. Las imágenes se reutilizan desde el sprint 3.

**Caso de uso principal CU-09: Resolver inscripción de equipo.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Organizador propietario / Administrador general |
| Descripción | Permite autorizar participantes del campeonato. |
| Precondiciones | Solicitud vinculada al campeonato. |
| Flujo principal | Abrir detalle del campeonato, pestaña Equipos; revisar solicitudes; enviar aprobado o rechazado; comprobar propiedad e inscripción; actualizar pivote y notificar al delegado. |
| Flujos alternativos | Organizador ajeno: 403; equipo no relacionado: 404; estado fuera del catálogo: error. El endpoint no exige que el estado anterior sea pendiente. |
| Postcondiciones | Estado de inscripción actualizado y notificación al delegado. |

**FIGURA 8. Casos de uso del sprint 2.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_02_casos_uso.puml).

**FIGURA 9. Secuencia del proceso principal del sprint 2.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_02_secuencia.puml).

**FIGURA 10. Actividades del proceso principal del sprint 2.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_02_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [campeonatos/create](resources/views/campeonatos/create.blade.php) | GET /campeonatos/create | CampeonatoController::create |
| [campeonatos/show](resources/views/campeonatos/show.blade.php) | GET /campeonatos/{campeonato} | CampeonatoController::show |
| [equipos/create](resources/views/equipos/create.blade.php) | GET /equipos/create?campeonato={id} | EquipoController::create |

**Pruebas existentes — NO EJECUTADAS:**

- [test_delegate_requests_enrollment_and_organizer_approves_it](tests/Feature/RecoveredWorkflowTest.php#L153).

**Límite:** No se encontró prueba específica para cada variante de borrado de equipos/campeonatos ni para la transición desde retirado por POST de gestionarSolicitud.

#### SPRINT 3: Jugadores, elegibilidad e imágenes

**Objetivo e historia:** Gestionar fichas y recursos gráficos con reglas de participación y revisión documentada con sus límites. Corresponde a HU-03.

**Requisitos y actores:** RF-05, RF-06, RF-13. Delegado propietario / Administrador general; los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Validar ficha, CI contextual y límites deportivos | Alta | Implementado con revisión inicial parcial |
| Consultar y revisar jugadores según organizador | Alta | Implementado con revisión inicial parcial |
| Proteger eliminación de jugadores con incidencias | Alta | Implementado con revisión inicial parcial |
| Centralizar imágenes y compensar fallos externos | Alta | Implementado con revisión inicial parcial |

**Diseño:** JugadorController consulta inscripciones activas para elegibilidad; estado reside en jugadores. CloudinaryImageService centraliza subida, optimización y eliminación, con compatibilidad local. El flujo de revisión inicial es parcial por el estado predeterminado.

**Caso de uso principal CU-11: Registrar y editar jugadores.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Delegado propietario / Administrador general |
| Descripción | Permite construir una plantilla que cumpla reglas de participación. |
| Precondiciones | Equipo existente y autorizado. |
| Flujo principal | Abrir formulario desde equipo; introducir nombre, CI opcional, nacimiento, sexo, posición y contacto opcional; validar pertenencia, límite más restrictivo, edad al inicio del torneo y sexo; comprobar CI en equipo y campeonatos compartidos; guardar o actualizar. |
| Flujos alternativos | CI repetida, plantilla llena, equipo ajeno o reglas incompatibles: error. Si no hay campeonatos activos relacionados no se impone su límite deportivo. El dorsal se elige por partido, no en esta ficha. |
| Postcondiciones | Jugador guardado; al crear no se asigna pendiente y opera el default aprobado de la migración. |

**FIGURA 11. Casos de uso del sprint 3.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_03_casos_uso.puml).

**FIGURA 12. Secuencia del proceso principal del sprint 3.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_03_secuencia.puml).

**FIGURA 13. Actividades del proceso principal del sprint 3.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_03_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [jugadores/create](resources/views/jugadores/create.blade.php) | GET /equipos/{equipo}/jugadores/create | JugadorController::create |
| [jugadores/show](resources/views/jugadores/show.blade.php) | GET /jugadores/{jugador} | JugadorController::show |
| [equipos/show](resources/views/equipos/show.blade.php) | GET /equipos/{equipo}?campeonato={id} | EquipoController::show |

**Pruebas existentes — NO EJECUTADAS:**

- [test_player_dni_cannot_repeat_in_same_championship_but_can_in_another](tests/Feature/RecoveredWorkflowTest.php#L407).
- [test_team_cannot_exceed_the_most_restrictive_active_championship_limit](tests/Feature/RecoveredWorkflowTest.php#L440).
- [test_player_must_match_championship_age_and_sex_rules](tests/Feature/RecoveredWorkflowTest.php#L465).

**Límite:** Las pruebas de Cloudinary usan mocks o URLs; no acreditan envío real. No hay prueba específica que exija pendiente al crear ni que cubra todas las compensaciones de fallos.

#### SPRINT 4: Arbitraje, formatos y calendario

**Objetivo e historia:** Vincular árbitros aprobados, producir fixture inicial y programar encuentros coherentes con grupos. Corresponde a HU-04.

**Requisitos y actores:** RF-07, RF-08. Organizador propietario / Administrador general; los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Registrar postulaciones y consultar asignaciones | Alta | Implementado con ciclo eliminatorio parcial |
| Resolver postulaciones y filtrar árbitros elegibles | Alta | Implementado con ciclo eliminatorio parcial |
| Calcular formato y generar fixture transaccional | Alta | Implementado con ciclo eliminatorio parcial |
| Validar programación, rivales y pertenencia al grupo | Alta | Implementado con ciclo eliminatorio parcial |
| Configurar clasificados y generar cruces iniciales | Alta | Implementado con ciclo eliminatorio parcial |

**Diseño:** CampeonatoArbitroController opera arbitro_campeonato; ConfiguracionFormatoService elige reglas; generadores reutilizan roundRobin; PartidoController programa y asigna. CampeonatoEliminatoriaController solo crea primera ronda desde grupos completos.

**Caso de uso principal CU-17: Generar fixture.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Organizador propietario / Administrador general |
| Descripción | Permite obtener emparejamientos del torneo. |
| Precondiciones | Campeonato propio y equipos aprobados suficientes. |
| Flujo principal | Desde detalle solicitar Configurar y generar fixture; verificar inexistencia de fases/partidos y ausencia de solicitudes pendientes; contar equipos aprobados; calcular configuración; seleccionar generador; crear fases y encuentros en transacción y cerrar inscripciones. |
| Flujos alternativos | Menos de dos equipos, Copa con menos de cuatro, solicitudes sin resolver o fixture previo: error. Directa solo genera emparejamientos iniciales; no hay avance completo de byes. |
| Postcondiciones | Fixture inicial persistido sin fecha, hora ni cancha; inscripción cerrada. |

**FIGURA 14. Casos de uso del sprint 4.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_04_casos_uso.puml).

**FIGURA 15. Secuencia del proceso principal del sprint 4.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_04_secuencia.puml).

**FIGURA 16. Actividades del proceso principal del sprint 4.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_04_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [arbitros/administrar](resources/views/arbitros/administrar.blade.php) | GET /campeonatos/{campeonato}/arbitros | CampeonatoArbitroController::administrar |
| [partidos/edit](resources/views/partidos/edit.blade.php) | GET /partidos/{partido}/edit | PartidoController::edit |
| [campeonatos/show](resources/views/campeonatos/show.blade.php) | GET /campeonatos/{campeonato} | CampeonatoController::show |

**Pruebas existentes — NO EJECUTADAS:**

- [test_fixture_keeps_phase_round_and_order](tests/Feature/RecoveredWorkflowTest.php#L499).
- [test_series_format_creates_two_series_and_interseries_matches](tests/Feature/RecoveredWorkflowTest.php#L514).
- [test_knockout_home_and_away_creates_two_matches_per_opening_key](tests/Feature/RecoveredWorkflowTest.php#L538).

**Límite:** El flujo completo de eliminatorias no está terminado. No hay botón de generación desde grupos, prevención de duplicación en ese POST ni prueba de avance automático o byes.

#### SPRINT 5: Nóminas, incidencias, resultados y disciplina

**Objetivo e historia:** Registrar participantes por partido, revisar listas, derivar marcador de incidencias y controlar suspensión. Corresponde a HU-05.

**Requisitos y actores:** RF-09, RF-10, RF-11. Árbitro asignado / Organizador propietario / Administrador general; los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Guardar convocatoria con dorsales por encuentro | Alta | Implementado; límites señalados en el capítulo |
| Aprobar o devolver listas desde Mesa | Alta | Implementado; límites señalados en el capítulo |
| Registrar/corregir goles y tarjetas en transacción | Alta | Implementado; límites señalados en el capítulo |
| Confirmar marcador y consultar suspensión posterior | Alta | Implementado; límites señalados en el capítulo |

**Diseño:** NominaPartido/NominaPartidoJugador separan cabecera y detalle; SuspensionJugadorService decide disponibilidad. IncidenciaController y MarcadorPartidoService sincronizan evento y marcador; PartidoController exige dos aprobaciones para cerrar.

**Caso de uso principal CU-22: Registrar y corregir incidencias.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Árbitro asignado / Organizador propietario / Administrador general |
| Descripción | Permite registrar goles y tarjetas vinculados a jugadores. |
| Precondiciones | Partido programado y jugador en nómina aprobada; interfaz muestra formulario al existir dos aprobadas. |
| Flujo principal | Abrir resultado; seleccionar jugador de nómina aprobada y gol, amarilla o roja; verificar autorización y partido programado; resolver segunda amarilla como doble_amarilla; guardar incidencia con dorsal y autor; sincronizar marcador. Eliminar incidencia corrige marcador y, si procede, doble amarilla. |
| Flujos alternativos | Jugador fuera de nómina, partido finalizado o árbitro ajeno: bloqueo. Tras expulsión se impiden nuevas tarjetas, pero se admite gol; una prueba existente lo refleja. No se registra minuto desde la interfaz. |
| Postcondiciones | Incidencia y marcador actualizados en transacción. |

**FIGURA 17. Casos de uso del sprint 5.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_05_casos_uso.puml).

**FIGURA 18. Secuencia del proceso principal del sprint 5.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_05_secuencia.puml).

**FIGURA 19. Actividades del proceso principal del sprint 5.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_05_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [partidos/nominas](resources/views/partidos/nominas.blade.php) | GET /partidos/{partido}/nominas | NominaPartidoController::index |
| [partidos/nomina-editar](resources/views/partidos/nomina-editar.blade.php) | GET /partidos/{partido}/nominas/{equipo}/editar | NominaPartidoController::editar |
| [partidos/resultado](resources/views/partidos/resultado.blade.php) | GET /partidos/{partido}/resultado | PartidoController::resultado |

**Pruebas existentes — NO EJECUTADAS:**

- [test_unassigned_referee_cannot_enter_result](tests/Feature/RecoveredWorkflowTest.php#L803).
- [test_match_rosters_use_per_match_numbers_and_block_a_red_card_suspension](tests/Feature/RecoveredWorkflowTest.php#L816).

**Límite:** La prueba integral cubre muchos caminos, pero no los PUT sobre listas aprobadas ni revisión tras partido jugado, carreras simultáneas, traslado de jugadores o sanción por hora.

#### SPRINT 6: Difusión, estadísticas y mensajería externa

**Objetivo e historia:** Exponer información deportiva pública y comunicar programación mediante avisos opcionales. Corresponde a HU-06.

**Requisitos y actores:** RF-12, RF-13. Visitante (también usuarios con sesión); los actores de apoyo figuran en el diagrama.

**Backlog principal:**

| Tarea | Prioridad | Estado |
|---|---|---|
| Buscar torneos y mostrar participantes y plantillas | Alta | Implementado; límites señalados en el capítulo |
| Calcular tablas, goleadores e historiales | Alta | Implementado; límites señalados en el capítulo |
| Representar llaves y campeón de datos existentes | Alta | Implementado; límites señalados en el capítulo |
| Enviar avisos consentidos y recordatorios programados | Alta | Implementado; límites señalados en el capítulo |

**Diseño:** PortalPublicoController prepara agregados y Blade; TablaPosicionesService calcula solo partidos jugados. Las llaves son representación de datos existentes. NotificacionPlataforma y WhatsAppService envían avisos síncronos; scheduler invoca recordatorios.

**Caso de uso principal CU-24: Consultar portal y plantillas públicas.** Los números CU corresponden al diagrama general; se seleccionan aquí los seis procesos centrales.

| Elemento | Especificación |
|---|---|
| Actor | Visitante (también usuarios con sesión) |
| Descripción | Permite informarse sin crear una cuenta. |
| Precondiciones | Ninguna autenticación requerida. |
| Flujo principal | Abrir /; buscar por nombre y cargar más resultados; abrir /torneos/{campeonato}; navegar Información, Equipos y Partidos; abrir plantilla por equipo con foto, nombre, posición y edad. |
| Flujos alternativos | Sin coincidencias: estado vacío. La plantilla pública filtra equipos y jugadores aprobados. No muestra CI ni WhatsApp; las fichas privadas tienen otras reglas. |
| Postcondiciones | Información consultada sin escritura de datos deportivos. |

**FIGURA 20. Casos de uso del sprint 6.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_06_casos_uso.puml).

**FIGURA 21. Secuencia del proceso principal del sprint 6.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_06_secuencia.puml).

**FIGURA 22. Actividades del proceso principal del sprint 6.**  
Fuente: Elaboración propia.

[PlantUML completo](documentacion_capitulo_3/sprint_06_actividades.puml).

**Interfaces más importantes:**

| Vista Blade | Ruta | Controlador y función |
|---|---|---|
| [publico/index](resources/views/publico/index.blade.php) | GET / | PortalPublicoController::index |
| [publico/show](resources/views/publico/show.blade.php) | GET /torneos/{campeonato} | PortalPublicoController::show |

**Pruebas existentes — NO EJECUTADAS:**

- [test_guest_can_search_the_public_championship_feed_without_an_account](tests/Feature/RecoveredWorkflowTest.php#L1083).
- [test_public_championship_detail_shows_sports_information_but_hides_private_data](tests/Feature/RecoveredWorkflowTest.php#L1098).
- [test_championships_show_the_organizer_name_without_exposing_their_email](tests/Feature/RecoveredWorkflowTest.php#L1510).

**Límite:** No hay exportación de reportes ni datos en tiempo real. No se midieron carga/accesibilidad; la entrega externa y las fallas parciales del recordatorio requieren pruebas adicionales.

## 3.3 PRUEBAS DEL SISTEMA

Se identificaron **66 métodos: 3 Unit y 63 Feature**. No se ejecutaron, por lo que no se informa que hayan pasado ni se calcula cobertura. No se crearon nuevas pruebas.

### 3.3.1 Pruebas Unitarias

**PRUEBA EXISTENTE:** CloudinaryImageServiceTest contiene dos comprobaciones: URL HTTPS con transformaciones de recorte, calidad, formato y tamaño; y excepción por ancho cero. No comprueban una subida real. Unit/ExampleTest agrega una aserción básica sin regla de negocio.

### 3.3.2 Pruebas Funcionales

**PRUEBA EXISTENTE:** RecoveredWorkflowTest contiene 62 métodos para registro, inscripciones, reglas deportivas, fixture, arbitraje, nóminas, incidencias, privacidad pública e integraciones simuladas. Feature/ExampleTest agrega GET / con respuesta esperada 200.

Las Feature utilizan RefreshDatabase. phpunit.xml configura SQLite en memoria, sesiones/correo de prueba y WhatsApp deshabilitado inicialmente. Revisar esta configuración no equivale a ejecutar migraciones ni validar MySQL.

### 3.3.3 Pruebas de Caja Negra

**PRUEBAS EXISTENTES:** aprobación de inscripción, denegación a árbitro no asignado, CI duplicado en un campeonato y ocultamiento de datos privados en el portal. Son perspectivas sobre pruebas ya contadas, no otra suite adicional.

| PRUEBA PROPUESTA PARA DOCUMENTACIÓN | Criterio a verificar | Situación actual |
|---|---|---|
| PUT directo sobre nómina aprobada | Impedir cambios del delegado después de aprobar | Falta el bloqueo en guardar; no ejecutada |
| Alta de jugador sin estado | Contrastar estado persistido con mensaje de revisión | Default aprobado; no ejecutada |
| Torneo eliminatorio con número impar de equipos | Verificar pases libres y continuidad | Implementación parcial; no ejecutada |
| Acceso ajeno al detalle autenticado de equipo | Revisar exposición del CI | Limitación identificada estáticamente; no ejecutada |

### 3.3.4 Pruebas de Caja Blanca

**PRUEBAS EXISTENTES:** construcción de fases/rondas, configuración automática del formato y escenario extenso de nóminas/incidencias/suspensión. Inspeccionan datos internos o llaman servicios, además de comprobar respuestas.

**PRUEBAS PROPUESTAS PARA DOCUMENTACIÓN:** fallos entre subida de imagen y guardado en BD; cierre simultáneo de resultados; eliminación de primera amarilla de una doble amarilla; recordatorio con éxito parcial. No se implementaron ni ejecutaron aquí.

**No se encontró una prueba automatizada específica para esta funcionalidad en el repositorio:** recuperación completa de contraseña con token y entrega externa; avance automático de eliminatorias; bloqueo del PUT sobre nómina ya aprobada. La ausencia de prueba no debe confundirse con funcionalidad completa ni con prueba fallida.

## 4. CAPTURAS PRINCIPALES PARA EL DOCUMENTO

Se seleccionan **12 capturas**, figuras 23 a 34. Usar registros existentes y reemplazar identificadores entre llaves por sus valores reales. No se realizaron capturas ni cambios en la plataforma durante este análisis. Si falta el estado necesario, indicar “no disponible con los datos actuales”, sin fabricar evidencia. Ocultar datos personales al publicar imágenes.

### FIGURA 23. Ingreso al sistema

- **Rol:** Sin sesión.
- **Página:** `/login`.
- **Acción:** Abrir el formulario sin enviar credenciales.
- **Debe observarse:** Correo, contraseña, recordar sesión y acceso al registro.

Fuente: Elaboración propia.

### FIGURA 24. Creación de campeonato: datos generales

- **Rol:** Organizador.
- **Página:** `/campeonatos/create`.
- **Acción:** Mostrar paso 1 sin enviar el formulario.
- **Debe observarse:** Nombre, fechas, inscripción, descripción y portada opcional.

Fuente: Elaboración propia.

### FIGURA 25. Creación de campeonato: reglas

- **Rol:** Organizador.
- **Página:** `/campeonatos/create`.
- **Acción:** Avanzar al paso 3 sin guardar.
- **Debe observarse:** Modalidad, jugadores en cancha, mínimos/máximos, sexo y categoría etaria.

Fuente: Elaboración propia.

### FIGURA 26. Revisión de solicitudes de equipos

- **Rol:** Organizador propietario.
- **Página:** `/campeonatos/{campeonato}`.
- **Acción:** Seleccionar Equipos en un torneo con solicitudes existentes.
- **Debe observarse:** Solicitudes pendientes, estado y controles de aprobación/rechazo visibles.

Fuente: Elaboración propia.

### FIGURA 27. Plantilla del equipo

- **Rol:** Delegado propietario.
- **Página:** `/equipos/{equipo}?campeonato={campeonato}`.
- **Acción:** Seleccionar Jugadores.
- **Debe observarse:** Tabla, edad, estado y acciones; usar datos de demostración al publicar CI.

Fuente: Elaboración propia.

### FIGURA 28. Registro de jugador

- **Rol:** Delegado propietario.
- **Página:** `/equipos/{equipo}/jugadores/create`.
- **Acción:** Abrir formulario sin enviarlo.
- **Debe observarse:** Reglas del torneo, identidad, nacimiento, sexo, posición, fotografía y consentimiento.

Fuente: Elaboración propia.

### FIGURA 29. Administración de árbitros

- **Rol:** Organizador propietario.
- **Página:** `/campeonatos/{campeonato}/arbitros`.
- **Acción:** Abrir postulaciones existentes.
- **Debe observarse:** Árbitros, estados y acciones de gestión.

Fuente: Elaboración propia.

### FIGURA 30. Fixture del campeonato

- **Rol:** Organizador propietario.
- **Página:** `/campeonatos/{campeonato}`.
- **Acción:** Seleccionar Partidos en un torneo que ya tenga fixture.
- **Debe observarse:** Fases, jornadas, cruces y programación; no presentar rondas futuras como partidos generados.

Fuente: Elaboración propia.

### FIGURA 31. Presentación de nómina

- **Rol:** Delegado del equipo.
- **Página:** `/partidos/{partido}/nominas/{equipo}/editar`.
- **Acción:** Abrir un partido programado con nómina editable.
- **Debe observarse:** Jugadores elegibles, selección y dorsales; si hay suspendidos existentes, mostrar su restricción.

Fuente: Elaboración propia.

### FIGURA 32. Mesa de control de nóminas

- **Rol:** Organizador propietario.
- **Página:** `/partidos/{partido}/nominas`.
- **Acción:** Abrir encuentro con listas existentes.
- **Debe observarse:** Estado de cada equipo, jugadores/dorsales y acciones de revisión.

Fuente: Elaboración propia.

### FIGURA 33. Registro de incidencias

- **Rol:** Árbitro asignado u organizador propietario.
- **Página:** `/partidos/{partido}/resultado`.
- **Acción:** Abrir partido programado con ambas nóminas aprobadas.
- **Debe observarse:** Marcador calculado, jugadores habilitados y controles de gol/amarilla/roja; no registrar eventos para obtener la captura.

Fuente: Elaboración propia.

### FIGURA 34. Tabla de posiciones de Liga

- **Rol:** Sin sesión.
- **Página:** `/torneos/{campeonato}#tabla`.
- **Acción:** Abrir una Liga con partidos jugados.
- **Debe observarse:** PJ, victorias, empates, derrotas, goles, diferencia y puntos.

Fuente: Elaboración propia.

## 5. ARCHIVOS Y CÓDIGO UTILIZADOS COMO EVIDENCIA

| Funcionalidad | Archivos principales |
|---|---|
| Acceso y roles | [routes/web.php](routes/web.php), [User.php](app/Models/User.php), [CheckRole.php](app/Http/Middleware/CheckRole.php), [RegisterController.php](app/Http/Controllers/Auth/RegisterController.php) |
| Campeonatos y permisos | [CampeonatoController.php](app/Http/Controllers/CampeonatoController.php), [CampeonatoPolicy.php](app/Policies/CampeonatoPolicy.php), [Campeonato.php](app/Models/Campeonato.php) |
| Equipos e inscripción | [EquipoController.php](app/Http/Controllers/EquipoController.php), [EquipoPolicy.php](app/Policies/EquipoPolicy.php), [Equipo.php](app/Models/Equipo.php) |
| Jugadores y elegibilidad | [JugadorController.php](app/Http/Controllers/JugadorController.php), [Jugador.php](app/Models/Jugador.php) |
| Arbitraje | [CampeonatoArbitroController.php](app/Http/Controllers/CampeonatoArbitroController.php), [arbitro_campeonato](database/migrations/2026_08_15_000000_create_arbitro_campeonato_table.php) |
| Fixture | [ConfiguracionFormatoService.php](app/Services/ConfiguracionFormatoService.php), [generadores](app/Services/FormatGenerators), [CampeonatoEliminatoriaController.php](app/Http/Controllers/CampeonatoEliminatoriaController.php) |
| Nóminas | [NominaPartidoController.php](app/Http/Controllers/NominaPartidoController.php), [NominaPartido.php](app/Models/NominaPartido.php), [NominaPartidoJugador.php](app/Models/NominaPartidoJugador.php) |
| Incidencias, resultados y sanción | [IncidenciaController.php](app/Http/Controllers/IncidenciaController.php), [PartidoController.php](app/Http/Controllers/PartidoController.php), [MarcadorPartidoService.php](app/Services/MarcadorPartidoService.php), [SuspensionJugadorService.php](app/Services/SuspensionJugadorService.php) |
| Portal y estadísticas | [PortalPublicoController.php](app/Http/Controllers/PortalPublicoController.php), [TablaPosicionesService.php](app/Services/TablaPosicionesService.php), [vistas públicas](resources/views/publico) |
| Imágenes | [CloudinaryImageService.php](app/Services/CloudinaryImageService.php), [config/services.php](config/services.php) |
| Avisos y recordatorios | [WhatsAppService.php](app/Services/WhatsAppService.php), [NotificadorWhatsAppPartido.php](app/Services/NotificadorWhatsAppPartido.php), [NotificacionPlataforma.php](app/Notifications/NotificacionPlataforma.php), [routes/console.php](routes/console.php) |
| Esquema | [Migraciones](database/migrations); las 34 se revisaron para reconstruir las 19 tablas |
| Presentación | [layout](resources/views/layouts/app.blade.php), [resources/css](resources/css), [resources/js](resources/js), [vite.config.js](vite.config.js) |
| Pruebas | [RecoveredWorkflowTest.php](tests/Feature/RecoveredWorkflowTest.php), [CloudinaryImageServiceTest.php](tests/Unit/CloudinaryImageServiceTest.php), [phpunit.xml](phpunit.xml) |

### Comprobación final y límites

Se releyeron rutas, modelos, controladores y migraciones antes de cerrar la documentación. Las referencias explícitas de rutas a métodos propios se contrastaron estáticamente; los métodos de autenticación se revisaron en los traits locales de Laravel UI. Los diagramas se entregan como código fuente y no como imágenes renderizadas. No se acredita validación visual por un renderizador PlantUML/Mermaid.

La conclusión principal es que la plataforma implementa la gestión administrativa y el control deportivo básico, con limitaciones relevantes en eliminatorias, aprobación inicial de jugadores y protección de cambios de nómina. Estas limitaciones deben permanecer visibles en el Proyecto de Grado.
