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
            <h1>Economize em até 10% de Diesel e <span>Eleve a Performance do seu Veículo</span></h1>
            <p class="hero-sub">O sistema Ecodiffusore potencializa a combustão, melhora o rendimento e reduz desperdícios. Mais força, mais economia, mais resultado — para caminhões, máquinas agrícolas e geradores a diesel.</p>
            <div class="hero-ctas">
                <a href="#contato" class="btn btn-primary">Quero Economizar Agora</a>
                <a href="https://wa.me/5545991021551" target="_blank" rel="noopener" class="btn btn-outline">Falar no WhatsApp</a>
            </div>
            <ul class="hero-stats">
                <li><strong>5% a 20%</strong><span>de economia de diesel (mínimo garantido a potencial máximo)</span></li>
                <li><strong>Até R$ 5 mil</strong><span>de economia mensal por caminhão</span></li>
            </ul>
        </div>
        <div class="hero-media" id="hero-media">
            <video autoplay muted loop playsinline controls poster="<?= View::asset('/assets/img/video-poster.svg') ?>" class="hero-video" id="hero-video">
                <source src="<?= View::asset('/assets/video/hero-institucional.mp4') ?>" type="video/mp4">
                Seu navegador não suporta vídeo HTML5.
            </video>
            <button type="button" class="hero-video-sound" id="hero-video-sound" aria-label="Ativar som">🔊 Ativar som</button>
        </div>
    </div>
</section>

<section class="publico">
    <div class="site-container">
        <h2>Para quem é o Ecodiffusore</h2>
        <p class="section-sub">Um único sistema, compatível com praticamente qualquer motor a diesel — não importa o setor.</p>
        <div class="grid-4">
            <div class="publico-item">
                <img src="<?= View::asset('/assets/img/publico-caminhoneiros.jpg') ?>" alt="Caminhoneiros autônomos" class="beneficio-icon" loading="lazy">
                <h3>Caminhoneiros autônomos</h3>
                <p>Mais economia no bolso de quem vive na estrada.</p>
            </div>
            <div class="publico-item">
                <img src="<?= View::asset('/assets/img/publico-frotas.jpg') ?>" alt="Transportadoras e frotas" class="beneficio-icon" loading="lazy">
                <h3>Transportadoras e frotas</h3>
                <p>Redução de custo multiplicada por cada veículo da frota.</p>
            </div>
            <div class="publico-item">
                <img src="<?= View::asset('/assets/img/publico-agronegocio.jpg') ?>" alt="Agronegócio" class="beneficio-icon" loading="lazy">
                <h3>Agronegócio</h3>
                <p>Tratores, colheitadeiras e máquinas agrícolas a diesel.</p>
            </div>
            <div class="publico-item">
                <img src="<?= View::asset('/assets/img/publico-linha-amarela.jpg') ?>" alt="Máquinas de linha amarela" class="beneficio-icon" loading="lazy">
                <h3>Máquinas de linha amarela</h3>
                <p>Escavadeiras, retroescavadeiras, motoniveladoras e tratores de esteira.</p>
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
            <div class="diesel-chart-card">
                <h4>O diesel só fica mais caro</h4>
                <svg class="diesel-chart-svg" viewBox="0 0 700 210" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="dieselGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6ea62c" stop-opacity=".5"/>
                            <stop offset="100%" stop-color="#6ea62c" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path class="chart-area" d="M50,156 L150,166 L250,116 L350,32 L450,58 L550,54 L650,46 L650,180 L50,180 Z"/>
                    <polyline class="chart-line" points="50,156 150,166 250,116 350,32 450,58 550,54 650,46"/>
                    <?php $dieselPoints = [['x' => 50, 'y' => 156, 'year' => '2019', 'value' => 'R$ 3,60'], ['x' => 150, 'y' => 166, 'year' => '2020', 'value' => 'R$ 3,35'], ['x' => 250, 'y' => 116, 'year' => '2021', 'value' => 'R$ 4,60'], ['x' => 350, 'y' => 32, 'year' => '2022', 'value' => 'R$ 6,70'], ['x' => 450, 'y' => 58, 'year' => '2023', 'value' => 'R$ 6,05'], ['x' => 550, 'y' => 54, 'year' => '2024', 'value' => 'R$ 6,15'], ['x' => 650, 'y' => 46, 'year' => '2025', 'value' => 'R$ 6,35']]; ?>
                    <?php foreach ($dieselPoints as $p): ?>
                        <circle class="chart-dot" cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="4"/>
                        <text class="chart-value" x="<?= $p['x'] ?>" y="<?= $p['y'] - 12 ?>" text-anchor="middle"><?= $p['value'] ?></text>
                        <text x="<?= $p['x'] ?>" y="198" text-anchor="middle"><?= $p['year'] ?></text>
                    <?php endforeach; ?>
                </svg>
                <p class="chart-note">*Média nacional aproximada do diesel S10, valores estimados por ano — referência ANP. Ilustrativo, não substitui cotação oficial do dia.</p>
            </div>
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
                <img src="<?= View::asset('/assets/img/beneficio-economia.jpg') ?>" alt="Economia de Combustível" class="beneficio-icon" loading="lazy">
                <h3>Economia de Combustível</h3>
                <p>De 5% (mínimo garantido) a 20% (potencial máximo) de redução no consumo de diesel — 10% é a média real.</p>
            </div>
            <div class="beneficio-item">
                <span class="beneficio-icon">⚙️</span>
                <h3>Mais Performance</h3>
                <p>Otimiza a queima na câmara de combustão e reduz o "delay" do acelerador.</p>
            </div>
            <div class="beneficio-item">
                <img src="<?= View::asset('/assets/img/beneficio-emissoes.jpg') ?>" alt="Menos Emissões" class="beneficio-icon" loading="lazy">
                <h3>Menos Emissões</h3>
                <p>Otimiza a mistura ar/combustível, reduzindo poluentes — abre caminho para selo ESG.</p>
            </div>
            <div class="beneficio-item">
                <img src="<?= View::asset('/assets/img/beneficio-instalacao.jpg') ?>" alt="Instalação Simples" class="beneficio-icon" loading="lazy">
                <h3>Instalação Simples</h3>
                <p>Rápida e prática, compatível com a grande maioria dos caminhões a diesel.</p>
            </div>
        </div>

        <div class="beneficios-extra">
            <div class="extra-chart-card">
                <h4>Sucção de ar potencializada</h4>
                <p>As lâminas magnetizadas aumentam a entrada de ar no motor — melhorando a mistura ar/combustível, no mesmo princípio de ganho que o uso de Arla 32 busca em motores modernos.</p>
                <div class="gauge-wrap">
                    <div class="progress-bar" style="flex:1;height:16px;border-radius:8px;background:#e3e7ee;overflow:hidden;">
                        <div style="width:70%;height:100%;background:var(--green-dark);border-radius:8px;"></div>
                    </div>
                    <span class="gauge-value">até 70%</span>
                </div>
            </div>
            <div class="extra-chart-card">
                <h4>Menos poluentes no escapamento</h4>
                <p>Combustão mais completa ajuda a reduzir a emissão de poluentes em até 40% — comparativo ilustrativo:</p>
                <div class="emissions-bars">
                    <div class="emissions-bar-row">
                        <span>Sem Ecodiffusore <strong>100%</strong></span>
                        <div class="emissions-bar-track"><div class="emissions-bar-fill before"></div></div>
                    </div>
                    <div class="emissions-bar-row">
                        <span>Com Ecodiffusore <strong>até 40% menos</strong></span>
                        <div class="emissions-bar-track"><div class="emissions-bar-fill after" style="width:60%;"></div></div>
                    </div>
                </div>
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
        <figure class="install-steps">
            <a href="<?= View::asset('/assets/img/instalacao-passo-a-passo.jpg') ?>" target="_blank" rel="noopener">
                <img src="<?= View::asset('/assets/img/instalacao-passo-a-passo.jpg') ?>" alt="Passo a passo da instalação do Ecodiffusore" loading="lazy">
            </a>
            <figcaption>Instalação simples, em 8 passos, sem eletrônica — clique na imagem para ampliar.</figcaption>
        </figure>
    </div>
</section>

<section class="calculadora">
    <div class="site-container">
        <h2>Calculadora de Economia</h2>
        <p class="section-sub">Preencha os dados abaixo pra ver uma estimativa aproximada da sua economia.</p>
        <div class="calc-box">
            <div class="calc-inputs">
                <div class="calc-input-group">
                    <label for="calc-km"><strong id="calc-km-label">12.000 km/mês</strong></label>
                    <input type="range" id="calc-km" min="5000" max="30000" step="1000" value="12000">
                </div>
                <div class="calc-input-group">
                    <label for="calc-preco-litro">Preço do diesel na sua região (por litro)</label>
                    <div class="calc-input-currency">
                        <span>R$</span>
                        <input type="number" id="calc-preco-litro" min="1" step="0.01" value="6.10">
                    </div>
                </div>
            </div>
            <div class="calc-results">
                <div class="calc-result calc-min">
                    <span class="calc-result-icon">🛡️</span>
                    <span class="calc-result-title">5% – Mínimo Garantido</span>
                    <span class="calc-result-desc">Se não atingir, devolvemos o investimento</span>
                    <span class="calc-result-label">Economia Mensal</span>
                    <strong id="calc-min-month">R$ 0</strong>
                    <small><span id="calc-min-year">R$ 0</span>/ano</small>
                    <small><span id="calc-min-5y">R$ 0</span> em 5 anos</small>
                </div>
                <div class="calc-result calc-avg">
                    <span class="calc-badge">MAIS COMUM</span>
                    <span class="calc-result-icon">📈</span>
                    <span class="calc-result-title">10% – Média Real</span>
                    <span class="calc-result-desc">O que a maioria dos caminhoneiros consegue</span>
                    <span class="calc-result-label">Economia Mensal</span>
                    <strong id="calc-avg-month">R$ 0</strong>
                    <small><span id="calc-avg-year">R$ 0</span>/ano</small>
                    <small><span id="calc-avg-5y">R$ 0</span> em 5 anos</small>
                </div>
                <div class="calc-result calc-max">
                    <span class="calc-result-icon">🚀</span>
                    <span class="calc-result-title">20% – Potencial Máximo</span>
                    <span class="calc-result-desc">Direção econômica + rotas otimizadas</span>
                    <span class="calc-result-label">Economia Mensal</span>
                    <strong id="calc-max-month">R$ 0</strong>
                    <small><span id="calc-max-year">R$ 0</span>/ano</small>
                    <small><span id="calc-max-5y">R$ 0</span> em 5 anos</small>
                </div>
            </div>
            <p class="calc-disclaimer">A economia varia de acordo com estilo de direção, tipo de carga e condições da estrada. Investimento único, sem manutenção, lucro pra sempre.</p>
            <a href="#contato" class="btn btn-primary calc-cta" id="calc-cta">Quero Economizar <span id="calc-cta-value">R$ 0</span>/mês</a>
        </div>
    </div>
</section>

<section id="depoimentos" class="depoimentos">
    <div class="site-container">
        <h2>Quem já usa, aprova</h2>
        <p class="section-sub">Depoimentos reais de quem já roda com o Ecodiffusore.</p>
        <?php
        $depoimentosDemo = [
            ['nome' => 'Caminhoneiro autônomo', 'texto' => 'Caminhão melhorou a média e trouxe mais torque.', 'video' => 'depoimento-1'],
            ['nome' => 'Cliente Ecodiffusore', 'texto' => 'Senti a diferença já nos primeiros abastecimentos, o motor responde melhor.', 'video' => 'depoimento-2'],
            ['nome' => 'Transportadora parceira', 'texto' => 'Reduzimos o custo de combustível em toda a frota de forma perceptível.', 'video' => 'depoimento-3'],
            ['nome' => 'Licenciado Ecodiffusore', 'texto' => 'O consumo no painel confirma a economia real, dia após dia.', 'video' => 'depoimento-4'],
            ['nome' => 'Motorista parceiro', 'texto' => 'Rodando com mais economia e sem perder desempenho.', 'video' => 'depoimento-5'],
        ];
        ?>
        <div class="depoimentos-carousel" id="depoimentos-carousel">
            <div class="depoimentos-carousel-track">
                <?php foreach ($depoimentosDemo as $d): ?>
                    <div class="depoimento-slide">
                        <div class="depoimento-card">
                            <div class="depoimento-video-slot">
                                <video controls preload="metadata" poster="<?= View::asset('/assets/img/' . $d['video'] . '-poster.jpg') ?>">
                                    <source src="<?= View::asset('/assets/video/' . $d['video'] . '.mp4') ?>" type="video/mp4">
                                </video>
                            </div>
                            <div class="depoimento-body">
                                <p>“<?= htmlspecialchars($d['texto'], ENT_QUOTES, 'UTF-8') ?>”</p>
                                <cite>— <?= htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') ?></cite>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="carousel-nav prev" aria-label="Anterior">‹</button>
            <button type="button" class="carousel-nav next" aria-label="Próximo">›</button>
            <div class="carousel-dots">
                <?php foreach ($depoimentosDemo as $i => $d): ?>
                    <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
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
                <p>Sim. Se você não atingir o mínimo de 5% de economia garantido, devolvemos o seu investimento.</p>
            </details>
        </div>
    </div>
</section>

<section id="licenciado" class="licenciado">
    <div class="site-container licenciado-inner">
        <h2>Seja um Licenciado Ecodiffusore Brasil</h2>
        <p>Um dos maiores mercados do Brasil: milhões de caminhoneiros, milhares de transportadoras e forte dependência logística rodoviária. Faça parte da transformação do transporte brasileiro — economia, tecnologia, sustentabilidade, benefícios reais e fortalecimento do caminhoneiro.</p>
        <a href="https://wa.me/5545991021551?text=Quero%20ser%20um%20licenciado%20Ecodiffusore%20Brasil" target="_blank" rel="noopener" class="btn btn-primary">Quero ser Licenciado</a>
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
