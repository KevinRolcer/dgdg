"""
Coloca las fotos ya incrustadas *dentro* de la celda (ancla twoCell), para que al
filtrar u ocultar filas no queden flotando encima.

Edita solo el XML de dibujos dentro del .xlsx (no usa openpyxl para guardar),
así se conservan WMF, JPEG y el resto de xl/media/.

Pide por consola: carpeta, nombre del .xlsx y columna de fotos (ej. F o B).
"""
from __future__ import annotations

import os
import sys

from excel_imagenes_util import pedir_columna, reanclar_columna_en_xlsx

# Valores por defecto si pulsas Enter
RUTA_CARPETA = r"C:\Users\dgdga\Downloads"
NOMBRE_ARCHIVO = "Aspirantes Presidentes Municipales.xlsx"


def pedir_ruta_excel(
    carpeta_def: str | None = None,
    nombre_def: str | None = None,
) -> str:
    """Pide carpeta y nombre por consola; Enter usa los valores por defecto."""
    carpeta_def = (carpeta_def or RUTA_CARPETA).strip()
    nombre_def = (nombre_def or NOMBRE_ARCHIVO).strip()

    while True:
        print(f"\nPor defecto: {os.path.join(carpeta_def, nombre_def)}")
        carpeta = input(f"Carpeta del Excel [{carpeta_def}]: ").strip() or carpeta_def
        nombre = input(f"Nombre del archivo [{nombre_def}]: ").strip() or nombre_def

        if os.path.isfile(nombre):
            return os.path.abspath(nombre)
        if os.path.isfile(carpeta) and carpeta.lower().endswith((".xlsx", ".xlsm")):
            return os.path.abspath(carpeta)

        if not nombre.lower().endswith((".xlsx", ".xlsm")):
            nombre = f"{nombre}.xlsx"

        ruta = os.path.join(carpeta, nombre)
        if os.path.isfile(ruta):
            return os.path.abspath(ruta)

        print(f"  No se encontró el archivo: {ruta}")
        print("  Vuelve a indicar carpeta y nombre (o pega la ruta completa en «Nombre»).\n")


def ejecutar_proceso() -> None:
    archivo = pedir_ruta_excel()
    col_letter = pedir_columna()

    print(f"\nReanclando columna {col_letter} (se conservan todas las imágenes del libro)…")
    print(f"Archivo: {archivo}\n")

    total, copia = reanclar_columna_en_xlsx(archivo, col_letter)

    print(f"Respaldo: {copia}")
    print(f"Guardado: {archivo}")
    print(
        "TOTAL — ajustadas:",
        total["ajustadas"],
        "| ya correctas:",
        total["ok"],
        "| otra columna:",
        total["otra_col"],
        "| sin tamaño:",
        total["sin_tamano"],
        "| dibujos XML tocados:",
        total["dibujos_tocados"],
    )


if __name__ == "__main__":
    try:
        ejecutar_proceso()
    except OSError as e:
        print(f"Error al guardar (¿archivo abierto en Excel?): {e}", file=sys.stderr)
        sys.exit(1)
