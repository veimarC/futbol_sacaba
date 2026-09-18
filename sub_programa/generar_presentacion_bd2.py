from pathlib import Path

from pptx import Presentation
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches

import generar_presentacion as ui


ROOT = Path(__file__).parent
OUT = ROOT / "PRESENTACION_BD2_MUNDO_REAL.pptx"
COVER = ROOT / "portada_bd_mundo_real.png"

# Reutilizamos el sistema visual del primer archivo, pero iniciamos una presentación nueva.
ui.prs = Presentation()
ui.prs.slide_width = Inches(13.333)
ui.prs.slide_height = Inches(7.5)
ui.blank = ui.prs.slide_layouts[6]


def notes(slide, value):
    try:
        slide.notes_slide.notes_text_frame.text = value
    except Exception:
        pass


def header(slide, section, number, accent=ui.CYAN):
    ui.rect(slide, 0, 0, 13.333, 0.10, accent, radius=False)
    ui.text(slide, section.upper(), 0.67, 0.34, 7.0, 0.23, 9, accent, True)
    ui.text(slide, f"{number:02d} / 10", 11.60, 0.34, 1.0, 0.23, 9, ui.MUTED, True,
            align=PP_ALIGN.RIGHT)


def titled(section, number, title, subtitle, accent=ui.CYAN):
    slide = ui.prs.slides.add_slide(ui.blank)
    fill = slide.background.fill
    fill.solid()
    fill.fore_color.rgb = ui.rgb(ui.BG)
    header(slide, section, number, accent)
    ui.text(slide, title, 0.67, 0.76, 12.0, 0.70, 29, ui.WHITE, True)
    ui.text(slide, subtitle, 0.69, 1.47, 11.7, 0.40, 12, ui.MUTED)
    return slide


def story(slide, y, marker, label, headline, body, accent):
    ui.circle(slide, 4.12, y + 0.08, 0.50, accent)
    ui.text(slide, marker, 4.12, y + 0.25, 0.50, 0.16, 9, ui.BG, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, label.upper(), 4.86, y, 2.4, 0.22, 9, accent, True)
    ui.text(slide, headline, 4.86, y + 0.30, 7.2, 0.34, 16, ui.WHITE, True)
    ui.text(slide, body, 4.86, y + 0.75, 7.25, 0.44, 10, ui.MUTED)


def case_slide(number, domain, title, subtitle, accent, code):
    slide = titled(domain, number, title, subtitle, accent)
    ui.rect(slide, 0.70, 2.10, 2.93, 3.90, ui.PANEL, line=accent)
    ui.text(slide, code, 0.97, 2.48, 2.40, 0.86, 37, accent, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, domain.upper(), 0.97, 3.48, 2.40, 0.30, 11, ui.WHITE, True,
            align=PP_ALIGN.CENTER)
    return slide


def quote(slide, value, accent):
    ui.rect(slide, 4.08, 6.18, 8.45, 0.72, ui.PANEL_2, line=accent)
    ui.text(slide, f"“{value}”", 4.32, 6.42, 7.97, 0.25, 13, ui.WHITE, True,
            align=PP_ALIGN.CENTER)


# 1. Portada
slide = ui.prs.slides.add_slide(ui.blank)
fill = slide.background.fill
fill.solid()
fill.fore_color.rgb = ui.rgb(ui.BG)
if COVER.exists():
    slide.shapes.add_picture(str(COVER), 0, 0, width=ui.prs.slide_width,
                             height=ui.prs.slide_height)
ui.rect(slide, 0, 0, 5.88, 7.50, ui.BG, radius=False)
ui.rect(slide, 0.74, 0.62, 1.62, 0.38, ui.CYAN)
ui.text(slide, "BASE DE DATOS 2", 0.74, 0.75, 1.62, 0.15, 8, ui.BG, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "BASES DE DATOS\nEN EL MUNDO REAL", 0.72, 1.42, 5.12, 1.65, 30,
        ui.WHITE, True)
ui.rect(slide, 0.74, 3.34, 3.60, 0.07, ui.VIOLET, radius=False)
ui.text(slide, "5 casos de uso que\nmueven al mundo", 0.74, 3.72, 4.65, 0.90, 20,
        ui.CYAN, True)
ui.text(slide, "CURSO  ·  Base de Datos 2", 0.76, 5.45, 4.55, 0.23, 10,
        ui.MUTED, True)
ui.text(slide, "ESTUDIANTE  ·  [Tu nombre]", 0.76, 5.90, 4.55, 0.23, 10,
        ui.MUTED, True)
ui.text(slide, "FECHA  ·  [Fecha de exposición]", 0.76, 6.35, 4.55, 0.23, 10,
        ui.MUTED, True)
notes(slide, "Abre con una pausa: hoy veremos las bases de datos como sistemas invisibles que sostienen acciones cotidianas.")


# 2. Pregunta inicial
slide = titled("El gancho", 2, "¿Qué tienen en común estas acciones?",
               "Cinco momentos cotidianos. Una infraestructura invisible.", ui.VIOLET)
actions = [
    ("01", "COMPRAR", "un producto", ui.CYAN),
    ("02", "TRANSFERIR", "dinero", ui.YELLOW),
    ("03", "CONSULTAR", "al médico", ui.CORAL),
    ("04", "DAR LIKE", "a una foto", ui.VIOLET),
    ("05", "ENVIAR", "un mensaje", ui.LIME),
]
for idx, (num, verb, detail, accent) in enumerate(actions):
    x = 0.72 + idx * 2.47
    ui.rect(slide, x, 2.30, 2.10, 2.33, ui.PANEL, line=accent)
    ui.circle(slide, x + 0.69, 2.65, 0.72, accent)
    ui.text(slide, num, x + 0.69, 2.89, 0.72, 0.17, 10, ui.BG, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, verb, x + 0.18, 3.62, 1.74, 0.23, 11, ui.WHITE, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, detail, x + 0.18, 4.06, 1.74, 0.22, 10, ui.MUTED,
            align=PP_ALIGN.CENTER)
ui.rect(slide, 3.70, 5.38, 5.93, 0.78, ui.PANEL_2, line=ui.VIOLET)
ui.text(slide, "PISTA  ·  No es simplemente que usen internet…", 3.92, 5.65,
        5.48, 0.22, 12, ui.WHITE, True, align=PP_ALIGN.CENTER)
notes(slide, "Pregunta al público y espera respuestas. No reveles la solución inmediatamente.")


# 3. La respuesta
slide = titled("La respuesta", 3, "¡Todas usan una base de datos!",
               "No es una carpeta gigante: es memoria, reglas, relaciones y velocidad.", ui.CYAN)
ui.text(slide, "EL CEREBRO\nOPERATIVO", 0.82, 2.45, 3.45, 1.18, 27, ui.WHITE, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "que hace funcionar\ncada sistema", 0.82, 3.88, 3.45, 0.68, 15,
        ui.MUTED, align=PP_ALIGN.CENTER)
for offset, accent in [(0, ui.VIOLET), (0.55, ui.CYAN), (1.10, ui.VIOLET)]:
    ui.shape = slide.shapes.add_shape(
        MSO_SHAPE.CAN, Inches(5.50), Inches(2.25 + offset),
        Inches(2.60), Inches(1.12)
    )
    ui.shape.fill.solid()
    ui.shape.fill.fore_color.rgb = ui.rgb(ui.PANEL_2)
    ui.shape.line.color.rgb = ui.rgb(accent)
for idx, (label, accent) in enumerate([
    ("GUARDA", ui.VIOLET), ("RELACIONA", ui.CYAN),
    ("VALIDA", ui.YELLOW), ("RESPONDE", ui.LIME)
]):
    x = 8.72
    y = 2.17 + idx * 0.82
    ui.line(slide, 8.10, 3.12, x, y + 0.28, accent, 1.5)
    ui.rect(slide, x, y, 2.45, 0.57, ui.PANEL, line=accent)
    ui.text(slide, label, x, y + 0.20, 2.45, 0.17, 10, accent, True,
            align=PP_ALIGN.CENTER)
ui.rect(slide, 1.70, 5.72, 9.95, 0.72, ui.VIOLET)
ui.text(slide, "Sin datos confiables, una aplicación solo es una pantalla bonita.",
        1.95, 5.96, 9.45, 0.24, 14, ui.WHITE, True, align=PP_ALIGN.CENTER)
notes(slide, "Diferencia almacenar de gestionar: una BD mantiene relaciones, reglas, consistencia y acceso eficiente.")


# 4. Mapa
slide = titled("La ruta", 4, "Un problema real. Una solución de BD2.",
               "Cinco casos para comprender qué debe garantizar cada diseño.", ui.CYAN)
route = [
    ("01", "COMERCIO", "ACID + bloqueo", ui.CYAN),
    ("02", "BANCA", "Atomicidad", ui.YELLOW),
    ("03", "MEDICINA", "Integridad", ui.CORAL),
    ("04", "REDES", "Índices", ui.VIOLET),
    ("05", "MÓVILES", "Sincronización", ui.LIME),
]
for idx, (num, domain, tech, accent) in enumerate(route):
    x = 0.72 + idx * 2.48
    ui.circle(slide, x + 0.53, 2.43, 1.24, accent)
    ui.text(slide, num, x + 0.53, 2.82, 1.24, 0.25, 17, ui.BG, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, domain, x, 4.02, 2.30, 0.23, 11, ui.WHITE, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, tech, x, 4.45, 2.30, 0.23, 10, accent, True,
            align=PP_ALIGN.CENTER)
    if idx < 4:
        ui.line(slide, x + 1.77, 3.05, x + 3.01, 3.05, ui.MUTED, 2)
ui.rect(slide, 2.63, 5.55, 8.05, 0.72, ui.PANEL_2, line=ui.CYAN)
ui.text(slide, "OBJETIVO  ·  pasar de “guardar datos” a garantizar resultados",
        2.87, 5.80, 7.55, 0.21, 12, ui.WHITE, True, align=PP_ALIGN.CENTER)
notes(slide, "Cada caso se explicará como acción, fallo posible y solución de Base de Datos 2.")


# 5. Comercio
slide = case_slide(5, "Sistemas comerciales", "Dos clientes. Un último producto.",
                   "¿Cómo evitamos vender una unidad dos veces?", ui.CYAN, "01")
story(slide, 2.02, "A", "Acción", "Dos compras ocurren al mismo tiempo",
      "Ambas sesiones leen: stock = 1.", ui.CYAN)
story(slide, 3.34, "!", "Fallo posible", "Las dos ventas se confirman",
      "Sin control de concurrencia terminamos con stock negativo.", ui.CORAL)
story(slide, 4.66, "BD", "Solución BD2", "Transacción ACID + bloqueo de fila",
      "La actualización valida stock > 0; después hace COMMIT o ROLLBACK.", ui.LIME)
ui.text(slide, "BEGIN", 1.10, 4.25, 2.12, 0.22, 11, ui.CYAN, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "validar  →  descontar", 1.02, 4.73, 2.28, 0.22, 10, ui.WHITE, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "COMMIT / ROLLBACK", 1.02, 5.20, 2.28, 0.22, 10, ui.LIME, True,
        align=PP_ALIGN.CENTER)
quote(slide, "Una venta rápida también debe ser una venta correcta.", ui.CYAN)
notes(slide, "ACID mantiene consistencia; el control de concurrencia impide vender dos veces la misma unidad.")


# 6. Banca
slide = case_slide(6, "Sistemas bancarios", "El dinero no puede quedar a medias.",
                   "Una transferencia es una sola operación lógica.", ui.YELLOW, "02")
story(slide, 2.02, "A", "Acción", "Debitar 100 y acreditar 100",
      "Dos cuentas cambian, pero el resultado debe ser indivisible.", ui.CYAN)
story(slide, 3.34, "!", "Fallo posible", "El sistema cae después del débito",
      "La cuenta origen baja, pero el destino todavía no recibe el dinero.", ui.CORAL)
story(slide, 4.66, "BD", "Solución BD2", "Atomicidad + ROLLBACK + auditoría",
      "O se confirman ambos movimientos o se deshacen; además queda un registro.", ui.LIME)
ui.text(slide, "−100", 0.94, 4.33, 0.85, 0.30, 18, ui.CORAL, True,
        align=PP_ALIGN.CENTER)
ui.line(slide, 1.84, 4.49, 2.54, 4.49, ui.YELLOW, 3)
ui.text(slide, "+100", 2.50, 4.33, 0.85, 0.30, 18, ui.LIME, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "TODO O NADA", 1.03, 5.10, 2.20, 0.25, 12, ui.YELLOW, True,
        align=PP_ALIGN.CENTER)
quote(slide, "Tu dinero nunca debe quedar en el limbo.", ui.YELLOW)
notes(slide, "ROLLBACK no actúa solo: forma parte de una transacción atómica que confirma o deshace el conjunto.")


# 7. Medicina
slide = case_slide(7, "Sistemas médicos", "La identidad también es integridad.",
                   "Paciente, consulta, diagnóstico y tratamiento deben coincidir.", ui.CORAL, "03")
story(slide, 2.02, "A", "Acción", "Revisar el historial antes de recetar",
      "El médico necesita antecedentes completos y de la persona correcta.", ui.CYAN)
story(slide, 3.34, "!", "Fallo posible", "Registros duplicados o mal relacionados",
      "Un error puede ocultar alergias o tratamientos anteriores.", ui.CORAL)
story(slide, 4.66, "BD", "Solución BD2", "ID único + claves foráneas + acceso",
      "Las relaciones preservan integridad; verificar identidad sigue siendo esencial.", ui.LIME)
for idx, label in enumerate(["PACIENTE", "CONSULTA", "RECETA"]):
    y = 4.08 + idx * 0.54
    ui.rect(slide, 1.03, y, 2.20, 0.39, ui.PANEL_2,
            line=ui.CORAL if idx == 0 else ui.VIOLET)
    ui.text(slide, label, 1.03, y + 0.13, 2.20, 0.14, 8, ui.WHITE, True,
            align=PP_ALIGN.CENTER)
quote(slide, "En salud, un dato correcto puede proteger una vida.", ui.CORAL)
notes(slide, "Un UUID identifica un registro, pero vincular personas entre hospitales también requiere verificación e interoperabilidad.")


# 8. Redes sociales
slide = case_slide(8, "Redes sociales", "Mil amigos. Un feed en segundos.",
                   "La consulta correcta importa tanto como la infraestructura.", ui.VIOLET, "04")
story(slide, 2.02, "A", "Acción", "Abrir el feed y ver contenido reciente",
      "La plataforma filtra, ordena y pagina grandes volúmenes.", ui.CYAN)
story(slide, 3.34, "!", "Fallo posible", "Escanear millones de filas",
      "Una consulta sin estrategia se vuelve lenta y costosa.", ui.CORAL)
story(slide, 4.66, "BD", "Solución BD2", "Índice compuesto + paginación + caché",
      "Ejemplo: (usuario_id, fecha DESC). Un feed real combina más técnicas.", ui.LIME)
ui.text(slide, "usuario_id", 1.02, 4.22, 2.24, 0.23, 11, ui.VIOLET, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "+", 1.02, 4.65, 2.24, 0.20, 14, ui.MUTED, True,
        align=PP_ALIGN.CENTER)
ui.text(slide, "fecha DESC", 1.02, 5.08, 2.24, 0.23, 11, ui.CYAN, True,
        align=PP_ALIGN.CENTER)
quote(slide, "Un buen índice evita buscar página por página.", ui.VIOLET)
notes(slide, "Compara el índice con el de un libro; en gran escala también se usan caché, particiones y servicios especializados.")


# 9. Aplicaciones móviles
slide = case_slide(9, "Aplicaciones móviles", "Sin señal no significa sin memoria.",
                   "La app trabaja localmente y sincroniza después.", ui.LIME, "05")
story(slide, 2.02, "A", "Acción", "Enviar un mensaje sin conexión",
      "La interfaz debe responder aunque la red desaparezca.", ui.CYAN)
story(slide, 3.34, "!", "Fallo posible", "Perder o duplicar el mensaje",
      "Los reintentos sin control pueden repetir una operación.", ui.CORAL)
story(slide, 4.66, "BD", "Solución BD2", "SQLite + cola + sincronización idempotente",
      "Se guarda pendiente, se reintenta y el servidor evita duplicados.", ui.LIME)
ui.rect(slide, 1.02, 4.16, 2.18, 0.52, ui.PANEL_2, line=ui.LIME)
ui.text(slide, "PENDIENTE", 1.02, 4.34, 2.18, 0.17, 9, ui.LIME, True,
        align=PP_ALIGN.CENTER)
ui.line(slide, 2.11, 4.69, 2.11, 5.03, ui.MUTED, 2)
ui.rect(slide, 1.02, 5.04, 2.18, 0.52, ui.PANEL_2, line=ui.CYAN)
ui.text(slide, "SINCRONIZADO", 1.02, 5.22, 2.18, 0.17, 9, ui.CYAN, True,
        align=PP_ALIGN.CENTER)
quote(slide, "Tu celular lleva una mini base de datos en el bolsillo.", ui.LIME)
notes(slide, "Explica el patrón outbox: guardar como pendiente y sincronizar al recuperar la conexión.")


# 10. Cierre
slide = titled("La conclusión", 10, "Una base de datos bien diseñada…",
               "…no solo guarda información: garantiza resultados confiables.", ui.CYAN)
ideas = [
    ("01", "PROTEGE", "el dinero", ui.YELLOW),
    ("02", "CUIDA", "la identidad", ui.CORAL),
    ("03", "ACELERA", "las respuestas", ui.VIOLET),
    ("04", "CONECTA", "sin conexión", ui.LIME),
    ("05", "MANTIENE", "la confianza", ui.CYAN),
]
for idx, (num, verb, detail, accent) in enumerate(ideas):
    x = 0.72 + idx * 2.48
    ui.rect(slide, x, 2.30, 2.10, 2.22, ui.PANEL, line=accent)
    ui.text(slide, num, x + 0.18, 2.55, 1.74, 0.22, 11, accent, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, verb, x + 0.16, 3.18, 1.78, 0.24, 12, ui.WHITE, True,
            align=PP_ALIGN.CENTER)
    ui.text(slide, detail, x + 0.16, 3.72, 1.78, 0.22, 10, ui.MUTED,
            align=PP_ALIGN.CENTER)
ui.rect(slide, 1.78, 5.17, 9.77, 1.10, ui.VIOLET)
ui.text(slide, "La pregunta ya no es “¿dónde guardo los datos?”", 2.05, 5.43,
        9.23, 0.22, 11, ui.WHITE, True, align=PP_ALIGN.CENTER)
ui.text(slide, "sino “¿qué debo garantizar con ellos?”", 2.05, 5.84,
        9.23, 0.27, 16, ui.WHITE, True, align=PP_ALIGN.CENTER)
ui.text(slide, "¿Preguntas?", 5.25, 6.75, 2.82, 0.28, 13, ui.CYAN, True,
        align=PP_ALIGN.CENTER)
notes(slide, "Resume: consistencia, atomicidad, integridad, rendimiento y sincronización. Termina con la pregunta final.")


ui.prs.core_properties.title = "Bases de datos en el mundo real"
ui.prs.core_properties.subject = "Cinco casos de uso de Base de Datos 2"
ui.prs.core_properties.author = "[Tu nombre]"
ui.prs.core_properties.comments = "Presentación editable con notas para la exposición"
ui.prs.save(OUT)
print(OUT.resolve())
