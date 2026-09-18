from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_CONNECTOR, MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches, Pt


OUT = Path(__file__).with_name("presentacion_casos_de_uso.pptx")

BG = "07111F"
PANEL = "101E33"
PANEL_2 = "152844"
WHITE = "F5F8FF"
MUTED = "A9B8CF"
CYAN = "32E6E2"
VIOLET = "8B5CF6"
LIME = "B7F34A"
CORAL = "FF6B6B"
YELLOW = "FFC857"


def rgb(value):
    return RGBColor.from_string(value)


prs = Presentation()
prs.slide_width = Inches(13.333)
prs.slide_height = Inches(7.5)
blank = prs.slide_layouts[6]


def rect(slide, x, y, w, h, color, radius=True, line=None):
    kind = MSO_SHAPE.ROUNDED_RECTANGLE if radius else MSO_SHAPE.RECTANGLE
    shape = slide.shapes.add_shape(kind, Inches(x), Inches(y), Inches(w), Inches(h))
    shape.fill.solid()
    shape.fill.fore_color.rgb = rgb(color)
    shape.line.color.rgb = rgb(line or color)
    return shape


def circle(slide, x, y, d, color, line=None):
    shape = slide.shapes.add_shape(MSO_SHAPE.OVAL, Inches(x), Inches(y), Inches(d), Inches(d))
    shape.fill.solid()
    shape.fill.fore_color.rgb = rgb(color)
    shape.line.color.rgb = rgb(line or color)
    return shape


def text(slide, value, x, y, w, h, size=22, color=WHITE, bold=False,
         font="Aptos", align=PP_ALIGN.LEFT, valign=MSO_ANCHOR.TOP):
    box = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    frame = box.text_frame
    frame.clear()
    frame.margin_left = frame.margin_right = Inches(0.03)
    frame.margin_top = frame.margin_bottom = Inches(0.02)
    frame.vertical_anchor = valign
    p = frame.paragraphs[0]
    p.alignment = align
    p.text = value
    p.font.name = font
    p.font.size = Pt(size)
    p.font.bold = bold
    p.font.color.rgb = rgb(color)
    return box


def line(slide, x1, y1, x2, y2, color=CYAN, width=2):
    shape = slide.shapes.add_connector(
        MSO_CONNECTOR.STRAIGHT, Inches(x1), Inches(y1), Inches(x2), Inches(y2)
    )
    shape.line.color.rgb = rgb(color)
    shape.line.width = Pt(width)
    return shape


def base_slide(kicker, title, subtitle=None, accent=CYAN):
    slide = prs.slides.add_slide(blank)
    bg = slide.background.fill
    bg.solid()
    bg.fore_color.rgb = rgb(BG)
    rect(slide, 0, 0, 0.12, 7.5, accent, radius=False)
    text(slide, kicker.upper(), 0.65, 0.40, 5.8, 0.35, 10, accent, True)
    text(slide, title, 0.65, 0.78, 12.0, 0.75, 29, WHITE, True)
    if subtitle:
        text(slide, subtitle, 0.67, 1.49, 11.8, 0.5, 13, MUTED)
    return slide


def add_footer(slide, number):
    text(slide, f"CASOS DE USO  /  {number:02d}", 10.75, 7.12, 1.9, 0.20, 8, MUTED,
         True, align=PP_ALIGN.RIGHT)


def add_notes(slide, value):
    try:
        frame = slide.notes_slide.notes_text_frame
        frame.text = value
    except Exception:
        pass


def card(slide, x, y, w, h, tag, title_value, body, accent=CYAN):
    rect(slide, x, y, w, h, PANEL)
    rect(slide, x, y, 0.08, h, accent, radius=False)
    text(slide, tag.upper(), x + 0.25, y + 0.22, w - 0.45, 0.24, 9, accent, True)
    text(slide, title_value, x + 0.25, y + 0.57, w - 0.45, 0.43, 17, WHITE, True)
    text(slide, body, x + 0.25, y + 1.05, w - 0.45, h - 1.18, 11, MUTED)


# 1 — Portada
slide = prs.slides.add_slide(blank)
bg = slide.background.fill
bg.solid()
bg.fore_color.rgb = rgb(BG)
rect(slide, 0, 0, 13.333, 0.12, CYAN, radius=False)
circle(slide, 9.15, 0.85, 2.7, PANEL_2, VIOLET)
circle(slide, 10.75, 2.55, 1.35, VIOLET)
circle(slide, 8.55, 4.25, 1.05, CYAN)
line(slide, 9.5, 2.4, 11.1, 3.0, VIOLET, 3)
line(slide, 9.4, 3.2, 9.0, 4.7, CYAN, 3)
line(slide, 10.0, 2.9, 11.3, 4.7, LIME, 2)
circle(slide, 11.0, 4.45, 0.6, LIME)
text(slide, "TECNOLOGÍA QUE CONECTA", 0.78, 0.78, 6.5, 0.35, 11, CYAN, True)
text(slide, "Sistemas digitales\nen acción", 0.75, 1.35, 7.8, 1.75, 38, WHITE, True)
text(slide, "Comercio · Banca · Medicina\nRedes sociales · Apps móviles", 0.80, 3.45, 6.8, 1.0, 19, MUTED)
rect(slide, 0.80, 5.45, 5.25, 0.68, PANEL)
text(slide, "CASOS DE USO Y RETOS COMPARTIDOS", 1.08, 5.67, 4.7, 0.25, 11, WHITE, True)
text(slide, "Presentación visual · 10 minutos", 0.82, 6.55, 4.7, 0.3, 10, MUTED)
add_notes(slide, "Presenta el tema: veremos cómo una misma base tecnológica resuelve necesidades muy diferentes en cinco ámbitos cotidianos.")


# 2 — Panorama
slide = base_slide("01 · Panorama", "Un sistema, cinco mundos", "Los datos conectan personas, procesos y decisiones.")
cx, cy = 6.25, 4.2
circle(slide, cx - 0.78, cy - 0.78, 1.56, VIOLET)
text(slide, "DATOS", cx - 0.55, cy - 0.15, 1.1, 0.3, 17, WHITE, True, align=PP_ALIGN.CENTER)
items = [
    (1.05, 2.4, "01", "COMERCIO", CYAN),
    (1.25, 5.15, "02", "BANCA", YELLOW),
    (5.05, 5.55, "03", "MEDICINA", CORAL),
    (9.35, 5.15, "04", "REDES", VIOLET),
    (9.55, 2.4, "05", "MÓVILES", LIME),
]
for x, y, num, label, accent in items:
    line(slide, cx, cy, x + 1.15, y + 0.6, accent, 2)
    rect(slide, x, y, 2.3, 1.15, PANEL)
    circle(slide, x + 0.18, y + 0.23, 0.62, accent)
    text(slide, num, x + 0.18, y + 0.40, 0.62, 0.2, 10, BG, True, align=PP_ALIGN.CENTER)
    text(slide, label, x + 0.92, y + 0.38, 1.15, 0.25, 11, WHITE, True)
add_footer(slide, 2)
add_notes(slide, "Explica que todos los ámbitos capturan datos, los procesan y convierten el resultado en una acción o decisión.")


# 3 — Comercio
slide = base_slide("02 · Sistemas comerciales", "Del clic a la entrega", "Automatizan ventas, inventario, pagos y relación con clientes.", CYAN)
steps = [(0.8, "CLIENTE"), (3.2, "TIENDA"), (5.6, "PAGO"), (8.0, "STOCK"), (10.4, "ENTREGA")]
for idx, (x, label) in enumerate(steps):
    circle(slide, x, 2.35, 0.78, CYAN if idx in (0, 4) else PANEL_2, CYAN)
    text(slide, str(idx + 1), x, 2.61, 0.78, 0.18, 10, WHITE, True, align=PP_ALIGN.CENTER)
    text(slide, label, x - 0.25, 3.25, 1.3, 0.25, 10, WHITE, True, align=PP_ALIGN.CENTER)
    if idx < len(steps) - 1:
        line(slide, x + 0.8, 2.74, steps[idx + 1][0], 2.74, CYAN, 2)
card(slide, 0.8, 4.25, 3.7, 1.65, "Impacto", "Más eficiencia", "Procesos rápidos y menos errores manuales.", CYAN)
card(slide, 4.82, 4.25, 3.7, 1.65, "Ejemplo", "Comercio electrónico", "Recomendaciones, carrito, cobro y seguimiento.", VIOLET)
card(slide, 8.85, 4.25, 3.7, 1.65, "Clave", "Experiencia", "Personalización basada en historial y preferencias.", LIME)
add_footer(slide, 3)
add_notes(slide, "Usa una compra en línea como ejemplo: el sistema coordina catálogo, pago, inventario y logística en segundos.")


# 4 — Banca
slide = base_slide("03 · Sistemas bancarios", "Confianza en tiempo real", "Cada operación combina velocidad, precisión y seguridad.", YELLOW)
circle(slide, 0.95, 2.35, 2.25, YELLOW)
text(slide, "24/7", 1.18, 2.94, 1.8, 0.5, 30, BG, True, align=PP_ALIGN.CENTER)
text(slide, "OPERACIONES", 1.25, 3.55, 1.65, 0.25, 10, BG, True, align=PP_ALIGN.CENTER)
card(slide, 3.75, 2.25, 2.65, 1.55, "Acceso", "Banca digital", "Consultas, pagos y transferencias.", CYAN)
card(slide, 6.65, 2.25, 2.65, 1.55, "Análisis", "Crédito", "Evaluación de riesgo y capacidad de pago.", VIOLET)
card(slide, 9.55, 2.25, 2.65, 1.55, "Defensa", "Antifraude", "Detecta patrones y operaciones inusuales.", CORAL)
rect(slide, 3.75, 4.35, 8.45, 1.25, PANEL_2)
text(slide, "IDENTIDAD", 4.05, 4.74, 1.35, 0.25, 11, YELLOW, True)
text(slide, "→", 5.42, 4.68, 0.5, 0.3, 18, MUTED, True)
text(slide, "CIFRADO", 6.00, 4.74, 1.2, 0.25, 11, CYAN, True)
text(slide, "→", 7.30, 4.68, 0.5, 0.3, 18, MUTED, True)
text(slide, "VALIDACIÓN", 7.92, 4.74, 1.5, 0.25, 11, VIOLET, True)
text(slide, "→", 9.55, 4.68, 0.5, 0.3, 18, MUTED, True)
text(slide, "ALERTA", 10.22, 4.74, 1.2, 0.25, 11, CORAL, True)
add_footer(slide, 4)
add_notes(slide, "Destaca que la banca necesita disponibilidad permanente, pero nunca a costa de la seguridad y la trazabilidad.")


# 5 — Medicina
slide = base_slide("04 · Sistemas médicos", "Datos que ayudan a cuidar", "La información correcta debe llegar a la persona correcta, en el momento correcto.", CORAL)
phases = [
    (0.85, "REGISTRO", "Historia clínica"),
    (3.85, "ANÁLISIS", "Pruebas e imágenes"),
    (6.85, "DECISIÓN", "Apoyo diagnóstico"),
    (9.85, "SEGUIMIENTO", "Telemedicina"),
]
for idx, (x, label, desc) in enumerate(phases):
    rect(slide, x, 2.35, 2.55, 1.65, PANEL)
    circle(slide, x + 0.18, 2.58, 0.48, CORAL if idx % 2 == 0 else VIOLET)
    text(slide, str(idx + 1), x + 0.18, 2.72, 0.48, 0.16, 9, WHITE, True, align=PP_ALIGN.CENTER)
    text(slide, label, x + 0.80, 2.62, 1.5, 0.22, 10, CORAL, True)
    text(slide, desc, x + 0.25, 3.28, 2.05, 0.35, 14, WHITE, True)
    if idx < 3:
        line(slide, x + 2.55, 3.18, phases[idx + 1][0], 3.18, MUTED, 2)
rect(slide, 0.85, 4.55, 11.55, 1.18, PANEL_2)
text(slide, "PRIVACIDAD", 1.20, 4.92, 1.55, 0.25, 12, CORAL, True)
text(slide, "Consentimiento · acceso controlado · datos protegidos · supervisión humana", 2.82, 4.89, 8.95, 0.32, 14, WHITE)
add_footer(slide, 5)
add_notes(slide, "Aclara que estos sistemas apoyan al personal médico; no sustituyen su criterio. La privacidad del paciente es esencial.")


# 6 — Redes sociales
slide = base_slide("05 · Redes sociales", "El motor de la atención", "Contenido, interacción y algoritmos forman un ciclo continuo.", VIOLET)
labels = [
    (1.0, 2.65, "CREAR", CYAN),
    (4.0, 2.05, "PUBLICAR", VIOLET),
    (7.1, 2.65, "RECOMENDAR", CORAL),
    (9.95, 4.15, "INTERACTUAR", LIME),
    (6.45, 5.25, "APRENDER", YELLOW),
    (2.6, 4.65, "MEDIR", CYAN),
]
for idx, (x, y, label, accent) in enumerate(labels):
    rect(slide, x, y, 2.15, 0.85, PANEL)
    text(slide, f"{idx + 1:02d}", x + 0.18, y + 0.28, 0.42, 0.2, 10, accent, True)
    text(slide, label, x + 0.68, y + 0.28, 1.25, 0.2, 10, WHITE, True)
    nxt = labels[(idx + 1) % len(labels)]
    line(slide, x + 2.15, y + 0.42, nxt[0], nxt[1] + 0.42, accent, 1)
circle(slide, 5.55, 3.55, 1.75, PANEL_2, VIOLET)
text(slide, "ALGORITMO", 5.76, 4.17, 1.35, 0.22, 12, WHITE, True, align=PP_ALIGN.CENTER)
text(slide, "Reto: evitar burbujas, desinformación y uso excesivo.", 3.35, 6.33, 6.9, 0.3, 13, MUTED, align=PP_ALIGN.CENTER)
add_footer(slide, 6)
add_notes(slide, "Describe el ciclo: cada interacción genera señales que el algoritmo usa para decidir qué mostrar después.")


# 7 — Aplicaciones móviles
slide = base_slide("06 · Aplicaciones móviles", "Todo cabe en tu bolsillo", "Una interfaz conecta sensores, servicios digitales y necesidades reales.", LIME)
phone = rect(slide, 0.95, 2.15, 3.05, 4.35, PANEL_2, True, LIME)
rect(slide, 1.18, 2.58, 2.60, 3.35, BG)
rect(slide, 1.80, 2.32, 1.36, 0.10, MUTED, radius=False)
circle(slide, 2.20, 6.10, 0.28, PANEL, MUTED)
text(slide, "APP", 1.70, 3.62, 1.60, 0.45, 27, LIME, True, align=PP_ALIGN.CENTER)
text(slide, "simple · útil · segura", 1.44, 4.33, 2.10, 0.25, 10, MUTED, align=PP_ALIGN.CENTER)
card(slide, 4.65, 2.15, 3.55, 1.35, "Contexto", "Sensores", "GPS, cámara, biometría y movimiento.", CYAN)
card(slide, 8.55, 2.15, 3.55, 1.35, "Conexión", "Servicios", "Mapas, pagos, nube y notificaciones.", VIOLET)
card(slide, 4.65, 4.05, 3.55, 1.35, "Diseño", "Experiencia", "Interacciones rápidas y accesibles.", LIME)
card(slide, 8.55, 4.05, 3.55, 1.35, "Protección", "Permisos", "Solo los datos realmente necesarios.", CORAL)
add_footer(slide, 7)
add_notes(slide, "Menciona ejemplos cotidianos: transporte, delivery, educación y salud. El buen diseño móvil reduce pasos y protege permisos.")


# 8 — Comparación
slide = base_slide("07 · Comparación", "Misma tecnología, distinta prioridad", "El valor cambia según el contexto de uso.", CYAN)
headers = ["ÁMBITO", "PRIORIDAD", "DATO CLAVE", "RIESGO"]
widths = [2.3, 3.1, 3.1, 3.45]
xs = [0.72, 3.02, 6.12, 9.22]
for x, w, value in zip(xs, widths, headers):
    rect(slide, x, 2.10, w, 0.62, VIOLET, radius=False)
    text(slide, value, x + 0.18, 2.31, w - 0.35, 0.18, 10, WHITE, True)
rows = [
    ("Comercio", "Eficiencia", "Preferencias", "Fraude"),
    ("Banca", "Confianza", "Transacciones", "Ciberataque"),
    ("Medicina", "Precisión", "Historia clínica", "Privacidad"),
    ("Redes", "Interacción", "Comportamiento", "Manipulación"),
    ("Móviles", "Usabilidad", "Ubicación", "Permisos"),
]
accents = [CYAN, YELLOW, CORAL, VIOLET, LIME]
for r, row in enumerate(rows):
    y = 2.78 + r * 0.68
    color = PANEL if r % 2 == 0 else PANEL_2
    for c, (x, w, value) in enumerate(zip(xs, widths, row)):
        rect(slide, x, y, w, 0.60, color, radius=False)
        text(slide, value, x + 0.18, y + 0.19, w - 0.30, 0.19, 11,
             accents[r] if c == 0 else WHITE, c == 0)
add_footer(slide, 8)
add_notes(slide, "Compara prioridades: no existe un sistema ideal universal; el diseño debe responder al contexto y al riesgo principal.")


# 9 — Retos compartidos
slide = base_slide("08 · Diseño responsable", "Innovar sin perder la confianza", "Los beneficios crecen cuando la tecnología es segura, ética e inclusiva.", CORAL)
circle(slide, 5.55, 2.28, 2.25, CORAL)
text(slide, "CONFIANZA", 5.82, 3.10, 1.70, 0.32, 17, BG, True, align=PP_ALIGN.CENTER)
risks = [
    (0.85, 2.25, "01", "SEGURIDAD", "Proteger accesos y sistemas.", CYAN),
    (0.85, 4.65, "02", "PRIVACIDAD", "Usar solo datos necesarios.", VIOLET),
    (9.65, 2.25, "03", "ÉTICA", "Evitar sesgos y manipulación.", YELLOW),
    (9.65, 4.65, "04", "INCLUSIÓN", "Diseñar para todas las personas.", LIME),
]
for x, y, num, title_value, body, accent in risks:
    line(slide, 6.67, 3.40, x + (2.85 if x < 5 else 0), y + 0.72, accent, 2)
    rect(slide, x, y, 2.85, 1.45, PANEL)
    text(slide, num, x + 0.20, y + 0.22, 0.48, 0.22, 10, accent, True)
    text(slide, title_value, x + 0.74, y + 0.22, 1.75, 0.22, 11, WHITE, True)
    text(slide, body, x + 0.22, y + 0.78, 2.38, 0.38, 10, MUTED)
add_footer(slide, 9)
add_notes(slide, "Resume los retos transversales. Una solución útil puede fracasar si las personas no confían en ella.")


# 10 — Cierre
slide = base_slide("09 · Conclusión", "La tecnología cambia.\nEl propósito conecta.", "Cinco ámbitos, una misma idea: convertir información en mejores decisiones.", CYAN)
card(slide, 0.82, 3.15, 3.70, 1.70, "Idea 01", "Automatizar", "Reduce tiempo y errores en tareas repetitivas.", CYAN)
card(slide, 4.82, 3.15, 3.70, 1.70, "Idea 02", "Personalizar", "Adapta servicios a cada necesidad y contexto.", VIOLET)
card(slide, 8.82, 3.15, 3.70, 1.70, "Idea 03", "Proteger", "La innovación sostenible comienza con confianza.", LIME)
rect(slide, 3.65, 5.55, 6.05, 0.82, VIOLET)
text(slide, "¿PREGUNTAS?", 3.65, 5.81, 6.05, 0.28, 17, WHITE, True, align=PP_ALIGN.CENTER)
add_footer(slide, 10)
add_notes(slide, "Cierra recordando las tres ideas: automatizar, personalizar y proteger. Invita al público a hacer preguntas.")


prs.core_properties.title = "Sistemas digitales en acción"
prs.core_properties.subject = "Casos de uso en comercio, banca, medicina, redes sociales y aplicaciones móviles"
prs.core_properties.author = "Presentación académica"
if __name__ == "__main__":
    prs.save(OUT)
    print(OUT.resolve())
