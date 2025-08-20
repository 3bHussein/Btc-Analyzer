<?php
// ⚠️ الكود تعليمي فقط – ليس نصيحة استثمارية

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
// 2- دالة الانحدار الخطي + R²
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

    // حساب R²
    $y_mean = $y_sum / $n;
    $ss_tot = 0;
    $ss_res = 0;
    for ($i = 0; $i < $n; $i++) {
        $y_pred = $slope * $x[$i] + $intercept;
        $ss_tot += pow($y[$i] - $y_mean, 2);
        $ss_res += pow($y[$i] - $y_pred, 2);
    }
    $r2 = 1 - ($ss_res / $ss_tot);
    return [$slope, $intercept, $r2];
}

// ----------------------
// 3- تطبيق الانحدار والتوقع
// ----------------------
list($slope, $intercept, $r2) = linearRegression($days, $prices);

// السعر الحالي
$lastPrice = end($prices);

// توقع الأيام القادمة (5 أيام)
$futurePredictions = [];
for ($i = 1; $i <= 5; $i++) {
    $futurePredictions[] = [
        "day" => "بعد $i يوم",
        "price" => round($slope * (count($prices) + $i) + $intercept, 2)
    ];
}

// ----------------------
// 4- التوصية
// ----------------------
$predictedPrice = $futurePredictions[0]['price'];
$recommendation = ($predictedPrice > $lastPrice) 
    ? "🚀 يفضل الشراء الآن، السعر متوقع أن يرتفع قريباً." 
    : "📉 يفضل البيع الآن، السعر متوقع أن ينخفض.";
$confidence = round($r2 * 100, 2);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>توقع سعر Bitcoin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #121212;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .card {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 12px;
            margin: 20px auto;
            max-width: 900px;
            box-shadow: 0 0 15px rgba(0,0,0,0.6);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 10px;
        }
        th {
            background: #333;
        }
        .pagination {
            margin-top: 15px;
        }
        .pagination button {
            padding: 8px 12px;
            margin: 3px;
            border: none;
            border-radius: 5px;
            background: #444;
            color: #fff;
            cursor: pointer;
        }
        .pagination button.active {
            background: #03a9f4;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>📊 توقع سعر Bitcoin</h2>
        <p><strong>السعر الحالي:</strong> $<?= number_format($lastPrice, 2) ?></p>
        <p><strong>التوصية:</strong> <?= $recommendation ?></p>
        <p><strong>مؤشر الثقة (R²):</strong> <?= $confidence ?>%</p>
        
        <canvas id="btcChart" height="120"></canvas>

        <h3>📅 التوقعات القادمة</h3>
        <ul>
            <?php foreach ($futurePredictions as $p): ?>
                <li><?= $p["day"] ?>: $<?= number_format($p["price"], 2) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>📜 أسعار آخر 30 يوم</h3>
        <table id="priceTable">
            <thead>
                <tr><th>اليوم</th><th>السعر (USD)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($prices as $i => $price): ?>
                    <tr><td><?= $i+1 ?></td><td>$<?= number_format($price, 2) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination" id="pagination"></div>
    </div>

    <script>
        // بيانات الرسم البياني
        const prices = <?= json_encode($prices) ?>;
        const days = <?= json_encode($days) ?>;
        const future = <?= json_encode($futurePredictions) ?>;

        const ctx = document.getElementById('btcChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...days, ...future.map(f => f.day)],
                datasets: [{
                    label: 'السعر USD',
                    data: [...prices, ...future.map(f => f.price)],
                    borderColor: '#03a9f4',
                    backgroundColor: 'rgba(3, 169, 244, 0.2)',
                    tension: 0.2
                }]
            },
            options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } },
                scales: { x: { ticks: { color: '#fff' } }, y: { ticks: { color: '#fff' } } }
            }
        });

        // Pagination للجدول
        const rowsPerPage = 10;
        const rows = document.querySelectorAll("#priceTable tbody tr");
        const pageCount = Math.ceil(rows.length / rowsPerPage);
        const pagination = document.getElementById("pagination");

        function showPage(page) {
            rows.forEach((row, i) => {
                row.style.display = (i >= (page-1)*rowsPerPage && i < page*rowsPerPage) ? "" : "none";
            });
            document.querySelectorAll(".pagination button").forEach((btn, i) => {
                btn.classList.toggle("active", i+1 === page);
            });
        }

        for (let i = 1; i <= pageCount; i++) {
            const btn = document.createElement("button");
            btn.innerText = i;
            btn.onclick = () => showPage(i);
            pagination.appendChild(btn);
        }
        showPage(1);
    </script>
</body>
</html>