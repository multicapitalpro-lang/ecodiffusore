<?php

namespace App\Core;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\SimpleType\Jc;

/** Geracao de .docx (Fase 104) -- primeira vez que esse codebase gera Word de verdade (o
 *  ClickSign so' preenche template remotamente, nunca gerou docx local). Espelha o mesmo layout
 *  do PDF da Proposta Comercial (app/Views/painel/proposta_comercial/pdf.php), com a mesma
 *  imagem de cabecalho -- so' a secao de beneficios fica como texto simples aqui (PhpWord nao
 *  reaproveita a imagem recortada do PDF pra essa parte, complexidade nao compensa pro Word). */
class WordDoc
{
    public static function downloadCommercialProposal(array $data): void
    {
        require_once BASE_PATH . '/vendor/autoload.php';

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::PT_BR));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900,
        ]);

        $heroPath = BASE_PATH . '/public_html/assets/img/proposta-comercial-hero.jpg';
        if (is_file($heroPath)) {
            $section->addImage($heroPath, ['width' => 480, 'height' => 118, 'alignment' => Jc::CENTER]);
            $section->addTextBreak(1);
        }

        $section->addText('PROPOSTA COMERCIAL', ['bold' => true, 'size' => 20, 'color' => '003254'], ['alignment' => Jc::CENTER]);
        $section->addText('ECODIFFUSORE BRASIL  •  TECNOLOGIA PARA EFICIÊNCIA DE FROTAS', ['bold' => true, 'size' => 9, 'color' => '1A7A4C'], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        $infoTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'DDDDDD', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        $infoTable->addRow();
        self::infoCell($infoTable, 'Nome do Cliente', $data['clientName']);
        self::infoCell($infoTable, 'Cidade', $data['city'] ?: '—');
        self::infoCell($infoTable, 'Responsável', $data['responsible'] ?: '—');
        $section->addTextBreak(1);

        $section->addText(
            'A Ecodiffusore Brasil apresenta uma solução exclusiva voltada à eficiência operacional de frotas, ' .
            'com tecnologia desenvolvida para potencializar a combustão, melhorar o rendimento do motor e ' .
            'contribuir para a redução do consumo de combustível e da emissão de poluentes, dimensionada de ' .
            'forma personalizada para atender as mais diversas necessidades.',
            ['size' => 10],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );

        self::bar($section, 'BENEFÍCIOS E UTILIDADES PARA A FROTA');
        $benefits = [
            ['Economia de Combustível', 'De 5% (mínimo garantido) a 20% (potencial máximo) de redução no consumo de diesel — 12% é a média real.'],
            ['Mais Performance', 'Otimiza a queima na câmara de combustão e reduz o "delay" do acelerador, proporcionando mais força e eficiência.'],
            ['Menos Emissões', 'Otimiza a mistura ar/combustível, reduzindo poluentes e contribui para as práticas ESG.'],
            ['Instalação Simples', 'Rápida e prática, compatível com a grande maioria dos caminhões, ônibus, máquinas agrícolas e equipamentos a diesel.'],
        ];
        foreach ($benefits as [$title, $desc]) {
            $section->addText($title, ['bold' => true, 'color' => '1A7A4C', 'size' => 10]);
            $section->addText($desc, ['size' => 9], ['spaceAfter' => 120]);
        }
        $section->addTextBreak(1);

        self::bar($section, 'PRODUTOS E INVESTIMENTO');
        $itemsTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'DDDDDD', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        $itemsTable->addRow();
        foreach (['Produto' => 2200, 'Quantidade' => 1000, 'Valor Unitário' => 1200, 'Valor Total' => 1200] as $label => $width) {
            $cell = $itemsTable->addCell($width, ['bgColor' => '003254']);
            $cell->addText($label, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }
        $fmt = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');
        foreach ($data['items'] as $item) {
            $itemsTable->addRow();
            $itemsTable->addCell(2200)->addText(htmlspecialchars($item['produto'] ?? '', ENT_QUOTES, 'UTF-8'), ['size' => 9]);
            $itemsTable->addCell(1000)->addText((string) (int) $item['quantity'], ['size' => 9], ['alignment' => Jc::END]);
            $itemsTable->addCell(1200)->addText($fmt($item['unit_price']), ['size' => 9], ['alignment' => Jc::END]);
            $itemsTable->addCell(1200)->addText($fmt($item['subtotal']), ['size' => 9], ['alignment' => Jc::END]);
        }
        $itemsTable->addRow();
        $totalLabelCell = $itemsTable->addCell(4200, ['bgColor' => '003254', 'gridSpan' => 3]);
        $totalLabelCell->addText('VALOR TOTAL DA PROPOSTA', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
        $totalValueCell = $itemsTable->addCell(1200, ['bgColor' => '2C8F09']);
        $totalValueCell->addText($fmt($data['total']), ['bold' => true, 'color' => 'FFFFFF', 'size' => 10], ['alignment' => Jc::END]);
        $section->addTextBreak(1);

        self::bar($section, 'CONDIÇÕES COMERCIAIS');
        $condTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'DDDDDD', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        $condTable->addRow();
        $condTable->addCell(1400)->addText('Condições de Pagamento', ['bold' => true, 'color' => '1A7A4C', 'size' => 9]);
        $condTable->addCell(4600)->addText(htmlspecialchars($data['paymentTerms'] ?: '—', ENT_QUOTES, 'UTF-8'), ['size' => 9]);
        $condTable->addRow();
        $condTable->addCell(1400)->addText('Validade da Proposta', ['bold' => true, 'color' => '1A7A4C', 'size' => 9]);
        $condTable->addCell(4600)->addText(htmlspecialchars($data['validity'] ?: '—', ENT_QUOTES, 'UTF-8'), ['size' => 9]);
        $section->addTextBreak(1);

        self::bar($section, 'OBSERVAÇÕES');
        $section->addText(htmlspecialchars($data['notes'] ?: '—', ENT_QUOTES, 'UTF-8'), ['size' => 9], ['spaceAfter' => 300]);

        $section->addText('De acordo com as condições comerciais apresentadas nesta proposta.', ['italic' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 600]);

        $signTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $signTable->addRow();
        $signTable->addCell(3000)->addText('ECODIFFUSORE BRASIL', ['bold' => true, 'color' => '1A7A4C', 'size' => 9], ['alignment' => Jc::CENTER]);
        $signTable->addCell(3000)->addText('CLIENTE', ['bold' => true, 'color' => '1A7A4C', 'size' => 9], ['alignment' => Jc::CENTER]);
        $signTable->addRow();
        $signTable->addCell(3000)->addText('Nome / Cargo: ______________________', ['size' => 8], ['alignment' => Jc::CENTER]);
        $signTable->addCell(3000)->addText('Nome / Cargo: ______________________', ['size' => 8], ['alignment' => Jc::CENTER]);

        $filename = 'proposta-comercial-ecodiffusore.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
        exit;
    }

    private static function infoCell($table, string $label, string $value): void
    {
        $cell = $table->addCell(3300);
        $cell->addText($label, ['bold' => true, 'color' => '1A7A4C', 'size' => 9]);
        $cell->addText(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), ['size' => 10]);
    }

    private static function bar($section, string $text): void
    {
        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();
        $cell = $table->addCell(6000, ['bgColor' => '003254']);
        $cell->addText($text, ['bold' => true, 'color' => 'FFFFFF', 'size' => 11]);
        $section->addTextBreak(0);
    }
}
