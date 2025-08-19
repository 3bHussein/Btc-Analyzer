<?php
// ⚠️ الكود تعليمي فقط – ليس نصيحة استثمارية
// ----------------------------------------------------
// إعدادات Telegram
// ----------------------------------------------------
$TELEGRAM_BOT_TOKEN     = "8211975510:AAGGwLx_gXvVKHIknKQ8luZD9TRbkIVVNDU";
$TELEGRAM_CHAT_ID       = "";           // مثال: "123456789" (اختياري)
$TELEGRAM_CHAT_USERNAME = "hussein3bz"; // Username بدون @

// ====================================================
// أدوات تيليجرام
// ====================================================
function sendTelegram($token, $chatId, $text) {
    if (!$token || !$chatId || !$text) return false;
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chatId}&text=" . urlencode($text);
    $ok = @file_get_contents($url);
    if ($ok !== false) return true;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res !== false;
    }
    return false;
}
function resolveUsernameToId($token, $username) {
    if (!$token || !$username) return "";
    $u = ltrim($username, "@");
    $url = "https://api.telegram.org/bot{$token}/getChat?chat_id=@{$u}";
    $json = @file_get_contents($url);
    if (!$json) return "";
    $resp = json_decode($json, true);
    if (isset($resp["ok"]) && $resp["ok"] === true && isset($resp["result"]["id"])) {
        return strval($resp["result"]["id"]);
    }
    return "";
}

// ====================================================
// 1) قراءة تفضيلات التنبيهات (RSI / أهداف السعر)
//    - نحفظها في الجلسة حتى تبقى بعد تحديث الصفحة
// ====================================================
session_start();
if (!isset($_SESSION['alerts_enabled_rsi']))  $_SESSION['alerts_enabled_rsi']  = true;
if (!isset($_SESSION['alerts_enabled_price']))$_SESSION['alerts_enabled_price']= true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $_SESSION['alerts_enabled_rsi']   = isset($_POST['enable_rsi']);
    $_SESSION['alerts_enabled_price'] = isset($_POST['enable_price']);
}

// ====================================================
// 2) جلب بيانات Bitcoin (آخر 30 يوم)
// ====================================================
$apiUrl = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30";
$data = json_decode(@file_get_contents($apiUrl), true);
if (!$data || !isset($data['prices'])) { die("تعذر جلب بيانات السعر الآن."); }

$prices = array_column($data['prices'], 1);
$days   = range(1, count($prices));
$lastPrice = end($prices);

// ====================================================
// 3) الدوال الفنية
// ====================================================
function SMA($data, $period = 7) {
    $sma = [];
    for ($i=0; $i<count($data); $i++) {
        if ($i + 1 < $period) $sma[] = null;
        else $sma[] = array_sum(array_slice($data, $i + 1 - $period, $period)) / $period;
    }
    return $sma;
}
function EMA($data, $period = 7) {
    $ema = [];
    $k = 2 / ($period + 1);
    $ema[0] = $data[0];
    for ($i=1; $i<count($data); $i++) $ema[$i] = $data[$i]*$k + $ema[$i-1]*(1-$k);
    return $ema;
}
function RSI($data, $period = 14) {
    $rsi = [];
    for ($i=0; $i<count($data); $i++) {
        if ($i < $period) $rsi[] = null;
        else {
            $gains = 0; $losses = 0;
            for ($j=$i-$period+1; $j<=$i; $j++) {
                $ch = $data[$j] - $data[$j-1];
                if ($ch > 0) $gains += $ch; else $losses -= $ch;
            }
            $avgGain = $gains/$period; $avgLoss = $losses/$period;
            $rsi[] = ($avgLoss == 0) ? 100 : 100 - (100 / (1 + ($avgGain/$avgLoss)));
        }
    }
    return $rsi;
}
function MACD($data, $short=12, $long=26, $signal=9) {
    $emaShort = EMA($data, $short);
    $emaLong  = EMA($data, $long);
    $macd = []; for ($i=0;$i<count($data);$i++) $macd[$i] = $emaShort[$i]-$emaLong[$i];
    $signalLine = EMA($macd, $signal);
    $hist=[]; for ($i=0;$i<count($data);$i++) $hist[$i] = $macd[$i]-$signalLine[$i];
    return [$macd,$signalLine,$hist];
}
function BollingerBands($data, $period=20, $mult=2) {
    $sma = SMA($data, $period);
    $upper=[]; $lower=[];
    for ($i=0;$i<count($data);$i++) {
        if ($i + 1 < $period) { $upper[] = null; $lower[] = null; }
        else {
            $slice = array_slice($data, $i + 1 - $period, $period);
            $mean = $sma[$i]; $var=0;
            foreach ($slice as $v) $var += pow($v-$mean,2);
            $stdDev = sqrt($var/$period);
            $upper[] = $mean + 2*$stdDev; $lower[] = $mean - 2*$stdDev;
        }
    }
    return [$upper,$lower,$sma];
}
function linearRegression($x,$y){
    $n=count($x); $x_sum=array_sum($x); $y_sum=array_sum($y);
    $xx_sum=0; $xy_sum=0; for($i=0;$i<$n;$i++){ $xx_sum+=$x[$i]*$x[$i]; $xy_sum+=$x[$i]*$y[$i]; }
    $slope = ($n*$xy_sum - $x_sum*$y_sum)/($n*$xx_sum - $x_sum*$x_sum);
    $intercept = ($y_sum - $slope*$x_sum)/$n;
    return [$slope,$intercept];
}

// ====================================================
// 4) الحسابات
// ====================================================
$sma = SMA($prices, 7);
$ema = EMA($prices, 7);
$rsi = RSI($prices, 14);
list($macd,$signalLine,$histogram) = MACD($prices);
list($bbUpper,$bbLower,$bbMid) = BollingerBands($prices, 20);

list($slope,$intercept) = linearRegression($days,$prices);
$predictions = []; for($i=1;$i<=5;$i++){ $predictions[] = $slope*(count($prices)+$i) + $intercept; }

// ====================================================
// 5) نماذج الضبط: أهداف السعر + تمكين/تعطيل التنبيهات
// ====================================================
$defaultBuy  = round($lastPrice*0.97,2);
$defaultSell = round($lastPrice*1.03,2);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_targets'])) {
    $buyTarget  = isset($_POST['buy_target'])  ? floatval($_POST['buy_target'])  : $defaultBuy;
    $sellTarget = isset($_POST['sell_target']) ? floatval($_POST['sell_target']) : $defaultSell;
    $_SESSION['buy_target']  = $buyTarget;
    $_SESSION['sell_target'] = $sellTarget;
}
$buyTarget  = $_SESSION['buy_target']  ?? $defaultBuy;
$sellTarget = $_SESSION['sell_target'] ?? $defaultSell;

// ====================================================
// 6) حساب التنبيهات وفق التفضيلات
// ====================================================
$rsiAlert=""; $priceAlert="";
$nonNullRsi = array_values(array_filter($rsi, fn($v)=>$v!==null));
$lastRsi = end($nonNullRsi);

if ($_SESSION['alerts_enabled_rsi'] && $lastRsi !== false) {
    if ($lastRsi > 70) $rsiAlert  = "🔴 RSI مرتفع (".round($lastRsi,2)."): تشبع شرائي → احتمال هبوط.";
    elseif ($lastRsi < 30) $rsiAlert = "🟢 RSI منخفض (".round($lastRsi,2)."): تشبع بيعي → احتمال صعود.";
}
if ($_SESSION['alerts_enabled_price']) {
    if ($lastPrice <= $buyTarget)  $priceAlert = "🟢 السعر الحالي (".number_format($lastPrice,2)."$) ≤ هدف الشراء (".number_format($buyTarget,2)."$) ⇒ فرصة شراء.";
    elseif ($lastPrice >= $sellTarget) $priceAlert = "🔴 السعر الحالي (".number_format($lastPrice,2)."$) ≥ هدف البيع (".number_format($sellTarget,2)."$) ⇒ فرصة بيع.";
}

// ====================================================
// 7) إرسال تيليجرام لو فيه تنبيه + مُمكّن
// ====================================================
$telegramChat = $TELEGRAM_CHAT_ID;
if (!$telegramChat && $TELEGRAM_CHAT_USERNAME) {
    $resolved = resolveUsernameToId($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_USERNAME);
    if ($resolved) $telegramChat = $resolved;
}
$finalAlertText = "";
if ($rsiAlert)   $finalAlertText .= $rsiAlert . "\n";
if ($priceAlert) $finalAlertText .= $priceAlert . "\n";
if ($finalAlertText && $TELEGRAM_BOT_TOKEN !== "YOUR_TELEGRAM_BOT_TOKEN" && $telegramChat) {
    @sendTelegram($TELEGRAM_BOT_TOKEN, $telegramChat, "🚨 Bitcoin Alert\n".$finalAlertText);
}

?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>Bitcoin Dashboard + Toggles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background:#0f172a; color:#fff; font-family:'Cairo',sans-serif; }
    .card { border-radius:15px; background:#1e293b; color:#fff; box-shadow:0 5px 20px rgba(0,0,0,.3); }
    .alert-box { font-weight:bold; border-radius:10px; padding:10px; }
    table { color:#fff; }
    th { color:#ffc107; }
    .form-switch .form-check-input { cursor:pointer; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background:#111827;">
  <div class="container-fluid">
    <a class="navbar-brand" href="#" style="color:#4cafef;">🚀 Bitcoin Dashboard + Telegram</a>
  </div>
</nav>

<div class="container py-4">

  <!-- تنبيهات على الصفحة -->
  <?php if ($rsiAlert || $priceAlert): ?>
    <div class="alert-box bg-warning text-dark text-center">
      <?php echo nl2br(htmlspecialchars(trim(($rsiAlert ? $rsiAlert."\n" : "").$priceAlert))); ?>
    </div>
  <?php else: ?>
    <div class="alert-box bg-success text-white text-center">لا توجد تنبيهات حالياً.</div>
  <?php endif; ?>

  <!-- إعدادات التفعيل/التعطيل -->
  <div class="card p-3 mb-3">
    <h5 class="mb-3">⚙️ إعدادات التنبيهات</h5>
    <form method="post" class="row gy-3 align-items-center">
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="enable_rsi" name="enable_rsi" <?php echo $_SESSION['alerts_enabled_rsi']?'checked':''; ?>>
          <label class="form-check-label" for="enable_rsi">تفعيل تنبيه RSI (تشبع 70/30)</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="enable_price" name="enable_price" <?php echo $_SESSION['alerts_enabled_price']?'checked':''; ?>>
          <label class="form-check-label" for="enable_price">تفعيل تنبيهات أهداف السعر (شراء/بيع)</label>
        </div>
      </div>
      <div class="col-12">
        <button type="submit" name="save_prefs" class="btn btn-outline-light">حفظ التفضيلات</button>
      </div>
    </form>
  </div>

  <!-- أهداف السعر -->
  <div class="card p-3 mb-3">
    <h5 class="mb-3">🎯 أهداف السعر</h5>
    <form method="post" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">هدف الشراء (Buy Target)</label>
        <input type="number" name="buy_target" step="0.01" value="<?php echo htmlspecialchars($buyTarget); ?>" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">هدف البيع (Sell Target)</label>
        <input type="number" name="sell_target" step="0.01" value="<?php echo htmlspecialchars($sellTarget); ?>" class="form-control" required>
      </div>
      <div class="col-12">
        <button type="submit" name="save_targets" class="btn btn-warning">حفظ الأهداف</button>
      </div>
    </form>
  </div>

  <!-- التبويبات -->
  <ul class="nav nav-tabs mt-2" id="myTab" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#price">📈 السعر</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rsiTab">RSI</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#macdTab">MACD</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="price">
      <div class="card p-3 mt-3"><canvas id="priceChart"></canvas></div>
    </div>
    <div class="tab-pane fade" id="rsiTab">
      <div class="card p-3 mt-3"><canvas id="rsiChart"></canvas></div>
    </div>
    <div class="tab-pane fade" id="macdTab">
      <div class="card p-3 mt-3"><canvas id="macdChart"></canvas></div>
    </div>
  </div>

  <!-- الجداول -->
  <div class="row mt-4">
    <div class="col-md-6">
      <div class="card p-3">
        <h5>📊 توقعات 5 أيام</h5>
        <table class="table table-striped">
          <thead><tr><th>اليوم</th><th>التوقع</th></tr></thead>
          <tbody>
            <?php foreach ($predictions as $i=>$p): ?>
              <tr><td><?php echo "اليوم +" . ($i+1); ?></td><td><?php echo number_format($p,2); ?> $</td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3">
        <h5>📌 ملخص المؤشرات</h5>
        <table class="table">
          <tbody>
            <tr><th>السعر الحالي</th><td><?php echo number_format($lastPrice,2); ?> $</td></tr>
            <tr><th>RSI</th><td><?php echo number_format($lastRsi,2); ?></td></tr>
            <tr><th>SMA (7)</th><td><?php echo number_format(end(array_filter($sma)),2); ?></td></tr>
            <tr><th>EMA (7)</th><td><?php echo number_format(end($ema),2); ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
const prices = <?php echo json_encode($prices); ?>;
const sma = <?php echo json_encode($sma); ?>;
const ema = <?php echo json_encode($ema); ?>;
const bbUpper = <?php echo json_encode($bbUpper); ?>;
const bbLower = <?php echo json_encode($bbLower); ?>;
const predictions = <?php echo json_encode($predictions); ?>;
const labels = Array.from({length: prices.length}, (_, i) => "يوم " + (i+1));

// السعر + المؤشرات
new Chart(document.getElementById('priceChart'), {
  type: 'line',
  data: {
    labels: [...labels, "توقع1","توقع2","توقع3","توقع4","توقع5"],
    datasets: [
      { label: 'السعر', data: prices, borderColor: '#4cafef', borderWidth:2, fill:false },
      { label: 'SMA (7)', data: sma, borderColor: '#ffc107', borderWidth:1, borderDash:[5,5], fill:false },
      { label: 'EMA (7)', data: ema, borderColor: '#00e676', borderWidth:1, borderDash:[5,5], fill:false },
      { label: 'Upper BB', data: bbUpper, borderColor: '#ff5252', borderWidth:1, fill:false },
      { label: 'Lower BB', data: bbLower, borderColor: '#ff5252', borderWidth:1, fill:false },
      { label: 'التوقعات', data: [...Array(prices.length).fill(null), ...predictions], borderColor: '#ff9800', borderWidth:2, borderDash:[3,3], fill:false }
    ]
  },
  options: {responsive:true, plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#ccc'}},y:{ticks:{color:'#ccc'}}}}
});

// RSI
const rsi = <?php echo json_encode($rsi); ?>;
new Chart(document.getElementById('rsiChart'), {
  type: 'line',
  data: { labels: labels, datasets: [{label:'RSI', data:rsi, borderColor:'#ff9800', borderWidth:2, fill:false}] },
  options: {responsive:true, plugins:{legend:{labels:{color:'#fff'}}}, scales:{y:{min:0,max:100,ticks:{color:'#ccc'}}}}
});

// MACD
const macd = <?php echo json_encode($macd); ?>;
const signalLine = <?php echo json_encode($signalLine); ?>;
const histogram = <?php echo json_encode($histogram); ?>;
new Chart(document.getElementById('macdChart'), {
  type: 'line',
  data: {
    labels: labels,
    datasets: [
      {label:'MACD', data:macd, borderColor:'#4cafef', borderWidth:2, fill:false},
      {label:'Signal', data:signalLine, borderColor:'#ff5252', borderWidth:2, fill:false},
      {label:'Histogram', data:histogram, borderColor:'#00e676', borderWidth:1, borderDash:[4,4], fill:false}
    ]
  },
  options: {responsive:true, plugins:{legend:{labels:{color:'#fff'}}}, scales:{x:{ticks:{color:'#ccc'}},y:{ticks:{color:'#ccc'}}}}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
