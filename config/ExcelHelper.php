<?php
/**
 * ExcelHelper.php
 * Utilidad para generar archivos .xlsx reales sin dependencias externas.
 * Usa ZipArchive y SimpleXML (extensiones nativas de PHP).
 *
 * Uso de escritura:
 *   $writer = new ExcelWriter();
 *   $writer->setHeaders(['Columna1', 'Columna2'], [20, 30]);
 *   $writer->addRow(['Valor1', 'Valor2']);
 *   $writer->download('nombre_archivo.xlsx');
 */

class ExcelWriter {
    private array $headers  = [];
    private array $rows     = [];
    private array $colWidths = [];
    /** Nombre de la hoja */
    private string $sheetName = 'Hoja1';

    public function setSheetName(string $name): void {
        $this->sheetName = $name;
    }

    public function setHeaders(array $headers, array $widths = []): void {
        $this->headers   = $headers;
        $this->colWidths = $widths;
    }

    public function addRow(array $row): void {
        $this->rows[] = $row;
    }

    /**
     * Envía el archivo xlsx al navegador como descarga.
     */
    public function download(string $filename): void {
        $xlsx = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Cache-Control: max-age=0, no-cache, no-store');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $xlsx;
        exit;
    }

    // -------------------------------------------------------------------------
    // Construcción interna del ZIP
    // -------------------------------------------------------------------------

    private function build(): string {
        // Tabla de strings compartidos
        $strings  = [];
        $strIndex = [];

        $addStr = function (string $s) use (&$strings, &$strIndex): int {
            if (!isset($strIndex[$s])) {
                $strIndex[$s] = count($strings);
                $strings[]    = $s;
            }
            return $strIndex[$s];
        };

        // Índices de cabeceras
        $headerIdx = array_map(fn($h) => $addStr((string)$h), $this->headers);

        // Índices de filas de datos
        $rowsIdx = [];
        foreach ($this->rows as $row) {
            $rowsIdx[] = array_map(fn($c) => $addStr((string)$c), $row);
        }

        // Crear archivo ZIP temporal
        $tmpFile = tempnam(sys_get_temp_dir(), 'bararida_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',              $this->xmlContentTypes());
        $zip->addFromString('_rels/.rels',                      $this->xmlRels());
        $zip->addFromString('xl/workbook.xml',                  $this->xmlWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels',       $this->xmlWorkbookRels());
        $zip->addFromString('xl/styles.xml',                    $this->xmlStyles());
        $zip->addFromString('xl/sharedStrings.xml',             $this->xmlSharedStrings($strings));
        $zip->addFromString('xl/worksheets/sheet1.xml',         $this->xmlSheet($headerIdx, $rowsIdx));

        $zip->close();

        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    private function xmlContentTypes(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"         ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"                ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function xmlRels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xmlWorkbook(): string {
        $name = htmlspecialchars($this->sheetName, ENT_XML1, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $name . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function xmlWorkbookRels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"       Target="styles.xml"/>
</Relationships>';
    }

    /**
     * Estilos: índice 0 = normal, índice 1 = cabecera (verde + texto blanco + negrita).
     */
    private function xmlStyles(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0D9039"/></patternFill></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>';
    }

    private function xmlSharedStrings(array $strings): string {
        $count = count($strings);
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
               . ' count="' . $count . '" uniqueCount="' . $count . '">';
        foreach ($strings as $s) {
            $xml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function xmlSheet(array $headerIdx, array $rowsIdx): string {
        // Letras de columna A-Z
        $letters = range('A', 'Z');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Anchos de columna
        if (!empty($this->colWidths)) {
            $xml .= '<cols>';
            foreach ($this->colWidths as $i => $w) {
                $col = $i + 1;
                $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        // Fila 1: cabecera (estilo 1 = verde)
        $xml .= '<row r="1">';
        foreach ($headerIdx as $ci => $si) {
            $col  = $letters[$ci];
            $xml .= '<c r="' . $col . '1" t="s" s="1"><v>' . $si . '</v></c>';
        }
        $xml .= '</row>';

        // Filas de datos (estilo 0 = normal)
        foreach ($rowsIdx as $ri => $cols) {
            $rowNum = $ri + 2;
            $xml   .= '<row r="' . $rowNum . '">';
            foreach ($cols as $ci => $si) {
                $col  = $letters[$ci];
                $xml .= '<c r="' . $col . $rowNum . '" t="s"><v>' . $si . '</v></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>'
              . '<sheetViews><sheetView workbookViewId="0"><selection activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
              . '</worksheet>';
        return $xml;
    }
}
