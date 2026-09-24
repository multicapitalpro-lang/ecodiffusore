<?php

namespace App\Core;

/**
 * Calculadora oficial de locação (Fase 122) -- réplica das fórmulas de uma calculadora externa
 * (Google Apps Script) que o usuário pediu pra virar página oficial do sistema, com todos os
 * valores conferidos manualmente contra a calculadora original (entrada de teste: gasto R$45.000/
 * mês, 12.000km/mês, diesel R$6,20, 10% economia, adesão R$4.490, mensalidade R$890, 1 e depois
 * 2 veículos -- toda saída bateu exatamente, incluindo o payback usando a MÉDIA mensal do ano 1,
 * não o resultado "regime" do mês 2 em diante).
 *
 * Sempre calcula em R$ internamente -- conversão de moeda acontece na borda (controller), igual
 * EconomyCalculator/PricingTier.
 */
class LeaseCalculator
{
    public static function estimate(
        float $gastoDiesel,
        float $kmRodados,
        float $mediaKmLInput,
        string $modo,
        float $precoDiesel,
        float $pctEconomia,
        float $valorAdesao,
        float $mensalidade,
        int $veiculos,
        int $parcelasAdesao = 1
    ): array {
        $litros = $precoDiesel > 0 ? $gastoDiesel / $precoDiesel : 0.0;

        if ($modo === 'media' && $mediaKmLInput > 0) {
            $mediaAtual = $mediaKmLInput;
            $kmRodados = $mediaAtual * $litros;
        } else {
            $mediaAtual = $litros > 0 ? $kmRodados / $litros : 0.0;
        }

        // Fatoração confirmada na calculadora original: reduzir o consumo em X% (menos litros
        // pra rodar a mesma distância) equivale a multiplicar o km/l por 1/(1-X/100), nunca
        // "km/l atual * (1+X%)" -- os dois só coincidem pra X pequeno.
        $fatorMelhora = $pctEconomia < 100 ? 1 / (1 - $pctEconomia / 100) : 0.0;
        $mediaComEco = $mediaAtual * $fatorMelhora;

        $litrosEconomizadosMesUnit = $litros * ($pctEconomia / 100);
        $kmExtrasMesUnit = $litrosEconomizadosMesUnit * $mediaComEco;
        $economiaMensalUnit = $gastoDiesel * ($pctEconomia / 100);
        $gastoComEcoUnit = $gastoDiesel * (1 - $pctEconomia / 100);

        $veiculos = max(1, $veiculos);

        $litrosEconomizadosMesFrota = $litrosEconomizadosMesUnit * $veiculos;
        $kmExtrasMesFrota = $kmExtrasMesUnit * $veiculos;
        $economiaMensalFrota = $economiaMensalUnit * $veiculos;
        $mensalidadeTotalFrota = $mensalidade * $veiculos;
        $adesaoTotalFrota = $valorAdesao * $veiculos;

        $parcelasAdesao = max(1, $parcelasAdesao);
        $parcelaAdesaoMensal = $adesaoTotalFrota / $parcelasAdesao;

        $meses = [];
        $acumulado = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $parcela = $m <= $parcelasAdesao ? $parcelaAdesaoMensal : 0.0;
            $totalPago = $mensalidadeTotalFrota + $parcela;
            $resultado = $economiaMensalFrota - $totalPago;
            $acumulado += $resultado;
            $meses[] = [
                'mes' => $m,
                'economia' => $economiaMensalFrota,
                'mensalidade' => $mensalidadeTotalFrota,
                'parcela_adesao' => $parcela > 0 ? $parcela : null,
                'total_pago' => $totalPago,
                'resultado' => $resultado,
                'acumulado' => $acumulado,
            ];
        }
        $resultadoAno1 = $acumulado;

        // Payback confirmado contra a calculadora original: adesão total / (resultado do ano 1
        // dividido por 12) -- ou seja, a MÉDIA mensal já considerando a adesão diluída no ano
        // inteiro, não o resultado "regime" (mês 2 em diante). As duas contas divergem quando a
        // adesão é grande em relação à mensalidade, e a original usa a média.
        $mediaMensalAno1 = $resultadoAno1 / 12;
        $paybackMeses = $mediaMensalAno1 > 0 ? $adesaoTotalFrota / $mediaMensalAno1 : null;

        $economiaAnualFrota = $economiaMensalFrota * 12;
        $mensalidadeAnualFrota = $mensalidadeTotalFrota * 12;

        $anos = [];
        $acumuladoAnos = 0.0;
        for ($a = 1; $a <= 5; $a++) {
            $custoAdesaoAno = $a === 1 ? $adesaoTotalFrota : 0.0;
            $resultadoAno = $economiaAnualFrota - $mensalidadeAnualFrota - $custoAdesaoAno;
            $acumuladoAnos += $resultadoAno;
            $anos[] = [
                'ano' => $a,
                'economia' => $economiaAnualFrota,
                'custo_locacao' => $mensalidadeAnualFrota,
                'custo_adesao' => $custoAdesaoAno,
                'resultado' => $resultadoAno,
                'acumulado' => $acumuladoAnos,
            ];
        }

        return [
            'media_atual' => $mediaAtual,
            'media_com_eco' => $mediaComEco,
            'km_rodados' => $kmRodados,
            'gasto_mensal_sem_eco' => $gastoDiesel,
            'gasto_mensal_com_eco' => $gastoComEcoUnit,
            'litros_economizados_mes' => $litrosEconomizadosMesFrota,
            'litros_economizados_ano' => $litrosEconomizadosMesFrota * 12,
            'km_extras_mes' => $kmExtrasMesFrota,
            'km_extras_ano' => $kmExtrasMesFrota * 12,
            'economia_mensal' => $economiaMensalFrota,
            'economia_anual' => $economiaAnualFrota,
            'mensalidade_total_frota' => $mensalidadeTotalFrota,
            'mensalidade_anual_frota' => $mensalidadeAnualFrota,
            'adesao_total_frota' => $adesaoTotalFrota,
            'parcelas_adesao' => $parcelasAdesao,
            'resultado_mensal' => $economiaMensalFrota - $mensalidadeTotalFrota,
            'resultado_ano1' => $resultadoAno1,
            'payback_meses' => $paybackMeses,
            'meses' => $meses,
            'anos' => $anos,
            'veiculos' => $veiculos,
            'pct_economia' => $pctEconomia,
        ];
    }
}
