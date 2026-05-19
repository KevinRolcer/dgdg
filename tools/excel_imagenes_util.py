"""Utilidades compartidas: anclar fotos en celda sin borrar imágenes del .xlsx."""
from __future__ import annotations

import os
import shutil
import xml.etree.ElementTree as ET
import zipfile
from datetime import datetime
from io import BytesIO
from pathlib import Path
from warnings import warn

# Reanclar editando solo drawing*.xml dentro del zip (no toca xl/media/).
XDR_NS = "http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"

import openpyxl
from openpyxl.drawing.image import Image
from openpyxl.drawing.spreadsheet_drawing import (
    AnchorClientData,
    AnchorMarker,
    OneCellAnchor,
    TwoCellAnchor,
)
from openpyxl.reader import drawings as openpyxl_drawings
from openpyxl.utils import column_index_from_string
from openpyxl.utils.units import pixels_to_EMU


def aplicar_lectura_tolerante_imagenes() -> None:
    """Evita que openpyxl falle si un JPEG/WMF dentro del .xlsx está corrupto."""
    if getattr(openpyxl_drawings.find_images, "_segob_tolerant", False):
        return

    _original = openpyxl_drawings.find_images

    def find_images(archive, path):
        src = archive.read(path)
        from openpyxl.xml.functions import fromstring
        from openpyxl.drawing.spreadsheet_drawing import SpreadsheetDrawing
        from openpyxl.packaging.relationship import (
            get_rels_path,
            get_dependents,
            get_rel,
        )
        from openpyxl.xml.constants import IMAGE_NS
        from openpyxl.chart.chartspace import ChartSpace
        from openpyxl.chart.reader import read_chart

        tree = fromstring(src)
        try:
            drawing = SpreadsheetDrawing.from_tree(tree)
        except TypeError:
            warn(
                "DrawingML incompleto; solo se conservan gráficas e imágenes soportadas."
            )
            return [], []

        rels_path = get_rels_path(path)
        deps = []
        if rels_path in archive.namelist():
            deps = get_dependents(archive, rels_path)

        charts = []
        for rel in drawing._chart_rels:
            try:
                cs = get_rel(archive, deps, rel.id, ChartSpace)
            except TypeError as e:
                warn(f"No se pudo leer gráfica {rel.id}: {e}")
                continue
            chart = read_chart(cs)
            chart.anchor = rel.anchor
            charts.append(chart)

        images = []
        if not openpyxl_drawings.PILImage:
            return charts, images

        for rel in drawing._blip_rels:
            dep = deps.get(rel.embed)
            if dep is None or dep.Type != IMAGE_NS:
                continue
            try:
                data = archive.read(dep.target)
            except (OSError, zipfile.BadZipFile, KeyError) as e:
                warn(
                    f"Imagen omitida (archivo dañado o ilegible): {dep.target} ({e})"
                )
                continue
            try:
                image = Image(BytesIO(data))
            except OSError:
                warn(f"Imagen omitida (no se puede decodificar): {dep.target}")
                continue
            if image.format.upper() == "WMF":
                warn(f"Imagen WMF omitida (no soportada): {dep.target}")
                continue
            image.anchor = rel.anchor
            images.append(image)

        return charts, images

    find_images._segob_tolerant = True  # type: ignore[attr-defined]
    openpyxl_drawings.find_images = find_images
    # excel.py hace «from .drawings import find_images» al importar; hay que parchear ambos.
    import openpyxl.reader.excel as openpyxl_excel

    openpyxl_excel.find_images = find_images


def load_workbook_seguro(path: str, **kwargs):
    aplicar_lectura_tolerante_imagenes()
    return openpyxl.load_workbook(path, **kwargs)


def crear_respaldo_xlsx(ruta: str) -> str:
    """Copia el .xlsx actual antes de sobrescribirlo. Devuelve la ruta del respaldo."""
    origen = Path(ruta)
    if not origen.is_file():
        raise FileNotFoundError(ruta)
    marca = datetime.now().strftime("%Y%m%d_%H%M%S")
    destino = origen.with_name(f"{origen.stem}_respaldo_{marca}{origen.suffix}")
    shutil.copy2(origen, destino)
    return str(destino)


def guardar_workbook_con_respaldo(wb, ruta: str) -> str:
    """Respaldo + guardado. Usar siempre antes de modificar un libro del usuario."""
    copia = crear_respaldo_xlsx(ruta)
    wb.save(ruta)
    return copia


def pedir_columna(mensaje: str = "Columna con fotos (ej. F, b): ") -> str:
    while True:
        raw = input(mensaje).strip()
        if not raw:
            print("  Indica una letra de columna (A, B, …, Z, AA, …).")
            continue
        letra = raw.upper()
        try:
            column_index_from_string(letra)
        except ValueError:
            print(f"  Columna no válida: {raw!r}")
            continue
        return letra


def _emu_desde_imagen(img: Image, anchor) -> tuple[int, int]:
    if isinstance(anchor, OneCellAnchor):
        ext = anchor.ext
        if ext and (ext.width or ext.height):
            return int(ext.width), int(ext.height)
    elif isinstance(anchor, TwoCellAnchor):
        fr, to = anchor._from, anchor.to
        w = int((to.colOff or 0) - (fr.colOff or 0))
        h = int((to.rowOff or 0) - (fr.rowOff or 0))
        if w > 0 and h > 0:
            return w, h
    w_px = int(getattr(img, "width", None) or 100)
    h_px = int(getattr(img, "height", None) or 100)
    return pixels_to_EMU(w_px), pixels_to_EMU(h_px)


def anclar_imagen_dentro_celda(
    img: Image,
    col_idx: int,
    row_idx: int,
    ancho_emu: int,
    alto_emu: int,
) -> None:
    """Ancla twoCell: la imagen queda dentro de una sola celda (no flotando encima)."""
    anchor = TwoCellAnchor(editAs="twoCell")
    anchor._from = AnchorMarker(col=col_idx, colOff=0, row=row_idx, rowOff=0)
    anchor.to = AnchorMarker(
        col=col_idx,
        colOff=ancho_emu,
        row=row_idx,
        rowOff=alto_emu,
    )
    anchor.clientData = AnchorClientData(
        fLocksWithSheet=True,
        fPrintsWithSheet=True,
    )
    img.anchor = anchor


def _ya_anclada_en_celda(anchor, ancho_emu: int, alto_emu: int) -> bool:
    if not isinstance(anchor, TwoCellAnchor):
        return False
    if getattr(anchor, "editAs", None) != "twoCell":
        return False
    fr, to = anchor._from, anchor.to
    if (fr.colOff or 0) != 0 or (fr.rowOff or 0) != 0:
        return False
    if to.col != fr.col or to.row != fr.row:
        return False
    return int(to.colOff or 0) == ancho_emu and int(to.rowOff or 0) == alto_emu


def normalizar_imagen_en_columna(img: Image, col_idx: int) -> str:
    """
    Devuelve: 'ajustadas' | 'ok' | 'otra_col' | 'sin_tamano' | 'tipo_desconocido'
    """
    anchor = img.anchor
    if not hasattr(anchor, "_from"):
        return "tipo_desconocido"
    fr = anchor._from
    if fr.col != col_idx:
        return "otra_col"

    ancho_emu, alto_emu = _emu_desde_imagen(img, anchor)
    if ancho_emu <= 0 or alto_emu <= 0:
        return "sin_tamano"

    if _ya_anclada_en_celda(anchor, ancho_emu, alto_emu):
        return "ok"

    anclar_imagen_dentro_celda(img, fr.col, fr.row, ancho_emu, alto_emu)
    return "ajustadas"


def reanclar_imagenes_en_columna(ws, col_letter: str) -> dict[str, int]:
    """Reancla vía openpyxl (puede perder WMF al guardar). Preferir reanclar_columna_en_xlsx."""
    col_idx = column_index_from_string(col_letter) - 1
    st = {
        "ajustadas": 0,
        "ok": 0,
        "otra_col": 0,
        "sin_tamano": 0,
        "tipo_desconocido": 0,
    }
    for img in ws._images:
        r = normalizar_imagen_en_columna(img, col_idx)
        st[r] = st.get(r, 0) + 1
    return st


def _xdr(tag: str) -> str:
    return f"{{{XDR_NS}}}{tag}"


def _local_tag(el: ET.Element) -> str:
    return el.tag.rsplit("}", 1)[-1] if "}" in el.tag else el.tag


def _int_text(el: ET.Element | None, default: int = 0) -> int:
    if el is None or el.text is None:
        return default
    return int(el.text)


def _set_child_int(parent: ET.Element, tag: str, value: int) -> None:
    child = parent.find(_xdr(tag))
    if child is None:
        child = ET.SubElement(parent, _xdr(tag))
    child.text = str(value)


def _ya_anclada_xml(from_el: ET.Element, to_el: ET.Element, cx: int, cy: int) -> bool:
    fr_col = _int_text(from_el.find(_xdr("col")))
    fr_row = _int_text(from_el.find(_xdr("row")))
    if _int_text(from_el.find(_xdr("colOff"))) != 0 or _int_text(from_el.find(_xdr("rowOff"))) != 0:
        return False
    if _int_text(to_el.find(_xdr("col"))) != fr_col or _int_text(to_el.find(_xdr("row"))) != fr_row:
        return False
    return _int_text(to_el.find(_xdr("colOff"))) == cx and _int_text(to_el.find(_xdr("rowOff"))) == cy


def _aplicar_two_cell_en_celda(anchor: ET.Element, cx: int, cy: int) -> None:
    from_el = anchor.find(_xdr("from"))
    if from_el is None:
        return
    fr_col = _int_text(from_el.find(_xdr("col")))
    fr_row = _int_text(from_el.find(_xdr("row")))
    _set_child_int(from_el, "colOff", 0)
    _set_child_int(from_el, "rowOff", 0)

    to_el = anchor.find(_xdr("to"))
    pic_el = anchor.find(_xdr("pic"))
    if to_el is None:
        to_el = ET.Element(_xdr("to"))
        idx = list(anchor).index(pic_el) if pic_el is not None else len(anchor)
        anchor.insert(idx, to_el)
    _set_child_int(to_el, "col", fr_col)
    _set_child_int(to_el, "row", fr_row)
    _set_child_int(to_el, "colOff", cx)
    _set_child_int(to_el, "rowOff", cy)

    anchor.tag = _xdr("twoCellAnchor")
    anchor.set("editAs", "twoCell")

    cd = anchor.find(_xdr("clientData"))
    if cd is None:
        cd = ET.SubElement(anchor, _xdr("clientData"))
    cd.set("fLocksWithSheet", "1")
    cd.set("fPrintsWithSheet", "1")


def _procesar_anchor_xml(anchor: ET.Element, col_idx: int, st: dict[str, int]) -> None:
    if anchor.find(_xdr("pic")) is None:
        return

    from_el = anchor.find(_xdr("from"))
    if from_el is None:
        st["tipo_desconocido"] += 1
        return

    if _int_text(from_el.find(_xdr("col"))) != col_idx:
        st["otra_col"] += 1
        return

    kind = _local_tag(anchor)

    if kind == "oneCellAnchor":
        ext = anchor.find(_xdr("ext"))
        if ext is None:
            st["sin_tamano"] += 1
            return
        cx = int(ext.get("cx", 0))
        cy = int(ext.get("cy", 0))
        if cx <= 0 or cy <= 0:
            st["sin_tamano"] += 1
            return
        anchor.remove(ext)
        _aplicar_two_cell_en_celda(anchor, cx, cy)
        st["ajustadas"] += 1
        return

    if kind == "twoCellAnchor":
        to_el = anchor.find(_xdr("to"))
        if to_el is None:
            st["sin_tamano"] += 1
            return
        fr_col_off = _int_text(from_el.find(_xdr("colOff")))
        fr_row_off = _int_text(from_el.find(_xdr("rowOff")))
        fr_col = _int_text(from_el.find(_xdr("col")))
        fr_row = _int_text(from_el.find(_xdr("row")))
        to_col = _int_text(to_el.find(_xdr("col")))
        to_row = _int_text(to_el.find(_xdr("row")))
        if to_col == fr_col and to_row == fr_row:
            cx = _int_text(to_el.find(_xdr("colOff"))) - fr_col_off
            cy = _int_text(to_el.find(_xdr("rowOff"))) - fr_row_off
        else:
            cx = _int_text(to_el.find(_xdr("colOff")))
            cy = _int_text(to_el.find(_xdr("rowOff")))
        if cx <= 0 or cy <= 0:
            st["sin_tamano"] += 1
            return
        if (
            anchor.get("editAs") == "twoCell"
            and _ya_anclada_xml(from_el, to_el, cx, cy)
        ):
            st["ok"] += 1
            return
        _aplicar_two_cell_en_celda(anchor, cx, cy)
        st["ajustadas"] += 1
        return

    st["tipo_desconocido"] += 1


def _procesar_drawing_xml(data: bytes, col_idx: int) -> tuple[bytes, dict[str, int]]:
    st = {
        "ajustadas": 0,
        "ok": 0,
        "otra_col": 0,
        "sin_tamano": 0,
        "tipo_desconocido": 0,
    }
    root = ET.fromstring(data)
    for anchor in list(root):
        if _local_tag(anchor) in ("oneCellAnchor", "twoCellAnchor"):
            _procesar_anchor_xml(anchor, col_idx, st)
    ET.register_namespace("", XDR_NS)
    ET.register_namespace(
        "a", "http://schemas.openxmlformats.org/drawingml/2006/main"
    )
    ET.register_namespace(
        "r", "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    )
    return ET.tostring(root, encoding="utf-8", xml_declaration=True), st


def contar_archivos_media(ruta: str) -> int:
    with zipfile.ZipFile(ruta, "r") as zf:
        return sum(1 for n in zf.namelist() if n.startswith("xl/media/"))


def reanclar_columna_en_xlsx(
    ruta: str,
    col_letter: str,
    *,
    crear_respaldo: bool = True,
) -> tuple[dict[str, int], str | None]:
    """
    Ajusta anclas en la columna indicada editando solo drawing*.xml.
    No elimina WMF, JPEG corruptos ni ningún archivo en xl/media/.
    """
    col_idx = column_index_from_string(col_letter) - 1
    copia = crear_respaldo_xlsx(ruta) if crear_respaldo else None

    total = {
        "ajustadas": 0,
        "ok": 0,
        "otra_col": 0,
        "sin_tamano": 0,
        "tipo_desconocido": 0,
        "dibujos_tocados": 0,
    }

    origen = Path(ruta)
    temporal = origen.with_suffix(".tmp.xlsx")

    with zipfile.ZipFile(ruta, "r") as zin:
        entradas = {info.filename: zin.read(info.filename) for info in zin.infolist()}

    for nombre in list(entradas):
        if not (
            nombre.startswith("xl/drawings/drawing")
            and nombre.endswith(".xml")
            and "/_rels/" not in nombre
        ):
            continue
        nuevo, st = _procesar_drawing_xml(entradas[nombre], col_idx)
        if st["ajustadas"] > 0:
            entradas[nombre] = nuevo
        if st["ajustadas"] or st["ok"]:
            total["dibujos_tocados"] += 1
        for k in ("ajustadas", "ok", "otra_col", "sin_tamano", "tipo_desconocido"):
            total[k] += st[k]

    with zipfile.ZipFile(temporal, "w", zipfile.ZIP_DEFLATED) as zout:
        for nombre, contenido in entradas.items():
            zout.writestr(nombre, contenido)

    os.replace(temporal, origen)
    return total, copia
