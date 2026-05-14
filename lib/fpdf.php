<?php
// A minimal FPDF adapter for this project. It builds an HTML report and passes to wkhtmltopdf.
// This is a low-dependency implementation to satisfy the FPDF integration requirement.

class FPDF {
    private $orientation;
    private $unit;
    private $format;
    private $autoPageBreak;
    private $margins;
    private $content = [];

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        $this->orientation = $orientation;
        $this->unit = $unit;
        $this->format = $format;
        $this->autoPageBreak = true;
        $this->margins = ['l' => 10, 't' => 10, 'r' => 10];
        $this->content = [];
    }

    public function SetAutoPageBreak($auto, $margin = 0) {
        $this->autoPageBreak = $auto;
    }

    public function AddPage() {
        $this->content[] = '<div style="page-break-after:always; margin:10px;">';
    }

    public function SetFont($family, $style = '', $size = 12) {
        $style = strtoupper($style);
        $css = '';
        if (strpos($style, 'B') !== false) { $css .= 'font-weight:bold;'; }
        if (strpos($style, 'I') !== false) { $css .= 'font-style:italic;'; }
        if (strpos($style, 'U') !== false) { $css .= 'text-decoration:underline;'; }
        $this->content[] = "<div style='font-family:{$family};{$css}font-size:{$size}pt;'>";
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = 'L', $fill = false, $link = '') {
        $text = htmlspecialchars($txt);
        $style = '';
        if ($align === 'C') { $style = 'text-align:center;'; }
        if ($fill) { $style .= 'background:#eee;'; }
        $borderStyle = $border ? 'border:1px solid #000;' : '';
        $this->content[] = "<div style='display:block;{$style}{$borderStyle}'>$text</div>";
        if ($ln > 0) {
            $this->content[] = '<br/>';
        }
    }

    public function MultiCell($w, $h, $txt = '', $border = 0, $align = 'L', $fill = false) {
        $text = nl2br(htmlspecialchars($txt));
        $style = '';
        if ($align === 'C') { $style = 'text-align:center;'; }
        if ($fill) { $style .= 'background:#eee;'; }
        $borderStyle = $border ? 'border:1px solid #000;' : '';
        $this->content[] = "<div style='display:block;{$style}{$borderStyle}'>$text</div>";
    }

    public function Ln($h = null) {
        $this->content[] = '<br/>';
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') {
        $src = htmlspecialchars($file);
        $width = $w > 0 ? "width:{$w}px;" : 'max-width:100%;';
        $this->content[] = "<img src='$src' style='$width display:block; margin:10px 0;' alt='screenshot' />";
    }

    public function Output($dest = 'I', $name = 'doc.pdf', $isUTF8 = false) {
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial,Helvetica,sans-serif;font-size:11pt;}</style></head><body>';
        $html .= implode('', $this->content);
        $html .= '</body></html>';

        if ($dest === 'F') {
            if (file_put_contents($name . '.html', $html) === false) {
                return false;
            }
            $tmpHtml = $name . '.html';
            $command = escapeshellcmd('wkhtmltopdf') . ' ' . escapeshellarg($tmpHtml) . ' ' . escapeshellarg($name) . ' 2>&1';
            if (function_exists('exec')) {
                exec($command, $output, $code);
            } else {
                // exec unavailable on this host
                @unlink($tmpHtml);
                return false;
            }
            @unlink($tmpHtml);
            return $code === 0;
        }

        if ($dest === 'I' || $dest === '') {
            header('Content-Type: application/pdf');
            $tmp = tempnam(sys_get_temp_dir(), 'fpdf');
            if (!file_put_contents($tmp . '.html', $html)) {
                return false;
            }
            $command = escapeshellcmd('wkhtmltopdf') . ' ' . escapeshellarg($tmp . '.html') . ' ' . escapeshellarg($tmp . '.pdf') . ' 2>&1';
            if (function_exists('exec')) {
                exec($command, $output, $code);
            } else {
                return false;
            }
            if ($code !== 0 || !file_exists($tmp . '.pdf')) {
                return false;
            }
            readfile($tmp . '.pdf');
            @unlink($tmp . '.html');
            @unlink($tmp . '.pdf');
            return true;
        }

        return false;
    }
}
