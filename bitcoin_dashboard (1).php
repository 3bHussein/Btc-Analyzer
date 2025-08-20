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
// 2) أدوات حساب المؤشرات
// ----------------------
function movingAverage($data, $period) {
    $out = [];
    for ($i=0; $i<count($data); $i++) {
        if ($i+1 < $period) $out[] = null;
        else { $slice = array_slice($data, $i+1-$period, $period); $out[] = array_sum($slice)/$period; }
    }
    return $out;
}
function ema($data, $period) {
    $k = 2/($period+1);
    $out = [];
    $out[0] = $data[0];
    for ($i=1; $i<count($data); $i++) $out[$i] = $data[$i]*$k + $out[$i-1]*(1-$k);
    return $out;
}
function bollinger($data, $period=20, $mult=2) {
    $mid=$up=$low=[];
    for ($i=0; $i<count($data); $i++) {
        if ($i+1 < $period) { $mid[]=$up[]=$low[]=null; }
        else {
            $slice = array_slice($data, $i+1-$period, $period);
            $avg = array_sum($slice)/$period;
            $var=0; foreach ($slice as $v) $var += pow($v-$avg,2);
            $std = sqrt($var/$period);
            $mid[] = $avg; $up[] = $avg+$mult*$std; $low[] = $avg-$mult*$std;
        }
    }
    return [$mid,$up,$low];
}
function rsi($data, $period=14) {
    $r=[];
    for ($i=0; $i<count($data); $i++) {
        if ($i < $period) { $r[] = null; continue; }
        $g=$l=0;
        for ($j=$i-$period+1; $j<=$i; $j++) {
            $d = $data[$j]-$data[$j-1];
            if ($d>0) $g+=$d; else $l-= $d;
        }
        if ($l==0) $r[] = 100; else { $rs=$g/$l; $r[] = 100 - (100/(1+$rs)); }
    }
    return $r;
}
function stochastic($data, $kPeriod=14, $dPeriod=3) {
    $K=$D=[];
    for ($i=0; $i<count($data); $i++) {
        if ($i+1 < $kPeriod) { $K[]=$D[]=null; }
        else {
            $slice = array_slice($data, $i+1-$kPeriod, $kPeriod);
            $low=min($slice); $high=max($slice);
            $Kval = ($high==$low) ? 50 : (($data[$i]-$low)/($high-$low))*100;
            $K[]=$Kval;
            if ($i+1 < $kPeriod+$dPeriod-1) $D[]=null;
            else { $sliceK = array_slice($K, $i-$dPeriod+1, $dPeriod); $D[]= array_sum($sliceK)/count($sliceK); }
        }
    }
    return [$K,$D];
}
function supportResistance($data, $lookback=30) {
    $n=count($data); $start=max(0,$n-$lookback); $slice=array_slice($data,$start);
    $sup=[]; $res=[];
    for ($i=1; $i<count($slice)-1; $i++) {
        if ($slice[$i]<$slice[$i-1] && $slice[$i]<$slice[$i+1]) $sup[]=round($slice[$i],2);
        if ($slice[$i]>$slice[$i-1] && $slice[$i]>$slice[$i+1]) $res[]=round($slice[$i],2);
    }
    $sup=array_values(array_unique($sup)); sort($sup);
    $res=array_values(array_unique($res)); sort($res);
    return [$sup,$res];
}

// ----------------------
// 3) حساب المؤشرات
// ----------------------
$ma7  = movingAverage($prices, 7);
$ma30 = movingAverage($prices, 30);
list($bb_mid,$bb_up,$bb_low) = bollinger($prices,20,2);
$rsiArr = rsi($prices,14);
$ema12 = ema($prices,12); $ema26 = ema($prices,26);
$macdLine=[]; for($i=0;$i<count($prices);$i++) $macdLine[$i]=$ema12[$i]-$ema26[$i];
$signalLine = ema($macdLine,9);
$hist=[]; for($i=0;$i<count($prices);$i++) $hist[$i]=$macdLine[$i]-$signalLine[$i];
list($stochK,$stochD) = stochastic($prices,14,3);
list($supLevelsAll,$resLevelsAll) = supportResistance($prices,30);

// أقرب 3 مستويات بالنسبة للسعر الحالي
$lastPrice = end($prices);
$sups=[]; $ress=[];
foreach ($supLevelsAll as $s) if ($s < $lastPrice) $sups[]=$s;
foreach ($resLevelsAll as $r) if ($r > $lastPrice) $ress[]=$r;
usort($sups, fn($a,$b)=>abs($lastPrice-$a)<=>abs($lastPrice-$b));
usort($ress, fn($a,$b)=>abs($lastPrice-$a)<=>abs($lastPrice-$b));
$sups = array_slice($sups,0,3);
$ress = array_slice($ress,0,3);

// تحليل موجز
$analysis = "السعر الحالي: $".number_format($lastPrice,2).". ";
if (count($sups)) $analysis .= "🟢 أقرب دعم: $".number_format($sups[0],2).". ";
if (count($ress)) $analysis .= "🔴 أقرب مقاومة: $".number_format($ress[0],2).". ";
$lastRSI = end($rsiArr);
if ($lastRSI!==null) {
    if ($lastRSI>70) $analysis.="| RSI: 🚨 تشبع شراء. ";
    elseif ($lastRSI<30) $analysis.="| RSI: 🟢 تشبع بيع. ";
    else $analysis.="| RSI: ⚖️ طبيعي. ";
}
if (end($ma7)!==null && end($ma30)!==null) $analysis .= (end($ma7)>end($ma30)) ? "| MA: ✅ تقاطع صاعد. " : "| MA: ⚠️ تقاطع هابط. ";
$analysis .= (end($macdLine)>end($signalLine)) ? "| MACD: ✅ شراء. " : "| MACD: ⚠️ بيع. ";

?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8" />
  <title>Bitcoin – Dashboard المؤشرات + دعم/مقاومة</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
  <style>
    body { background:#121212; color:#f1f1f1; font-family:Arial, sans-serif; padding:24px; }
    .wrap { max-width:1200px; margin:0 auto; }
    .card { background:#1e1e1e; border-radius:14px; padding:28px; margin-bottom:18px; box-shadow:0 10px 30px rgba(0,0,0,.35); }
    h2 { margin:0 0 12px; }
    .meta { margin: 8px 0 22px; opacity:.95; }
    canvas { background:#181818; border-radius:10px; padding:14px; }
    .grid { display:grid; grid-template-columns:1fr; gap:18px; }
    @media (min-width: 900px) { .grid { grid-template-columns: 1fr 1fr; } }
    .toolbar { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
    .btn { background:#03a9f4; color:#fff; border:none; border-radius:8px; padding:10px 14px; cursor:pointer; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    th,td { border:1px solid #333; padding:8px 10px; }
    th { background:#2a2a2a; }
  </style>
</head>
<body>
<div class="wrap" id="report">
  <div class="card">
    <h2>📊 الرسم الرئيسي: السعر + MA7/MA30 + Bollinger + دعم/مقاومة</h2>
    <div class="meta"><?= $analysis ?></div>
    <canvas id="mainChart" height="120"></canvas>
    <div class="toolbar">
      <button class="btn" onclick="downloadPDF()">📄 تحميل كمستند PDF</button>
      <button class="btn" onclick="downloadCSV()">📑 تحميل CSV</button>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>RSI (14)</h2>
      <canvas id="rsiChart" height="80"></canvas>
    </div>
    <div class="card">
      <h2>MACD</h2>
      <canvas id="macdChart" height="80"></canvas>
    </div>
    <div class="card">
      <h2>Stochastic %K / %D</h2>
      <canvas id="stochChart" height="80"></canvas>
    </div>
  </div>

  <div class="card">
    <h2>📜 جدول البيانات (آخر 90 يوم)</h2>
    <table id="dataTable">
      <thead>
        <tr>
          <th>اليوم</th><th>السعر</th><th>MA7</th><th>MA30</th><th>BB Up</th><th>BB Mid</th><th>BB Low</th>
          <th>RSI</th><th>MACD</th><th>Signal</th><th>Hist</th><th>%K</th><th>%D</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($i=0; $i<count($prices); $i++): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>$<?= number_format($prices[$i],2) ?></td>
            <td><?= $ma7[$i]!==null ? number_format($ma7[$i],2) : "-" ?></td>
            <td><?= $ma30[$i]!==null ? number_format($ma30[$i],2) : "-" ?></td>
            <td><?= $bb_up[$i]!==null ? number_format($bb_up[$i],2) : "-" ?></td>
            <td><?= $bb_mid[$i]!==null ? number_format($bb_mid[$i],2) : "-" ?></td>
            <td><?= $bb_low[$i]!==null ? number_format($bb_low[$i],2) : "-" ?></td>
            <td><?= $rsiArr[$i]!==null ? number_format($rsiArr[$i],2) : "-" ?></td>
            <td><?= number_format($macdLine[$i],2) ?></td>
            <td><?= number_format($signalLine[$i],2) ?></td>
            <td><?= number_format($hist[$i],2) ?></td>
            <td><?= $stochK[$i]!==null ? number_format($stochK[$i],2) : "-" ?></td>
            <td><?= $stochD[$i]!==null ? number_format($stochD[$i],2) : "-" ?></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const prices = <?= json_encode($prices) ?>;
const days   = <?= json_encode($days) ?>;
const ma7 = <?= json_encode($ma7) ?>;
const ma30= <?= json_encode($ma30) ?>;
const bb_up = <?= json_encode($bb_up) ?>;
const bb_mid= <?= json_encode($bb_mid) ?>;
const bb_low= <?= json_encode($bb_low) ?>;
const rsiArr= <?= json_encode($rsiArr) ?>;
const macdLine = <?= json_encode($macdLine) ?>;
const signalLine = <?= json_encode($signalLine) ?>;
const hist = <?= json_encode($hist) ?>;
const stochK = <?= json_encode($stochK) ?>;
const stochD = <?= json_encode($stochD) ?>;
const supLevels = <?= json_encode(array_values($sups)) ?>;
const resLevels = <?= json_encode(array_values($ress)) ?>;

function makeHorizontalDataset(level, label, color, dash=[6,6]) {
  return { label, data: Array(prices.length).fill(level), borderColor: color, borderDash: dash, pointRadius: 0, fill: false, tension: 0 };
}

// الرسم الرئيسي
const mainSets = [
  { label:'السعر', data:prices, borderColor:'#03a9f4', fill:false, pointRadius:0, tension:.15 },
  { label:'MA7', data:ma7, borderColor:'orange', fill:false, pointRadius:0 },
  { label:'MA30', data:ma30, borderColor:'green', fill:false, pointRadius:0 },
  { label:'BB Upper', data:bb_up, borderColor:'rgba(0,162,255,0.7)', borderDash:[5,5], fill:false },
  { label:'BB Middle', data:bb_mid, borderColor:'gray', borderDash:[5,5], fill:false },
  { label:'BB Lower', data:bb_low, borderColor:'rgba(0,162,255,0.7)', borderDash:[5,5], fill:false },
];
supLevels.forEach((lvl,i)=> mainSets.push(makeHorizontalDataset(lvl, 'Support #'+(i+1), 'rgba(0,200,83,.95)')));
resLevels.forEach((lvl,i)=> mainSets.push(makeHorizontalDataset(lvl, 'Resistance #'+(i+1), 'rgba(244,67,54,.95)')));

new Chart(document.getElementById('mainChart').getContext('2d'), {
  type:'line',
  data:{ labels:days, datasets: mainSets },
  options:{ responsive:true, plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ ticks:{ color:'#fff' } } } }
});

// RSI
new Chart(document.getElementById('rsiChart').getContext('2d'), {
  type:'line',
  data:{ labels:days, datasets:[{ label:'RSI', data:rsiArr, borderColor:'#ffeb3b', fill:false }] },
  options:{ plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ min:0, max:100, ticks:{ color:'#fff' } } } }
});

// MACD
new Chart(document.getElementById('macdChart').getContext('2d'), {
  type:'bar',
  data:{ labels:days, datasets:[
    { type:'line', label:'MACD', data:macdLine, borderColor:'#03a9f4', fill:false },
    { type:'line', label:'Signal', data:signalLine, borderColor:'red', fill:false },
    { label:'Histogram', data:hist, backgroundColor: hist.map(v=> v>=0 ? 'green' : 'red') }
  ]},
  options:{ plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ ticks:{ color:'#fff' } } } }
});

// Stochastic
new Chart(document.getElementById('stochChart').getContext('2d'), {
  type:'line',
  data:{ labels:days, datasets:[
    { label:'%K', data:stochK, borderColor:'blue', fill:false },
    { label:'%D', data:stochD, borderColor:'red', fill:false }
  ]},
  options:{ plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ min:0, max:100, ticks:{ color:'#fff' } } } }
});

// PDF
function downloadPDF(){
  html2pdf().set({ margin:10, filename:'bitcoin_dashboard.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'} }).from(document.getElementById('report')).save();
}

// CSV
function downloadCSV(){
  const rows = document.querySelectorAll('#dataTable tbody tr');
  let csv = "day,price,MA7,MA30,BB_Up,BB_Mid,BB_Low,RSI,MACD,Signal,Hist,K,D\n";
  rows.forEach(r=>{
    const cols = Array.from(r.querySelectorAll('td')).map(td=>td.innerText.replace(/\$/g,''));
    csv += cols.join(",") + "\n";
  });
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'bitcoin_dashboard.csv';
  a.click();
}
</script>
</body>
</html>