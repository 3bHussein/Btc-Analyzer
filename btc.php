<?php
/**
 * btc_analyzer.php — محلل بتكوين تحليلي شامل عبر سطر الأوامر + رسوم بيانية (سعر + SMA + RSI + MACD)
 *
 * الاستخدام:
 *   php btc_analyzer.php [interval] [limit]
 *
 * يتطلب:
 * - PHP 8+
 * - امتداد cURL
 * - مكتبة jpgraph (https://jpgraph.net/download/) مثبّتة في مجلد jpgraph/
 */

ini_set('memory_limit', '1024M');
declare(strict_types=1);

const SYMBOL     = 'BTCUSDT';
const BASE_URL   = 'https://api.binance.com';
const RISK_FREE  = 0.0;

// ====== (الدوال الحسابية والمؤشرات كما في النسخة السابقة) ======
// ... [الكود الأصلي للمؤشرات والملخص كما هو] ...

// ===== رسم بياني باستخدام JpGraph =====
function plot_chart(array $time, array $close, array $sma20, array $sma50, array $sma200, array $rsi, array $macd, array $signal, array $hist, string $path): void {
    require_once __DIR__ . '/jpgraph/src/jpgraph.php';
    require_once __DIR__ . '/jpgraph/src/jpgraph_line.php';
    require_once __DIR__ . '/jpgraph/src/jpgraph_bar.php';

    $n = count($close);
    $xdata = [];
    foreach ($time as $t) $xdata[] = date('m-d', $t);

    // لوحة رئيسية متعددة
    $graph = new Graph(1000,800);
    $graph->SetMargin(50,40,40,100);
    $graph->SetScale('textlin');
    $graph->title->Set('Bitcoin Price & Indicators');

    $graph->xaxis->SetTickLabels($xdata);
    $graph->xaxis->SetTextTickInterval(intdiv($n, 15));
    $graph->xaxis->SetLabelAngle(45);

    // --- القسم 1: السعر + SMA ---
    $p1 = new LinePlot($close);
    $p1->SetColor('black');
    $p1->SetLegend('Close');

    $p2 = new LinePlot($sma20);
    $p2->SetColor('blue');
    $p2->SetLegend('SMA20');

    $p3 = new LinePlot($sma50);
    $p3->SetColor('green');
    $p3->SetLegend('SMA50');

    $p4 = new LinePlot($sma200);
    $p4->SetColor('red');
    $p4->SetLegend('SMA200');

    $graph->Add($p1);
    $graph->Add($p2);
    $graph->Add($p3);
    $graph->Add($p4);

    // --- القسم 2: RSI ---
    $rsigraph = new Graph(1000,200);
    $rsigraph->SetScale('textlin',0,100);
    $rsigraph->xaxis->SetTickLabels($xdata);
    $rsigraph->xaxis->SetTextTickInterval(intdiv($n, 15));
    $rsigraph->xaxis->SetLabelAngle(45);
    $rsigraph->title->Set('RSI(14)');

    $rsiPlot = new LinePlot($rsi);
    $rsiPlot->SetColor('purple');
    $rsigraph->Add($rsiPlot);

    // خطوط 30 و70
    $r30 = new LinePlot(array_fill(0,$n,30));
    $r30->SetColor('red');
    $r30->SetStyle('dashed');
    $rsigraph->Add($r30);

    $r70 = new LinePlot(array_fill(0,$n,70));
    $r70->SetColor('red');
    $r70->SetStyle('dashed');
    $rsigraph->Add($r70);

    // --- القسم 3: MACD ---
    $macdgraph = new Graph(1000,200);
    $macdgraph->SetScale('textlin');
    $macdgraph->xaxis->SetTickLabels($xdata);
    $macdgraph->xaxis->SetTextTickInterval(intdiv($n, 15));
    $macdgraph->xaxis->SetLabelAngle(45);
    $macdgraph->title->Set('MACD 12/26/9');

    $macdLine = new LinePlot($macd);
    $macdLine->SetColor('blue');
    $macdLine->SetLegend('MACD');
    $macdgraph->Add($macdLine);

    $signalLine = new LinePlot($signal);
    $signalLine->SetColor('red');
    $signalLine->SetLegend('Signal');
    $macdgraph->Add($signalLine);

    $histBar = new BarPlot($hist);
    $histBar->SetFillColor('gray');
    $macdgraph->Add($histBar);

    // --- دمج الرسوم ---
    $mgraph = new MGraph(1000,1200);
    $mgraph->Add($graph,0,0);
    $mgraph->Add($rsigraph,0,600);
    $mgraph->Add($macdgraph,0,800);
    $mgraph->Stroke($path);
}

// ===== البرنامج الرئيسي =====
try {
    $interval = $argv[1] ?? '1d';
    $limit    = isset($argv[2]) ? (int)$argv[2] : 365;

    $data = fetch_klines(SYMBOL, $interval, $limit);
    $time = array_column($data, 'time');
    $open = array_column($data, 'open');
    $high = array_column($data, 'high');
    $low  = array_column($data, 'low');
    $close= array_column($data, 'close');
    $vol  = array_column($data, 'volume');

    // المؤشرات الأساسية
    $sma20 = sma($close, 20);
    $sma50 = sma($close, 50);
    $sma200= sma($close, 200);

    [$macdLine, $signalLine, $macdHist] = macd($close, 12, 26, 9);
    $rsi14 = rsi($close, 14);

    // ... [باقي التحليل وملخص الطباعة كما في النسخة السابقة] ...

    // إنشاء رسوم بيانية
    ensure_dir(__DIR__ . '/output');
    $chartPath = __DIR__ . "/output/btc_chart_{$interval}.png";
    plot_chart($time, $close, $sma20, $sma50, $sma200, $rsi14, $macdLine, $signalLine, $macdHist, $chartPath);
    echo "\nتم إنشاء الرسم البياني (سعر + RSI + MACD): $chartPath\n";

} catch (Throwable $e) {
    fwrite(STDERR, "خطأ: " . $e->getMessage() . "\n");
    exit(1);
}
