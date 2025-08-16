<?php
/**
 * btc_dashboard.php — نسخة Dashboard تعرض البيانات التحليلية مع واجهة ويب جميلة
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('SYMBOL', 'BTCUSDT');
define('BASE_URL', 'https://api.binance.com');

function fetch_klines(string $symbol, string $interval, int $limit): array {
    if (!function_exists('curl_init')) {
        throw new Exception('cURL غير مثبت على السيرفر');
    }
    $url = BASE_URL . "/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        throw new Exception('API response error');
    }

    $out = [];
    foreach ($data as $row) {
        $out[] = [
            'time' => date('Y-m-d H:i', $row[0]/1000),
            'open' => (float)$row[1],
            'high' => (float)$row[2],
            'low'  => (float)$row[3],
            'close'=> (float)$row[4],
            'volume'=> (float)$row[5],
        ];
    }
    return $out;
}

function sma(array $values, int $period): array {
    $result = [];
    $count = count($values);
    for ($i=0; $i<$count; $i++) {
        if ($i+1 < $period) $result[] = null;
        else $result[] = array_sum(array_slice($values, $i+1-$period, $period))/$period;
    }
    return $result;
}

try {
    $interval = $_GET['interval'] ?? '1d';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    $data = fetch_klines(SYMBOL, $interval, $limit);
    $time = array_column($data,'time');
    $close = array_column($data,'close');
    $sma20 = sma($close,20);

} catch(Throwable $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>Bitcoin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f6f9; }
.card { margin-bottom: 20px; }
.chart-container { height: 400px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
<h1 class="mb-4 text-center">Bitcoin Dashboard</h1>
<div class="row">
    <div class="col-md-6">
        <div class="card p-3">
            <h5>سعر BTC اليوم</h5>
            <p class="display-6"><?= end($close); ?> USD</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <h5>متوسط 20 يوم</h5>
            <p class="display-6"><?= end($sma20); ?> USD</p>
        </div>
    </div>
</div>
<div class="card p-3 chart-container">
    <canvas id="btcChart"></canvas>
</div>
</div>
<script>
const ctx = document.getElementById('btcChart').getContext('2d');
const btcChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($time); ?>,
        datasets: [
            { label: 'Close', data: <?= json_encode($close); ?>, borderColor: 'black', fill: false },
            { label: 'SMA 20', data: <?= json_encode($sma20); ?>, borderColor: 'blue', fill: false }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: { x: { display: true, title: { display: true, text: 'التاريخ' } }, y: { display: true, title: { display: true, text: 'السعر USD' } } }
    }
});
</script>
</body>
</html>
