<?php
// ⚠️ الكود تعليمي فقط – ليس نصيحة استثمارية

// ----------------------
// 1) جلب بيانات Bitcoin مع كاش
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=90";
$cacheFile = __DIR__ . "/btc_cache.json";

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
    $data = json_decode(file_get_contents($cacheFile), true);
} else {
    $response = @file_get_contents($apiUrl);
    if (!$response) die("⚠️ فشل في جلب البيانات من CoinGecko");
    $data = json_decode($response, true);
    if (!isset($data['prices'])) die("⚠️ البيانات غير متوفرة حالياً، حاول لاحقاً.");
    file_put_contents($cacheFile, json_encode($data));
}

$prices = array_column($data['prices'], 1);
$days   = range(1, count($prices));

// ----------------------
// 2) استخراج دعم/مقاومة لآخر 30 يوم (قمم/قيعان محلية)
// ----------------------
function supportResistance($data, $lookback = 30) {
    $n = count($data);
    $start = max(0, $n - $lookback);
    $slice = array_slice($data, $start);
    $supports = [];
    $resistances = [];
    for ($i = 1; $i < count($slice)-1; $i++) {
        if ($slice[$i] < $slice[$i-1] && $slice[$i] < $slice[$i+1]) $supports[] = $slice[$i];
        if ($slice[$i] > $slice[$i-1] && $slice[$i] > $slice[$i+1]) $resistances[] = $slice[$i];
    }
    // إزالة التكرارات مع تقريب خفيف لتجميع المستويات القريبة
    $supports = array_values(array_unique(array_map(function($v){ return round($v, 2); }, $supports)));
    $resistances = array_values(array_unique(array_map(function($v){ return round($v, 2); }, $resistances)));
    sort($supports);
    sort($resistances);
    return [$supports, $resistances];
}

list($supportsAll, $resistancesAll) = supportResistance($prices, 30);

// اختيار أقرب 3 مستويات دعم أسفل السعر وأقرب 3 مقاومات أعلى السعر
$lastPrice = end($prices);
$supports = [];
$resistances = [];
foreach ($supportsAll as $s) if ($s < $lastPrice) $supports[] = $s;
foreach ($resistancesAll as $r) if ($r > $lastPrice) $resistances[] = $r;
// ترتيب حسب القرب
usort($supports, function($a,$b) use ($lastPrice){ return abs($lastPrice-$a) <=> abs($lastPrice-$b); });
usort($resistances, function($a,$b) use ($lastPrice){ return abs($lastPrice-$a) <=> abs($lastPrice-$b); });
$supports = array_slice($supports, 0, 3);
$resistances = array_slice($resistances, 0, 3);

// تحليل نصي سريع
$analysis = "السعر الحالي: $" . number_format($lastPrice, 2) . ". ";
if (count($supports)) $analysis .= "🟢 أقرب دعم: $" . number_format($supports[0], 2) . ". ";
if (count($resistances)) $analysis .= "🔴 أقرب مقاومة: $" . number_format($resistances[0], 2) . ". ";

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <title>Bitcoin – خطوط دعم/مقاومة أفقية</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <style>
        body { background:#121212; color:#f1f1f1; font-family:Arial, sans-serif; padding:24px; }
        .card { background:#1e1e1e; border-radius:14px; padding:28px; max-width:1000px; margin:0 auto; box-shadow:0 10px 30px rgba(0,0,0,.35); }
        h2 { margin:0 0 12px; }
        .meta { margin: 8px 0 22px; opacity:.9; }
        canvas { background:#181818; border-radius:10px; padding:14px; }
        .toolbar { margin-top:18px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
        .btn { background:#03a9f4; color:#fff; border:none; border-radius:8px; padding:10px 14px; cursor:pointer; }
        .legend { margin-top:12px; font-size:14px; opacity:.9 }
        .pill { display:inline-block; padding:4px 8px; border-radius:999px; margin:0 6px 6px 0; }
        .pill.green { background:#1b5e20; }
        .pill.red { background:#b71c1c; }
        .pill.gray { background:#424242; }
    </style>
</head>
<body>
    <div class="card" id="report">
        <h2>📊 Bitcoin – دعم & مقاومة (آخر 30 يوم)</h2>
        <div class="meta"><?= $analysis ?></div>

        <canvas id="priceChart" height="120"></canvas>

        <div class="legend">
            <span class="pill gray">السعر</span>
            <?php foreach ($supports as $i => $s): ?>
                <span class="pill green">Support #<?= $i+1 ?>: $<?= number_format($s,2) ?></span>
            <?php endforeach; ?>
            <?php foreach ($resistances as $i => $r): ?>
                <span class="pill red">Resistance #<?= $i+1 ?>: $<?= number_format($r,2) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="toolbar">
            <button class="btn" onclick="downloadPDF()">📄 تحميل PDF</button>
        </div>
    </div>

<script>
const prices = <?= json_encode($prices) ?>;
const days = <?= json_encode($days) ?>;
const supportLevels = <?= json_encode(array_values($supports)) ?>;
const resistanceLevels = <?= json_encode(array_values($resistances)) ?>;

// نحول مستويات الدعم/المقاومة إلى Datasets أفقية عبر Arrays ثابتة
function makeHorizontalDataset(level, label, color, dash=[6,6]) {
    return {
        label,
        data: Array(prices.length).fill(level),
        borderColor: color,
        borderDash: dash,
        pointRadius: 0,
        fill: false,
        tension: 0,
    };
}

const datasets = [
    { label:'السعر', data:prices, borderColor:'#9e9e9e', fill:false, pointRadius:0, tension:0.15 }
];

supportLevels.forEach((lvl, idx) => {
    datasets.push(makeHorizontalDataset(lvl, 'Support #' + (idx+1), 'rgba(0,200,83,0.95)'));
});
resistanceLevels.forEach((lvl, idx) => {
    datasets.push(makeHorizontalDataset(lvl, 'Resistance #' + (idx+1), 'rgba(244,67,54,0.95)'));
});

new Chart(document.getElementById('priceChart').getContext('2d'), {
    type: 'line',
    data: { labels: days, datasets },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#fff' } } },
        scales: {
            x: { ticks: { color: '#fff' } },
            y: { ticks: { color: '#fff' } }
        }
    }
});

function downloadPDF(){
    html2pdf().set({
        margin: 10,
        filename: 'bitcoin_support_lines.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(document.getElementById('report')).save();
}
</script>
</body>
</html>
