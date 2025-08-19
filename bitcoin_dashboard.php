<?php
// ⚠️ الكود تعليمي – مش نصيحة استثمارية

// ----------------------
// 1- جلب بيانات Bitcoin
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(file_get_contents($apiUrl), true);
$prices = array_column($data['prices'], 1);
$days = range(1, count($prices));
$lastPrice = end($prices);

// ----------------------
// 2- دوال التوقع
// ----------------------

// انحدار خطي
function linearRegression($x, $y) {
    $n = count($x);
    $x_sum = array_sum($x);
    $y_sum = array_sum($y);
    $xx_sum = 0; $xy_sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $xx_sum += $x[$i] * $x[$i];
        $xy_sum += $x[$i] * $y[$i];
    }
    $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $xx_sum - $x_sum * $x_sum);
    $intercept = ($y_sum - $slope * $x_sum) / $n;
    return [$slope, $intercept];
}

// متوسط متحرك بسيط
function simpleMovingAverage($data, $period = 5) {
    if (count($data) < $period) return null;
    $sum = array_sum(array_slice($data, -$period));
    return $sum / $period;
}

// متوسط متحرك أسي
function exponentialMovingAverage($data, $period = 5) {
    $k = 2 / ($period + 1);
    $ema = $data[0];
    for ($i = 1; $i < count($data); $i++) {
        $ema = $data[$i] * $k + $ema * (1 - $k);
    }
    return $ema;
}

// ----------------------
// 3- حساب التوقعات
// ----------------------
list($slope, $intercept) = linearRegression($days, $prices);
$predictedLinear = $slope * (count($prices) + 1) + $intercept;
$predictedSMA = simpleMovingAverage($prices, 5);
$predictedEMA = exponentialMovingAverage($prices, 5);

// ----------------------
// 4- التوصيات
// ----------------------
$recommendation = ($predictedLinear > $lastPrice) 
    ? "🚀 يفضل الشراء (اتجاه صاعد)" 
    : "📉 يفضل البيع (اتجاه هابط)";

// ----------------------
// 5- أهداف التنبيه
// ----------------------
$buyTarget = isset($_POST['buy_target']) ? floatval($_POST['buy_target']) : 25000;
$sellTarget = isset($_POST['sell_target']) ? floatval($_POST['sell_target']) : 35000;
$alertMessage = "";
if ($lastPrice <= $buyTarget) {
    $alertMessage = "🚀 تنبيه: السعر نزل تحت $buyTarget $ → فرصة شراء!";
} elseif ($lastPrice >= $sellTarget) {
    $alertMessage = "📉 تنبيه: السعر طلع فوق $sellTarget $ → فرصة بيع!";
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>Bitcoin Dashboard متطور</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background: linear-gradient(to right, #141e30, #243b55); color: #fff; font-family: 'Cairo', sans-serif; }
    .card { border-radius: 15px; background: #1f2a38; color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
    .price { font-size: 2rem; font-weight: bold; color: #4cafef; }
    .predicted { font-size: 1.2rem; color: #ffc107; }
    .signal-buy { color: #4caf50; font-weight: bold; }
    .signal-sell { color: #ff5252; font-weight: bold; }
    .alert-box { font-size: 1.1rem; font-weight: bold; padding: 15px; border-radius: 10px; margin-top: 15px; }
    table { color:#fff; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg" style="background:#111827;">
    <div class="container-fluid">
      <a class="navbar-brand" href="#" style="color:#4cafef;">🚀 Bitcoin Dashboard</a>
    </div>
  </nav>

  <div class="container py-5">
    <div class="row g-4">
      <div class="col-md-3">
        <div class="card p-4 text-center">
          <h3>السعر الحالي</h3>
          <p class="price"><?php echo number_format($lastPrice, 2); ?> $</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-4 text-center">
          <h3>توقع بالانحدار</h3>
          <p class="predicted"><?php echo number_format($predictedLinear, 2); ?> $</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-4 text-center">
          <h3>توقع SMA</h3>
          <p class="predicted"><?php echo number_format($predictedSMA, 2); ?> $</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-4 text-center">
          <h3>توقع EMA</h3>
          <p class="predicted"><?php echo number_format($predictedEMA, 2); ?> $</p>
        </div>
      </div>
    </div>

    <!-- جدول مقارنة التوقعات -->
    <div class="card mt-4 p-4">
      <h4 class="mb-3">📊 مقارنة بين طرق التوقع</h4>
      <table class="table table-dark table-bordered text-center">
        <thead>
          <tr>
            <th>المؤشر</th>
            <th>القيمة (USD)</th>
            <th>الفرق عن السعر الحالي</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>السعر الحالي</td>
            <td><?php echo number_format($lastPrice, 2); ?> $</td>
            <td>-</td>
          </tr>
          <tr>
            <td>انحدار خطي</td>
            <td><?php echo number_format($predictedLinear, 2); ?> $</td>
            <td><?php echo number_format($predictedLinear - $lastPrice, 2); ?> $</td>
          </tr>
          <tr>
            <td>SMA (5 أيام)</td>
            <td><?php echo number_format($predictedSMA, 2); ?> $</td>
            <td><?php echo number_format($predictedSMA - $lastPrice, 2); ?> $</td>
          </tr>
          <tr>
            <td>EMA (5 أيام)</td>
            <td><?php echo number_format($predictedEMA, 2); ?> $</td>
            <td><?php echo number_format($predictedEMA - $lastPrice, 2); ?> $</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- تنبيهات -->
    <div class="card mt-4 p-4">
      <h4>⚡️ إعداد تنبيهات السعر</h4>
      <form method="post">
        <div class="row">
          <div class="col-md-6">
            <label>سعر الشراء (Buy Target)</label>
            <input type="number" name="buy_target" step="0.01" value="<?php echo $buyTarget; ?>" class="form-control">
          </div>
          <div class="col-md-6">
            <label>سعر البيع (Sell Target)</label>
            <input type="number" name="sell_target" step="0.01" value="<?php echo $sellTarget; ?>" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-warning mt-3">حفظ الأهداف</button>
      </form>
      <?php if ($alertMessage): ?>
        <div class="alert-box mt-3 bg-danger text-white text-center">
          <?php echo $alertMessage; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- الرسم البياني -->
    <div class="card mt-5 p-4">
      <h3 class="text-center">📈 حركة البيتكوين آخر 30 يوم + التوقع (Linear)</h3>
      <canvas id="btcChart" height="120"></canvas>
    </div>
  </div>

  <script>
    const ctx = document.getElementById('btcChart').getContext('2d');
    const prices = <?php echo json_encode($prices); ?>;
    const labels = Array.from({length: prices.length}, (_, i) => "Day " + (i+1));
    const predicted = <?php echo json_encode($predictedLinear); ?>;
    const extendedPrices = [...prices, predicted];
    const extendedLabels = [...labels, "Tomorrow"];

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: extendedLabels,
            datasets: [{
                label: 'السعر التاريخي',
                data: prices,
                borderColor: '#4cafef',
                backgroundColor: 'rgba(76, 175, 239, 0.2)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            },
            {
                label: 'توقع (Linear)',
                data: extendedPrices,
                borderColor: '#ffc107',
                borderDash: [5, 5],
                borderWidth: 2,
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#fff' } } },
            scales: {
                x: { ticks: { color: '#ddd' } },
                y: { ticks: { color: '#ddd' } }
            }
        }
    });
  </script>
</body>
</html>