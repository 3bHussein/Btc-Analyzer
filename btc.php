<?php
// ⚠️ الكود تعليمي فقط – مش نصيحة استثمارية

// ----------------------
// 1- جلب بيانات Bitcoin مع كاش
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$cacheFile = __DIR__ . "/btc_cache.json";

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
    $data = json_decode(file_get_contents($cacheFile), true);
} else {
    $response = file_get_contents($apiUrl);
    if (!$response) die("⚠️ فشل في جلب البيانات من CoinGecko");
    $data = json_decode($response, true);
    if (!isset($data['prices'])) die("⚠️ البيانات غير متوفرة حالياً، حاول لاحقاً.");
    file_put_contents($cacheFile, json_encode($data));
}

// استخراج الأسعار
$prices = array_column($data['prices'], 1);
$days = range(1, count($prices));

// ----------------------
// 2- دالة الانحدار الخطي
// ----------------------
function linearRegression($x, $y) {
    $n = count($x);
    $x_sum = array_sum($x);
    $y_sum = array_sum($y);
    $xx_sum = 0;
    $xy_sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $xx_sum += $x[$i] * $x[$i];
        $xy_sum += $x[$i] * $y[$i];
    }
    $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $xx_sum - $x_sum * $x_sum);
    $intercept = ($y_sum - $slope * $x_sum) / $n;
    return [$slope, $intercept];
}

// ----------------------
// 3- تطبيق الانحدار والتوقع
// ----------------------
list($slope, $intercept) = linearRegression($days, $prices);

// السعر الحالي
$lastPrice = end($prices);

// توقع الأيام القادمة (5 أيام)
$futurePredictions = [];
for ($i = 1; $i <= 5; $i++) {
    $futurePredictions[] = [
        "day" => "بعد $i يوم",
        "price" => $slope * (count($prices) + $i) + $intercept
    ];
}

// ----------------------
// 4- التوصية
// ----------------------
$predictedPrice = $futurePredictions[0]['price'];
$recommendation = ($predictedPrice > $lastPrice) 
    ? "🚀 يفضل الشراء الآن، السعر متوقع أن يرتفع قريباً." 
    : "📉 يفضل البيع الآن، السعر متوقع أن ينخفض.";

$changePercent = (($predictedPrice - $lastPrice) / $lastPrice) * 100;

// ----------------------
// 5- حساب RSI
// ----------------------
function calculateRSI($prices, $period = 14) {
    $gains = $losses = [];
    for ($i = 1; $i < count($prices); $i++) {
        $change = $prices[$i] - $prices[$i - 1];
        $gains[] = $change > 0 ? $change : 0;
        $losses[] = $change < 0 ? abs($change) : 0;
    }

    $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
    $rsi = [];

    for ($i = $period; $i < count($gains); $i++) {
        $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
        $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
        if ($avgLoss == 0) {
            $rsi[] = 100;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi[] = 100 - (100 / (1 + $rs));
        }
    }
    return $rsi;
}
$rsiValues = calculateRSI($prices);

// ----------------------
// 6- حساب MACD
// ----------------------
function ema($data, $period) {
    $k = 2 / ($period + 1);
    $emaArray = [];
    $emaArray[0] = $data[0];
    for ($i = 1; $i < count($data); $i++) {
        $emaArray[$i] = $data[$i] * $k + $emaArray[$i - 1] * (1 - $k);
    }
    return $emaArray;
}
function calculateMACD($prices, $short=12, $long=26, $signal=9) {
    $emaShort = ema($prices, $short);
    $emaLong = ema($prices, $long);
    $macd = [];
    for ($i = 0; $i < count($prices); $i++) {
        $macd[] = $emaShort[$i] - $emaLong[$i];
    }
    $signalLine = ema($macd, $signal);
    return [$macd, $signalLine];
}
list($macdValues, $signalLine) = calculateMACD($prices);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>Bitcoin Dashboard مع مؤشرات RSI و MACD</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <style>
    body { background: linear-gradient(to right, #141e30, #243b55); color: #fff; font-family: 'Cairo', sans-serif; }
    .card { border-radius: 15px; background: #1f2a38; color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.3); margin-bottom:20px; }
    .price { font-size: 2rem; font-weight: bold; color: #4cafef; }
    .predicted { font-size: 1.5rem; color: #ffc107; }
    .signal-buy { color: #4caf50; font-size: 1.2rem; font-weight: bold; }
    .signal-sell { color: #ff5252; font-size: 1.2rem; font-weight: bold; }
    .alert-box { font-size: 1.2rem; font-weight: bold; padding: 15px; border-radius: 10px; margin-top: 15px; }
    canvas { max-width: 100%; height: auto !important; }
    table.dataTable { color: #fff; font-size:0.9rem; }
    .dataTables_wrapper .dataTables_filter input, 
    .dataTables_wrapper .dataTables_length select { background: #243b55; color: #fff; border: 1px solid #555; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg" style="background:#111827;">
    <div class="container-fluid">
      <a class="navbar-brand" href="#" style="color:#4cafef;">🚀 Bitcoin Dashboard</a>
    </div>
  </nav>

  <div class="container py-5">
    <!-- السعر والتوقع -->
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h3>السعر الحالي</h3>
          <p class="price"><?php echo number_format($lastPrice, 2); ?> $</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h3>التوقع غداً</h3>
          <p class="predicted"><?php echo number_format($predictedPrice, 2); ?> $</p>
          <p>النسبة المتوقعة: <?=number_format($changePercent,2)?> %</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h3>التوصية</h3>
          <p class="<?php echo ($predictedPrice > $lastPrice) ? 'signal-buy' : 'signal-sell'; ?>">
            <?php echo $recommendation; ?>
          </p>
        </div>
      </div>
    </div>

    <!-- مؤشرات RSI و MACD -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card p-4 text-center">
          <h3>RSI (14)</h3>
          <canvas id="rsiChart"></canvas>
          <p class="mt-2">آخر قيمة: <?=number_format(end($rsiValues),2)?></p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-4 text-center">
          <h3>MACD</h3>
          <canvas id="macdChart"></canvas>
          <p class="mt-2">آخر قيمة MACD: <?=number_format(end($macdValues),2)?> | Signal: <?=number_format(end($signalLine),2)?></p>
        </div>
      </div>
    </div>

    <!-- الفورم لتحديد الأهداف -->
    <div class="card p-4">
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

    <!-- الرسم البياني للسعر -->
    <div class="card p-4">
      <h3 class="text-center">📈 حركة البيتكوين آخر 30 يوم + التوقعات</h3>
      <canvas id="btcChart" height="120"></canvas>
    </div>
  </div>

  <script>
    const prices = <?php echo json_encode($prices); ?>;
    const labels = Array.from({length: prices.length}, (_, i) => "Day " + (i+1));
    const future = <?php echo json_encode(array_column($futurePredictions, 'price')); ?>;
    const futureLabels = future.map((_, i) => "بعد " + (i+1) + " يوم");
    const rsi = <?php echo json_encode($rsiValues); ?>;
    const macd = <?php echo json_encode($macdValues); ?>;
    const signalLine = <?php echo json_encode($signalLine); ?>;

    // سعر البيتكوين + التوقعات
    new Chart(document.getElementById('btcChart'), {
      type: 'line',
      data: {
        labels: [...labels, ...futureLabels],
        datasets: [{
          label: 'السعر التاريخي',
          data: prices,
          borderColor: '#4cafef',
          backgroundColor: 'rgba(76, 175, 239, 0.2)',
          borderWidth: 2,
          tension: 0.4,
          fill: true
        },{
          label: 'التوقعات (5 أيام)',
          data: [...Array(prices.length).fill(null), ...future],
          borderColor: '#ffc107',
          borderDash: [5, 5],
          borderWidth: 2,
          tension: 0.4,
          fill: false
        }]
      },
      options: { plugins: { legend: { labels: { color: '#fff' } } }, scales: { x:{ticks:{color:'#ddd'}}, y:{ticks:{color:'#ddd'}} } }
    });

    // RSI Chart
    new Chart(document.getElementById('rsiChart'), {
      type: 'line',
      data: { labels: labels.slice(-rsi.length), datasets: [{ label: 'RSI', data: rsi, borderColor: '#00e676', borderWidth:2 }] },
      options: { plugins: { legend: { labels: { color: '#fff' } } }, scales: { x:{ticks:{color:'#ddd'}}, y:{min:0,max:100,ticks:{color:'#ddd'}} } }
    });

    // MACD Chart
    new Chart(document.getElementById('macdChart'), {
      type: 'line',
      data: { labels: labels, datasets: [
        { label:'MACD', data: macd, borderColor:'#ff9800', borderWidth:2 },
        { label:'Signal', data: signalLine, borderColor:'#4caf50', borderWidth:2 }
      ] },
      options: { plugins: { legend: { labels: { color: '#fff' } } }, scales: { x:{ticks:{color:'#ddd'}}, y:{ticks:{color:'#ddd'}} } }
    });
  </script>
</body>
</html>
