<?php
// ⚠️ تنبيه: الكود تعليمي فقط – مش نصيحة استثمارية

// ----------------------
// 1- جلب بيانات Bitcoin
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(file_get_contents($apiUrl), true);

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
$lastPrice = end($prices);
$predictedPrice = $slope * (count($prices) + 1) + $intercept;

// ----------------------
// 4- التوصية
// ----------------------
$recommendation = ($predictedPrice > $lastPrice)
    ? "🚀 يفضل الشراء الآن، السعر متوقع أن يرتفع قريباً."
    : "📉 يفضل البيع الآن، السعر متوقع أن ينخفض.";

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
  <title>Bitcoin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background: #121212; color: #fff; font-family: 'Cairo', sans-serif; }
    .card { border-radius: 15px; background: #1f2a38; color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
    .price { font-size: 2rem; font-weight: bold; color: #4cafef; }
    .predicted { font-size: 1.5rem; color: #ffc107; }
    .signal-buy { color: #4caf50; font-size: 1.2rem; font-weight: bold; }
    .signal-sell { color: #ff5252; font-size: 1.2rem; font-weight: bold; }
    .alert-box { font-size: 1.2rem; font-weight: bold; padding: 15px; border-radius: 10px; margin-top: 15px; }
    .export-buttons { margin-bottom: 10px; }
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

  <div class="card mt-5 p-4">
    <h3 class="text-center">📈 حركة البيتكوين آخر 30 يوم + التوقع</h3>
    <canvas id="btcChart" height="120"></canvas>
  </div>

  <!-- جدول الأسعار -->
  <div class="card mt-5 p-4">
    <h3>📊 جدول الأسعار</h3>
    <div class="export-buttons">
      <button class="btn btn-success btn-sm" onclick="exportTableToCSV('prices.csv','pricesTable')">CSV</button>
      <button class="btn btn-primary btn-sm" onclick="exportTableToExcel('pricesTable','prices.xlsx')">Excel</button>
      <button class="btn btn-secondary btn-sm" onclick="printTable('pricesTable')">🖨️ Print</button>
    </div>
    <table class="table table-dark table-striped" id="pricesTable">
      <thead><tr><th>اليوم</th><th>السعر ($)</th></tr></thead>
      <tbody>
        <?php foreach ($prices as $i => $p): ?>
          <tr><td><?php echo $i+1; ?></td><td><?php echo number_format($p,2); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// الرسم البياني
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
      },
      {
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

// تصدير CSV
function exportTableToCSV(filename, tableId) {
    var csv = [];
    var rows = document.querySelectorAll('#' + tableId + ' tr');
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) row.push(cols[j].innerText);
        csv.push(row.join(","));
    }
    downloadCSV(csv.join("\n"), filename);
}
function downloadCSV(csv, filename) {
    var csvFile = new Blob([csv], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

// تصدير Excel
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
    filename = filename?filename+'.xls':'excel_data.xls';
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}

// طباعة جدول محدد
function printTable(tableId) {
    var divToPrint=document.getElementById(tableId);
    var newWin=window.open("");
    newWin.document.write("<html><head><title>Print</title></head><body>");
    newWin.document.write(divToPrint.outerHTML);
    newWin.document.write("</body></html>");
    newWin.print();
    newWin.close();
}
</script>
</body>
</html>
