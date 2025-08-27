    <?php
// ⚠️ الكود تعليمي فقط – ليس نصيحة استثمارية

// ----------------------
// 1- جلب بيانات Bitcoin مع كاش
// ----------------------
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=90";
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
// 2- Moving Averages
// ----------------------
function movingAverage($data, $period) {
    $result = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i+1 < $period) {
            $result[] = null;
        } else {
            $slice = array_slice($data, $i+1-$period, $period);
            $result[] = array_sum($slice) / $period;
        }
    }
    return $result;
}
$ma7 = movingAverage($prices, 7);
$ma30 = movingAverage($prices, 30);

// ----------------------
// 3- Bollinger Bands (20 يوم)
// ----------------------
function bollingerBands($data, $period = 20, $mult = 2) {
    $middle = [];
    $upper = [];
    $lower = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i+1 < $period) {
            $middle[] = $upper[] = $lower[] = null;
        } else {
            $slice = array_slice($data, $i+1-$period, $period);
            $avg = array_sum($slice) / $period;
            $variance = 0;
            foreach ($slice as $v) $variance += pow($v - $avg, 2);
            $std = sqrt($variance / $period);
            $middle[] = $avg;
            $upper[] = $avg + $mult*$std;
            $lower[] = $avg - $mult*$std;
        }
    }
    return [$middle, $upper, $lower];
}
list($bb_mid, $bb_up, $bb_low) = bollingerBands($prices);

// ----------------------
// 4- RSI (14 يوم)
// ----------------------
function calcRSI($data, $period = 14) {
    $rsis = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i < $period) {
            $rsis[] = null;
        } else {
            $gains = 0; $losses = 0;
            for ($j = $i-$period+1; $j <= $i; $j++) {
                $diff = $data[$j] - $data[$j-1];
                if ($diff > 0) $gains += $diff;
                else $losses -= $diff;
            }
            if ($losses == 0) $rsis[] = 100;
            else {
                $rs = $gains / $losses;
                $rsis[] = 100 - (100 / (1 + $rs));
            }
        }
    }
    return $rsis;
}
$rsi = calcRSI($prices);

// ----------------------
// 5- MACD
// ----------------------
function ema($data, $period) {
    $k = 2 / ($period + 1);
    $emaArray = [];
    $emaArray[0] = $data[0];
    for ($i = 1; $i < count($data); $i++) {
        $emaArray[$i] = $data[$i] * $k + $emaArray[$i-1] * (1 - $k);
    }
    return $emaArray;
}
$ema12 = ema($prices, 12);
$ema26 = ema($prices, 26);
$macdLine = [];
for ($i = 0; $i < count($prices); $i++) $macdLine[] = $ema12[$i] - $ema26[$i];
$signalLine = ema($macdLine, 9);
$histogram = [];
for ($i = 0; $i < count($macdLine); $i++) $histogram[] = $macdLine[$i] - $signalLine[$i];

// ----------------------
// 6- Stochastic Oscillator (%K, %D)
// ----------------------
function stochasticOscillator($data, $kPeriod = 14, $dPeriod = 3) {
    $K = [];
    $D = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i+1 < $kPeriod) {
            $K[] = null;
            $D[] = null;
        } else {
            $slice = array_slice($data, $i+1-$kPeriod, $kPeriod);
            $low = min($slice);
            $high = max($slice);
            if ($high == $low) {
                $Kval = 50;
            } else {
                $Kval = (($data[$i] - $low) / ($high - $low)) * 100;
            }
            $K[] = $Kval;
            if ($i+1 < $kPeriod+$dPeriod-1) {
                $D[] = null;
            } else {
                $sliceK = array_slice($K, $i-$dPeriod+1, $dPeriod);
                $D[] = array_sum($sliceK)/count($sliceK);
            }
        }
    }
    return [$K, $D];
}
list($stochK, $stochD) = stochasticOscillator($prices);

// ----------------------
// 7- التحليل النصي
// ----------------------
$lastPrice = end($prices);
$lastRsi = end($rsi);
$lastK = end($stochK);
$lastD = end($stochD);

$analysis_text = "";
if ($lastRsi !== null) {
    if ($lastRsi > 70) $analysis_text .= "RSI: 🚨 تشبع شراء. ";
    elseif ($lastRsi < 30) $analysis_text .= "RSI: 🟢 تشبع بيع. ";
    else $analysis_text .= "RSI: ⚖️ طبيعي. ";
}
if ($lastK !== null && $lastD !== null) {
    if ($lastK > 80) $analysis_text .= "Stochastic: 🚨 تشبع شراء. ";
    elseif ($lastK < 20) $analysis_text .= "Stochastic: 🟢 تشبع بيع. ";
    if ($lastK > $lastD) $analysis_text .= "📈 إشارة شراء. ";
    elseif ($lastK < $lastD) $analysis_text .= "📉 إشارة بيع. ";
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Bitcoin + Stochastic Oscillator</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <style>
        body { background:#121212; color:#f1f1f1; font-family:Arial,sans-serif; text-align:center; padding:20px; }
        .card { background:#1e1e1e; padding:30px; border-radius:12px; margin:20px auto; max-width:1000px; box-shadow:0 0 15px rgba(0,0,0,0.6); }
        canvas { margin:30px auto; padding:15px; background:#181818; border-radius:10px; }
        .btn { display:inline-block; padding:12px 18px; margin:15px; border:none; border-radius:6px; background:#03a9f4; color:#fff; cursor:pointer; text-decoration:none; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th,td { border:1px solid #333; padding:10px; }
        th { background:#333; }
        .analysis { margin-top:20px; font-size:18px; padding:15px; background:#2a2a2a; border-radius:8px; }
    </style>
</head>
<body>
    <div class="card" id="report">
        <h2>📊 Bitcoin + Indicators (MA, Bollinger, RSI, MACD, Stochastic)</h2>

        <canvas id="priceChart" height="100"></canvas>
        <canvas id="rsiChart" height="70"></canvas>
        <canvas id="macdChart" height="90"></canvas>
        <canvas id="stochChart" height="70"></canvas>

        <div class="analysis"><?= $analysis_text ?></div>

        <h3>📜 بيانات آخر 90 يوم</h3>
        <table id="priceTable">
            <thead>
                <tr><th>اليوم</th><th>السعر</th><th>MA7</th><th>MA30</th><th>RSI</th><th>MACD</th><th>Signal</th><th>Hist</th><th>%K</th><th>%D</th></tr>
            </thead>
            <tbody>
                <?php foreach ($prices as $i => $price): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>$<?= number_format($price,2) ?></td>
                        <td><?= $ma7[$i] ? number_format($ma7[$i],2) : "-" ?></td>
                        <td><?= $ma30[$i] ? number_format($ma30[$i],2) : "-" ?></td>
                        <td><?= $rsi[$i] ? number_format($rsi[$i],2) : "-" ?></td>
                        <td><?= number_format($macdLine[$i],2) ?></td>
                        <td><?= number_format($signalLine[$i],2) ?></td>
                        <td><?= number_format($histogram[$i],2) ?></td>
                        <td><?= $stochK[$i] ? number_format($stochK[$i],2) : "-" ?></td>
                        <td><?= $stochD[$i] ? number_format($stochD[$i],2) : "-" ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button class="btn" onclick="downloadPDF()">📄 تحميل PDF</button>
    <button class="btn" onclick="downloadCSV()">📑 تحميل CSV</button>

    <script>
        const prices = <?= json_encode($prices) ?>;
        const days = <?= json_encode($days) ?>;
        const ma7 = <?= json_encode($ma7) ?>;
        const ma30 = <?= json_encode($ma30) ?>;
        const rsi = <?= json_encode($rsi) ?>;
        const macdLine = <?= json_encode($macdLine) ?>;
        const signalLine = <?= json_encode($signalLine) ?>;
        const histogram = <?= json_encode($histogram) ?>;
        const stochK = <?= json_encode($stochK) ?>;
        const stochD = <?= json_encode($stochD) ?>;

        // السعر + MA
        new Chart(document.getElementById('priceChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: days,
                datasets: [
                    { label:'السعر', data:prices, borderColor:'#03a9f4', fill:false },
                    { label:'MA7', data:ma7, borderColor:'orange', fill:false },
                    { label:'MA30', data:ma30, borderColor:'green', fill:false }
                ]
            },
            options: { responsive:true, plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#fff'}},y:{ticks:{color:'#fff'}}} }
        });

        // RSI
        new Chart(document.getElementById('rsiChart').getContext('2d'), {
            type: 'line',
            data: { labels: days, datasets: [{ label:'RSI', data:rsi, borderColor:'#ffeb3b', fill:false }] },
            options: { plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#fff'}},y:{ticks:{color:'#fff',beginAtZero:true,max:100}}} }
        });

        // MACD
        new Chart(document.getElementById('macdChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: days,
                datasets: [
                    { type:'line', label:'MACD', data:macdLine, borderColor:'#03a9f4', fill:false },
                    { type:'line', label:'Signal', data:signalLine, borderColor:'red', fill:false },
                    { label:'Histogram', data:histogram, backgroundColor:histogram.map(v => v>=0 ? 'green':'red') }
                ]
            },
            options: { plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#fff'}},y:{ticks:{color:'#fff'}}} }
        });

        // Stochastic Oscillator
        new Chart(document.getElementById('stochChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: days,
                datasets: [
                    { label:'%K', data:stochK, borderColor:'blue', fill:false },
                    { label:'%D', data:stochD, borderColor:'red', fill:false }
                ]
            },
            options: { plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#fff'}},y:{ticks:{color:'#fff',beginAtZero:true,max:100}}} }
        });

        // PDF
        function downloadPDF() {
            html2pdf().set({margin:10, filename:'bitcoin_stochastic.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}}).from(document.getElementById("report")).save();
        }

        // CSV
        function downloadCSV() {
            let csv = "اليوم,السعر,MA7,MA30,RSI,MACD,Signal,Hist,%K,%D\n";
            const rows = document.querySelectorAll("#priceTable tbody tr");
            rows.forEach(row => {
                const cols = row.querySelectorAll("td");
                const data = Array.from(cols).map(col => col.innerText);
                csv += data.join(",") + "\n";
            });
            const blob = new Blob([csv], { type:"text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "bitcoin_stochastic.csv";
            link.click();
        }
    </script>
</body>
</html>


