<?php
// ⚠️ الكود تعليمي فقط – مش نصيحة استثمارية

// ----------------------
// 1- جلب بيانات Bitcoin
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(file_get_contents($apiUrl), true);

// استخراج الأسعار
$prices = array_column($data['prices'], 1);
$days = range(1, count($prices));

// ----------------------
// 2- دالة الانحدار الخطي (Linear Regression)
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

// ----------------------
// 5- أهداف التنبيه (مستخدمة من الفورم)
// ----------------------
$buyTarget = isset($_POST['buy_target']) ? floatval($_POST['buy_target']) : 25000;
$sellTarget = isset($_POST['sell_target']) ? floatval($_POST['sell_target']) : 35000;

// تحقق من التنبيه
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
  <title>Bitcoin Dashboard مع تنبيهات</title>
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
    .card { border-radius: 15px; background: #1f2a38; color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
    .price { font-size: 2rem; font-weight: bold; color: #4cafef; }
    .predicted { font-size: 1.5rem; color: #ffc107; }
    .signal-buy { color: #4caf50; font-size: 1.2rem; font-weight: bold; }
    .signal-sell { color: #ff5252; font-size: 1.2rem; font-weight: bold; }
    .alert-box { font-size: 1.2rem; font-weight: bold; padding: 15px; border-radius: 10px; margin-top: 15px; }
    table.dataTable { color: #fff; }
    .dataTables_wrapper .dataTables_filter input { background: #243b55; color: #fff; border: 1px solid #555; }
    .dataTables_wrapper .dataTables_length select { background: #243b55; color: #fff; border: 1px solid #555; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg" style="background:#111827;">
    <div class="container-fluid">
      <a class="navbar-brand" href="#" style="color:#4cafef;">🚀 Bitcoin Dashboard</a>
    </div>
  </nav>

  <div class="container py-5">
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

    <!-- الفورم لتحديد الأهداف -->
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

    <!-- جدول الأسعار -->
    <div class="card mt-5 p-4">
      <h3 class="text-center">📊 أسعار آخر 30 يوم</h3>
      <table id="pricesTable" class="display nowrap" style="width:100%">
        <thead>
          <tr><th>اليوم</th><th>السعر ($)</th></tr>
        </thead>
        <tbody>
        <?php foreach($prices as $i => $price): ?>
          <tr>
            <td><?="Day ".($i+1)?></td>
            <td><?=number_format($price, 2)?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- جدول التوقعات -->
    <div class="card mt-5 p-4">
      <h3 class="text-center">🤖 توقعات قادمة (5 أيام)</h3>
      <table id="predictTable" class="display nowrap" style="width:100%">
        <thead>
          <tr><th>اليوم</th><th>السعر المتوقع ($)</th></tr>
        </thead>
        <tbody>
        <?php foreach($futurePredictions as $pred): ?>
          <tr>
            <td><?=$pred['day']?></td>
            <td><?=number_format($pred['price'], 2)?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- الرسم البياني -->
    <div class="card mt-5 p-4">
      <h3 class="text-center">📈 حركة البيتكوين آخر 30 يوم + التوقع</h3>
      <canvas id="btcChart" height="120"></canvas>
    </div>
  </div>

  <script>
    // DataTables init
    $(document).ready(function() {
        $('#pricesTable, #predictTable').DataTable({
            pageLength: 10,
            dom: 'Bfrtip',
            buttons: ['csv', 'excel', 'print'],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    // Chart.js
    const ctx = document.getElementById('btcChart').getContext('2d');
    const prices = <?php echo json_encode($prices); ?>;
    const labels = Array.from({length: prices.length}, (_, i) => "Day " + (i+1));
    const predicted = <?php echo json_encode($predictedPrice); ?>;
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
            },{
                label: 'التوقع',
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