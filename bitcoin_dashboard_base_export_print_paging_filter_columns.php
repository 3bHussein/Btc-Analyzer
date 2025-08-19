<?php
// ⚠️ تنبيه: الكود تعليمي فقط – مش نصيحة استثمارية

$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(file_get_contents($apiUrl), true);
$prices = array_column($data['prices'], 1);
$days = range(1, count($prices));

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

list($slope, $intercept) = linearRegression($days, $prices);
$lastPrice = end($prices);
$predictedPrice = $slope * (count($prices) + 1) + $intercept;
$recommendation = ($predictedPrice > $lastPrice) ? "🚀 شراء" : "📉 بيع";
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>Bitcoin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css"/>
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css"/>
  <style>
    body { background: #121212; color: #fff; font-family: 'Cairo', sans-serif; }
    .card { background: #1e1e1e; color: #fff; }
    table.dataTable thead th { color: #4cafef; }
  </style>
</head>
<body>
<div class="container py-5">
  <h2 class="text-center">📊 جدول أسعار البيتكوين</h2>
  <div class="card p-3">
    <table id="btcTable" class="table table-dark table-striped" style="width:100%">
      <thead>
        <tr><th>اليوم</th><th>السعر $</th></tr>
      </thead>
      <tbody>
        <?php foreach($prices as $i => $p): ?>
          <tr><td><?="Day " . ($i+1)?></td><td><?=number_format($p,2)?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#btcTable').DataTable({
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: ['csv', 'excel', 'print'],
        initComplete: function () {
            this.api().columns().every(function () {
                var column = this;
                var select = $('<select><option value=""></option></select>')
                    .appendTo($(column.header()))
                    .on('change', function () {
                        var val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^'+val+'$' : '', true, false).draw();
                    });
                column.data().unique().sort().each(function (d, j) {
                    select.append('<option value="'+d+'">'+d+'</option>')
                });
            });
        }
    });
});
</script>
</body>
</html>
