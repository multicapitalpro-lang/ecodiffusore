<?php
use App\Core\CardPricing;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Order;

/** @var array $order */
/** @var array $items */
/** @var array $payments */
/** @var string $termsText */
/** @var int $maxInstallments */
/** @var array $feeSettings */

$token = $order['public_token'];
$statusLabels = ['em_andamento' => 'Em andamento', 'atendido' => 'Atendido', 'verificado' => 'Pago', 'cancelado' => 'Cancelado'];
$methodLabels = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];

$termsAccepted = !empty($order['terms_accepted_at']);
// Fase 66: cobranca CANCELADA (comprador trocou de forma de pagamento) nao conta como "ja tem
// pagamento" -- senao a pagina ficava travada mostrando um Pix morto pra sempre. So' pendente/pago
// contam de verdade.
$activePayments = array_values(array_filter($payments, fn ($p) => in_array($p['status'], ['pendente', 'pago'], true)));
$hasPayment = (bool) $activePayments;
$isPaid = $order['status'] === 'verificado';

$basePrice = (float) $order['total_value'];
$installmentsTable = [];
if ($basePrice > 0) {
    for ($n = 1; $n <= $maxInstallments; $n++) {
        $installmentsTable[] = ['n' => $n, 'total' => CardPricing::chargeAmount($basePrice, $n), 'parcela' => CardPricing::installmentValue($basePrice, $n)];
    }
}

$missing = Order::missingDocumentLabels($order);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedido #<?= (int) $order['id'] ?> — Ecodiffusore Brasil</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<style>
:root{--green:#2e9b43;--green-dark:#1f7a33;--navy:#0d2f55;--ink:#14243a;--muted:#66756d;--bg:#f3f7fb;--line:#dfe7e2;--amber:#b7791f;--amber-bg:#fff4dc;--red:#c53030;}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink);line-height:1.5}
.wrap{max-width:560px;margin:0 auto;padding:0 0 40px}
header{background:var(--navy);padding:18px 16px;text-align:center}
header img{max-width:220px;height:auto}
h1{font-size:1.25rem;margin:14px 16px 4px}
h2{font-size:1.05rem;margin:0 0 8px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;margin:12px 16px}
.hint{color:var(--muted);font-size:.85rem}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700}
.badge-active{background:#e5f6e0;color:var(--green-dark)}
.badge-novo{background:#dbeafe;color:#1d4ed8}
.badge-inactive{background:#f1f2f5;color:#6b7280}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{text-align:left;padding:8px 4px;border-bottom:1px solid var(--line)}
th{font-size:.72rem;text-transform:uppercase;color:var(--muted);font-weight:700}
tfoot td{font-weight:800;border-bottom:none}
label{display:block;font-weight:700;font-size:.88rem;margin:12px 0 6px}
input[type=text],input[type=file],select{width:100%;padding:11px 12px;border:1px solid #c7d2cc;border-radius:10px;font-size:1rem;background:#fbfdfb}
input[type=checkbox]{width:18px;height:18px;flex-shrink:0}
.btn{display:block;width:100%;text-align:center;padding:14px 16px;border-radius:10px;border:none;font-weight:800;font-size:1rem;cursor:pointer;text-decoration:none;margin-top:14px}
.btn-primary{background:var(--green);color:#fff}
.btn-outline{background:#fff;color:var(--ink);border:1px solid var(--line)}
.msg{border-radius:10px;padding:12px 14px;margin:12px 16px;font-size:.9rem}
.msg-ok{background:#e5f6e0;color:var(--green-dark)}
.msg-erro{background:#fde8e8;color:var(--red)}
.msg-warn{background:var(--amber-bg);color:var(--amber)}
.terms-box{max-height:240px;overflow-y:auto;background:#fbfdfb;border:1px solid var(--line);border-radius:10px;padding:12px;font-size:.85rem;white-space:pre-wrap;margin:10px 0}
.checklist{list-style:none;padding:0;margin:10px 0;font-size:.9rem}
.checklist li{padding:4px 0}
.accept-row{display:flex;gap:10px;align-items:flex-start;margin-top:12px}
.pix-code{width:100%;font-size:.75rem;padding:8px;border-radius:8px;border:1px solid var(--line);margin-top:8px;word-break:break-all}
footer{text-align:center;color:var(--muted);font-size:.78rem;padding:20px 16px 0}
.trust-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 16px}
.trust-item{background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px;font-size:.76rem;text-align:center;color:var(--muted)}
.trust-item strong{display:block;font-size:1.3rem;margin-bottom:2px}
.overlay{display:none;position:fixed;inset:0;background:rgba(13,47,85,.72);z-index:999;align-items:flex-end;justify-content:center;padding:0}
.overlay-box{background:#fff;border-radius:18px 18px 0 0;padding:22px 20px 26px;max-width:560px;width:100%;text-align:center}
.overlay-box .emoji{font-size:2.2rem}
.overlay-box h3{margin:8px 0 6px;font-size:1.15rem}
.overlay-box p{color:var(--muted);font-size:.9rem;margin:0 0 6px}
@media (min-width:601px){.overlay{align-items:center}.overlay-box{border-radius:18px}}
</style>
</head>
<body>
<header>
    <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="Ecodiffusore Brasil">
</header>

<div class="wrap">
    <h1>Pedido #<?= (int) $order['id'] ?></h1>
    <p style="margin:0 16px 12px;">
        <span class="badge badge-<?= $order['status'] === 'verificado' ? 'active' : ($order['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span>
    </p>

    <?php if (isset($_GET['termos_ok'])): ?>
        <p class="msg msg-ok">Termos aceitos! Agora escolha como prefere pagar.</p>
    <?php endif; ?>
    <?php if (isset($_GET['erro_termos'])): ?>
        <p class="msg msg-erro">Marque a caixinha de aceite pra continuar.</p>
    <?php endif; ?>
    <?php if (isset($_GET['cobranca_ok'])): ?>
        <p class="msg msg-ok">Cobrança gerada! Confira abaixo como pagar.</p>
    <?php endif; ?>
    <?php if (isset($_GET['cobranca_cancelada'])): ?>
        <p class="msg msg-ok">Cobrança anterior cancelada — escolha outra forma de pagamento abaixo.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['erro_cobranca'])): ?>
        <p class="msg msg-erro">Não foi possível gerar a cobrança: <?= View::e($_GET['erro_cobranca']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['docs_sucesso'])): ?>
        <p class="msg msg-ok">Documentos enviados! Assim que confirmarmos, seu pedido segue pra fabricação.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['erro_docs'])): ?>
        <p class="msg msg-erro"><?= $_GET['erro_docs'] === '1' ? 'Sessão expirada, tente de novo.' : View::e($_GET['erro_docs']) ?></p>
    <?php endif; ?>

    <div class="card">
        <h2>Resumo</h2>
        <p style="margin:0 0 4px;"><strong><?= View::e($order['client_name']) ?></strong></p>
        <?php if (!empty($order['seller_name'])): ?>
            <p class="hint" style="margin:0 0 10px;">Atendido por <?= View::e($order['seller_name']) ?></p>
        <?php endif; ?>
        <table>
            <thead><tr><th>Produto</th><th>Qtd.</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= View::e($item['product_name']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Total</td><td>R$ <?= number_format($basePrice, 2, ',', '.') ?></td></tr>
            </tfoot>
        </table>
        <?php if (!empty($order['tracking_code']) || !empty($order['tracking_carrier'])): ?>
            <p class="hint" style="margin-top:10px;">Rastreio: <?= View::e($order['tracking_carrier'] ?: '—') ?><?= $order['tracking_code'] ? ' — ' . View::e($order['tracking_code']) : '' ?></p>
        <?php endif; ?>
    </div>

    <?php if (!$isPaid): ?>
        <div class="trust-grid">
            <div class="trust-item"><strong>🔒</strong>Pagamento processado com segurança pela Asaas</div>
            <div class="trust-item"><strong>📜</strong>Produto com patente e marca registradas no INPI</div>
            <div class="trust-item"><strong>🛡️</strong>Garantia de 90 dias + devolução se não atingir 5% de economia</div>
            <div class="trust-item"><strong>🇧🇷</strong>Fabricado no Brasil, sob encomenda</div>
        </div>
    <?php endif; ?>

    <?php if (!$termsAccepted): ?>
        <div class="card">
            <h2>📋 Termos de Compra</h2>
            <div class="terms-box"><?= $termsText !== '' ? View::e($termsText) : 'Termos de compra ainda não cadastrados — fale com quem te vendeu o produto.' ?></div>
            <form action="/pedido/<?= View::e($token) ?>/aceitar-termos" method="post">
                <?= Csrf::field() ?>
                <div class="accept-row">
                    <input type="checkbox" id="aceite" name="aceite" value="1" required>
                    <label for="aceite" style="margin:0;">Li e aceito os Termos de Compra do Ecodiffusore.</label>
                </div>
                <button type="submit" class="btn btn-primary">Aceitar e continuar</button>
            </form>
        </div>
    <?php else: ?>

        <?php if (!$hasPayment): ?>
            <div class="card">
                <h2>Forma de pagamento</h2>
                <p class="hint">Pix e Boleto saem pelo mesmo preço de tabela. No cartão, o valor já é o total certinho pra cada opção de parcelas.</p>
                <form action="/pedido/<?= View::e($token) ?>/cobranca" method="post" id="charge-form">
                    <?= Csrf::field() ?>
                    <?php if (empty($order['client_document'])): ?>
                        <label for="document">CPF ou CNPJ</label>
                        <input type="text" id="document" name="document" placeholder="Só números" required inputmode="numeric">
                    <?php endif; ?>
                    <label for="billing_type">Como você quer pagar?</label>
                    <select id="billing_type" name="billing_type">
                        <option value="PIX">Pix — sem taxa</option>
                        <option value="BOLETO">Boleto — sem taxa</option>
                        <option value="CREDIT_CARD">Cartão de crédito</option>
                    </select>
                    <div id="installments-wrap" style="display:none;">
                        <label for="installments">Parcelas</label>
                        <select id="installments" name="installments">
                            <?php foreach ($installmentsTable as $row): ?>
                                <option value="<?= $row['n'] ?>"><?= $row['n'] ?>x de R$ <?= number_format($row['parcela'], 2, ',', '.') ?><?= $row['n'] === 1 ? ' (à vista)' : ' — total R$ ' . number_format($row['total'], 2, ',', '.') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Gerar cobrança</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>Pagamento</h2>
                <?php foreach ($activePayments as $p): ?>
                    <p style="margin:6px 0;"><?= View::e($methodLabels[$p['method']] ?? $p['method']) ?> — R$ <?= number_format((float) $p['amount'], 2, ',', '.') ?>
                        <?php if ($p['status'] === 'pendente'): ?>
                            <span class="badge badge-novo">Pendente</span>
                        <?php else: ?>
                            <span class="badge badge-active">Pago</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($p['status'] === 'pendente'): ?>
                        <?php if ($p['method'] === 'PIX' && $p['pix_payload']): ?>
                            <label for="pix-<?= (int) $p['id'] ?>">Pix copia-e-cola</label>
                            <input type="text" id="pix-<?= (int) $p['id'] ?>" class="pix-code" readonly value="<?= View::e($p['pix_payload']) ?>" onclick="this.select()">
                            <p class="hint" style="margin-top:6px;">Abra o app do seu banco, escolha pagar com Pix Copia e Cola, e cole o código acima.</p>
                        <?php elseif ($p['method'] === 'BOLETO'): ?>
                            <?php if ($p['pix_payload']): ?>
                                <label for="boleto-<?= (int) $p['id'] ?>">Linha digitável do boleto</label>
                                <input type="text" id="boleto-<?= (int) $p['id'] ?>" class="pix-code" readonly value="<?= View::e($p['pix_payload']) ?>" onclick="this.select()">
                            <?php endif; ?>
                            <?php if ($p['checkout_url']): ?>
                                <a href="<?= View::e($p['checkout_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="margin-top:10px;">📄 Baixar boleto (PDF)</a>
                            <?php endif; ?>
                        <?php elseif ($p['method'] === 'CREDIT_CARD' && $p['checkout_url']): ?>
                            <p class="hint">O número do cartão é digitado numa página segura da Asaas (nunca no nosso site) — redirecionando você agora...</p>
                            <a href="<?= View::e($p['checkout_url']) ?>" class="btn btn-primary" id="card-redirect-link">Continuar pro pagamento seguro</a>
                            <script>window.location.href = <?= json_encode($p['checkout_url']) ?>;</script>
                        <?php endif; ?>
                        <form action="/pedido/<?= View::e($token) ?>/cancelar-cobranca" method="post" style="margin-top:12px;">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-outline">Prefiro pagar de outro jeito</button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($isPaid): ?>
            <div class="card" style="<?= $missing ? '' : 'display:none;' ?>">
                <h2>📎 Cadastro do veículo</h2>
                <?php if ($missing): ?>
                    <p class="hint">O seu Ecodiffusore só vai pra fabricação depois que você enviar tudo abaixo.</p>
                    <ul class="checklist">
                        <?php foreach (Order::REQUIRED_VEHICLE_FIELDS as $field => $label): ?>
                            <li><?= empty($order[$field]) ? '❌' : '✅' ?> <?= View::e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <form action="/pedido/<?= View::e($token) ?>/documentos" method="post" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <?php if (empty($order['vehicle_plate'])): ?>
                            <label for="vehicle_plate">Placa do veículo</label>
                            <input type="text" id="vehicle_plate" name="vehicle_plate" maxlength="10" style="text-transform:uppercase">
                        <?php endif; ?>
                        <?php if (empty($order['vehicle_document_path'])): ?>
                            <label for="vehicle_document">Documento do veículo (CRLV)</label>
                            <input type="file" id="vehicle_document" name="vehicle_document" accept="image/*,.pdf">
                        <?php endif; ?>
                        <?php if (empty($order['cnh_document_path'])): ?>
                            <label for="cnh_document">CNH</label>
                            <input type="file" id="cnh_document" name="cnh_document" accept="image/*,.pdf">
                        <?php endif; ?>
                        <?php if (empty($order['photo1_path'])): ?>
                            <label for="photo1">Foto 1 do veículo</label>
                            <input type="file" id="photo1" name="photo1" accept="image/*">
                        <?php endif; ?>
                        <?php if (empty($order['photo2_path'])): ?>
                            <label for="photo2">Foto 2 do veículo</label>
                            <input type="file" id="photo2" name="photo2" accept="image/*">
                        <?php endif; ?>
                        <?php if (empty($order['photo3_path'])): ?>
                            <label for="photo3">Foto 3 do veículo</label>
                            <input type="file" id="photo3" name="photo3" accept="image/*">
                        <?php endif; ?>
                        <?php if (empty($order['telemetry_path'])): ?>
                            <label for="telemetry">Telemetria (foto do painel/rastreador)</label>
                            <input type="file" id="telemetry" name="telemetry" accept="image/*,.pdf">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Enviar</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (!$missing): ?>
                <p class="msg msg-ok">✅ Cadastro do veículo completo — seu pedido já está liberado pra fabricação.</p>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

    <footer>ECODIFFUSORE BRASIL · Ecologia · Potência · Economia</footer>
</div>

<?php if (!$isPaid): ?>
<div class="overlay" id="exit-overlay">
    <div class="overlay-box">
        <div class="emoji">⏳</div>
        <h3>Antes de você sair...</h3>
        <p>O Ecodiffusore se paga sozinho em até 2 meses com a economia de diesel. Depois disso, todo abastecimento vira economia direto no seu bolso — é dinheiro que fica com você.</p>
        <button type="button" class="btn btn-primary" id="exit-overlay-close">Continuar minha compra</button>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var billing = document.getElementById('billing_type');
    var wrap = document.getElementById('installments-wrap');
    if (billing && wrap) {
        var sync = function () { wrap.style.display = billing.value === 'CREDIT_CARD' ? 'block' : 'none'; };
        billing.addEventListener('change', sync);
        sync();
    }
})();

(function () {
    // Fase 64: remarketing suave de saida -- nao oferece desconto, so relembra o payback do
    // produto. Desktop: deteta o mouse indo em direcao a aba/fechar. Mobile: intercepta o botao
    // "voltar" (nao ha "mouseleave" confiavel em touch). Mostra so 1 vez por visita.
    var overlay = document.getElementById('exit-overlay');
    if (!overlay) return;
    var shown = false;
    function showOverlay() {
        if (shown) return;
        shown = true;
        overlay.style.display = 'flex';
    }
    document.addEventListener('mouseleave', function (e) {
        if (e.clientY <= 0) showOverlay();
    });
    try {
        history.pushState({ exitGuard: true }, '');
        window.addEventListener('popstate', function () {
            if (!shown) {
                showOverlay();
                history.pushState({ exitGuard: true }, '');
            }
        });
    } catch (e) {}
    document.getElementById('exit-overlay-close')?.addEventListener('click', function () {
        overlay.style.display = 'none';
    });
})();
</script>
</body>
</html>
