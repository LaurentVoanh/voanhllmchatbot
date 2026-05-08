<?php
set_time_limit(600);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'config.php';
require_once 'database.php';

// Force UTF-8 et JSON propre
mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Session & Auth ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['sid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'SESSION_EXPIRED', 'timestamp' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
    exit;
}
$session = $_SESSION['sid'];
$user_email = $_SESSION['user_email'] ?? 'anonyme';
ensure_session($session, $_SESSION['user_id'] ?? null);

// ── Input ────────────────────────────────────────────────────
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune donnée reçue'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalide: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
    exit;
}

$message    = trim($input['message'] ?? '');
$mode       = $input['mode']    ?? ($_SESSION['mode'] ?? 'info');
$model_task = $input['model']   ?? ($_SESSION['model_task'] ?? 'chat');
$phase      = $input['phase']   ?? 'reply';
$msg_id_ref = (int)($input['msg_id'] ?? 0);
$pre_prompt = $input['pre_prompt'] ?? '';
$agent_type = $input['agent_type'] ?? 'standard';

// Sauvegarder mode et modèle en session
$_SESSION['mode'] = $mode;
$_SESSION['model_task'] = $model_task;

if (!$message && !in_array($phase, ['suggest', 'correct', 'devil', 'compare', 'plan', 'tutor', 'left_suggest', 'extract_topics', 'wikipedia', 'next_question', 'thematic_help'])) { 
    http_response_code(400);
    echo json_encode(['error' => 'Message vide'], JSON_UNESCAPED_UNICODE); 
    exit; 
}

// ── Helpers cURL optimisés Hostinger ─────────────────────────
function do_curl(string $url, string $key, array $payload, int $timeout = 55): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return ['raw' => '', 'code' => 0, 'err' => 'Échec initialisation cURL'];
    }
    
    $headers = [
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Aether/5.0"
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);
    
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    // Log erreur si problème
    if ($errno !== 0 || $code >= 400) {
        error_log("cURL Error: $errno - $err - HTTP $code");
    }
    
    return ['raw' => $raw, 'code' => $code, 'err' => $err, 'errno' => $errno];
}

function extract_content(array $res): ?string {
    if (empty($res['raw']) || $res['code'] !== 200) {
        if (!empty($res['raw'])) {
            error_log("API Response: " . substr($res['raw'], 0, 500));
        }
        return null;
    }
    $d = json_decode($res['raw'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in API response: " . json_last_error_msg());
        return null;
    }
    return $d['choices'][0]['message']['content'] ?? null;
}

function parse_json_safe(array $res, string $fallback): array {
    $content = extract_content($res);
    if (empty($content)) {
        return json_decode($fallback, true) ?? [];
    }
    // Nettoyer markdown et backticks
    $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
    $content = preg_replace('/\s*```$/', '', $content);
    $content = trim($content);
    
    $parsed = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        return $parsed;
    }
    
    // Tentative de récupération partielle
    error_log("JSON parse failed, attempting fallback. Content: " . substr($content, 0, 200));
    return json_decode($fallback, true) ?? [];
}

function clean_markdown(string $text): string {
    // Retirer les blocs de code
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    // Retirer les titres markdown
    $text = preg_replace('/^#+\s*/m', '', $text);
    // Retirer gras/italique
    $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
    $text = preg_replace('/\*(.*?)\*/', '$1', $text);
    $text = preg_replace('/__(.*?)__/', '$1', $text);
    // Retirer les liens markdown
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);
    // Retirer listes
    $text = preg_replace('/^[\-\*]\s+/m', '', $text);
    return trim($text);
}

// ── Système prompts par mode ─────────────────────────────────
$temp_map    = ['info'=>0.5, 'redaction'=>0.4, 'critique'=>0.3, 'brainstorm'=>0.8, 'technique'=>0.2, 'apprendre'=>0.3];
$temperature = $temp_map[$mode] ?? 0.5;

// Récupérer contexte mémoire
$ctx_summary = get_context_summary($session);
$ctx_inject  = $ctx_summary
    ? "\n\n[MÉMOIRE CONTEXTE UTILISATEUR ({$user_email})]\n$ctx_summary\n[FIN MÉMOIRE]"
    : '';

$system_reply = SYSTEM_PROMPTS[$mode] ?? SYSTEM_PROMPTS['info'];
$system_reply .= $ctx_inject;

// ════════════════════════════════════════════
// PHASE 1 — REPLY (1 appel, ~5-15s)
// ════════════════════════════════════════════
if ($phase === 'reply') {
    $history      = get_history($session, 8);
    $messages_ctx = array_map(fn($m) => ['role'=>$m['role'],'content'=>$m['content']], $history);
    
    // Ajout pre-prompt si fourni
    $final_message = $message;
    if (!empty($pre_prompt)) {
        $final_message = "[INSTRUCTION: " . $pre_prompt . "]\n\n" . $message;
    }
    
    // Agent spécial - prompt personnalisé
    $agent_prompts = [
        'standard' => '',
        'expert' => 'Tu es un expert senior. Réponds avec profondeur technique et précision.',
        'creative' => 'Tu es un créatif innovant. Propose des idées originales et surprenantes.',
        'critic' => 'Tu es un critique rigoureux. Analyse les points faibles et propose améliorations.',
        'tutor' => 'Tu es un pédagogue patient. Explique simplement avec exemples.',
        'coach' => 'Tu es un coach motivant. Encourage et guide vers l'action.'
    ];
    $agent_instruction = $agent_prompts[$agent_type] ?? '';
    if (!empty($agent_instruction)) {
        $system_reply .= "\n\n[MODE AGENT: $agent_instruction]";
    }
    
    $messages_ctx[] = ['role'=>'user','content'=>$final_message];

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

// ════════════════════════════════════════════
// NOUVELLES PHASES — OUTILS D'AIDE LLM
// ════════════════════════════════════════════

// WIKIPEDIA — Recherche encyclopédique
if ($phase === 'wikipedia') {
    $model_reply = select_model($model_task);
    $wiki_prompt = "Recherche et synthétise des informations sur: \"$message\". Fournis une réponse structurée avec faits vérifiables, contexte historique, concepts clés. Si le sujet est ambigu, demande clarification.";
    
    $res = do_curl(MISTRAL_API, get_key('responder'), [
        'model'       => $model_reply,
        'messages'    => [
            ['role'=>'system','content'=>"Tu es un assistant encyclopédique expert. Réponds de manière factuelle, organisée, avec exemples concrets."],
            ['role'=>'user','content'=>$wiki_prompt]
        ],
        'temperature' => 0.3,
        'max_tokens'  => 1500,
    ]);
    
    $latency = (int)((microtime(true) - (microtime(true) - 1)) * 1000);
    
    if (!$res['raw'] || $res['code'] !== 200) {
        echo json_encode(['error' => 'Erreur Wikipedia API', 'timestamp' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $result = json_decode($res['raw'], true);
    $reply_raw = clean_markdown($result['choices'][0]['message']['content'] ?? '');
    
    echo json_encode([
        'reply'     => $reply_raw,
        'source'    => 'wikipedia_helper',
        'timestamp' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// NEXT_QUESTION — Suggestion prochaine question
if ($phase === 'next_question') {
    $history = get_history($session, 6);
    $ctx = implode("\n", array_map(fn($m) => $m['role'].': '.$m['content'], $history));
    
    $res = do_curl(MISTRAL_API, get_key('analyzer1'), [
        'model'       => 'mistral-small-2506',
        'messages'    => [
            ['role'=>'system','content'=>"Génère 3 suggestions de questions pertinentes pour approfondir la discussion. JSON uniquement: {questions: string[]}. Pas de backticks."],
            ['role'=>'user','content'=>"Contexte conversation:\n$ctx\n\nSujet actuel: $message"]
        ],
        'temperature'     => 0.7,
        'max_tokens'      => 300,
        'response_format' => ['type'=>'json_object'],
    ]);
    
    $suggestions = parse_json_safe($res, '{"questions":["Question 1","Question 2","Question 3"]}');
    
    echo json_encode([
        'suggestions' => $suggestions['questions'] ?? [],
        'timestamp'   => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// THEMATIC_HELP — Aide thématique
if ($phase === 'thematic_help') {
    $topic = $input['topic'] ?? 'général';
    
    $res = do_curl(MISTRAL_API, get_key('responder'), [
        'model'       => select_model($model_task),
        'messages'    => [
            ['role'=>'system','content'=>"Tu es un guide expert. Pour chaque thématique, fournis: concepts clés, ressources recommandées, pièges à éviter, exercices pratiques."],
            ['role'=>'user','content'=>"Thématique: $topic. Message utilisateur: $message"]
        ],
        'temperature' => 0.4,
        'max_tokens'  => 1200,
    ]);
    
    if (!$res['raw'] || $res['code'] !== 200) {
        echo json_encode(['error' => 'Erreur aide thématique', 'timestamp' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $result = json_decode($res['raw'], true);
    $reply_raw = clean_markdown($result['choices'][0]['message']['content'] ?? '');
    
    echo json_encode([
        'reply'     => $reply_raw,
        'topic'     => $topic,
        'timestamp' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// SUGGEST — Suggestions générales
if ($phase === 'suggest') {
    $history = get_history($session, 4);
    $ctx = implode("\n", array_map(fn($m) => $m['content'], $history));
    
    $res = do_curl(MISTRAL_API, get_key('analyzer2'), [
        'model'       => 'ministral-3b-2512',
        'messages'    => [
            ['role'=>'system','content'=>"Propose 5 suggestions courtes et pertinentes pour continuer la conversation. JSON: {suggestions: string[]}"],
            ['role'=>'user','content'=>"Conversation:\n$ctx"]
        ],
        'temperature'     => 0.8,
        'max_tokens'      => 250,
        'response_format' => ['type'=>'json_object'],
    ]);
    
    $data = parse_json_safe($res, '{"suggestions":[]}');
    
    echo json_encode([
        'suggestions' => $data['suggestions'] ?? [],
        'timestamp'   => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error'=>'Phase inconnue: '.$phase], JSON_UNESCAPED_UNICODE);
