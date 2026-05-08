<?php require_once 'config.php'; require_once 'database.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#060810">
<title>AETHER v4.0 • PANOPTICON NEURAL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?1">
</head>
<body>

<div class="scanlines"></div>
<div class="grid-overlay"></div>

<!-- ═══ LOGIN MODAL ══════════════════════════════════════════ -->
<div class="login-overlay" id="login-overlay">
  <div class="login-card">
    <div class="login-logo">⬡</div>
    <div class="login-title">AETHER v4.0</div>
    <div class="login-sub">◈ PANOPTICON NEURAL — ACCÈS SÉCURISÉ</div>
    <label class="login-label" for="login-email">◤ IDENTIFIANT EMAIL</label>
    <input type="email" id="login-email" class="login-input" placeholder="votre@email.com" autocomplete="email">
    <button class="login-btn" id="login-btn">⟶ INITIALISER SESSION</button>
    <div class="login-error" id="login-error"></div>
    <div class="login-hint">Votre email crée ou reprend votre profil. Aucun mot de passe requis. Vos analyses sont mémorisées entre les sessions.</div>
  </div>
</div>

<!-- ═══ APP SHELL ═════════════════════════════════════════════ -->
<div class="app-shell" id="app-shell">

  <!-- SIDEBAR ──────────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <div class="brand-block">
      <div class="brand-logo">⬡</div>
      <div class="brand-text">
        <span class="brand-name">AETHER</span>
        <span class="brand-ver">v4.0 • PANOPTICON</span>
      </div>
    </div>

    <div class="user-badge">
      <div class="user-avatar" id="user-avatar">?</div>
      <div>
        <div class="user-email" id="user-email-display">non connecté</div>
        <div class="user-since" id="user-since">—</div>
      </div>
    </div>

    <div class="status-bar">
      <span class="dot dot-green"></span> NEXUS ACTIF
      <span class="session-id" id="sid-display">—</span>
    </div>

    <nav class="side-nav">
      <a href="#" class="nav-item active" data-section="chat"><span class="nav-icon">◈</span>Chat</a>
      <a href="#" class="nav-item" data-section="analysis"><span class="nav-icon">◉</span>Analyse</a>
      <a href="#" class="nav-item" data-section="history"><span class="nav-icon">◎</span>Historique</a>
      <a href="#" class="nav-item" data-section="system"><span class="nav-icon">⬟</span>Système</a>
    </nav>

    <div class="sidebar-section">
      <div class="section-label">◤ MODE OPÉRATOIRE</div>
      <div class="mode-grid">
        <button class="mode-btn active" data-mode="normal">NORMAL</button>
        <button class="mode-btn" data-mode="profond">PROFOND</button>
        <button class="mode-btn" data-mode="creatif">CRÉATIF</button>
        <button class="mode-btn" data-mode="technique">TECH</button>
        <button class="mode-btn" data-mode="poetique">POÉSIE</button>
      </div>
    </div>

    <div class="sidebar-section">
      <div class="section-label">◤ MODÈLE NEURAL</div>
      <select id="model-select" class="cyber-select">
        <option value="chat">nemo · CHAT</option>
        <option value="analysis">small · ANALYSE</option>
        <option value="reasoning">large · RAISON.</option>
        <option value="creative">small · CRÉATIF</option>
        <option value="code">codestral · CODE</option>
        <option value="fast">ministral · RAPIDE</option>
      </select>
    </div>

    <div class="sidebar-section">
      <div class="section-label">◤ MOTEURS PARALLÈLES</div>
      <div class="api-keys-status">
        <div class="key-row"><span class="dot dot-green"></span> RÉPONDEUR <span class="key-tag">K1</span></div>
        <div class="key-row"><span class="dot dot-cyan"></span> NEXUS-A <span class="key-tag">K2</span></div>
        <div class="key-row"><span class="dot dot-purple"></span> NEXUS-B <span class="key-tag">K3</span></div>
      </div>
    </div>

    <div class="sidebar-section">
      <div class="section-label">◤ SESSION</div>
      <div class="stats-grid">
        <div class="stat-item"><span class="stat-val" id="total-tokens">0</span><span class="stat-lbl">TOKENS</span></div>
        <div class="stat-item"><span class="stat-val" id="total-msgs">0</span><span class="stat-lbl">MSGS</span></div>
        <div class="stat-item"><span class="stat-val" id="last-latency">—</span><span class="stat-lbl">MS</span></div>
        <div class="stat-item"><span class="stat-val" id="avg-sentiment">—</span><span class="stat-lbl">SENTIM.</span></div>
      </div>
    </div>

    <button id="clear-btn" class="clear-btn">⬡ RÉINITIALISER</button>
  </aside>

  <!-- MAIN CHAT PANEL ──────────────────────────────────────── -->
  <main class="chat-panel">

    <!-- SECTION CHAT -->
    <div id="section-chat" class="section-panel active">
      <div class="chat-header">
        <div class="chat-title">
          <span class="pulse-dot"></span>
          CANAL NEURAL AETHER
        </div>
        <div class="chat-meta">
          <span id="chat-model-label">nemo</span>
          <span id="chat-mode-label">NORMAL</span>
          <span id="chat-time">--:--:--</span>
        </div>
      </div>

      <div id="messages" class="messages-container">
        <div class="welcome-msg">
          <div class="welcome-icon">⬡</div>
          <div class="welcome-text">
            <strong>AETHER v4.0 — PANOPTICON NEURAL ACTIF</strong><br>
            <span>Chaque message est analysé en temps réel par <em>3 moteurs IA</em> : réponse (K1), analyse psycho-émotionnelle NEXUS-A (K2), sociolinguistique NEXUS-B (K3). Vos patterns sont décryptés et visualisés en direct.</span>
          </div>
        </div>
      </div>

      <div class="input-zone">
        <div class="input-meta">
          <span id="char-count">0 car.</span>
          <span id="word-count-input">0 mots</span>
          <span id="input-complexity">—</span>
        </div>
        <div class="input-row">
          <textarea id="msg-input" placeholder="Message… [ENTER envoyer, SHIFT+ENTER saut de ligne]" rows="2"></textarea>
          <button id="send-btn" type="button"><span>⟶</span></button>
        </div>
      </div>
    </div>

    <!-- SECTION ANALYSE COGNITIVE -->
    <div id="section-analysis" class="section-panel">
      <div id="cognitive-content">
        <div class="section-idle">
          <div class="section-idle-icon">◉</div>
          <div class="section-idle-title">ANALYSE COGNITIVE</div>
          <div class="section-idle-sub">Vue BIG BROTHER — radiographie de toutes les sessions.<br>Démarrez une conversation pour peupler cette section.</div>
        </div>
      </div>
    </div>

    <!-- SECTION HISTORIQUE -->
    <div id="section-history" class="section-panel">
      <div id="history-content">
        <div class="section-idle">
          <div class="section-idle-icon">◎</div>
          <div class="section-idle-title">HISTORIQUE</div>
          <div class="section-idle-sub">Vos échanges de session seront affichés ici.<br>Chargement automatique à l'ouverture.</div>
        </div>
      </div>
    </div>

    <!-- SECTION SYSTÈME -->
    <div id="section-system" class="section-panel">
      <div id="system-content">
        <div class="section-idle">
          <div class="section-idle-icon">⬟</div>
          <div class="section-idle-title">DIAGNOSTICS SYSTÈME</div>
          <div class="section-idle-sub">Statut des clés API, base de données, PHP.<br>Chargement automatique à l'ouverture.</div>
        </div>
      </div>
    </div>

  </main>

  <!-- PANOPTICON PANEL ─────────────────────────────────────── -->
  <aside class="analysis-panel" id="analysis-panel">

    <div class="panel-header">
      <div class="panel-title">PANOPTICON<span class="panel-ver">-7</span></div>
      <div class="panel-sub">RADIOGRAPHIE LINGUISTIQUE TEMPS RÉEL</div>
      <div class="analysis-status" id="analysis-status">
        <span class="status-idle">◈ EN ATTENTE</span>
      </div>
    </div>

    <!-- ❶ VECTEUR ÉMOTIONNEL -->
    <div class="analysis-block" id="block-sentiment">
      <div class="block-title">❶ VECTEUR ÉMOTIONNEL</div>
      <div class="sentiment-row">
        <span class="sentiment-label" id="sentiment-label">NEUTRE</span>
        <span class="sentiment-score" id="sentiment-score">50/100</span>
      </div>
      <div class="sentiment-track"><div class="sentiment-bar" id="sentiment-bar" style="width:50%"></div></div>
      <div class="emotion-grid">
        <div class="emotion-item"><span class="emo-label">PRIMAIRE</span><span class="emo-val" id="emotion-primary">—</span></div>
        <div class="emotion-item"><span class="emo-label">SECONDAIRE</span><span class="emo-val" id="emotion-secondary">—</span></div>
      </div>
      <div class="field-row" style="margin-top:.3rem"><span class="field-label">TON</span><span class="field-val accent" id="tone-val">—</span></div>
    </div>

    <!-- ❷ STYLE -->
    <div class="analysis-block">
      <div class="block-title">❷ VECTEUR STYLISTIQUE</div>
      <div class="style-meters">
        <div class="style-meter-row"><span>FORMEL</span><div class="style-track"><div class="style-fill accent" id="sb-formal"></div></div><span id="sb-formal-v">0</span></div>
        <div class="style-meter-row"><span>ASSERTIF</span><div class="style-track"><div class="style-fill purple" id="sb-assert"></div></div><span id="sb-assert-v">0</span></div>
        <div class="style-meter-row"><span>CRÉATIF</span><div class="style-track"><div class="style-fill green" id="sb-creative"></div></div><span id="sb-creative-v">0</span></div>
      </div>
    </div>

    <!-- ❸ PROFIL PSYCHOLOGIQUE -->
    <div class="analysis-block">
      <div class="block-title">❸ PROFIL PSYCHOLOGIQUE</div>
      <div class="psych-meters">
        <div class="meter-row"><span>STRESS</span><div class="meter-track"><div class="meter-fill danger" id="m-stress"></div></div><span id="mv-stress">—</span></div>
        <div class="meter-row"><span>DISSONANCE</span><div class="meter-track"><div class="meter-fill warn" id="m-dissonance"></div></div><span id="mv-dissonance">—</span></div>
        <div class="meter-row"><span>OUVERTURE</span><div class="meter-track"><div class="meter-fill accent" id="m-motivation-bar"></div></div><span id="mv-motivation">—</span></div>
      </div>
      <div class="psycho-grid">
        <div class="pg-item"><span class="pg-label">MASLOW</span><span class="pg-val" id="pg-maslow">—</span></div>
        <div class="pg-item"><span class="pg-label">ATTACHEMENT</span><span class="pg-val" id="pg-attach">—</span></div>
        <div class="pg-item"><span class="pg-label">LOCUS</span><span class="pg-val" id="pg-locus">—</span></div>
        <div class="pg-item"><span class="pg-label">MOTIVATION</span><span class="pg-val" id="pg-motiv">—</span></div>
      </div>
      <div class="field-row mt-half"><span class="field-label">DÉFENSES</span></div>
      <div class="tags-wrap" id="defense-tags"></div>
    </div>

    <!-- ❹ BIG FIVE -->
    <div class="analysis-block charts-section" id="block-big5">
      <div class="block-title">❹ TRAITS PERSONNALITÉ BIG FIVE</div>
      <div class="big5-grid">
        <div class="big5-bar-wrap">
          <div class="big5-bar-outer"><div class="big5-bar-fill" id="b5-open" style="height:50%"></div></div>
          <div class="big5-bar-val" id="bv-open">50</div>
          <div class="big5-bar-label">OUVERT.</div>
        </div>
        <div class="big5-bar-wrap">
          <div class="big5-bar-outer"><div class="big5-bar-fill" id="b5-cons" style="height:50%"></div></div>
          <div class="big5-bar-val" id="bv-cons">50</div>
          <div class="big5-bar-label">CONSCI.</div>
        </div>
        <div class="big5-bar-wrap">
          <div class="big5-bar-outer"><div class="big5-bar-fill" id="b5-extra" style="height:50%"></div></div>
          <div class="big5-bar-val" id="bv-extra">50</div>
          <div class="big5-bar-label">EXTRAV.</div>
        </div>
        <div class="big5-bar-wrap">
          <div class="big5-bar-outer"><div class="big5-bar-fill" id="b5-agree" style="height:50%"></div></div>
          <div class="big5-bar-val" id="bv-agree">50</div>
          <div class="big5-bar-label">AGRÉAB.</div>
        </div>
        <div class="big5-bar-wrap">
          <div class="big5-bar-outer"><div class="big5-bar-fill" id="b5-neuro" style="height:50%"></div></div>
          <div class="big5-bar-val" id="bv-neuro">50</div>
          <div class="big5-bar-label">NÉVROT.</div>
        </div>
      </div>
    </div>

    <!-- ❺ MARKETING -->
    <div class="analysis-block">
      <div class="block-title">❺ PROFIL MARKETING</div>
      <div class="mkt-persona" id="mkt-persona">PERSONA INDÉTERMINÉ</div>
      <div class="mkt-meters">
        <div class="meter-row"><span>ENGAGEMENT</span><div class="meter-track"><div class="meter-fill green" id="m-engage"></div></div><span id="mv-engage">—</span></div>
        <div class="meter-row"><span>URGENCE</span><div class="meter-track"><div class="meter-fill warn" id="m-urgency"></div></div><span id="mv-urgency">—</span></div>
        <div class="meter-row"><span>OBJECTION</span><div class="meter-track"><div class="meter-fill danger" id="m-objection"></div></div><span id="mv-objection">—</span></div>
        <div class="meter-row"><span>PERSUASION</span><div class="meter-track"><div class="meter-fill purple" id="m-persuasion"></div></div><span id="mv-persuasion">—</span></div>
      </div>
      <div class="mkt-row"><span class="field-label">DÉCISION</span><span class="field-val accent" id="mkt-decision">—</span></div>
      <div class="mkt-row"><span class="field-label">PRIX</span><span class="field-val" id="mkt-price">—</span></div>
      <div class="field-row mt-half"><span class="field-label">DOULEURS</span></div>
      <div class="tags-wrap" id="pain-tags"></div>
      <div class="field-row mt-half"><span class="field-label">DÉSIRS</span></div>
      <div class="tags-wrap" id="desire-tags"></div>
    </div>

    <!-- ❻ RADAR STYLISTIQUE -->
    <div class="analysis-block charts-section">
      <div class="block-title">❻ RADAR STYLISTIQUE</div>
      <canvas id="style-chart" height="200"></canvas>
    </div>

    <!-- ❼ SOCIOLOGIQUE -->
    <div class="analysis-block">
      <div class="block-title">❼ PROFIL SOCIOLOGIQUE</div>
      <div class="socio-grid">
        <div class="sg-item"><span class="sg-label">ÉDUCATION</span><span class="sg-val" id="sg-edu">—</span></div>
        <div class="sg-item"><span class="sg-label">GÉNÉRATION</span><span class="sg-val" id="sg-gen">—</span></div>
        <div class="sg-item"><span class="sg-label">CLASSE</span><span class="sg-val" id="sg-class">—</span></div>
        <div class="sg-item"><span class="sg-label">POLITIQUE</span><span class="sg-val" id="sg-polit">—</span></div>
        <div class="sg-item"><span class="sg-label">SOCIOLECTE</span><span class="sg-val" id="sg-socio">—</span></div>
      </div>
      <div class="socio-meters">
        <div class="meter-row"><span>INDIVID.</span><div class="meter-track"><div class="meter-fill accent" id="m-indiv"></div></div><span id="mv-indiv">—</span></div>
        <div class="meter-row"><span>CONFORM.</span><div class="meter-track"><div class="meter-fill purple" id="m-conform"></div></div><span id="mv-conform">—</span></div>
      </div>
      <div class="field-row mt-half"><span class="field-label">RÉFÉRENCES</span></div>
      <div class="tags-wrap" id="cult-tags"></div>
    </div>

    <!-- ❽ STRUCTURE & COGNITION + CHART -->
    <div class="analysis-block">
      <div class="block-title">❽ STRUCTURE &amp; COGNITION</div>
      <div class="struct-grid6">
        <div class="struct-item"><div class="struct-val" id="st-complexity">—</div><div class="struct-label">COMPLEXITÉ</div></div>
        <div class="struct-item"><div class="struct-val" id="st-richness">—</div><div class="struct-label">RICHESSE</div></div>
        <div class="struct-item"><div class="struct-val" id="st-density">—</div><div class="struct-label">DENSITÉ</div></div>
        <div class="struct-item"><div class="struct-val" id="st-cogload">—</div><div class="struct-label">COG.LOAD</div></div>
        <div class="struct-item"><div class="struct-val" id="st-certainty">—</div><div class="struct-label">CERTITUDE</div></div>
        <div class="struct-item"><div class="struct-val" id="st-hedging">—</div><div class="struct-label">HEDGING</div></div>
      </div>
      <canvas id="struct-chart" height="110"></canvas>
    </div>

    <!-- ❾ COMPORTEMENTAL -->
    <div class="analysis-block">
      <div class="block-title">❾ SIGNAUX COMPORTEMENTAUX</div>
      <div class="beh-meters">
        <div class="meter-row"><span>DÉCISION</span><div class="meter-track"><div class="meter-fill green" id="m-decision"></div></div><span id="mv-decision">—</span></div>
        <div class="meter-row"><span>RISQUE</span><div class="meter-track"><div class="meter-fill warn" id="m-risk"></div></div><span id="mv-risk">—</span></div>
        <div class="meter-row"><span>INFO.</span><div class="meter-track"><div class="meter-fill accent" id="m-info"></div></div><span id="mv-info">—</span></div>
        <div class="meter-row"><span>AUTORITÉ</span><div class="meter-track"><div class="meter-fill purple" id="m-auth"></div></div><span id="mv-auth">—</span></div>
        <div class="meter-row"><span>COHÉRENCE</span><div class="meter-track"><div class="meter-fill danger" id="m-consist"></div></div><span id="mv-consist">—</span></div>
      </div>
      <div class="field-row mt-half"><span class="field-label">BIAIS COGNITIFS</span></div>
      <div class="tags-wrap" id="bias-tags"></div>
    </div>

    <!-- ❿ INTENTION & THÈMES -->
    <div class="analysis-block">
      <div class="block-title">❿ INTENTION &amp; THÈMES</div>
      <div class="intent-badge" id="intent-badge">INDÉTERMINÉ</div>
      <div class="tags-wrap" id="themes-tags"></div>
      <div class="field-row mt-half"><span class="field-label">MOTS-CLÉS</span></div>
      <div class="tags-wrap" id="keywords-tags"></div>
    </div>

    <!-- ⓫ EMPREINTE LINGUISTIQUE -->
    <div class="analysis-block">
      <div class="block-title">⓫ EMPREINTE LINGUISTIQUE</div>
      <div class="ling-grid">
        <div class="lg-item"><span class="lg-label">STRUCTURE</span><span class="lg-val" id="lg-struct">—</span></div>
        <div class="lg-item"><span class="lg-label">VOIX</span><span class="lg-val" id="lg-voice">—</span></div>
        <div class="lg-item"><span class="lg-label">PONCTUATION</span><span class="lg-val" id="lg-punct">—</span></div>
        <div class="lg-item"><span class="lg-label">DIV. LEX.</span><span class="lg-val" id="lg-lexdiv">—</span></div>
      </div>
      <div class="field-row mt-half"><span class="field-label">PATTERNS</span></div>
      <div class="tags-wrap" id="patterns-tags"></div>
      <div class="field-row mt-half"><span class="field-label">PROCÉDÉS</span></div>
      <div class="tags-wrap" id="devices-tags"></div>
      <div class="field-row mt-half"><span class="field-label">ANOMALIES</span></div>
      <div class="tags-wrap" id="anomaly-tags"></div>
    </div>

    <!-- ⓬ META -->
    <div class="analysis-block meta-block">
      <div class="block-title">⓬ MÉTADONNÉES SYSTÈME</div>
      <div class="meta-grid">
        <div><span class="mg-label">MODÈLE</span><span class="mg-val" id="meta-model">—</span></div>
        <div><span class="mg-label">LATENCE</span><span class="mg-val" id="meta-latency">—</span></div>
        <div><span class="mg-label">TOKENS ↑</span><span class="mg-val" id="meta-tin">—</span></div>
        <div><span class="mg-label">TOKENS ↓</span><span class="mg-val" id="meta-tout">—</span></div>
        <div><span class="mg-label">SESSION</span><span class="mg-val" id="meta-session">—</span></div>
        <div><span class="mg-label">HEURE</span><span class="mg-val" id="meta-time">—</span></div>
      </div>
    </div>

  </aside>

</div><!-- /app-shell -->

<!-- Mobile NEXUS toggle -->
<button class="mobile-nexus-btn" id="mobile-nexus-btn" title="Afficher analyses">◉</button>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="script.js?1"></script>
</body>
</html>
