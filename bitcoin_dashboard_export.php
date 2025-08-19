<?php
// ⚠️ تنبيه: الكود تعليمي فقط – مش نصيحة استثمارية
// ----------------------------------
// 1- جلب بيانات Bitcoin آخر 30 يوم
// ----------------------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(file_get_contents($apiUrl), true);
$prices = array_column($data['prices'], 1);
$days = range(1, count($prices));

// ----------------------------------
// 2- حساب SMA و EMA
// ----------------------------------
function movingAverage($data, $period) {
    $result = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i + 1 < $period) {
            $result[] = null;
        } else {
            $subset = array_slice($data, $i + 1 - $period, $period);
            $result[] = array_sum($subset) / $period;
        }
    }
    return $result;
}
function exponentialMovingAverage($data, $period) {
    $k = 2 / ($period + 1);
    $ema = [];
    $ema[0] = $data[0];
    for ($i = 1; $i < count($data); $i++) {
        $ema[$i] = $data[$i] * $k + $ema[$i - 1] * (1 - $k);
    }
    return $ema;
}
$sma = movingAverage($prices, 7);
$ema = exponentialMovingAverage($prices, 7);

// ----------------------------------
// 3- توليد ملفات CSV / XLSX حسب الجدول المطلوب
// ----------------------------------
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $table = $_GET['table'] ?? '';

    $filename = $table . "_data." . $type;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');

    if ($table === 'prices') {
        fputcsv($output, ['Day', 'Price']);
        foreach ($prices as $i => $p) {
            fputcsv($output, [$i+1, $p]);
        }
    } elseif ($table === 'indicators') {
        fputcsv($output, ['Day', 'Price', 'SMA(7)', 'EMA(7)']);
        foreach ($prices as $i => $p) {
            fputcsv($output, [$i+1, $p, $sma[$i] ?? '-', $ema[$i] ?? '-']);
        }
    } elseif ($table === 'predictions') {
        fputcsv($output, ['Day', 'Predicted Price']);
        for ($i=1; $i<=5; $i++) {
            fputcsv($output, ["Day ".$i, end($prices) + ($i*50)]);
        }
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>Bitcoin Dashboard مع تصدير CSV/XLSX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-4">
  <h1 class="text-center mb-4">📊 Bitcoin Dashboard</h1>

  <!-- جدول الأسعار -->
  <div class="card mb-4 p-3 bg-secondary">
    <h3>📈 الأسعار (آخر 30 يوم)</h3>
    <a href="?export=csv&table=prices" class="btn btn-warning btn-sm">Export CSV</a>
    <a href="?export=xlsx&table=prices" class="btn btn-success btn-sm">Export Excel</a>
    <table class="table table-dark table-striped mt-3">
      <thead><tr><th>اليوم</th><th>السعر</th></tr></thead>
      <tbody>
        <?php foreach ($prices as $i => $p): ?>
          <tr><td><?= $i+1 ?></td><td><?= number_format($p,2) ?> $</td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- جدول المؤشرات -->
  <div class="card mb-4 p-3 bg-secondary">
    <h3>📊 المؤشرات (SMA/EMA)</h3>
    <a href="?export=csv&table=indicators" class="btn btn-warning btn-sm">Export CSV</a>
    <a href="?export=xlsx&table=indicators" class="btn btn-success btn-sm">Export Excel</a>
    <table class="table table-dark table-striped mt-3">
      <thead><tr><th>اليوم</th><th>السعر</th><th>SMA(7)</th><th>EMA(7)</th></tr></thead>
      <tbody>
        <?php foreach ($prices as $i => $p): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= number_format($p,2) ?> $</td>
            <td><?= $sma[$i] ? number_format($sma[$i],2) : '-' ?></td>
            <td><?= $ema[$i] ? number_format($ema[$i],2) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- جدول التوقعات -->
  <div class="card mb-4 p-3 bg-secondary">
    <h3>🔮 توقعات (5 أيام قادمة)</h3>
    <a href="?export=csv&table=predictions" class="btn btn-warning btn-sm">Export CSV</a>
    <a href="?export=xlsx&table=predictions" class="btn btn-success btn-sm">Export Excel</a>
    <table class="table table-dark table-striped mt-3">
      <thead><tr><th>اليوم</th><th>السعر المتوقع</th></tr></thead>
      <tbody>
        <?php for ($i=1; $i<=5; $i++): ?>
          <tr><td>Day <?= $i ?></td><td><?= number_format(end($prices) + ($i*50), 2) ?> $</td></tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
