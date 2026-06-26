<?php
// 사용: GEMINI_API_KEY=... php scripts/gen-images.php
// ⚠️ 무료티어는 이미지 쿼터=0 → Google AI Studio 프로젝트 결제 활성화 필요.
$key = getenv('GEMINI_API_KEY');
if (!$key) { fwrite(STDERR, "GEMINI_API_KEY 미설정\n"); exit(1); }

$style = getenv('IMG_STYLE') ?: 'illustration'; // illustration | photo
$base = "A warm, calming visual for an adult mental-wellness platform. Palette: deep green #1F4D3F, teal #2E7D6B, navy, cream #F7F3EA, mint #BFE3D4. Mood: warm research lab meets sophisticated digital clinic. No text.";
$styleHint = $style === 'photo'
    ? "Realistic soft-light photograph, muted calming tones."
    : "Clean editorial flat illustration, soft gradients, gentle shapes, motifs of tree/light/room/mind-map.";

$jobs = [
    'rooms/elem.png'   => "$base $styleHint An elementary school child, safe and bright, gentle and reassuring.",
    'rooms/middle.png' => "$base $styleHint A teenage student, navigating emotions and future paths, hopeful.",
    'rooms/univ.png'   => "$base $styleHint A university student in their early 20s, hopeful, exploring identity and relationships.",
    'rooms/worker.png' => "$base $styleHint A working adult in their 30s-40s, calm recovery from a busy day.",
    'rooms/silver.png' => "$base $styleHint A dignified senior, gentle vitality, warmth and reflection.",
    'tests/kmsia-sample.png' => "$base $styleHint Symbol of an adult mind state check, balanced and introspective.",
    'tests/placeholder.png'  => "$base $styleHint Abstract calm mind motif, neutral.",
    'etc/intro.png'    => "$base $styleHint Friendly orientation scene before starting a self-check.",
    'etc/scoring.png'  => "$base $styleHint Gentle analyzing/loading motif, soft and reassuring.",
    'etc/empty.png'    => "$base $styleHint Empty cozy room waiting to be filled, inviting.",
];

$model = $style === 'photo' ? 'imagen-4.0-generate-001' : 'gemini-2.5-flash-image';
foreach ($jobs as $path => $prompt) {
    $out = __DIR__ . "/../public/images/$path";
    @mkdir(dirname($out), 0777, true);
    $b64 = ($model === 'imagen-4.0-generate-001')
        ? gen_imagen($key, $model, $prompt)
        : gen_gemini($key, $model, $prompt);
    if ($b64) { file_put_contents($out, base64_decode($b64)); echo "OK $path\n"; }
    else { echo "FAIL $path\n"; }
}

function http_post_json($url, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>120]);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true);
}
function gen_gemini($key, $model, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$key";
    $r = http_post_json($url, ['contents'=>[['parts'=>[['text'=>$prompt]]]]]);
    foreach ($r['candidates'][0]['content']['parts'] ?? [] as $p) {
        if (isset($p['inlineData']['data'])) return $p['inlineData']['data'];
    }
    return null;
}
function gen_imagen($key, $model, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:predict?key=$key";
    $r = http_post_json($url, ['instances'=>[['prompt'=>$prompt]], 'parameters'=>['sampleCount'=>1, 'aspectRatio'=>'16:9']]);
    return $r['predictions'][0]['bytesBase64Encoded'] ?? null;
}
