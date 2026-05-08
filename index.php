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
        <span class="brand-ver">v4.0 • ASSISTANT ÉCRITURE</span>
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

    <!-- ═══ OPTIMISATION MODÈLE & RÉGLAGES ═══════════════════ -->
    <div class="sidebar-section">
      <div class="section-label">◤ AUDIT IA — MODÈLE OPTIMAL</div>
      <div class="audit-box" id="audit-box">
        <div class="audit-status" id="audit-status">◈ EN ATTENTE D'AUDIT</div>
        <div class="audit-recommendation" id="audit-recommendation"></div>
      </div>
      <button class="audit-btn" id="audit-btn">⟶ LANCER AUDIT IA</button>
    </div>

    <div class="sidebar-section">
      <div class="section-label">◤ MODÈLE NEURAL SUGGÉRÉ</div>
      <select id="model-select" class="cyber-select">
        <option value="chat">nemo · CHAT</option>
        <option value="analysis">small · ANALYSE</option>
        <option value="reasoning">large · RAISON.</option>
        <option value="creative">small · CRÉATIF</option>
        <option value="code">codestral · CODE</option>
        <option value="fast">ministral · RAPIDE</option>
      </select>
      <div class="model-hint" id="model-hint">Sélection automatique par audit IA recommandée</div>
    </div>

    <!-- ═══ MODES D'ÉCRITURE ═══════════════════════════════ -->
    <div class="sidebar-section">
      <div class="section-label">◤ MODE D'ÉCRITURE</div>
      <div class="mode-grid">
        <button class="mode-btn active" data-mode="normal">NORMAL</button>
        <button class="mode-btn" data-mode="profond">PROFOND</button>
        <button class="mode-btn" data-mode="creatif">CRÉATIF</button>
        <button class="mode-btn" data-mode="technique">TECH</button>
        <button class="mode-btn" data-mode="poetique">POÉSIE</button>
      </div>
    </div>

    <!-- ═══ SUGGESTIONS DE QUESTIONS ═══════════════════════ -->
    <div class="sidebar-section">
      <div class="section-label">◤ SUGGESTIONS IA</div>
      <div class="suggestions-list" id="suggestions-list">
        <div class="suggestion-item" data-inject="Peux-tu développer davantage ce point ?">▸ Développer ce point</div>
        <div class="suggestion-item" data-inject="Quelle est la source de cette information ?">▸ Demander sources</div>
        <div class="suggestion-item" data-inject="Peux-tu reformuler plus simplement ?">▸ Reformuler simple</div>
        <div class="suggestion-item" data-inject="Donne-moi des exemples concrets">▸ Exemples concrets</div>
        <div class="suggestion-item" data-inject="Quelles sont les limites de cette approche ?">▸ Limites & critiques</div>
      </div>
    </div>

    <!-- ═══ MOTEURS PARALLÈLES ═══════════════════════════ -->
    <div class="sidebar-section">
      <div class="section-label">◤ MOTEURS PARALLÈLES</div>
      <div class="api-keys-status">
        <div class="key-row"><span class="dot dot-green"></span> RÉPONDEUR <span class="key-tag">K1</span></div>
        <div class="key-row"><span class="dot dot-cyan"></span> NEXUS-A <span class="key-tag">K2</span></div>
        <div class="key-row"><span class="dot dot-purple"></span> NEXUS-B <span class="key-tag">K3</span></div>
      </div>
    </div>

    <!-- ═══ STATISTIQUES SESSION ═════════════════════════ -->
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
      <div class="panel-title">ASSISTANT<span class="panel-ver">-ÉCRITURE</span></div>
      <div class="panel-sub">AIDE INTELLIGENTE À LA RÉDACTION</div>
      <div class="analysis-status" id="analysis-status">
        <span class="status-idle">◈ EN ATTENTE</span>
      </div>
    </div>

    <!-- ❶ OPTIMISATION DE RÉPONSE -->
    <div class="analysis-block" id="block-response-opt">
      <div class="block-title">❶ OPTIMISATION DE RÉPONSE</div>
      <div class="opt-suggestions" id="opt-suggestions">
        <div class="opt-item clickable" data-inject="Peux-tu rendre cette réponse plus concise ?">▸ Rendre plus concis</div>
        <div class="opt-item clickable" data-inject="Peux-tu développer avec plus de détails ?">▸ Développer davantage</div>
        <div class="opt-item clickable" data-inject="Reformule avec un ton plus professionnel">▸ Ton professionnel</div>
        <div class="opt-item clickable" data-inject="Ajoute des exemples pratiques">▸ Ajouter exemples</div>
        <div class="opt-item clickable" data-inject="Simplifie le langage pour débutants">▸ Simplifier langage</div>
      </div>
    </div>

    <!-- ❷ ANALYSE WIKIPÉDIA LIÉE -->
    <div class="analysis-block">
      <div class="block-title">❷ CONNEXIONS WIKIPÉDIA</div>
      <div class="wiki-links" id="wiki-links">
        <div class="wiki-placeholder">En attente d'analyse contextuelle...</div>
      </div>
      <button class="wiki-btn" id="wiki-btn">⟶ ANALYSER CONTEXTE WIKIPÉDIA</button>
    </div>

    <!-- ❸ SUGGESTIONS DE QUESTIONS -->
    <div class="analysis-block">
      <div class="block-title">❸ QUESTIONS PERTINENTES</div>
      <div class="question-suggestions" id="question-suggestions">
        <div class="qs-item clickable" data-inject="Quelles sont les implications pratiques ?">▸ Implications pratiques</div>
        <div class="qs-item clickable" data-inject="Y a-t-il des contre-arguments ?">▸ Contre-arguments</div>
        <div class="qs-item clickable" data-inject="Comment appliquer cela concrètement ?">▸ Application concrète</div>
        <div class="qs-item clickable" data-inject="Quelles ressources pour approfondir ?">▸ Ressources approfondies</div>
        <div class="qs-item clickable" data-inject="Quels sont les risques potentiels ?">▸ Risques potentiels</div>
      </div>
    </div>

    <!-- ❹ AMÉLIORATION STYLE -->
    <div class="analysis-block">
      <div class="block-title">❹ AMÉLIORATION STYLE</div>
      <div class="style-improvements">
        <div class="style-meter-row"><span>CLARTÉ</span><div class="style-track"><div class="style-fill accent" id="si-clarity"></div></div><span id="si-clarity-v">0</span></div>
        <div class="style-meter-row"><span>PRÉCISION</span><div class="style-track"><div class="style-fill purple" id="si-precision"></div></div><span id="si-precision-v">0</span></div>
        <div class="style-meter-row"><span>COHÉRENCE</span><div class="style-track"><div class="style-fill green" id="si-coherence"></div></div><span id="si-coherence-v">0</span></div>
        <div class="style-meter-row"><span>RICHESSE</span><div class="style-track"><div class="style-fill warn" id="si-richness"></div></div><span id="si-richness-v">0</span></div>
      </div>
      <div class="style-actions">
        <button class="style-action-btn" data-inject="Améliore la clarté de mon texte">Clarté ↑</button>
        <button class="style-action-btn" data-inject="Rends mon texte plus précis">Précision ↑</button>
        <button class="style-action-btn" data-inject="Enrichis mon vocabulaire">Vocabulaire ↑</button>
      </div>
    </div>

    <!-- ❺ LEETCH & RECHERCHE -->
    <div class="analysis-block">
      <div class="block-title">❺ LEECH & RECHERCHE</div>
      <div class="leech-options">
        <button class="leech-btn" data-inject="Trouve des sources académiques sur ce sujet">📚 Sources académiques</button>
        <button class="leech-btn" data-inject="Extrais les points clés de ce texte">📋 Points clés</button>
        <button class="leech-btn" data-inject="Génère un résumé exécutif">📝 Résumé exécutif</button>
        <button class="leech-btn" data-inject="Crée une fiche de révision">📇 Fiche révision</button>
        <button class="leech-btn" data-inject="Liste les concepts à connaître">💡 Concepts clés</button>
      </div>
    </div>

    <!-- ❻ STRUCTURE & ORGANISATION -->
    <div class="analysis-block">
      <div class="block-title">❻ STRUCTURE TEXTE</div>
      <div class="struct-grid6">
        <div class="struct-item"><div class="struct-val" id="st-paragraphs">—</div><div class="struct-label">PARAGRAPHES</div></div>
        <div class="struct-item"><div class="struct-val" id="st-sentences">—</div><div class="struct-label">PHRASES</div></div>
        <div class="struct-item"><div class="struct-val" id="st-words">—</div><div class="struct-label">MOTS</div></div>
        <div class="struct-item"><div class="struct-val" id="st-chars">—</div><div class="struct-label">CARACT.</div></div>
        <div class="struct-item"><div class="struct-val" id="st-readability">—</div><div class="struct-label">LISIBILITÉ</div></div>
        <div class="struct-item"><div class="struct-val" id="st-level">—</div><div class="struct-label">NIVEAU</div></div>
      </div>
      <canvas id="struct-chart" height="110"></canvas>
    </div>

    <!-- ❼ GRAMMAIRE & ORTHOGRAPHE -->
    <div class="analysis-block">
      <div class="block-title">❼ VÉRIFICATION LANGUE</div>
      <div class="grammar-check" id="grammar-check">
        <div class="grammar-status">En attente d'analyse...</div>
      </div>
      <button class="grammar-btn" id="grammar-btn">⟶ VÉRIFIER GRAMMAIRE</button>
    </div>

    <!-- ❽ TON & REGISTRE -->
    <div class="analysis-block">
      <div class="block-title">❽ TON & REGISTRE</div>
      <div class="tone-display" id="tone-display">
        <div class="tone-badge" id="tone-badge">NEUTRE</div>
        <div class="tone-options">
          <button class="tone-option" data-inject="Rends le ton plus formel">Formel</button>
          <button class="tone-option" data-inject="Rends le ton plus amical">Amical</button>
          <button class="tone-option" data-inject="Rends le ton plus persuasif">Persuasif</button>
          <button class="tone-option" data-inject="Rends le ton plus neutre">Neutre</button>
        </div>
      </div>
    </div>

    <!-- ❾ IDÉES & BRAINSTORMING -->
    <div class="analysis-block">
      <div class="block-title">❾ BRAINSTORMING IA</div>
      <div class="brainstorm-options">
        <button class="brainstorm-btn" data-inject="Donne-moi 5 angles différents pour aborder ce sujet">🔄 5 angles différents</button>
        <button class="brainstorm-btn" data-inject="Propose des analogies pour expliquer ce concept">💡 Analogies</button>
        <button class="brainstorm-btn" data-inject="Génère des idées de titres accrocheurs">📰 Titres accrocheurs</button>
        <button class="brainstorm-btn" data-inject="Suggère des sous-thèmes à explorer">🗂️ Sous-thèmes</button>
      </div>
    </div>

    <!-- ❿ EXPORT & PARTAGE -->
    <div class="analysis-block">
      <div class="block-title">❿ EXPORT & FORMATAGE</div>
      <div class="export-options">
        <button class="export-btn" onclick="exportToMarkdown()">📄 Markdown</button>
        <button class="export-btn" onclick="exportToHTML()">🌐 HTML</button>
        <button class="export-btn" onclick="exportToPDF()">📕 PDF</button>
        <button class="export-btn" onclick="copyToClipboard()">📋 Copier tout</button>
      </div>
    </div>

    <!-- ⓫ MÉTADONNÉES -->
    <div class="analysis-block meta-block">
      <div class="block-title">⓫ MÉTADONNÉES SESSION</div>
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
