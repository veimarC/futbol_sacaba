# Inscripciones con cupos reservados

## Uso

En un campeonato aprobado que todavía no tenga fixture, el organizador define los **cupos para equipos que ingresen después** y la **jornada límite** al generar el calendario.

- Con cero cupos se conserva el cierre al generar el fixture.
- Con cupos se reemplaza el límite por fecha por el límite por jornada. Se admiten solicitudes y aprobaciones hasta esa jornada inclusive.
- El límite debe ser anterior a la última jornada planificada de liga o grupos. Se pueden reservar hasta 32 cupos, sin superar la cantidad de equipos ya aprobados.
- Se admite liga, liga de ida y vuelta y grupos con eliminatorias. No se admite eliminación directa ni formatos antiguos de series.
- Una solicitud pendiente no reserva una plaza. La plaza se ocupa al aprobarla y se vuelve a comprobar disponibilidad y plazo.
- Los cupos y el límite quedan fijados al generar el fixture. No se modifica retroactivamente un calendario existente sin reservas.

## Cierre

El ingreso se bloquea cuando un partido de una jornada posterior tiene resultado, incidencias, una nómina aprobada o ha alcanzado su fecha/hora programada. Si tiene fecha sin hora se considera el inicio de ese día. Una jornada adelantada también cierra el plazo; no es necesario que terminen todos los encuentros anteriores.

También se bloquea si se ocupan todas las plazas, termina el campeonato, se genera la eliminatoria o el organizador cierra las inscripciones. La aprobación de nóminas, los resultados y la reprogramación conservan el cierre para que una corrección posterior no vuelva a abrirlo. Las consultas aplican el límite temporal aunque no haya tareas programadas.

El botón **Cerrar inscripciones definitivamente** permite concluir antes el plazo. Las solicitudes restantes pueden rechazarse, pero no aprobarse. Un equipo retirado no libera una plaza ni permite reutilizar su historial.

## Fixture y resultados

Las reservas se guardan en la metadata de las fases, sin equipos ficticios ni partidos con rivales nulos. Se conserva el calendario completo por posiciones. Al aprobar un equipo se asigna una posición libre y se crean solamente los encuentros cuyos dos rivales ya están definidos.

Los nuevos partidos mantienen su jornada original, quedan programados sin fecha/hora/cancha y requieren programación por parte del organizador. Esto incluye los pendientes de jornadas anteriores. Los partidos existentes, sus fechas, resultados y estadísticas no se modifican.

En grupos se ocupa primero un cupo de los grupos con menos participantes, con desempate por orden del grupo. Los clasificados por grupo se calculan sobre los equipos iniciales, no sobre plazas vacías, y no se incrementan automáticamente al admitir equipos. No se generan eliminatorias mientras puedan ingresar equipos; después deben estar finalizados todos los partidos reales de los grupos.

Una plaza que queda vacía no genera puntos, goles ni W.O. Los equipos cuyos rivales todavía no ingresaron esperan su definición; si ingresan, tendrán que disputar los partidos pendientes. No se permite añadir o eliminar manualmente encuentros del calendario reservado, pero sí programar o reprogramar los existentes.

## Integridad y pruebas

La generación, las solicitudes y las aprobaciones usan transacciones con bloqueo del campeonato. La aprobación comprueba otra vez la situación de la solicitud, la disponibilidad, el delegado y las coincidencias de CI. No se requiere migración: se utilizan `formato_config` y `metadata` existentes.

Las pruebas `InscripcionTardiaTest` cubren conservación de resultados, cierre por jornada y fecha, incorporación en grupos, ida/vuelta, cupos agotados, retirados, aprobación repetida, permisos, bloqueo de cambios manuales y reversión de una configuración inválida.
