<?php

namespace App\Services\Reports;

class SimplePdfReport
{
    public function render(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n14 TL\n50 790 Td\n";

        foreach ($lines as $line) {
            $content .= '('.$this->escape($this->normalize($line)).") Tj\nT*\n";
        }

        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= str_pad((string) $offsets[$index], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function normalize(string $line): string
    {
        $line = str_replace(["\r", "\n", "\t"], ' ', $line);

        return mb_substr($line, 0, 110);
    }

    private function escape(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $line);
    }
}
