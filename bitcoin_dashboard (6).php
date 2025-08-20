<?php
// ============================================
//  Bitcoin Dashboard (All-in-One PHP file)
//  ⚠️ للتعليم فقط – ليست نصيحة استثمارية
// ============================================

// 1) جلب بيانات Bitcoin + كاش + تعامل غير قاتل مع فشل الجلب
$apiUrl    = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=90";
$cacheFile = DIR . "/btc_cache.json";
$fetch_error = false;
$used_cache  = false;

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
    $data = json_decode(file_get_contents($cacheFile), true);
    $used_cache = true;
} else {
    $response = @file_get_contents($apiUrl);
    if (!$response) {
        $fetch_error = true;
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $used_cache = true;
        } else {
            $data = ["prices" => []]; // Fallback فارغ
        }
    } else {
        $data = json_decode($response, true);
        if (!isset($data['prices'])) {
            $fetch_error = true;
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                $used_cache = true;
            } else {
                $data = ["prices" => []];
            }
        } else {
            file_put_contents($cacheFile, json_encode($data));
        }
    }
}

// 2) استخراج الأسعار
$prices = array_column($data['prices'], 1);
$days   = range(1, count($prices));

// 3) دوال المؤشرات
function movingAverage($data, $period) {
    $out = [];
    for ($i=0; $i<count($data); $i++) {
        if ($i+1 < $period) $out[] = null;
        else {
            $slice = array_slice($data, $i+1-$period, $period);
            $out[] = array_sum($slice)/$period;
        }
    }
    return $out;
}
function ema($data, $period) {
    if (empty($data)) return [];
    $k = 2/($period+1);
    $out = [];
    $out[0] = $data[0];
    for ($i=1; $i<count($data); $i++) {
        $out[$i] = $data[$i]*$k + $out[$i-1]*(1-$k);
    }
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
// 4) حساب المؤشرات
$ma7   = movingAverage($prices, 7);
$ma30  = movingAverage($prices, 30);
list($bb_mid,$bb_up,$bb_low) = bollinger($prices,20,2);
$rsiArr = rsi($prices,14);

$ema12 = ema($prices,12); $ema26 = ema($prices,26);
$macdLine=[]; for($i=0;$i<count($prices);$i++) $macdLine[$i] = ($ema12[$i] ?? null) - ($ema26[$i] ?? null);
$signalLine = ema($macdLine,9);
$hist=[]; for($i=0;$i<count($prices);$i++) $hist[$i] = ($macdLine[$i] ?? null) - ($signalLine[$i] ?? null);

list($stochK,$stochD) = stochastic($prices,14,3);
list($supLevelsAll,$resLevelsAll) = supportResistance($prices,30);

// أقرب 3 مستويات بالنسبة للسعر الحالي
$lastPrice = end($prices) ?: 0;
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
if ($lastRSI!==false && $lastRSI!==null) {
    if ($lastRSI>70) $analysis.="| RSI: 🚨 تشبع شراء. ";
    elseif ($lastRSI<30) $analysis.="| RSI: 🟢 تشبع بيع. ";
    else $analysis.="| RSI: ⚖️ طبيعي. ";
}
if (end($ma7)!==null && end($ma30)!==null) $analysis .= (end($ma7)>end($ma30)) ? "| MA: ✅ تقاطع صاعد. " : "| MA: ⚠️ تقاطع هابط. ";
if (!empty($macdLine) && !empty($signalLine)) $analysis .= (end($macdLine)>end($signalLine)) ? "| MACD: ✅ شراء. " : "| MACD: ⚠️ بيع. ";

// 5) AJAX endpoint (يرجع JSON عند ?ajax=1)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $last_update_ts = time();
    if (file_exists($cacheFile)) { $last_update_ts = filemtime($cacheFile); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "ok" => true,
        "last_update_ts" => $last_update_ts,
        "days"    => $days,
        "prices"  => $prices,
        "ma7"     => $ma7,
        "ma30"    => $ma30,
        "bb_up"   => $bb_up,
        "bb_mid"  => $bb_mid,
        "bb_low"  => $bb_low,
        "rsi"     => $rsiArr,
        "macd"    => $macdLine,
        "signal"  => $signalLine,
        "hist"    => $hist,
        "stochK"  => $stochK,
        "stochD"  => $stochD,
        "supports"=> $sups,
        "resistances" => $ress
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8" />
  <title>Bitcoin – Dashboard المؤشرات + دعم/مقاومة</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
  <style>
    body { background:#121212; color:#f1f1f1; font-family:Arial, sans-serif; padding:24px; }
    .wrap { max-width:1200px; margin:0 auto; }
    .card { background:#1e1e1e; border-radius:14px; padding:28px; margin-bottom:18px; box-shadow:0 10px 30px rgba(0,0,0,.35); }
    h2 { margin:0 0 12px; }
    .meta { margin: 8px 0 22px; opacity:.95; }
    canvas { background:#181818; border-radius:10px; padding:14px; min-height:160px; }
    .grid { display:grid; grid-template-columns:1fr; gap:18px; }
    @media (min-width: 900px) { .grid { grid-template-columns: 1fr 1fr; } }
    .toolbar { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
    .btn { background:#03a9f4; color:#fff; border:none; border-radius:8px; padding:10px 14px; cursor:pointer; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }th,td { border:1px solid #333; padding:8px 10px; }
    th { background:#2a2a2a; }
    .filters{background:#1b1b1b;border:1px solid #333;padding:12px;border-radius:10px;margin-top:10px}
    .filters .filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .filters label{opacity:.9}
    .filters input{background:#111;border:1px solid #333;border-radius:6px;color:#fff;padding:6px 8px;min-width:120px}
    .filters select{background:#111;border:1px solid #333;border-radius:6px;color:#fff;padding:6px 8px;min-width:120px}
    .pagination {margin-top:12px; display:flex; gap:6px; justify-content:center;}
    .pagination-btn{ background:#03a9f4; color:#fff; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; }
    .pagination-btn.active{ background:orange; }
    .alert{background:#402020;border:1px solid #a33;color:#ffd7d7;padding:12px;border-radius:8px;margin:10px 0;display:none}
    .alert .row{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
    .alert .msg{font-size:14px}
    .alert .btn{background:#ff5252}
  </style>
</head>
<body>
<div class="wrap" id="report">
  <div class="card">
    <h2>📊 الرسم الرئيسي: السعر + MA7/MA30 + Bollinger + دعم/مقاومة</h2>
    <div class="alert" id="fetchAlert">
      <div class="row">
        <span class="msg">⚠️ تعذر جلب البيانات من المصدر الآن. سيتم عرض آخر بيانات محفوظة إن وجدت.</span>
        <div><button class="btn" id="retryFetch">إعادة المحاولة</button></div>
      </div>
    </div>
    <div class="meta"><span id="lastUpdateLabel"></span> — <?= htmlspecialchars($analysis) ?></div>
    <canvas id="mainChart"></canvas>
    <div class="toolbar">
      <button class="btn" onclick="downloadPDF()">📄 تحميل كمستند PDF</button>
      <button class="btn" onclick="downloadCSV()">📑 تحميل CSV (الكل حسب الفلاتر)</button>
      <button class="btn" onclick="downloadCSVPage()">📑 تحميل CSV (الصفحة الحالية)</button>
      <button class="btn" onclick="refreshDataAjax()">🔄 تحديث البيانات</button>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>RSI (14)</h2>
      <canvas id="rsiChart"></canvas>
    </div>
    <div class="card">
      <h2>MACD</h2>
      <canvas id="macdChart"></canvas>
    </div>
    <div class="card">
      <h2>Stochastic %K / %D</h2>
      <canvas id="stochChart"></canvas>
    </div>
  </div>

  <div class="card">
    <h2>📜 جدول البيانات (آخر 90 يوم)</h2>

    <!-- فلاتر -->
    <div id="tableFilters" class="filters">
      <div class="filter-row">
        <label>عدد الصفوف لكل صفحة:</label>
        <select id="rowsPerPage">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>

        <label>الفترة:</label>
        <select id="periodSelect">
          <option value="7">7 أيام</option>
          <option value="30">30 يوم</option>
          <option value="90" selected>90 يوم</option>
        </select>

        <label>MACD من:</label><input type="number" step="0.01" id="minMACD" placeholder="min">
        <label>إلى:</label><input type="number" step="0.01" id="maxMACD" placeholder="max">
      </div>

      <div class="filter-row">
        <label>Histogram من:</label><input type="number" step="0.01" id="minHist" placeholder="min">
        <label>إلى:</label><input type="number" step="0.01" id="maxHist" placeholder="max">
        <label>%K من:</label><input type="number" step="0.1" id="minK" placeholder="0">
        <label>إلى:</label><input type="number" step="0.1" id="maxK" placeholder="100">
        <label>%D من:</label><input type="number" step="0.1" id="minD" placeholder="0">
        <label>إلى:</label><input type="number" step="0.1" id="maxD" placeholder="100">
      </div><div class="filter-row" style="margin-top:8px;">
        <label>السعر من:</label><input type="number" step="0.01" id="minPrice" placeholder="min">
        <label>إلى:</label><input type="number" step="0.01" id="maxPrice" placeholder="max">
        <label>RSI من:</label><input type="number" step="0.1" id="minRSI" placeholder="0">
        <label>إلى:</label><input type="number" step="0.1" id="maxRSI" placeholder="100">
        <label>بحث نصي:</label><input type="text" id="textSearch" placeholder="ابحث في الصفوف...">
        <button class="btn" id="resetFilters">إعادة التعيين</button>
      </div>
    </div>

    <!-- الجدول -->
    <table id="dataTable">
      <thead>
        <tr>
          <th>اليوم</th><th>السعر</th><th>MA7</th><th>MA30</th>
          <th>BB Up</th><th>BB Mid</th><th>BB Low</th>
          <th>RSI</th><th>MACD</th><th>Signal</th><th>Hist</th><th>%K</th><th>%D</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($i=0; $i<count($prices); $i++): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>$<?= number_format($prices[$i] ?? 0,2) ?></td>
            <td><?= $ma7[$i]!==null ? number_format($ma7[$i],2) : "-" ?></td>
            <td><?= $ma30[$i]!==null ? number_format($ma30[$i],2) : "-" ?></td>
            <td><?= $bb_up[$i]!==null ? number_format($bb_up[$i],2) : "-" ?></td>
            <td><?= $bb_mid[$i]!==null ? number_format($bb_mid[$i],2) : "-" ?></td>
            <td><?= $bb_low[$i]!==null ? number_format($bb_low[$i],2) : "-" ?></td>
            <td><?= $rsiArr[$i]!==null ? number_format($rsiArr[$i],2) : "-" ?></td>
            <td><?= number_format($macdLine[$i] ?? 0,2) ?></td>
            <td><?= number_format($signalLine[$i] ?? 0,2) ?></td>
            <td><?= number_format($hist[$i] ?? 0,2) ?></td>
            <td><?= $stochK[$i]!==null ? number_format($stochK[$i],2) : "-" ?></td>
            <td><?= $stochD[$i]!==null ? number_format($stochD[$i],2) : "-" ?></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="pagination" id="tablePagination"></div>
  </div>
</div>

<script>
// ===== متغيرات من PHP إلى JS =====
let days        = <?= json_encode($days) ?>;
let prices      = <?= json_encode($prices) ?>;
let ma7         = <?= json_encode($ma7) ?>;
let ma30        = <?= json_encode($ma30) ?>;
let bb_up       = <?= json_encode($bb_up) ?>;
let bb_mid      = <?= json_encode($bb_mid) ?>;
let bb_low      = <?= json_encode($bb_low) ?>;
let rsiArr      = <?= json_encode($rsiArr) ?>;
let macdLine    = <?= json_encode($macdLine) ?>;
let signalLine  = <?= json_encode($signalLine) ?>;
let hist        = <?= json_encode($hist) ?>;
let stochK      = <?= json_encode($stochK) ?>;
let stochD      = <?= json_encode($stochD) ?>;
const FETCH_ERROR = <?= json_encode($fetch_error) ?>;
const USED_CACHE  = <?= json_encode($used_cache) ?>;
const LAST_UPDATE_TS = <?= json_encode(file_exists($cacheFile) ? filemtime($cacheFile) : time()) ?>;

// ===== Debug & Safe Builders =====
console.log("DBG lengths =>",
  "prices:", prices?.length, 
  "ma7:", ma7?.length, 
  "ma30:", ma30?.length,
  "bb_up:", bb_up?.length,
  "rsi:", rsiArr?.length,
  "macd:", macdLine?.length,
  "signal:", signalLine?.length,
  "hist:", hist?.length,
  "stochK:", stochK?.length,
  "stochD:", stochD?.length
);

function showNoData(canvasId, reason){
  const cv = document.getElementById(canvasId);
  if(!cv) return;
  const box = cv.parentNode;
  if(!box) return;
  const msg = document.createElement('div');
  msg.style.color = '#bbb';
  msg.style.textAlign = 'center';
  msg.style.padding = '20px';
  msg.style.fontSize = '14px';
  msg.innerText = reason || 'لا توجد بيانات لعرضها';
  box.replaceChild(msg, cv);
}
function buildLineChartOrMessage(canvasId, labels, datasetDefs, guardArrays){
  const ok = (guardArrays||[]).every(arr => Array.isArray(arr) && arr.length > 0);if(!ok){ showNoData(canvasId, 'لا توجد بيانات كافية لهذا الرسم'); return null; }
  const ctx = document.getElementById(canvasId)?.getContext('2d');
  if(!ctx){ return null; }
  return new Chart(ctx, {
    type:'line',
    data:{ labels, datasets: datasetDefs },
    options:{ responsive:true, plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ ticks:{ color:'#fff' } } } }
  });
}
function buildMacdChartOrMessage(canvasId, labels, macd, signal, histo){
  const ok = [macd, signal, histo].every(arr => Array.isArray(arr) && arr.length > 0);
  if(!ok){ showNoData(canvasId, 'لا توجد بيانات MACD لعرضها'); return null; }
  const ctx = document.getElementById(canvasId)?.getContext('2d');
  if(!ctx){ return null; }
  return new Chart(ctx, {
    type:'bar',
    data:{ labels, datasets:[
      { type:'line', label:'MACD', data:macd, borderColor:'#03a9f4', fill:false },
      { type:'line', label:'Signal', data:signal, borderColor:'red', fill:false },
      { label:'Histogram', data:histo, backgroundColor: histo.map(v=> v>=0 ? 'green' : 'red') }
    ]},
    options:{ plugins:{ legend:{ labels:{ color:'#fff' } } }, scales:{ x:{ ticks:{ color:'#fff' } }, y:{ ticks:{ color:'#fff' } } } }
  });
}

// ====== بناء الرسوم الأول ======
function makeHorizontalDataset(level, label, color, dash=[6,6]) {
  return { label, data: Array(prices.length).fill(level), borderColor: color, borderDash: dash, pointRadius: 0, fill: false, tension: 0 };
}
const supLevels = <?= json_encode(array_values($sups)) ?>;
const resLevels = <?= json_encode(array_values($ress)) ?>;

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

buildLineChartOrMessage('mainChart', days, mainSets, [prices]);
buildLineChartOrMessage('rsiChart', days, [{ label:'RSI', data:rsiArr, borderColor:'#ffeb3b', fill:false }], [rsiArr]);
buildMacdChartOrMessage('macdChart', days, macdLine, signalLine, hist);
buildLineChartOrMessage('stochChart', days, [
  { label:'%K', data:stochK, borderColor:'blue', fill:false },
  { label:'%D', data:stochD, borderColor:'red', fill:false }
], [stochK, stochD]);

// ===== PDF
function downloadPDF(){
  html2pdf().set({ margin:10, filename:'bitcoin_dashboard.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'} }).from(document.getElementById('report')).save();
}

// ===== CSV (الكل حسب الفلاتر) + (الصفحة الحالية)
function downloadCSVPage(){
  const table = document.getElementById('dataTable');
  if(!table) return;
  const rows = Array.from(table.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');
  let csv = "day,price,MA7,MA30,BB_Up,BB_Mid,BB_Low,RSI,MACD,Signal,Hist,K,D\n";
  rows.forEach(tr => {
    const cols = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.replace(/\$/g,''));
    csv += cols.join(",") + "\n";
  });
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'bitcoin_dashboard_page.csv';
  a.click();
}// ===== فلاتر + Pagination + مدة العرض =====
(function(){
  const table = document.getElementById('dataTable');
  if(!table) return;
  const tbody = table.querySelector('tbody');
  const pag = document.getElementById('tablePagination');
  let perPage = parseInt(document.getElementById('rowsPerPage')?.value || '10');

  // Snapshot أصلي يبنى من DOM
  let original = Array.from(tbody.querySelectorAll('tr')).map(tr => {
    const tds = tr.querySelectorAll('td');
    function num(cell){ return parseFloat(String(cell?.innerText||'').replace(/[^0-9.\-]/g,'')) }
    return {
      row: tr.cloneNode(true),
      day: parseInt(tds[0]?.innerText || '0'),
      price: num(tds[1]),
      rsi: num(tds[7]),
      macd: num(tds[8]),
      hist: num(tds[10]),
      k: num(tds[11]),
      d: num(tds[12]),
      text: tr.innerText.toLowerCase(),
    };
  });

  function getPeriod(){
    const sel = document.getElementById('periodSelect');
    const val = sel ? parseInt(sel.value) : 90;
    return [isNaN(val)?90:val, days.length];
  }

  function valFloat(id){
    const el = document.getElementById(id);
    const v = el ? parseFloat(el.value) : NaN;
    return isNaN(v) ? null : v;
  }

  function applyFilters(){
    const [period, total] = getPeriod();
    const minDay = total - period + 1;

    const minP = valFloat('minPrice'), maxP = valFloat('maxPrice');
    const minR = valFloat('minRSI'),   maxR = valFloat('maxRSI');
    const minM = valFloat('minMACD'),  maxM = valFloat('maxMACD');
    const minH = valFloat('minHist'),  maxH = valFloat('maxHist');
    const minK = valFloat('minK'),     maxK = valFloat('maxK');
    const minD = valFloat('minD'),     maxD = valFloat('maxD');
    const txt  = (document.getElementById('textSearch')?.value || '').toLowerCase();

    return original.filter(o => {
      if(!(o.day >= minDay)) return false;
      if(minP!==null && !(o.price >= minP)) return false;
      if(maxP!==null && !(o.price <= maxP)) return false;
      if(minR!==null && !(o.rsi   >= minR)) return false;
      if(maxR!==null && !(o.rsi   <= maxR)) return false;
      if(minM!==null && !(o.macd  >= minM)) return false;
      if(maxM!==null && !(o.macd  <= maxM)) return false;
      if(minH!==null && !(o.hist  >= minH)) return false;
      if(maxH!==null && !(o.hist  <= maxH)) return false;
      if(minK!==null && !(o.k     >= minK)) return false;
      if(maxK!==null && !(o.k     <= maxK)) return false;
      if(minD!==null && !(o.d     >= minD)) return false;
      if(maxD!==null && !(o.d     <= maxD)) return false;
      if(txt && !o.text.includes(txt)) return false;
      return true;
    }).sort((a,b)=> a.day - b.day);
  }

  let currentPage = 1;
  function render(){
    perPage = parseInt(document.getElementById('rowsPerPage')?.value || '10');
    const filtered = applyFilters();
    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    if(currentPage > totalPages) currentPage = 1;

    tbody.innerHTML = '';
    const start = (currentPage-1)*perPage;
    filtered.slice(start, start+perPage).forEach(o=> tbody.appendChild(o.row.cloneNode(true)));

    pag.innerHTML = '';
    for(let i=1;i<=totalPages;i++){
      const btn = document.createElement('button');
      btn.className = 'pagination-btn' + (i===currentPage?' active':'');
      btn.textContent = i;
      btn.onclick = ()=>{ currentPage=i; render(); };
      pag.appendChild(btn);
    }

    // تحديث الرسوم حسب "الفترة" فقط (الفلاتر النصية لا تغيّر الرسوم)
    applyPeriodToCharts();
  }

  function sliceLastN(arr, n){ return Array.isArray(arr) ? arr.slice(arr.length - n) : arr; }
  function chartOf(id){ return (window.Chart && Chart.getChart) ? Chart.getChart(document.getElementById(id)) : null; }

  window.applyPeriodToCharts = function(){
    const sel = document.getElementById('periodSelect');
    const period = sel ? parseInt(sel.value) : 90;
    const pr = isNaN(period) ? 90 : period;
    const sDays = sliceLastN(days, pr);const mc = chartOf('mainChart');
    if(mc){
      mc.data.labels = sDays;
      const mainData = [prices,ma7,ma30,bb_up,bb_mid,bb_low];
      mc.data.datasets.forEach((ds,idx)=>{
        if(idx < mainData.length){
          ds.data = sliceLastN(mainData[idx], pr);
        } else if (ds.label && (ds.label.startsWith('Support') || ds.label.startsWith('Resistance'))) {
          const level = parseFloat(ds.data[0]);
          ds.data = Array(pr).fill(level);
        }
      });
      mc.update();
    }
    const rc = chartOf('rsiChart');
    if(rc){ rc.data.labels = sDays; rc.data.datasets[0].data = sliceLastN(rsiArr, pr); rc.update(); }
    const mac = chartOf('macdChart');
    if(mac){
      mac.data.labels = sDays;
      mac.data.datasets[0].data = sliceLastN(macdLine, pr);
      mac.data.datasets[1].data = sliceLastN(signalLine, pr);
      mac.data.datasets[2].data = sliceLastN(hist, pr);
      mac.update();
    }
    const sc = chartOf('stochChart');
    if(sc){
      sc.data.labels = sDays;
      sc.data.datasets[0].data = sliceLastN(stochK, pr);
      sc.data.datasets[1].data = sliceLastN(stochD, pr);
      sc.update();
    }
  }

  function recomputeOriginal(){
    return Array.from(tbody.querySelectorAll('tr')).map(tr => {
      const tds = tr.querySelectorAll('td');
      function num(cell){ return parseFloat(String(cell?.innerText||'').replace(/[^0-9.\-]/g,'')) }
      return {
        row: tr.cloneNode(true),
        day: parseInt(tds[0]?.innerText || '0'),
        price: num(tds[1]),
        rsi: num(tds[7]),
        macd: num(tds[8]),
        hist: num(tds[10]),
        k: num(tds[11]),
        d: num(tds[12]),
        text: tr.innerText.toLowerCase(),
      };
    });
  }

  window.addEventListener('tableDataRebuilt', ()=>{ original = recomputeOriginal(); currentPage=1; render(); });

  // زر إعادة التعيين
  const reset = document.getElementById('resetFilters');
  if(reset){
    reset.addEventListener('click', ()=>{
      ['minPrice','maxPrice','minRSI','maxRSI','textSearch','minMACD','maxMACD','minHist','maxHist','minK','maxK','minD','maxD'].forEach(id=>{
        const el = document.getElementById(id); if(el) el.value='';
      });
      const sel = document.getElementById('periodSelect'); if(sel) sel.value='90';
      const rpp = document.getElementById('rowsPerPage'); if(rpp) rpp.value='10';
      currentPage=1; render();
    });
  }

  // مستمعون لكل الفلاتر
  ['minPrice','maxPrice','minRSI','maxRSI','textSearch','minMACD','maxMACD','periodSelect','minHist','maxHist','minK','maxK','minD','maxD','rowsPerPage'].forEach(id=>{
    const el = document.getElementById(id); if(!el) return;
    el.addEventListener('input', ()=>{ currentPage=1; render(); });
    el.addEventListener('change', ()=>{ currentPage=1; render(); });
  });

  // تعريف CSV (الكل حسب الفلاتر)
  window.downloadCSV = function(){
    const filtered = applyFilters();
    let csv = "day,price,MA7,MA30,BB_Up,BB_Mid,BB_Low,RSI,MACD,Signal,Hist,K,D\n";
    filtered.forEach(o => {
      const tds = o.row.querySelectorAll('td');
      const row = Array.from(tds).map(td => td.innerText.replace(/\$/g,''));
      csv += row.join(",") + "\n";
    });
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'bitcoin_dashboard_filtered.csv';
    a.click();
  };

  render();
})();

// ===== عرض آخر تحديث + بانر الفشل
function formatTs(ts){
  try{
    const d = new Date(ts*1000);
    return d.toLocaleString('ar-EG');
  }catch(e){ return String(ts); }
}
(function(){
  const lbl = document.getElementById('lastUpdateLabel');
  if(lbl && typeof LAST_UPDATE_TS!=='undefined'){ lbl.textContent = 'آخر تحديث: ' + formatTs(LAST_UPDATE_TS); }

  const alertBox = document.getElementById('fetchAlert');
  const retryBtn = document.getElementById('retryFetch');
  if (FETCH_ERROR) { if (alertBox) alertBox.style.display = 'block'; }
  if (retryBtn) retryBtn.addEventListener('click', ()=> { location.reload(); });
})();// ===== إعادة بناء الجدول من المصفوفات (يستدعى بعد AJAX)
function rebuildTableFromArrays(){
  const tbody = document.querySelector('#dataTable tbody');
  if(!tbody) return;
  const n = prices.length;
  let htmlRows = '';
  const c = (v)=> v===null || typeof v==='undefined' ? '-' : (typeof v==='number' ? Number(v).toFixed(2) : v);
  for(let i=0;i<n;i++){
    htmlRows += <tr>
      <td>${i+1}</td>
      <td>$${c(prices[i])}</td>
      <td>${c(ma7[i])}</td>
      <td>${c(ma30[i])}</td>
      <td>${c(bb_up[i])}</td>
      <td>${c(bb_mid[i])}</td>
      <td>${c(bb_low[i])}</td>
      <td>${c(rsiArr[i])}</td>
      <td>${c(macdLine[i])}</td>
      <td>${c(signalLine[i])}</td>
      <td>${c(hist[i])}</td>
      <td>${c(stochK[i])}</td>
      <td>${c(stochD[i])}</td>
    </tr>;
  }
  tbody.innerHTML = htmlRows;
  window.dispatchEvent(new Event('tableDataRebuilt'));
}

// ===== تحديث البيانات عبر AJAX (زر 🔄)
async function refreshDataAjax(){
  try{
    const url = (location.pathname.indexOf('.php')>-1 ? location.pathname : location.href) + (location.search ? '&' : '?') + 'ajax=1';
    const res = await fetch(url, { cache: 'no-store' });
    const data = await res.json();
    if(!data || !data.ok){ alert('تعذر تحديث البيانات الآن'); return; }

    // Update globals
    days = data.days; prices = data.prices; ma7 = data.ma7; ma30 = data.ma30;
    bb_up = data.bb_up; bb_mid = data.bb_mid; bb_low = data.bb_low;
    rsiArr = data.rsi; macdLine = data.macd; signalLine = data.signal; hist = data.hist;
    stochK = data.stochK; stochD = data.stochD;

    // Update charts
    if (typeof applyPeriodToCharts === 'function') { applyPeriodToCharts(); }

    // Update table
    rebuildTableFromArrays();

    // Update last update label
    const lbl = document.getElementById('lastUpdateLabel');
    if(lbl && data.last_update_ts){ lbl.textContent = 'آخر تحديث: ' + formatTs(data.last_update_ts); }

    // Hide alert if visible
    const alertBox = document.getElementById('fetchAlert');
    if (alertBox) alertBox.style.display = 'none';
  }catch(e){
    console.error(e);
    alert('حدث خطأ أثناء تحديث البيانات.');
  }
}
</script>
</body>
</html>