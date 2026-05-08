<?php
set_time_limit(600);
require_once 'config.php';
require_once 'database.php';
header('Content-Type: application/json; charset=utf-8');

// ── Session & Auth ───────────────────────────────────────────
if (empty($_SESSION['sid'])) {
    echo json_encode(['error' => 'SESSION_EXPIRED', 'timestamp' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
    exit;
}
$session = $_SESSION['sid'];
$user_email = $_SESSION['user_email'] ?? 'anonyme';
ensure_session($session, $_SESSION['user_id'] ?? null);

// ── Input ────────────────────────────────────────────────────
$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$message    = trim($input['message'] ?? '');
$mode       = $input['mode']    ?? 'normal';
$model_task = $input['model']   ?? 'chat';
$phase      = $input['phase']   ?? 'reply';
$msg_id_ref = (int)($input['msg_id'] ?? 0);

if (!$message) { echo json_encode(['error' => 'Message vide'], JSON_UNESCAPED_UNICODE); exit; }

// ── Helpers cURL ─────────────────────────────────────────────
function do_curl(string $url, string $key, array $payload, int $timeout = 55): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key", "Content-Type: application/json"],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['raw' => $raw, 'code' => $code, 'err' => $err];
}

function extract_content(array $res): ?string {
    if (!$res['raw'] || $res['code'] !== 200) return null;
    $d = json_decode($res['raw'], true);
    return $d['choices'][0]['message']['content'] ?? null;
}

function parse_json_safe(array $res, string $fallback): array {
    $content = extract_content($res);
    if (!$content) return json_decode($fallback, true) ?? [];
    $content = preg_replace('/^```json\s*/i', '', trim($content));
    $content = preg_replace('/\s*```$/', '', $content);
    $parsed  = json_decode($content, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($parsed))
        ? $parsed : (json_decode($fallback, true) ?? []);
}

// ── Système prompts ───────────────────────────────────────────
$temp_map    = ['normal'=>0.5,'profond'=>0.3,'creatif'=>0.9,'technique'=>0.2,'poetique'=>0.95];
$temperature = $temp_map[$mode] ?? 0.5;

// Récupérer contexte mémoire
$ctx_summary = get_context_summary($session);
$ctx_inject  = $ctx_summary
    ? "\n\n[MÉMOIRE CONTEXTE UTILISATEUR ({$user_email})]\n$ctx_summary\n[FIN MÉMOIRE]"
    : '';

$system_reply = match($mode) {
    'profond'   => "Tu es AETHER v4.0, IA d'analyse profonde. Réponds avec profondeur et nuance.",
    'creatif'   => "Tu es AETHER v4.0, mode créatif. Réponds avec imagination et originalité.",
    'technique' => "Tu es AETHER v4.0, mode technique. Sois précis, structuré, cite des données.",
    'poetique'  => "Tu es AETHER v4.0, mode poétique. Exprime-toi avec lyrisme et images sensorielles.",
    default     => "Tu es AETHER v4.0, assistant IA avancé. Réponds en français de manière claire et utile.",
} . $ctx_inject;

// ════════════════════════════════════════════
// PHASE 1 — REPLY (1 appel, ~5-15s)
// ════════════════════════════════════════════
if ($phase === 'reply') {
    $history      = get_history($session, 8);
    $messages_ctx = array_map(fn($m) => ['role'=>$m['role'],'content'=>$m['content']], $history);
    $messages_ctx[] = ['role'=>'user','content'=>$message];

    $model_reply = select_model($model_task);
    $t0          = microtime(true);

    $res = do_curl(MISTRAL_API, get_key('responder'), [
        'model'       => $model_reply,
        'messages'    => array_merge([['role'=>'system','content'=>$system_reply]], $messages_ctx),
        'temperature' => $temperature,
        'max_tokens'  => 1200,
    ]);

    $latency = (int)((microtime(true) - $t0) * 1000);

    if (!$res['raw'] || $res['code'] !== 200) {
        $detail = '';
        if ($res['raw']) {
            $d = json_decode($res['raw'], true);
            $detail = $d['message'] ?? $d['error']['message'] ?? '';
        }
        echo json_encode([
            'error'     => ($res['err'] ?: "HTTP {$res['code']}") . ($detail ? " — $detail" : ''),
            'timestamp' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result     = json_decode($res['raw'], true);
    $reply_raw  = $result['choices'][0]['message']['content'] ?? '';
    $tokens_in  = $result['usage']['prompt_tokens']     ?? 0;
    $tokens_out = $result['usage']['completion_tokens'] ?? 0;

    if (!$reply_raw) {
        echo json_encode(['error'=>'Réponse vide de l\'IA','timestamp'=>date('H:i:s')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $msg_id = save_message($session, 'user',      $message,   $tokens_in,  0,           $model_reply, $latency);
             save_message($session, 'assistant',  $reply_raw, 0,           $tokens_out, $model_reply, $latency);

    // Mise à jour contexte mémoire tous les 5 messages
    $stats = get_session_stats($session);
    $msg_count = (int)($stats['cnt'] ?? 0);
    if ($msg_count > 0 && $msg_count % 5 === 0) {
        // On résume le contexte en arrière-plan (best effort, pas bloquant)
        $history_for_ctx = get_history($session, 10);
        $ctx_text = implode("\n", array_map(fn($m) => strtoupper($m['role']).': '.$m['content'], $history_for_ctx));
        $ctx_res = do_curl(MISTRAL_API, get_key('analyzer1'), [
            'model'       => 'mistral-small-2506',
            'messages'    => [
                ['role'=>'system','content'=>"Résume en 3-5 phrases les informations clés sur cet utilisateur (préférences, sujets abordés, style, contexte) pour que l'IA s'en souvienne. Sois factuel et concis. Réponds uniquement avec le résumé, pas d'introduction."],
                ['role'=>'user','content'=>$ctx_text],
            ],
            'temperature' => 0.1,
            'max_tokens'  => 300,
        ], 30);
        $ctx_content = extract_content($ctx_res);
        if ($ctx_content) save_context_summary($session, $ctx_content, $msg_count);
    }

    echo json_encode([
        'reply'     => $reply_raw,
        'msg_id'    => $msg_id,
        'meta'      => ['model'=>$model_reply,'latency'=>$latency,'tokens'=>['in'=>$tokens_in,'out'=>$tokens_out],'session'=>substr($session,0,10)],
        'timestamp' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ════════════════════════════════════════════
// PHASE 2 — ANALYZE (2 appels séparés, ~15-30s)
// ════════════════════════════════════════════
if ($phase === 'analyze') {
    $model = 'mistral-small-2506';

    $p_a = 'Analyse psycho-émotionnelle et marketing. JSON uniquement, sans backticks. Champs: sentiment(positif/négatif/neutre/ambigu), sentiment_score(0-100), emotion_primary, emotion_secondary, tone(formel/informel/académique/familier/ironique/sarcastique/empathique/autoritaire/assertif/contemplatif/ludique), style_formal(0-100), style_assertive(0-100), style_creative(0-100), psychological{big5_openness,big5_conscientiousness,big5_extraversion,big5_agreeableness,big5_neuroticism,stress_level,cognitive_dissonance,motivation_type,maslow_level,attachment_style,locus_control,defense_mechanisms[]}, marketing{buyer_persona,decision_style,pain_points[],desires[],objection_likelihood,engagement_score,brand_affinity_signals[],price_sensitivity,urgency_level,trust_signals[],persuasion_susceptibility}, source_text.';

    $p_b = 'Analyse sociolinguistique et comportementale. JSON uniquement, sans backticks. Champs: complexity(0-100), vocabulary_richness(0-100), intent(question/affirmation/demande/narration/argumentation/exploration/critique/brainstorming/création/confession/recherche/négociation), themes[], keywords[], language_patterns[], rhetorical_devices[], cognitive_load(0-100), information_density(0-100), certainty_level(0-100), sociological{estimated_education,sociolect,cultural_references[],generational_marker,social_class_signals,political_signals,individualism_score(0-100),conformity_score(0-100),community_signals[]}, behavioral{decision_readiness(0-100),risk_tolerance(0-100),information_seeking(0-100),authority_deference(0-100),novelty_seeking(0-100),cognitive_biases[],communication_needs[],consistency_bias(0-100)}, linguistic_fingerprint{lexical_diversity(0-100),hedging_frequency(0-100),sentence_structure(simple/composée/complexe/mixte),voice(active/passive/mixte),punctuation_style}, anomaly_signals[].';

    $fb_a = '{"sentiment":"neutre","sentiment_score":50,"emotion_primary":"ind\u00e9termin\u00e9","emotion_secondary":null,"tone":"neutre","style_formal":50,"style_assertive":50,"style_creative":50,"psychological":{"big5_openness":50,"big5_conscientiousness":50,"big5_extraversion":50,"big5_agreeableness":50,"big5_neuroticism":50,"stress_level":30,"cognitive_dissonance":20,"motivation_type":"ind\u00e9termin\u00e9","maslow_level":"ind\u00e9termin\u00e9","attachment_style":"ind\u00e9termin\u00e9","locus_control":"mixte","defense_mechanisms":[]},"marketing":{"buyer_persona":"ind\u00e9termin\u00e9","decision_style":"ind\u00e9termin\u00e9","pain_points":[],"desires":[],"objection_likelihood":50,"engagement_score":50,"brand_affinity_signals":[],"price_sensitivity":"ind\u00e9termin\u00e9e","urgency_level":50,"trust_signals":[],"persuasion_susceptibility":50},"source_text":""}';
    $fb_b = '{"complexity":50,"vocabulary_richness":50,"intent":"ind\u00e9termin\u00e9","themes":[],"keywords":[],"language_patterns":[],"rhetorical_devices":[],"cognitive_load":50,"information_density":50,"certainty_level":50,"sociological":{"estimated_education":"ind\u00e9termin\u00e9","sociolect":"standard","cultural_references":[],"generational_marker":"ind\u00e9termin\u00e9","social_class_signals":"ind\u00e9termin\u00e9","political_signals":"ind\u00e9termin\u00e9","individualism_score":50,"conformity_score":50,"community_signals":[]},"behavioral":{"decision_readiness":50,"risk_tolerance":50,"information_seeking":50,"authority_deference":50,"novelty_seeking":50,"cognitive_biases":[],"communication_needs":[],"consistency_bias":50},"linguistic_fingerprint":{"lexical_diversity":50,"hedging_frequency":30,"sentence_structure":"mixte","voice":"active","punctuation_style":"standard"},"anomaly_signals":[]}';

    $t0 = microtime(true);

    // KEY 2 → Analyse A
    $res_a = do_curl(MISTRAL_API, get_key('analyzer1'), [
        'model'           => $model,
        'messages'        => [['role'=>'system','content'=>$p_a],['role'=>'user','content'=>'Analyse: '.$message]],
        'temperature'     => 0.1, 'max_tokens' => 1000,
        'response_format' => ['type'=>'json_object'],
    ]);

    sleep(1); // Rate limit Free Tier

    // KEY 3 → Analyse B
    $res_b = do_curl(MISTRAL_API, get_key('analyzer2'), [
        'model'           => $model,
        'messages'        => [['role'=>'system','content'=>$p_b],['role'=>'user','content'=>'Analyse: '.$message]],
        'temperature'     => 0.1, 'max_tokens' => 1000,
        'response_format' => ['type'=>'json_object'],
    ]);

    $latency = (int)((microtime(true) - $t0) * 1000);

    $ana_a = parse_json_safe($res_a, $fb_a);
    $ana_b = parse_json_safe($res_b, $fb_b);
    $ana_a['source_text'] = $message;

    if ($msg_id_ref > 0) save_analysis($session, $msg_id_ref, $ana_a, $ana_b);

    echo json_encode([
        'analysis'        => ['a'=>$ana_a,'b'=>$ana_b],
        'stats'           => get_session_stats($session),
        'latency_analyze' => $latency,
        'timestamp'       => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error'=>'Phase inconnue'], JSON_UNESCAPED_UNICODE);
