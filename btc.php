<?php
/**
 * btc_analyzer.php — الصفحة الرئيسية لعرض Dashboard التفاعلي لبيتكوين بشكل كامل ومتوافق مع الهواتف
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('SYMBOL', 'BTCUSDT');
define('BASE_URL', 'https://api.binance.com');

function fetch_klines($symbol, $interval='1h', $limit=200) {
    $ch = curl_init(BASE_URL . "/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if ($resp === false) throw new Exception('cURL Error: ' . curl_error($ch));
    curl_close($ch);
    $data = json_decode($resp, true);
    $out = [];
    foreach($data as $row){
        $out[] = [
            'time'=>date('Y-m-d H:i', $row[0]/1000),
            'open'=>(float)$row[1],
            'high'=>(float)$row[2],
            'low'=>(float)$row[3],
            'close'=>(float)$row[4],
            'volume'=>(float)$row[5],
        ];
    }
    return $out;
}

function sma($values, $period){ $result=[]; for($i=0;$i<count($values);$i++) $result[]=$i+1<$period?null:array_sum(array_slice($values,$i+1-$period,$period))/$period; return $result; }
function ema($values,$period){ $result=[]; $k=2/($period+1); $prev=$values[0]; foreach($values as $i=>$v) $result[]=$prev=$i==0?$v:$v*$k+$prev*(1-$k); return $result; }
function macd($values,$fast=12,$slow=26,$signal=9){ $emaFast=ema($values,$fast); $emaSlow=ema($values,$slow); $macd=[]; foreach($values as $i=>$v) $macd[$i]=$emaFast[$i]-$emaSlow[$i]; $signalLine=ema($macd,$signal); $hist=[]; foreach($macd as $i=>$m) $hist[$i]=$m-$signalLine[$i]; return [$macd,$signalLine,$hist]; }
function rsi($values,$period=14){ $rsis=[]; $gains=[]; $losses=[]; for($i=1;$i<count($values);$i++){ $diff=$values[$i]-$values[$i-1]; $gains[]=max($diff,0); $losses[]=max(-$diff,0); } $avgGain=array_sum(array_slice($gains,0,$period))/$period; $avgLoss=array_sum(array_slice($losses,0,$period))/$period; $rsis=array_fill(0,$period,null); for($i=$period;$i<count($gains);$i++){ $avgGain=(($avgGain*($period-1))+$gains[$i])/$period; $avgLoss=(($avgLoss*($period-1))+$losses[$i])/$period; $rs= $avgLoss==0?0:$avgGain/$avgLoss; $rsis[] = 100-(100/(1+$rs)); } return $rsis; }

try{
    $interval = $_GET['interval'] ?? '1h';
    $limit = $_GET['limit'] ?? 200;
    $data = fetch_klines(SYMBOL,$interval,$limit);
    $time=array_column($data,'time');
    $close=array_column($data,'close');
    $volume=array_column($data,'volume');

    $sma20=sma($close,20);
    $sma50=sma($close,50);
    $sma200=sma($close,200);
    [$macdLine,$signalLine,$macdHist]=macd($close);
    $rsi14=rsi($close,14);

    $alertMessage='🔹 السوق مستقر';
    $lastRSI=end($rsi14);
    $lastMACD=end($macdLine);
    $lastSignal=end($signalLine);

    if($lastRSI < 35 && $lastMACD > $lastSignal) $alertMessage='⚡ يُنصح بالشراء';
    elseif($lastRSI > 65 && $lastMACD < $lastSignal) $alertMessage='⚠ يُنصح بالبيع';

    $cards=[
        ['title'=>'سعر BTC','value'=>end($close)],
        ['title'=>'SMA 20','value'=>end($sma20)],
        ['title'=>'SMA 50','value'=>end($sma50)],
        ['title'=>'SMA 200','value'=>end($sma200)],
        ['title'=>'RSI 14','value'=>round(end($rsi14),2)],
        ['title'=>'MACD','value'=>round(end($macdLine),2)],
    ];

}catch(Throwable $e){die('خطأ: '.$e->getMessage());}

$cardClass='card-neutral';
if($alertMessage==='⚡ يُنصح بالشراء') $cardClass='card-buy';
elseif($alertMessage==='⚠ يُنصح بالبيع') $cardClass='card-sell';
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Bitcoin Trading Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* تحسين مظهر الرسم البياني على الموبايل */
.chart-container { width: 100%; max-width: 100%; height: auto; padding: 15px; }
canvas#btcChart { width: 100% !important; height: 400px !important; }
.button-trade{width:48%; margin:5px 1%;}
@media (max-width: 576px){
  .col-md-2.col-6 { width: 48%; margin-bottom: 10px; }
  canvas#btcChart { height: 300px !important; }
}
</style>
</head>
<body>
<div class="container mt-3">
<h1 class="mb-3 text-center">Bitcoin Trading Dashboard</h1>
<div class="alert alert-info alert-message <?= $cardClass ?>"><?= htmlspecialchars($alertMessage) ?></div>
<div class="row g-2">
<?php if(!empty($cards) && is_array($cards)) {
    foreach($cards as $c){
        echo "<div class='col-md-2 col-6'><div class='card p-3 text-center $cardClass'><h5 class='card-title'>{$c['title']}</h5><p class='display-6'>{$c['value']}</p></div></div>";
    }
} ?>
</div>
<div class="mb-3 text-center">
<button class="button-trade button-buy" onclick="alert('عملية شراء BTC')">شراء</button>
<button class="button-trade button-sell" onclick="alert('عملية بيع BTC')">بيع</button>
</div>
<div class="card p-3 chart-container"><canvas id="btcChart"></canvas></div>
<div class="indicator-expl">
<h5>شرح المؤشرات / Indicators Explanation</h5>
<ul>
<li><strong>SMA:</strong> يعكس متوسط السعر ويحدد الاتجاه العام للسعر. / Shows average price, indicates trend.</li>
<li><strong>EMA:</strong> أسرع في الاستجابة للتغيرات الأخيرة في السعر. / Reacts faster to recent price changes.</li>
<li><strong>MACD:</strong> لتحديد قوة واتجاه الحركة ونقاط الشراء/البيع. / Determines trend strength and buy/sell points.</li>
<li><strong>RSI:</strong> يقيس تشبع الشراء (>70) أو البيع (<30). / Indicates overbought (>70) or oversold (<30).</li>
<li><strong>Volume:</strong> يساعد على تأكيد قوة التحركات السعرية. / Confirms strength of price movements.</li>
</ul>
</div>
</div>
<script>
const ctx=document.getElementById('btcChart').getContext('2d');
const btcChart=new Chart(ctx,{type:'line',data:{labels:<?= json_encode($time) ?>,datasets:[{label:'Close',data:<?= json_encode($close) ?>,borderColor:'#e67e22',fill:false,tension:0.2,pointRadius:3,pointBackgroundColor:'#e67e22'},{label:'SMA 20',data:<?= json_encode($sma20) ?>,borderColor:'#00b894',fill:false,tension:0.2,pointRadius:0},{label:'SMA 50',data:<?= json_encode($sma50) ?>,borderColor:'#6c5ce7',fill:false,tension:0.2,pointRadius:0},{label:'SMA 200',data:<?= json_encode($sma200) ?>,borderColor:'#e84393',borderWidth:3,fill:false,tension:0.2,pointRadius:0},{label:'Volume',data:<?= json_encode($volume) ?>,borderColor:'#3498db',fill:true,backgroundColor:'rgba(52,152,219,0.25)',yAxisID:'y1'}]},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:10,font:{size:12}}}},scales:{x:{display:true,title:{display:true,text:'التاريخ',font:{size:12}}},y:{display:true,title:{display:true,text:'السعر USD',font:{size:12}}},y1:{display:true,position:'right',title:{display:true,text:'Volume',font:{size:12}}}},animation:{duration:800,easing:'easeInOutCubic'},elements:{line:{borderWidth:2}},hover:{mode:'nearest',intersect:true}}});
setInterval(()=>{location.reload();},60000);
</script>
</body>
</html>

    