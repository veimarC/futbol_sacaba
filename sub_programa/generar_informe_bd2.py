from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor


ROOT = Path(__file__).parent
OUT = ROOT / "INFORME_BASES_DE_DATOS_MUNDO_REAL.docx"
COVER = ROOT / "portada_bd_mundo_real.png"

NAVY = "07111F"
BLUE = "123A63"
CYAN = "00A7A7"
VIOLET = "6842C2"
LIGHT = "EAF4F7"
GRAY = "5D6B7A"
WHITE = "FFFFFF"
CORAL = "D84C63"


def color(hex_value):
    return RGBColor.from_string(hex_value)


def shade(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_text(cell, value, bold=False, font_color=NAVY, size=9):
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    r = p.add_run(value)
    r.bold = bold
    r.font.name = "Aptos"
    r.font.size = Pt(size)
    r.font.color.rgb = color(font_color)
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER


def set_repeat_table_header(row):
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = paragraph.add_run("Página ")
    run.font.size = Pt(8)
    fld_char1 = OxmlElement("w:fldChar")
    fld_char1.set(qn("w:fldCharType"), "begin")
    instr_text = OxmlElement("w:instrText")
    instr_text.set(qn("xml:space"), "preserve")
    instr_text.text = "PAGE"
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char1)
    run._r.append(instr_text)
    run._r.append(fld_char2)


def add_heading(doc, value, level=1):
    p = doc.add_heading(value, level=level)
    p.paragraph_format.keep_with_next = True
    return p


def add_body(doc, value, bold_prefix=None):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.space_after = Pt(7)
    p.paragraph_format.line_spacing = 1.15
    if bold_prefix and value.startswith(bold_prefix):
        r = p.add_run(bold_prefix)
        r.bold = True
        p.add_run(value[len(bold_prefix):])
    else:
        p.add_run(value)
    return p


def add_bullets(doc, items):
    for item in items:
        p = doc.add_paragraph(style="List Bullet")
        p.paragraph_format.space_after = Pt(3)
        p.add_run(item)


def add_numbered(doc, items):
    for item in items:
        p = doc.add_paragraph(style="List Number")
        p.paragraph_format.space_after = Pt(3)
        p.add_run(item)


def add_code(doc, code_text):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    shade(cell, NAVY)
    cell.margin_top = cell.margin_bottom = Cm(0.15)
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    for idx, line in enumerate(code_text.strip().splitlines()):
        if idx:
            p.add_run().add_break()
        r = p.add_run(line)
        r.font.name = "Consolas"
        r.font.size = Pt(8.5)
        r.font.color.rgb = color("D8F3F5")
    doc.add_paragraph().paragraph_format.space_after = Pt(0)


def add_case_box(doc, action, problem, solution, guarantee):
    table = doc.add_table(rows=4, cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    labels = [
        ("ACCIÓN", action, CYAN),
        ("PROBLEMA", problem, CORAL),
        ("SOLUCIÓN BD2", solution, VIOLET),
        ("GARANTÍA", guarantee, BLUE),
    ]
    for row, (label, value, accent) in zip(table.rows, labels):
        row.cells[0].width = Cm(3.3)
        row.cells[1].width = Cm(12.5)
        shade(row.cells[0], accent)
        shade(row.cells[1], LIGHT)
        set_cell_text(row.cells[0], label, True, WHITE, 9)
        set_cell_text(row.cells[1], value, False, NAVY, 9)
    doc.add_paragraph()


doc = Document()
section = doc.sections[0]
section.top_margin = Cm(2.0)
section.bottom_margin = Cm(1.8)
section.left_margin = Cm(2.5)
section.right_margin = Cm(2.2)

# Estilos globales
styles = doc.styles
styles["Normal"].font.name = "Aptos"
styles["Normal"].font.size = Pt(10.5)
styles["Normal"].font.color.rgb = color(NAVY)
styles["Title"].font.name = "Aptos Display"
styles["Title"].font.color.rgb = color(NAVY)
styles["Heading 1"].font.name = "Aptos Display"
styles["Heading 1"].font.size = Pt(20)
styles["Heading 1"].font.color.rgb = color(BLUE)
styles["Heading 2"].font.name = "Aptos Display"
styles["Heading 2"].font.size = Pt(14)
styles["Heading 2"].font.color.rgb = color(VIOLET)
styles["Heading 3"].font.name = "Aptos"
styles["Heading 3"].font.size = Pt(11.5)
styles["Heading 3"].font.color.rgb = color(CYAN)

# Encabezado y pie
header = section.header.paragraphs[0]
header.text = "BASE DE DATOS 2   |   BASES DE DATOS EN EL MUNDO REAL"
header.alignment = WD_ALIGN_PARAGRAPH.RIGHT
for run in header.runs:
    run.font.name = "Aptos"
    run.font.size = Pt(8)
    run.font.bold = True
    run.font.color.rgb = color(GRAY)
page_number(section.footer.paragraphs[0])

# Portada
if COVER.exists():
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(COVER), width=Inches(6.75))

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(18)
r = p.add_run("BASES DE DATOS EN EL MUNDO REAL")
r.bold = True
r.font.name = "Aptos Display"
r.font.size = Pt(27)
r.font.color.rgb = color(NAVY)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Informe detallado de cinco casos de uso que mueven al mundo")
r.italic = True
r.font.size = Pt(14)
r.font.color.rgb = color(CYAN)

doc.add_paragraph()
cover_data = [
    ("Curso", "Base de Datos 2"),
    ("Estudiante", "[Tu nombre]"),
    ("Docente", "[Nombre del docente]"),
    ("Fecha", "[Fecha de presentación]"),
]
table = doc.add_table(rows=len(cover_data), cols=2)
table.alignment = WD_TABLE_ALIGNMENT.CENTER
table.autofit = False
for row, (label, value) in zip(table.rows, cover_data):
    row.cells[0].width = Cm(4.0)
    row.cells[1].width = Cm(9.0)
    shade(row.cells[0], NAVY)
    shade(row.cells[1], LIGHT)
    set_cell_text(row.cells[0], label.upper(), True, WHITE, 9)
    set_cell_text(row.cells[1], value, False, NAVY, 10)

doc.add_page_break()

# Contenido y resumen
add_heading(doc, "Contenido del informe", 1)
contents = [
    "Resumen ejecutivo",
    "Introducción y objetivos",
    "Fundamentos de Base de Datos 2",
    "Caso 1: sistemas comerciales",
    "Caso 2: sistemas bancarios",
    "Caso 3: sistemas médicos",
    "Caso 4: redes sociales",
    "Caso 5: aplicaciones móviles",
    "Análisis comparativo y retos transversales",
    "Conclusiones y recomendaciones",
    "Referencias bibliográficas",
]
add_numbered(doc, contents)

add_heading(doc, "Resumen ejecutivo", 1)
add_body(doc, "Las bases de datos forman parte de la infraestructura invisible que sostiene gran parte de las actividades digitales modernas. Comprar un producto, transferir dinero, consultar un historial clínico, abrir una red social o enviar un mensaje desde un teléfono parecen acciones distintas, pero todas dependen de datos organizados, reglas de integridad y mecanismos que permiten responder de forma segura y eficiente.")
add_body(doc, "Este informe analiza cinco escenarios representativos desde la perspectiva de Base de Datos 2. En los sistemas comerciales, el reto central es controlar la concurrencia para evitar la sobreventa. En la banca, la atomicidad impide que una transferencia quede incompleta. En medicina, las claves y restricciones preservan las relaciones entre pacientes y eventos clínicos, mientras que la seguridad controla quién puede acceder a información sensible. En redes sociales, los índices y la paginación reducen el costo de recuperar contenido. En aplicaciones móviles, una base local y una estrategia de sincronización permiten trabajar sin conexión.")
add_body(doc, "La conclusión principal es que una base de datos bien diseñada no se limita a almacenar información. Su función consiste en garantizar propiedades concretas: consistencia, atomicidad, integridad, rendimiento, disponibilidad, trazabilidad y protección de los datos.")

doc.add_page_break()

# Introducción y objetivos
add_heading(doc, "1. Introducción", 1)
add_body(doc, "En la vida cotidiana, el usuario suele observar únicamente la interfaz de una aplicación. Sin embargo, detrás de cada botón existe una capa de datos que registra hechos, conecta entidades y aplica reglas. Cuando una tienda confirma una compra, debe comprobar el inventario; cuando un banco ejecuta una transferencia, debe modificar dos saldos como una unidad; cuando un médico consulta un expediente, debe acceder al paciente correcto. Estas garantías no aparecen por casualidad: son el resultado de decisiones de modelado, transacciones, restricciones, índices y mecanismos de seguridad.")
add_body(doc, "Un sistema gestor de bases de datos, o SGBD, ofrece herramientas para definir estructuras, consultar registros, controlar accesos, coordinar operaciones simultáneas y recuperarse ante fallos. En una asignatura de Base de Datos 2, el interés se desplaza desde la creación básica de tablas hacia problemas reales como la concurrencia, el rendimiento, la integridad referencial, la recuperación y la distribución de datos.")

add_heading(doc, "1.1 Objetivo general", 2)
add_body(doc, "Analizar la aplicación de conceptos avanzados de bases de datos en sistemas comerciales, bancarios, médicos, redes sociales y aplicaciones móviles, identificando el problema que resuelve cada técnica y la garantía que ofrece al usuario.")

add_heading(doc, "1.2 Objetivos específicos", 2)
add_bullets(doc, [
    "Explicar el papel de las transacciones ACID y el control de concurrencia en operaciones comerciales.",
    "Describir cómo la atomicidad y el rollback protegen una transferencia bancaria.",
    "Relacionar identificadores, claves foráneas, interoperabilidad y control de acceso en información clínica.",
    "Analizar la utilidad y los límites de los índices para recuperar contenido de redes sociales.",
    "Explicar el almacenamiento local y la sincronización en aplicaciones que funcionan sin conexión.",
    "Comparar las prioridades, riesgos y decisiones de diseño presentes en los cinco ámbitos.",
])

add_heading(doc, "1.3 Metodología", 2)
add_body(doc, "Se utiliza un análisis de casos. Cada ámbito se estudia mediante cuatro elementos: acción del usuario, fallo posible, solución basada en conceptos de BD2 y garantía obtenida. El contenido se contrasta con documentación oficial de PostgreSQL, SQLite, Android Developers y HL7/FHIR.")

doc.add_page_break()

# Fundamentos
add_heading(doc, "2. Fundamentos de Base de Datos 2", 1)
add_heading(doc, "2.1 De almacenar datos a garantizar resultados", 2)
add_body(doc, "Una base de datos organiza información mediante estructuras y relaciones, pero su valor real aparece cuando impone condiciones sobre el comportamiento del sistema. Una restricción puede impedir un precio negativo; una clave foránea evita que una consulta médica apunte a un paciente inexistente; una transacción evita que solo se complete la mitad de una operación.")

add_heading(doc, "2.2 Propiedades ACID", 2)
acid = [
    ("Atomicidad", "La operación ocurre completamente o no ocurre. Si falla una parte, se deshacen sus efectos."),
    ("Consistencia", "Cada transacción lleva la base desde un estado válido a otro estado válido, respetando reglas y restricciones."),
    ("Aislamiento", "Las transacciones concurrentes no deben observar estados intermedios incompatibles."),
    ("Durabilidad", "Una vez confirmado el COMMIT, el resultado debe permanecer incluso ante fallos posteriores."),
]
table = doc.add_table(rows=1, cols=2)
table.alignment = WD_TABLE_ALIGNMENT.CENTER
table.style = "Table Grid"
set_cell_text(table.rows[0].cells[0], "PROPIEDAD", True, WHITE, 9)
set_cell_text(table.rows[0].cells[1], "SIGNIFICADO PRÁCTICO", True, WHITE, 9)
shade(table.rows[0].cells[0], BLUE)
shade(table.rows[0].cells[1], BLUE)
set_repeat_table_header(table.rows[0])
for idx, (name, meaning) in enumerate(acid):
    cells = table.add_row().cells
    shade(cells[0], LIGHT if idx % 2 == 0 else WHITE)
    shade(cells[1], LIGHT if idx % 2 == 0 else WHITE)
    set_cell_text(cells[0], name, True, VIOLET, 9)
    set_cell_text(cells[1], meaning, False, NAVY, 9)

add_body(doc, "La documentación de PostgreSQL define una transacción como un conjunto de pasos tratado como una operación de «todo o nada». También explica que los cambios intermedios permanecen invisibles para otras transacciones hasta la confirmación y que ROLLBACK cancela los cambios realizados dentro del bloque transaccional (PostgreSQL Global Development Group, 2026).")

add_heading(doc, "2.3 Concurrencia y aislamiento", 2)
add_body(doc, "La concurrencia aparece cuando varias sesiones leen o modifican los mismos datos al mismo tiempo. Sin coordinación pueden ocurrir lecturas sucias, lecturas no repetibles, datos fantasma o anomalías de serialización. Los niveles de aislamiento equilibran consistencia y rendimiento. PostgreSQL señala que el nivel Serializable busca un efecto equivalente a ejecutar las transacciones una después de otra, aunque una aplicación debe estar preparada para reintentar operaciones rechazadas por conflictos.")

add_heading(doc, "2.4 Integridad e índices", 2)
add_body(doc, "Las claves primarias identifican filas y las claves foráneas exigen que un valor relacionado exista en la tabla referenciada. Esta regla mantiene la integridad referencial. Los índices, por su parte, aceleran ciertos patrones de consulta a cambio de espacio y costo adicional en las escrituras. En índices B-tree de varias columnas, el orden importa: las condiciones sobre las columnas iniciales suelen determinar la porción del índice que puede reducirse de manera más eficiente.")

doc.add_page_break()

# Caso 1
add_heading(doc, "3. Caso 1: sistemas comerciales", 1)
add_case_box(
    doc,
    "Dos clientes intentan comprar simultáneamente la última unidad de un producto.",
    "Ambas sesiones leen stock = 1 y confirman su pedido, generando sobreventa.",
    "Transacción ACID, validación del stock y control de concurrencia mediante actualización atómica o bloqueo de fila.",
    "El inventario no queda negativo y solo una compra puede consumir la última unidad.",
)
add_heading(doc, "3.1 Contexto", 2)
add_body(doc, "Un sistema comercial conecta catálogo, pedidos, inventario, pagos y logística. Una venta no consiste únicamente en insertar un pedido; también puede reservar existencias, calcular descuentos, registrar el pago y preparar un envío. Estas acciones deben coordinarse incluso cuando muchos clientes operan al mismo tiempo.")

add_heading(doc, "3.2 Problema de concurrencia", 2)
add_body(doc, "Supóngase que quedan una unidad y dos compradores. Si ambos leen el inventario antes de que cualquiera lo modifique, los dos pueden interpretar que existe disponibilidad. Una implementación ingenua resta una unidad en cada sesión y termina con stock negativo o con dos pedidos confirmados para un solo artículo. Esta anomalía se conoce normalmente como actualización perdida o condición de carrera, según el flujo concreto.")

add_heading(doc, "3.3 Solución transaccional", 2)
add_body(doc, "Una estrategia consiste en actualizar el inventario de forma condicional dentro de una transacción. La condición stock > 0 hace que la propia escritura participe en la decisión. Otra opción es bloquear la fila mediante SELECT ... FOR UPDATE, que en PostgreSQL impide que otras transacciones modifiquen o bloqueen esa fila hasta que termine la transacción actual.")
add_code(doc, """
BEGIN;

UPDATE productos
SET stock = stock - 1
WHERE id = 42 AND stock > 0;

-- Confirmar solo si se actualizó exactamente una fila.
INSERT INTO pedidos (cliente_id, producto_id, estado)
VALUES (7, 42, 'confirmado');

COMMIT;   -- o ROLLBACK ante cualquier error
""")
add_heading(doc, "3.4 Consideraciones", 2)
add_bullets(doc, [
    "La operación debe ser breve para no retener bloqueos innecesariamente.",
    "La aplicación debe comprobar cuántas filas fueron actualizadas.",
    "El pago externo requiere patrones adicionales; no debe asumirse que una transacción SQL controla servicios de terceros.",
    "Conviene registrar eventos de inventario para auditoría y conciliación.",
])

doc.add_page_break()

# Caso 2
add_heading(doc, "4. Caso 2: sistemas bancarios", 1)
add_case_box(
    doc,
    "Transferir 100 unidades monetarias desde una cuenta origen hacia una cuenta destino.",
    "El sistema falla después de descontar el origen y antes de acreditar el destino.",
    "Una única transacción atómica que incluye débito, crédito, validaciones y registro contable; ROLLBACK ante fallo.",
    "La transferencia se completa entera o no produce ningún cambio.",
)
add_heading(doc, "4.1 Atomicidad financiera", 2)
add_body(doc, "Una transferencia contiene como mínimo dos movimientos relacionados. Tratar cada UPDATE como una operación independiente permitiría estados parciales. La atomicidad reúne los pasos en una unidad lógica: o todos se confirman o ninguno conserva efecto. El ejemplo oficial de transacciones de PostgreSQL utiliza precisamente un traslado de saldo entre cuentas para explicar esta garantía.")
add_code(doc, """
BEGIN;

UPDATE cuentas
SET saldo = saldo - 100
WHERE id = 10 AND saldo >= 100;

UPDATE cuentas
SET saldo = saldo + 100
WHERE id = 25;

INSERT INTO movimientos
    (cuenta_origen, cuenta_destino, monto, tipo)
VALUES
    (10, 25, 100, 'transferencia');

COMMIT;
""")
add_heading(doc, "4.2 Papel del rollback", 2)
add_body(doc, "ROLLBACK es el mecanismo que cancela los cambios de una transacción no confirmada. No debe presentarse como una solución aislada: funciona porque los pasos fueron agrupados previamente dentro de una transacción. También pueden utilizarse SAVEPOINT para deshacer una sección concreta sin abandonar todo el bloque, aunque una transferencia simple normalmente debe confirmarse como una unidad.")

add_heading(doc, "4.3 Requisitos adicionales", 2)
add_bullets(doc, [
    "Bloqueo o aislamiento suficiente para impedir gastos simultáneos sobre el mismo saldo.",
    "Identificador idempotente para no repetir una transferencia cuando el cliente reintenta.",
    "Libro de movimientos o auditoría inmutable para reconstruir el historial.",
    "Autenticación fuerte, cifrado en tránsito y control de acceso.",
    "Copias de seguridad, replicación y procedimientos de recuperación probados.",
])

doc.add_page_break()

# Caso 3
add_heading(doc, "5. Caso 3: sistemas médicos", 1)
add_case_box(
    doc,
    "Un profesional consulta el historial de un paciente antes de indicar un tratamiento.",
    "Registros duplicados, identidad incorrecta o relaciones rotas pueden ocultar alergias y antecedentes.",
    "Identificador único, claves foráneas, validación de identidad, interoperabilidad y control de acceso.",
    "Cada evento clínico queda asociado al paciente correcto y solo usuarios autorizados acceden a él.",
)
add_heading(doc, "5.1 Integridad de la información clínica", 2)
add_body(doc, "Los sistemas médicos relacionan pacientes, consultas, diagnósticos, resultados, profesionales y tratamientos. Una clave primaria identifica cada registro y una clave foránea impide crear, por ejemplo, una consulta que apunte a un paciente inexistente. PostgreSQL define este comportamiento como mantenimiento de la integridad referencial.")
add_code(doc, """
CREATE TABLE pacientes (
    id UUID PRIMARY KEY,
    documento VARCHAR(30),
    nombre VARCHAR(120) NOT NULL
);

CREATE TABLE consultas (
    id UUID PRIMARY KEY,
    paciente_id UUID NOT NULL REFERENCES pacientes(id),
    profesional_id UUID NOT NULL,
    fecha TIMESTAMP NOT NULL,
    observaciones TEXT
);
""")
add_heading(doc, "5.2 Por qué un UUID no es suficiente", 2)
add_body(doc, "Un UUID reduce la probabilidad de colisión y resulta útil en sistemas distribuidos, pero identifica un registro, no demuestra por sí mismo que dos registros de hospitales diferentes correspondan a la misma persona. La vinculación entre instituciones necesita reglas de identidad, verificación de datos demográficos, identificadores institucionales y estándares de interoperabilidad.")
add_body(doc, "FHIR, publicado por HL7, define el recurso Patient para representar información administrativa y demográfica sobre la persona que recibe atención. La especificación también advierte que FHIR no es un protocolo de seguridad por sí mismo: el intercambio debe combinarse con TLS, autorización y políticas de acceso.")

add_heading(doc, "5.3 Privacidad y trazabilidad", 2)
add_bullets(doc, [
    "Aplicar el principio de mínimo privilegio según función y contexto.",
    "Registrar quién consultó o modificó información sensible.",
    "Cifrar comunicaciones y proteger copias de seguridad.",
    "Gestionar consentimiento, finalidad y tiempo de conservación.",
    "Evitar mostrar más información clínica de la necesaria.",
])

doc.add_page_break()

# Caso 4
add_heading(doc, "6. Caso 4: redes sociales", 1)
add_case_box(
    doc,
    "El usuario abre su feed y espera publicaciones recientes de sus contactos.",
    "Una exploración completa sobre millones de registros provoca latencia y alto consumo.",
    "Índice alineado con filtros y ordenamiento, paginación estable y caché selectiva.",
    "La consulta recupera rápidamente un subconjunto relevante sin leer toda la tabla.",
)
add_heading(doc, "6.1 El costo de buscar sin estrategia", 2)
add_body(doc, "Una tabla de publicaciones puede crecer rápidamente. Si una consulta filtra por autor y ordena por fecha, un índice compuesto puede reducir el trabajo. PostgreSQL explica que un índice puede abarcar varias columnas y que, en B-tree, suele ser más eficiente cuando las restricciones utilizan las columnas iniciales del índice.")
add_code(doc, """
CREATE INDEX idx_publicaciones_autor_fecha
ON publicaciones (autor_id, fecha_publicacion DESC);

SELECT id, autor_id, contenido, fecha_publicacion
FROM publicaciones
WHERE autor_id = 250
  AND fecha_publicacion < :ultima_fecha
ORDER BY fecha_publicacion DESC
LIMIT 20;
""")
add_heading(doc, "6.2 Paginación y caché", 2)
add_body(doc, "La paginación por cursor, usando la fecha y un identificador estable, suele evitar el costo creciente de OFFSET en páginas profundas. La caché puede conservar resultados frecuentes, pero introduce el problema de invalidación: si cambia un registro, debe decidirse cuándo actualizar o descartar la copia almacenada.")

add_heading(doc, "6.3 Límites de la simplificación", 2)
add_body(doc, "Un feed moderno no se resuelve únicamente con un índice. Puede incluir ranking, afinidad, moderación, particionamiento, colas de eventos y almacenamiento distribuido. El índice es una pieza importante para determinados patrones de lectura, pero debe diseñarse a partir de consultas reales y verificarse mediante el plan de ejecución.")
add_bullets(doc, [
    "Los índices aceleran lecturas, pero consumen espacio.",
    "Cada INSERT o UPDATE puede requerir mantener varios índices.",
    "El orden de columnas debe corresponder con filtros y ordenamientos frecuentes.",
    "Un índice innecesario puede empeorar el rendimiento global.",
])

doc.add_page_break()

# Caso 5
add_heading(doc, "7. Caso 5: aplicaciones móviles", 1)
add_case_box(
    doc,
    "El usuario envía un mensaje cuando el dispositivo no dispone de conexión.",
    "La aplicación puede perder el mensaje o enviarlo varias veces durante los reintentos.",
    "Base local SQLite, cola de operaciones pendientes, sincronización y clave idempotente.",
    "La interfaz continúa funcionando y el servidor procesa cada operación lógica una sola vez.",
)
add_heading(doc, "7.1 Base de datos local", 2)
add_body(doc, "SQLite es un motor SQL embebido, sin proceso de servidor independiente y almacenado normalmente en un archivo. Estas características lo hacen adecuado para persistencia local. Su documentación señala que ofrece transacciones y que permite múltiples lectores simultáneos, aunque solo una transacción de escritura al mismo tiempo.")
add_body(doc, "La guía de arquitectura de Android recomienda una fuente de datos local como fuente de verdad para aplicaciones offline-first. Las lecturas se sirven desde el almacenamiento local y, cuando regresa la conectividad, el repositorio coordina la actualización con la fuente de red.")

add_heading(doc, "7.2 Cola de salida y estados", 2)
add_code(doc, """
CREATE TABLE mensajes_pendientes (
    id_local TEXT PRIMARY KEY,
    conversacion_id TEXT NOT NULL,
    contenido TEXT NOT NULL,
    creado_en TEXT NOT NULL,
    estado TEXT NOT NULL
        CHECK (estado IN ('pendiente', 'enviando', 'enviado', 'error'))
);
""")
add_body(doc, "Cuando el usuario envía un mensaje, la aplicación lo guarda primero como pendiente. Un proceso en segundo plano intenta enviarlo cuando existe conectividad. Si el servidor lo acepta, el estado cambia a enviado. Si el intento falla, la operación permanece disponible para reintento.")

add_heading(doc, "7.3 Idempotencia y conflictos", 2)
add_body(doc, "El identificador local debe acompañar cada reintento. El servidor guarda esa clave y devuelve el resultado anterior si recibe la misma operación otra vez. Así se evita duplicar mensajes o pagos por fallos de red. Cuando local y remoto modifican el mismo dato, el sistema necesita una política de conflictos: última escritura, versión, fusión manual o regla específica del negocio.")
add_bullets(doc, [
    "La sincronización debe tolerar interrupciones y reanudarse.",
    "Los reintentos necesitan límites y espera progresiva.",
    "Los datos sensibles del dispositivo deben cifrarse cuando corresponda.",
    "El usuario debe ver estados claros: pendiente, enviado o error.",
])

doc.add_page_break()

# Comparación
add_heading(doc, "8. Análisis comparativo", 1)
table = doc.add_table(rows=1, cols=5)
table.alignment = WD_TABLE_ALIGNMENT.CENTER
table.style = "Table Grid"
headers = ["ÁMBITO", "RIESGO PRINCIPAL", "TÉCNICA BD2", "GARANTÍA", "COSTO / RETO"]
for cell, value in zip(table.rows[0].cells, headers):
    shade(cell, BLUE)
    set_cell_text(cell, value, True, WHITE, 8)
set_repeat_table_header(table.rows[0])
rows = [
    ("Comercio", "Sobreventa", "ACID y concurrencia", "Consistencia", "Bloqueos y reintentos"),
    ("Banca", "Operación parcial", "Atomicidad y rollback", "Todo o nada", "Alta disponibilidad"),
    ("Medicina", "Identidad incorrecta", "Claves, integridad y acceso", "Trazabilidad", "Privacidad e interoperabilidad"),
    ("Redes", "Consultas lentas", "Índices y paginación", "Rendimiento", "Costo de escrituras"),
    ("Móviles", "Pérdida o duplicado", "BD local y sincronización", "Continuidad", "Conflictos y conectividad"),
]
for idx, values in enumerate(rows):
    cells = table.add_row().cells
    for cell, value in zip(cells, values):
        shade(cell, LIGHT if idx % 2 == 0 else WHITE)
        set_cell_text(cell, value, False, NAVY, 8)

add_heading(doc, "8.1 Elementos comunes", 2)
add_body(doc, "Los cinco sistemas comparten un ciclo: capturar datos, validarlos, persistirlos, relacionarlos, consultarlos y convertirlos en una acción. Lo que cambia es la prioridad. En comercio domina la coordinación del inventario; en banca, la atomicidad; en medicina, la integridad y confidencialidad; en redes sociales, la latencia; y en móviles, la continuidad entre estados de conexión.")

add_heading(doc, "8.2 Retos transversales", 2)
add_bullets(doc, [
    "Seguridad: autenticación, autorización, cifrado y gestión de secretos.",
    "Privacidad: minimización, consentimiento, finalidad y retención de datos.",
    "Disponibilidad: replicación, monitoreo y tolerancia a fallos.",
    "Recuperación: copias de seguridad verificadas y objetivos RPO/RTO.",
    "Observabilidad: métricas, registros y trazas para detectar anomalías.",
    "Escalabilidad: particionamiento, réplicas de lectura y arquitectura adecuada al volumen.",
    "Calidad: restricciones, validaciones, deduplicación y gobierno de datos.",
])

add_heading(doc, "8.3 La importancia de medir", 2)
add_body(doc, "Ninguna técnica debe elegirse únicamente por moda. Un índice necesita justificarse con consultas y planes de ejecución; un nivel de aislamiento debe corresponder al riesgo de anomalías; una caché necesita objetivos de frescura; una sincronización requiere métricas de reintentos y conflictos. La ingeniería de datos combina garantías funcionales con mediciones de rendimiento y operación.")

doc.add_page_break()

# Conclusiones
add_heading(doc, "9. Conclusiones", 1)
conclusions = [
    "Las bases de datos actúan como el cerebro operativo de numerosos sistemas, porque además de almacenar aplican relaciones, restricciones y reglas de ejecución.",
    "Las transacciones ACID permiten tratar varios pasos como una unidad y son esenciales cuando un resultado parcial produciría inconsistencias.",
    "El control de concurrencia es imprescindible en sistemas con múltiples usuarios; una lectura aparentemente simple puede quedar obsoleta antes de la escritura.",
    "En banca, ROLLBACK debe entenderse dentro de una transacción atómica, acompañado de auditoría e idempotencia.",
    "En salud, un identificador único y las claves foráneas ayudan a preservar la integridad, pero no reemplazan la verificación de identidad, la interoperabilidad ni los controles de privacidad.",
    "Los índices mejoran patrones específicos de lectura; no aceleran todo y deben equilibrarse con su costo de mantenimiento.",
    "Las aplicaciones offline-first necesitan una fuente local, estados explícitos, reintentos e idempotencia para evitar pérdida o duplicación.",
    "El diseño correcto depende de la garantía que el sistema debe ofrecer, no únicamente de la tecnología seleccionada.",
]
add_numbered(doc, conclusions)

add_heading(doc, "10. Recomendaciones", 1)
add_bullets(doc, [
    "Definir primero las invariantes del negocio: qué condiciones nunca deben romperse.",
    "Mantener las transacciones tan pequeñas como sea posible, pero tan completas como exija la integridad.",
    "Probar operaciones concurrentes y no limitar las pruebas al flujo de un solo usuario.",
    "Aplicar restricciones en la base de datos además de validaciones en la interfaz.",
    "Diseñar índices desde consultas observadas y revisar periódicamente su utilidad.",
    "Usar identificadores idempotentes en operaciones que puedan repetirse por red o reintentos.",
    "Proteger datos sensibles mediante mínimo privilegio, cifrado y auditoría.",
    "Realizar pruebas reales de restauración; una copia no verificada no constituye un plan de recuperación.",
])

add_heading(doc, "11. Glosario básico", 1)
glossary = [
    ("ACID", "Conjunto de propiedades: atomicidad, consistencia, aislamiento y durabilidad."),
    ("COMMIT", "Confirmación permanente de una transacción."),
    ("ROLLBACK", "Cancelación de los cambios no confirmados de una transacción."),
    ("Concurrencia", "Ejecución simultánea o solapada de varias operaciones."),
    ("Clave primaria", "Atributo o conjunto de atributos que identifica una fila."),
    ("Clave foránea", "Restricción que enlaza valores con una fila existente de otra tabla."),
    ("Índice", "Estructura auxiliar que acelera determinados accesos a datos."),
    ("Idempotencia", "Propiedad por la que repetir una petición produce el mismo efecto lógico que ejecutarla una vez."),
    ("SQLite", "Motor SQL embebido y sin servidor independiente."),
    ("FHIR", "Estándar de HL7 para intercambio de información sanitaria."),
]
for term, definition in glossary:
    add_body(doc, f"{term}: {definition}", bold_prefix=f"{term}:")

doc.add_page_break()

# Bibliografía
add_heading(doc, "12. Referencias bibliográficas", 1)
refs = [
    "Android Developers. (2026). Data layer: App architecture. https://developer.android.com/topic/architecture/data-layer",
    "Android Developers. (2026). Build an offline-first app. https://developer.android.com/topic/architecture/data-layer/offline-first",
    "HL7 International. (s. f.). FHIR R5: Patient Resource. https://hl7.org/fhir/patient.html",
    "HL7 International. (s. f.). FHIR Security. https://www.hl7.org/fhir/security.html",
    "PostgreSQL Global Development Group. (2026). Constraints. https://www.postgresql.org/docs/current/ddl-constraints.html",
    "PostgreSQL Global Development Group. (2026). Explicit Locking. https://www.postgresql.org/docs/current/explicit-locking.html",
    "PostgreSQL Global Development Group. (2026). Multicolumn Indexes. https://www.postgresql.org/docs/current/indexes-multicolumn.html",
    "PostgreSQL Global Development Group. (2026). Transaction Isolation. https://www.postgresql.org/docs/current/transaction-iso.html",
    "PostgreSQL Global Development Group. (2026). Transactions. https://www.postgresql.org/docs/current/tutorial-transactions.html",
    "SQLite Consortium. (2025). About SQLite. https://www.sqlite.org/about.html",
    "SQLite Consortium. (2026). Transaction. https://www.sqlite.org/lang_transaction.html",
]
for ref in refs:
    p = doc.add_paragraph(style="List Bullet")
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.left_indent = Cm(0.65)
    p.paragraph_format.first_line_indent = Cm(-0.4)
    p.add_run(ref)

add_body(doc, "Fecha de consulta de las fuentes web: 17 de agosto de 2026.")

doc.core_properties.title = "Informe: Bases de datos en el mundo real"
doc.core_properties.subject = "Cinco casos de uso de Base de Datos 2"
doc.core_properties.author = "[Tu nombre]"
doc.core_properties.comments = "Informe académico generado a partir de la presentación del mismo título."
doc.save(OUT)
print(OUT.resolve())
