<?php
/**
 * btc_dashboard_predictive.php — Dashboard متقدم مع توقعات شراء/بيع تلقائية مع شرح المؤشرات
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('SYMBOL', 'BTCUSDT');
define('BASE_URL', 'https://api.binance.com');

function fetch_klines(string $symbol, string $interval, int $limit): array {
    $ch = curl_init(BASE_URL . "/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if ($resp === false) throw new Exception('cURL Error: ' . curl_error($ch));
    curl_close($ch);

    $data = json_decode($resp, true);
    $out = [];
    foreach ($data as $row) {
        $out[] = [
            'time' => date('Y-m-d H:i', $row[0]/1000),
            'open' => (float)$row[1],
            'high' => (float)$row[2],
            'low'  => (float)$row[3],
            'close'=> (float)$row[4],
            'volume'=> (float)$row[5],
        ];
    }
    return $out;
}

function sma(array $values, int $period): array {
    $result = [];
    for ($i=0;$i<count($values);$i++) $result[] = $i+1<$period?null:array_sum(array_slice($values,$i+1-$period,$period))/$period;
    return $result;
}

function ema(array $values,int $period): array {
    $result=[];
    $k=2/($period+1);
    $prev=$values[0];
    foreach ($values as $i=>$v) $result[]=$prev=$i==0?$v:$v*$k+$prev*(1-$k);
    return $result;
}

function macd(array $values,$fast=12,$slow=26,$signal=9){
    $emaFast=ema($values,$fast); $emaSlow=ema($values,$slow);
    $macd=[]; foreach($values as $i=>$v) $macd[$i]=$emaFast[$i]-$emaSlow[$i];
    $signalLine=ema($macd,$signal); $hist=[]; foreach($macd as $i=>$m) $hist[$i]=$m-$signalLine[$i];
    return [$macd,$signalLine,$hist];
}

function rsi(array $values,int $period=14){
    $rsis=[]; $gains=[];$losses=[];
    for($i=1;$i<count($values);$i++){
        $diff=$values[$i]-$values[$i-1]; $gains[] = max($diff,0); $losses[]= max(-$diff,0);
    }
    $avgGain=array_sum(array_slice($gains,0,$period))/$period;
    $avgLoss=array_sum(array_slice($losses,0,$period))/$period;
    $rsis=array_fill(0,$period,null);
    for($i=$period;$i<count($gains);$i++){
        $avgGain=(($avgGain*($period-1))+$gains[$i])/$period;
        $avgLoss=(($avgLoss*($period-1))+$losses[$i])/$period;
        $rs= $avgLoss==0?0:$avgGain/$avgLoss;
        $rsis[]=100-(100/(1+$rs));
    }
    return $rsis;
}

try{
    $interval=$_GET['interval']??'1h';
    $limit=$_GET['limit']??100;
    $data=fetch_klines(SYMBOL,$interval,$limit);
    $time=array_column($data,'time'); $close=array_column($data,'close'); $volume=array_column($data,'volume');
    $sma20=sma($close,20); $sma50=sma($close,50); $sma200=sma($close,200);
    [$macdLine,$signalLine,$macdHist]=macd($close); $rsi14=rsi($close,14);

    $alertMessage='';
    $lastRSI=end($rsi14);
    $lastMACD=end($macdLine);
    $lastSignal=end($signalLine);

    // توقع الشراء أو البيع
    if($lastRSI < 35 && $lastMACD > $lastSignal){
        $alertMessage='توقع شراء ⚡';
    } elseif($lastRSI > 65 && $lastMACD < $lastSignal){
        $alertMessage='توقع بيع ⚠';
    } else {
        $alertMessage='السوق مستقر 🔹';
    }
}catch(Throwable $e){die("خطأ: ".$e->getMessage());}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>Bitcoin Trading Dashboard Predictive</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{background:#eef2f7;}
.card{margin-bottom:20px;cursor:pointer;transition:transform 0.3s, box-shadow 0.3s;border-radius:12px;}
.card:hover{transform:scale(1.08); box-shadow:0 12px 25px rgba(0,0,0,0.2);}
.chart-container{height:550px;}
.card-title{font-weight:600;}
.display-6{font-weight:bold;}
.button-trade{margin-right:15px;padding:12px 25px;font-size:1.1rem;font-weight:bold;}
.button-buy{background:#28a745;color:#fff;border:none;border-radius:8px;transition:transform 0.2s;}
.button-buy:hover{transform:scale(1.1);}
.button-sell{background:#dc3545;color:#fff;border:none;border-radius:8px;transition:transform 0.2s;}
.button-sell:hover{transform:scale(1.1);}
.alert-message{font-size:1.4rem;font-weight:bold;margin-bottom:20px;text-align:center;}
.indicator-expl{background:#fff;padding:15px;margin-top:20px;border-radius:12px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.indicator-expl h5{font-weight:bold;margin-bottom:10px;}
</style>
</head>
<body>
<div class="container mt-4">
<h1 class="mb-4 text-center">Bitcoin Trading Dashboard</h1>
<div class="alert alert-info alert-message"><?= $alertMessage ?></div>
<div class="row g-3">
  <?php $cards=[
    ['title'=>'سعر BTC','value'=>end($close)],
    ['title'=>'SMA 20','value'=>end($sma20)],
    ['title'=>'SMA 50','value'=>end($sma50)],
    ['title'=>'SMA 200','value'=>end($sma200)],
    ['title'=>'RSI 14','value'=>round(end($rsi14),2)],
    ['title'=>'MACD','value'=>round(end($macdLine),2)],
  ];
  foreach($cards as $c){
    echo "<div class='col-md-2'><div class='card p-3'><h5 class='card-title'>{$c['title']}</h5><p class='display-6'>{$c['value']}</p></div></div>";
  }
  ?>
</div>
<div class="mb-4 text-center">
<button class="button-trade button-buy" onclick="alert('عملية شراء BTC')">شراء</button>
<button class="button-trade button-sell" onclick="alert('عملية بيع BTC')">بيع</button>
</div>
<div class="card p-3 chart-container"><canvas id="btcChart"></canvas></div>

<div class="indicator-expl">
<h5>شرح المؤشرات / Indicators Explanation</h5>
<ul>
<li><strong>SMA (Simple Moving Average) / المتوسط المتحرك البسيط:</strong> يعكس متوسط السعر خلال فترة معينة، يساعد على تحديد الاتجاه العام للسعر. / Shows average price over a period, helps identify trend direction.</li>
<li><strong>EMA (Exponential Moving Average) / المتوسط المتحرك الأسي:</strong> يعطي وزناً أكبر للأسعار الأخيرة ويكون أسرع في الاستجابة لتغيرات السوق. / Gives more weight to recent prices, reacts faster to market changes.</li>
<li><strong>MACD (Moving Average Convergence Divergence) / مؤشر تقارب وتباعد المتوسطات المتحركة:</strong> يقارن بين EMA قصير وطويل لتحديد قوة واتجاه الحركة، يساعد في تحديد نقاط الشراء أو البيع. / Compares short and long EMAs to identify trend strength, helps spotting buy/sell signals.</li>
<li><strong>RSI (Relative Strength Index) / مؤشر القوة النسبية:</strong> يقيس قوة وتغيرات السعر على مدى فترة معينة، يشير إلى حالات تشبع الشراء (>70) أو البيع (>30). / Measures price momentum, indicates overbought (>70) or oversold (<30) conditions.</li>
<li><strong>Volume / حجم التداول:</strong> كمية التداولات، يساعد في تأكيد قوة التحركات السعرية. / Trading quantity, helps confirm strength of price movements.</li>
</ul>
</div>

</div>
<script>
const ctx=document.getElementById('btcChart').getContext('2d');
const btcChart=new Chart(ctx,{type:'line',data:{
    labels: <?= json_encode($time); ?>,
    datasets:[
        {label:'Close',data:<?= json_encode($close); ?>,borderColor:'#000',fill:false,tension:0.3,pointRadius:5,pointBackgroundColor:'#000'},
        {label:'SMA 20',data:<?= json_encode($sma20); ?>,borderColor:'#007bff',fill:false,tension:0.3,pointRadius:0},
        {label:'SMA 50',data:<?= json_encode($sma50); ?>,borderColor:'#28a745',fill:false,tension:0.3,pointRadius:0},
        {label:'SMA 200',data:<?= json_encode($sma200); ?>,borderColor:'#dc3545',fill:false,tension:0.3,pointRadius:0},
        {label:'Volume',data:<?= json_encode($volume); ?>,borderColor:'orange',fill:true,backgroundColor:'rgba(255,165,0,0.25)',yAxisID:'y1'}
    ]
},options:{
    responsive:true,
    interaction:{mode:'index',intersect:false},
    plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:15}}},
    scales:{x:{display:true,title:{display:true,text:'التاريخ'}},y:{display:true,title:{display:true,text:'السعر USD'}},y1:{display:true,position:'right',title:{display:true,text:'Volume'}}},
    animation:{duration:1000,easing:'easeInOutQuart'},
    elements:{line:{borderWidth:2}}
}});
</script>
</body>
</html>
