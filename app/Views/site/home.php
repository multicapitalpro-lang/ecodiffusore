<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>

<section class="hero">
    <div class="site-container hero-inner">
        <div class="hero-text">
            <span class="badge">Produto Patenteado · Registro INPI</span>
            <h1>Economize Diesel e <span>Eleve a Performance</span></h1>
            <p class="hero-sub">O sistema Ecodiffusore potencializa a combustão, melhora o rendimento e reduz desperdícios. Mais força, mais economia, mais resultado — para caminhões, máquinas agrícolas e geradores a diesel.</p>
            <div class="hero-ctas">
                <a href="#contato" class="btn btn-primary">Quero Economizar Agora</a>
                <a href="https://wa.me/5541988962839" target="_blank" rel="noopener" class="btn btn-outline">Falar no WhatsApp</a>
            </div>
            <ul class="hero-stats">
                <li><strong>8% a 15%</strong><span>de economia de diesel</span></li>
                <li><strong>Até R$ 5 mil</strong><span>de economia mensal por caminhão</span></li>
                <li><strong>30 dias</strong><span>de garantia real</span></li>
            </ul>
        </div>
        <div class="hero-media">
            <video controls poster="<?= View::asset('/assets/img/video-poster.svg') ?>" class="hero-video">
                <source src="" type="video/mp4">
                Seu navegador não suporta vídeo HTML5.
            </video>
            <p class="hero-media-note">Vídeo de apresentação em breve — envie o arquivo final para publicarmos aqui.</p>
        </div>
    </div>
</section>

<section class="publico">
    <div class="site-container">
        <h2>Para quem é o Ecodiffusore</h2>
        <p class="section-sub">Um único sistema, compatível com praticamente qualquer motor a diesel — não importa o setor.</p>
        <div class="grid-4">
            <div class="publico-item">
                <span class="beneficio-icon">🚛</span>
                <h3>Caminhoneiros autônomos</h3>
                <p>Mais economia no bolso de quem vive na estrada.</p>
            </div>
            <div class="publico-item">
                <span class="beneficio-icon">📦</span>
                <h3>Transportadoras e frotas</h3>
                <p>Redução de custo multiplicada por cada veículo da frota.</p>
            </div>
            <div class="publico-item">
                <span class="beneficio-icon">🚜</span>
                <h3>Agronegócio</h3>
                <p>Tratores, colheitadeiras e máquinas agrícolas a diesel.</p>
            </div>
            <div class="publico-item">
                <span class="beneficio-icon">⚡</span>
                <h3>Geração de energia</h3>
                <p>Geradores a diesel de obras, sítios e indústrias.</p>
            </div>
        </div>
    </div>
</section>

<section class="problema">
    <div class="site-container">
        <h2>O setor de transporte enfrenta desafios diários</h2>
        <div class="grid-4">
            <div class="card-problema">Alto custo do diesel</div>
            <div class="card-problema">Baixa margem operacional</div>
            <div class="card-problema">Pouco acesso a tecnologias de economia</div>
            <div class="card-problema">Pressão crescente por adequação ESG</div>
        </div>
        <p class="problema-cta">A Ecodiffusore Brasil surge para resolver esses problemas.</p>
    </div>
</section>

<section id="beneficios" class="beneficios">
    <div class="site-container">
        <h2>Um ecossistema completo de benefícios</h2>
        <p class="section-sub">Redução real de custos operacionais, aumento da lucratividade, tecnologia para economia de combustível e redução de emissão de poluentes.</p>
        <div class="grid-4">
            <div class="beneficio-item">
                <span class="beneficio-icon">⛽</span>
                <h3>Economia de Combustível</h3>
                <p>Entre 8% e 15% de redução no consumo de diesel.</p>
            </div>
            <div class="beneficio-item">
                <span class="beneficio-icon">⚙️</span>
                <h3>Mais Performance</h3>
                <p>Otimiza a queima na câmara de combustão e reduz o "delay" do acelerador.</p>
            </div>
            <div class="beneficio-item">
                <span class="beneficio-icon">🌱</span>
                <h3>Menos Emissões</h3>
                <p>Otimiza a mistura ar/combustível, reduzindo poluentes — abre caminho para selo ESG.</p>
            </div>
            <div class="beneficio-item">
                <span class="beneficio-icon">🔧</span>
                <h3>Instalação Simples</h3>
                <p>Rápida e prática, compatível com a grande maioria dos caminhões a diesel.</p>
            </div>
        </div>
    </div>
</section>

<section class="tecnologia">
    <div class="site-container">
        <span class="badge">Tecnologia Patenteada</span>
        <h2>Como funciona, de fato</h2>
        <p class="section-sub">Sem promessa vazia: o Ecodiffusore é um sistema mecânico com princípio de funcionamento claro e patente registrada no INPI.</p>
        <div class="grid-2 tecnologia-grid">
            <div class="tecnologia-card">
                <h3>Onde e como é instalado</h3>
                <p>O Ecodiffusore é acoplado entre o corpo de admissão (TBI) e o filtro de ar do motor. Suas lâminas helicoidais magnetizadas potencializam a sucção de ar para dentro do motor, melhorando a mistura ar/combustível antes da queima na câmara de combustão.</p>
                <p>O resultado é uma combustão mais completa: menos diesel desperdiçado sem queimar, mais torque disponível e menor liberação de fumaça e poluentes no escapamento.</p>
            </div>
            <div class="tecnologia-card">
                <h3>Validação e fabricação</h3>
                <ul class="check-list">
                    <li>Produto e marca com registro de patente no INPI (Instituto Nacional da Propriedade Industrial)</li>
                    <li>Fabricado no Brasil, por indústria especializada em tecnologia para motores a diesel desde 2002</li>
                    <li>Compatível com motores a diesel de caminhões, máquinas agrícolas e geradores</li>
                    <li>Sem eletrônica, sem manutenção — peça mecânica de instalação única</li>
                </ul>
                <p class="tecnologia-nota">Laudos técnicos e documentação de validação completa estão disponíveis mediante solicitação. Fale com um licenciado para receber o material técnico.</p>
            </div>
        </div>
    </div>
</section>

<section class="payback">
    <div class="site-container">
        <h2>Economia real e lucro certo</h2>
        <p class="section-sub">Payback rápido. Eficiência que gera resultado no bolso do caminhoneiro.</p>
        <div class="table-scroll">
            <table class="payback-table">
                <thead>
                    <tr>
                        <th>Indicador</th>
                        <th>Economia 8%</th>
                        <th>Economia 11%</th>
                        <th>Economia 15%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Gasto mensal com combustível</td>
                        <td>R$ 35.000,00</td>
                        <td>R$ 35.000,00</td>
                        <td>R$ 35.000,00</td>
                    </tr>
                    <tr>
                        <td>Economia líquida mensal</td>
                        <td>R$ 2.800,00</td>
                        <td>R$ 3.850,00</td>
                        <td>R$ 5.250,00</td>
                    </tr>
                    <tr>
                        <td>Payback do equipamento</td>
                        <td>1,1 meses</td>
                        <td>0,8 meses</td>
                        <td>0,6 meses</td>
                    </tr>
                    <tr>
                        <td>Economia líquida no 1º ano</td>
                        <td>R$ 30.469,00</td>
                        <td>R$ 43.069,00</td>
                        <td>R$ 59.869,00</td>
                    </tr>
                    <tr>
                        <td>Economia líquida em 5 anos</td>
                        <td>R$ 164.869,00</td>
                        <td>R$ 227.869,00</td>
                        <td>R$ 311.869,00</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="calc-disclaimer">*Payback calculado sobre o preço de referência do Ecodiffusore (R$ 3.131,00, linha Scania NTG/DAF). Valores aproximados, podem variar por modelo e uso.</p>
    </div>
</section>

<section class="calculadora">
    <div class="site-container">
        <h2>Calcule a sua economia</h2>
        <p class="section-sub">Arraste o slider com a quilometragem média rodada por mês.</p>
        <div class="calc-box">
            <label for="calc-km">Km rodados por mês: <strong id="calc-km-label">12.000 km</strong></label>
            <input type="range" id="calc-km" min="5000" max="30000" step="1000" value="12000">
            <div class="calc-results">
                <div class="calc-result calc-min">
                    <span>Economia mínima (8%)</span>
                    <strong id="calc-min-month">R$ 0</strong>
                    <small><span id="calc-min-year">R$ 0</span>/ano · <span id="calc-min-5y">R$ 0</span> em 5 anos</small>
                </div>
                <div class="calc-result calc-avg">
                    <span>Economia média (12%)</span>
                    <strong id="calc-avg-month">R$ 0</strong>
                    <small><span id="calc-avg-year">R$ 0</span>/ano · <span id="calc-avg-5y">R$ 0</span> em 5 anos</small>
                </div>
                <div class="calc-result calc-max">
                    <span>Economia máxima (15%)</span>
                    <strong id="calc-max-month">R$ 0</strong>
                    <small><span id="calc-max-year">R$ 0</span>/ano · <span id="calc-max-5y">R$ 0</span> em 5 anos</small>
                </div>
            </div>
            <p class="calc-disclaimer">*A economia varia de acordo com estilo de direção, tipo de carga e condições da estrada. Simulação baseada em consumo médio de 2,8 km/l e diesel a R$ 6,10/l — valores aproximados.</p>
        </div>
    </div>
</section>

<section id="precos" class="precos">
    <div class="site-container">
        <h2>Modelos e investimento</h2>
        <p class="section-sub">Parcelamos em até 6x sem juros. À vista com 11% de desconto.</p>
        <div class="table-scroll">
            <table class="precos-table">
                <thead>
                    <tr><th>Modelo</th><th>À vista (11% OFF)</th><th>6x sem juros</th></tr>
                </thead>
                <tbody>
                    <tr><td>Linha Scania (até 2018)</td><td>R$ 2.836,00</td><td>6x R$ 529,83</td></tr>
                    <tr><td>Linha Volvo (FH, FM, VM...)</td><td>R$ 2.876,00</td><td>6x R$ 537,17</td></tr>
                    <tr><td>Linha Iveco</td><td>R$ 2.916,00</td><td>6x R$ 544,50</td></tr>
                    <tr><td>Linha Mercedes</td><td>R$ 2.935,00</td><td>6x R$ 548,17</td></tr>
                    <tr><td>Volvo Robocop</td><td>R$ 3.043,00</td><td>6x R$ 568,33</td></tr>
                    <tr><td>Linha Meteor</td><td>R$ 3.106,10</td><td>6x R$ 581,67</td></tr>
                    <tr><td>Scania NTG</td><td>R$ 3.131,00</td><td>6x R$ 584,83</td></tr>
                    <tr><td>Linha DAF</td><td>R$ 3.131,00</td><td>6x R$ 584,83</td></tr>
                </tbody>
            </table>
        </div>
        <p class="precos-garantia">✔ Garantia de 30 dias reais · ✔ Produto patenteado (INPI) · ✔ Sem custo de manutenção</p>
    </div>
</section>

<section class="depoimentos">
    <div class="site-container">
        <h2>Quem já usa, aprova</h2>
        <div class="grid-2">
            <blockquote>“Caminhão melhorou a média e trouxe mais torque.” <cite>— Caminhoneiro autônomo</cite></blockquote>
            <blockquote>“Senti a diferença já nos primeiros abastecimentos, o motor responde melhor.” <cite>— Cliente Ecodiffusore</cite></blockquote>
        </div>
    </div>
</section>

<section class="esg">
    <div class="site-container esg-inner">
        <div>
            <h2>Transporte mais sustentável</h2>
            <p>Atuamos alinhados às práticas ESG: redução significativa de emissão de gases, maior eficiência energética, menor impacto ambiental e valorização da imagem da transportadora.</p>
            <p class="esg-highlight">O futuro do transporte será sustentável — e nós estamos preparados para liderar essa transformação.</p>
        </div>
    </div>
</section>

<section id="faq" class="faq">
    <div class="site-container">
        <h2>Perguntas frequentes</h2>
        <div class="faq-list">
            <details>
                <summary>O Ecodiffusore funciona em qualquer caminhão?</summary>
                <p>Sim. É compatível com a grande maioria dos caminhões movidos a diesel (Scania, Volvo, Iveco, Mercedes, DAF e outras marcas). Também atende máquinas agrícolas (tratores, colheitadeiras) e geradores a diesel. Nossa equipe confirma a compatibilidade antes do envio.</p>
            </details>
            <details>
                <summary>A instalação é difícil? Precisa de mecânico?</summary>
                <p>A instalação é simples, rápida e prática, e em muitos casos pode ser feita facilmente. Também oferecemos orientação profissional.</p>
            </details>
            <details>
                <summary>Quanto tempo leva para ver resultado?</summary>
                <p>Muitos clientes relatam melhora perceptível já nos primeiros abastecimentos, com redução no consumo de diesel e melhor desempenho do motor logo nos primeiros dias de uso.</p>
            </details>
            <details>
                <summary>Posso parcelar?</summary>
                <p>Sim, parcelamos em até 6x sem juros.</p>
            </details>
            <details>
                <summary>Existe garantia?</summary>
                <p>Sim, garantia de 30 dias reais.</p>
            </details>
        </div>
    </div>
</section>

<section id="licenciado" class="licenciado">
    <div class="site-container licenciado-inner">
        <h2>Seja um Licenciado Ecodiffusore Brasil</h2>
        <p>Um dos maiores mercados do Brasil: milhões de caminhoneiros, milhares de transportadoras e forte dependência logística rodoviária. Faça parte da transformação do transporte brasileiro — economia, tecnologia, sustentabilidade, benefícios reais e fortalecimento do caminhoneiro.</p>
        <a href="https://wa.me/5541988962839?text=Quero%20ser%20um%20licenciado%20Ecodiffusore%20Brasil" target="_blank" rel="noopener" class="btn btn-primary">Quero ser Licenciado</a>
    </div>
</section>

<section id="contato" class="contato">
    <div class="site-container contato-inner">
        <div>
            <h2>Fale com a gente</h2>
            <p>Preencha seus dados que um de nossos licenciados entra em contato pelo WhatsApp.</p>
            <?php if ($sucesso): ?>
                <p class="form-msg form-msg-ok">Recebemos seu contato! Em breve falaremos com você.</p>
            <?php elseif ($erro): ?>
                <p class="form-msg form-msg-erro">Preencha ao menos nome e WhatsApp para enviar.</p>
            <?php endif; ?>
            <form action="/contato" method="post" class="contato-form">
                <?= Csrf::field() ?>
                <input type="text" name="name" placeholder="Seu nome" required>
                <input type="text" name="whatsapp" placeholder="Seu WhatsApp" required>
                <input type="text" name="city" placeholder="Cidade">
                <input type="text" name="truck_brand" placeholder="Marca do caminhão">
                <textarea name="message" placeholder="Mensagem (opcional)"></textarea>
                <button type="submit" class="btn btn-primary">Quero Economizar Agora</button>
            </form>
        </div>
    </div>
</section>
