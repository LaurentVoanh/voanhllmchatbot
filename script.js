/* ═══════════════════════════════════════════════════
   AETHER v4.0 — SCRIPT COMPLET (2-PHASE + LOGIN + MOBILE)
═══════════════════════════════════════════════════ */

'use strict';

// ── State ────────────────────────────────────────────────────
let currentMode  = 'normal';
let currentModel = 'chat';
let totalTokens  = 0;
let totalMsgs    = 0;
let styleChart   = null;
let structChart  = null;
let isProcessing = false;
let isLoggedIn   = false;
let allAnalyses  = [];

// ── DOM refs ─────────────────────────────────────────────────
const msgInput    = document.getElementById('msg-input');
const sendBtn     = document.getElementById('send-btn');
const messagesEl  = document.getElementById('messages');
const clearBtn    = document.getElementById('clear-btn');
const modelSelect = document.getElementById('model-select');
const charCount   = document.getElementById('char-count');
const wordCountEl = document.getElementById('word-count-input');
const loginOverlay= document.getElementById('login-overlay');
const loginEmail  = document.getElementById('login-email');
const loginBtn    = document.getElementById('login-btn');
const loginError  = document.getElementById('login-error');
const mobileBtn   = document.getElementById('mobile-nexus-btn');
const analysisPanel = document.getElementById('analysis-panel');

// ════════════════════════════════════════════════════════
// LOGIN
// ════════════════════════════════════════════════════════
async function doLogin() {
  const email = loginEmail.value.trim();
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    loginError.textContent = '◈ Email invalide — vérifiez le format';
    loginEmail.focus();
    return;
  }
  loginBtn.disabled = true;
  loginBtn.textContent = '◈ CONNEXION…';
  loginError.textContent = '';

  try {
    const res  = await fetch('login.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({email})
    });
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    // Succès
    isLoggedIn = true;
    loginOverlay.classList.add('hidden');
    setText('user-email-display', data.email);
    setText('user-since', 'depuis ' + (data.member_since||'').substring(0,10));
    setText('sid-display', data.sid || '—');
    const initial = data.email.charAt(0).toUpperCase();
    setText('user-avatar', initial);

    if (msgInput) msgInput.focus();
  } catch(err) {
    loginError.textContent = '◈ ERREUR: ' + err.message;
    loginBtn.disabled = false;
    loginBtn.textContent = '⟶ INITIALISER SESSION';
  }
}

loginBtn.addEventListener('click', doLogin);
loginEmail.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

// ════════════════════════════════════════════════════════
// NAVIGATION
// ════════════════════════════════════════════════════════
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', e => {
    e.preventDefault();
    if (!isLoggedIn) return;
    switchSection(item.dataset.section);
  });
});

function switchSection(section) {
  document.querySelectorAll('.nav-item').forEach(i => i.classList.toggle('active', i.dataset.section === section));
  document.querySelectorAll('.section-panel').forEach(p => p.classList.toggle('active', p.id === 'section-' + section));
  if (section === 'history')  loadHistory();
  if (section === 'analysis') loadCognitiveAnalysis();
  if (section === 'system')   loadSystem();
}

// ════════════════════════════════════════════════════════
// CHARTS
// ════════════════════════════════════════════════════════
function initCharts() {
  const defs = { animation:{duration:900}, plugins:{legend:{display:false}} };

  const ctxS = document.getElementById('style-chart');
  if (ctxS) {
    styleChart = new Chart(ctxS.getContext('2d'), {
      type: 'radar',
      data: {
        labels: ['FORMEL','ASSERTIF','CRÉATIF','DENSE','COMPLEXE','CERTAIN'],
        datasets: [{ data:[0,0,0,0,0,0], backgroundColor:'rgba(0,229,255,.07)', borderColor:'rgba(0,229,255,.55)', pointBackgroundColor:'#00e5ff', pointRadius:3, borderWidth:1.5 }]
      },
      options: { ...defs, scales: { r: { min:0,max:100, grid:{color:'rgba(255,255,255,.05)'}, angleLines:{color:'rgba(255,255,255,.05)'}, ticks:{display:false}, pointLabels:{color:'#4a5a80',font:{family:'Share Tech Mono',size:8}} } } }
    });
  }

  const ctxT = document.getElementById('struct-chart');
  if (ctxT) {
    structChart = new Chart(ctxT.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['COMPL.','RICH.','DENS.','COG.','CERT.'],
        datasets: [{ data:[0,0,0,0,0],
          backgroundColor:['rgba(0,229,255,.28)','rgba(124,58,237,.28)','rgba(245,158,11,.28)','rgba(239,68,68,.28)','rgba(16,185,129,.28)'],
          borderColor:['rgba(0,229,255,.7)','rgba(124,58,237,.7)','rgba(245,158,11,.7)','rgba(239,68,68,.7)','rgba(16,185,129,.7)'],
          borderWidth:1, borderRadius:2 }]
      },
      options: { ...defs, indexAxis:'y', scales: {
        x:{min:0,max:100,grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#4a5a80',font:{size:8,family:'Share Tech Mono'}}},
        y:{grid:{display:false},ticks:{color:'#4a5a80',font:{size:8,family:'Share Tech Mono'}}} } }
    });
  }
}

// ════════════════════════════════════════════════════════
// SEND MESSAGE (2 phases)
// ════════════════════════════════════════════════════════
async function sendMessage() {
  if (isProcessing || !isLoggedIn) return;
  const text = msgInput.value.trim();
  if (!text) return;

  isProcessing = true;
  sendBtn.disabled = true;
  msgInput.value = '';
  updateInputMeta();

  appendMessage('user', text);
  totalMsgs++;

  const typingEl = appendTyping();
  setAnalysisStatus('processing', '◈ PHASE 1 — GÉNÉRATION RÉPONSE…');

  // ── PHASE 1 : reply ──────────────────────────────────────
  let replyData;
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ message:text, mode:currentMode, model:currentModel, phase:'reply' })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status} — ${res.statusText}`);
    replyData = await res.json();
  } catch(err) {
    typingEl.remove();
    appendMessage('assistant', '⚠ Erreur réseau phase 1: ' + err.message);
    setAnalysisStatus('idle', '◈ ERREUR — ' + err.message);
    isProcessing = false; sendBtn.disabled = false; msgInput.focus();
    return;
  }

  typingEl.remove();

  if (replyData.error === 'SESSION_EXPIRED') {
    loginOverlay.classList.remove('hidden');
    isProcessing = false; sendBtn.disabled = false;
    return;
  }

  if (replyData.error) {
    appendMessage('assistant', '⚠ ' + replyData.error);
    setAnalysisStatus('idle', '◈ ERREUR API — ' + replyData.error);
    isProcessing = false; sendBtn.disabled = false; msgInput.focus();
    return;
  }

  appendMessage('assistant', replyData.reply, replyData.timestamp, replyData.meta);
  totalMsgs++;
  totalTokens += (replyData.meta?.tokens?.in||0) + (replyData.meta?.tokens?.out||0);
  updateSidebar(replyData.meta, {});

  // Débloque l'input immédiatement
  isProcessing = false; sendBtn.disabled = false; msgInput.focus();

  // ── PHASE 2 : analyze (non bloquant) ─────────────────────
  setAnalysisStatus('processing', '◈ PHASE 2 — NEXUS ANALYSE…');

  try {
    const res2 = await fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ message:text, mode:currentMode, model:currentModel, phase:'analyze', msg_id:replyData.msg_id })
    });
    if (!res2.ok) throw new Error(`HTTP ${res2.status}`);
    const ad = await res2.json();

    updateAnalysis(ad.analysis, replyData.meta);
    updateSidebar(replyData.meta, ad.stats);
    setAnalysisStatus('done', '◈ NEXUS COMPLET — ' + ad.timestamp);
    allAnalyses.push({ ts:ad.timestamp, text, analysis:ad.analysis });

  } catch(err) {
    setAnalysisStatus('idle', '◈ NEXUS ÉCHOUÉ — ' + err.message);
  }
}

// ════════════════════════════════════════════════════════
// MESSAGES DOM
// ════════════════════════════════════════════════════════
function appendMessage(role, text, timestamp, meta) {
  const wrap   = document.createElement('div');
  wrap.className = 'msg-wrap ' + role;
  const bubble = document.createElement('div');
  bubble.className = 'msg-bubble';
  bubble.textContent = text;
  const m = document.createElement('div');
  m.className = 'msg-meta';
  m.textContent = role === 'user'
    ? 'VOUS • ' + new Date().toLocaleTimeString('fr-FR')
    : 'AETHER • ' + (timestamp||'') + ' • ' + (meta?.model||'');
  wrap.appendChild(bubble); wrap.appendChild(m);
  messagesEl.appendChild(wrap);
  // SCROLL to bottom — critique
  requestAnimationFrame(() => { messagesEl.scrollTop = messagesEl.scrollHeight; });
  return wrap;
}

function appendTyping() {
  const wrap = document.createElement('div');
  wrap.className = 'msg-wrap assistant';
  wrap.innerHTML = `<div class="typing-indicator"><div class="typing-dots"><span></span><span></span><span></span></div>NEXUS TRAITE…</div>`;
  messagesEl.appendChild(wrap);
  requestAnimationFrame(() => { messagesEl.scrollTop = messagesEl.scrollHeight; });
  return wrap;
}

// ════════════════════════════════════════════════════════
// UPDATE ANALYSIS (mapping complet 12 blocs)
// ════════════════════════════════════════════════════════
function updateAnalysis(analysis, meta) {
  if (!analysis) return;
  const a     = analysis.a || {};
  const b     = analysis.b || {};
  const psych = a.psychological || {};
  const mkt   = a.marketing     || {};
  const socio = b.sociological  || {};
  const beh   = b.behavioral    || {};
  const ling  = b.linguistic_fingerprint || {};

  // ❶ Émotionnel
  const score = parseInt(a.sentiment_score)||50;
  setText('sentiment-label', (a.sentiment||'neutre').toUpperCase());
  setText('sentiment-score', score+'/100');
  setWidth('sentiment-bar',  score);
  setText('emotion-primary',   a.emotion_primary||'—');
  setText('emotion-secondary', a.emotion_secondary||'—');
  setText('tone-val', a.tone||'—');

  // ❷ Style
  setBar('sb-formal',   'sb-formal-v',   a.style_formal);
  setBar('sb-assert',   'sb-assert-v',   a.style_assertive);
  setBar('sb-creative', 'sb-creative-v', a.style_creative);

  // ❸ Psycho
  setMeter('m-stress',       'mv-stress',     psych.stress_level);
  setMeter('m-dissonance',   'mv-dissonance', psych.cognitive_dissonance);
  setMeter('m-motivation-bar','mv-motivation', psych.big5_openness);
  setText('pg-maslow', psych.maslow_level||'—');
  setText('pg-attach',  psych.attachment_style||'—');
  setText('pg-locus',   psych.locus_control||'—');
  setText('pg-motiv',   psych.motivation_type||'—');
  renderTags('defense-tags', psych.defense_mechanisms||[], 'tag-pattern');

  // ❹ BIG FIVE barres verticales
  setBig5('b5-open','bv-open', psych.big5_openness);
  setBig5('b5-cons','bv-cons', psych.big5_conscientiousness);
  setBig5('b5-extra','bv-extra',psych.big5_extraversion);
  setBig5('b5-agree','bv-agree',psych.big5_agreeableness);
  setBig5('b5-neuro','bv-neuro',psych.big5_neuroticism);

  // ❺ Marketing
  setText('mkt-persona', mkt.buyer_persona||'INDÉTERMINÉ');
  setMeter('m-engage',    'mv-engage',    mkt.engagement_score);
  setMeter('m-urgency',   'mv-urgency',   mkt.urgency_level);
  setMeter('m-objection', 'mv-objection', mkt.objection_likelihood);
  setMeter('m-persuasion','mv-persuasion',mkt.persuasion_susceptibility);
  setText('mkt-decision', mkt.decision_style||'—');
  setText('mkt-price',    mkt.price_sensitivity||'—');
  renderTags('pain-tags',   mkt.pain_points||[], 'tag-keyword');
  renderTags('desire-tags', mkt.desires||[], 'tag-theme');

  // ❻ Radar
  if (styleChart) {
    styleChart.data.datasets[0].data = [
      a.style_formal||0, a.style_assertive||0, a.style_creative||0,
      b.information_density||0, b.complexity||0, b.certainty_level||0
    ];
    styleChart.update();
  }

  // ❼ Sociologique
  setText('sg-edu',   socio.estimated_education||'—');
  setText('sg-gen',   socio.generational_marker||'—');
  setText('sg-class', socio.social_class_signals||'—');
  setText('sg-polit', socio.political_signals||'—');
  setText('sg-socio', socio.sociolect||'—');
  setMeter('m-indiv',  'mv-indiv',  socio.individualism_score);
  setMeter('m-conform','mv-conform',socio.conformity_score);
  renderTags('cult-tags', socio.cultural_references||[], 'tag-theme');

  // ❽ Structure + chart
  setText('st-complexity', b.complexity||'—');
  setText('st-richness',   b.vocabulary_richness||'—');
  setText('st-density',    b.information_density||'—');
  setText('st-cogload',    b.cognitive_load||'—');
  setText('st-certainty',  b.certainty_level||'—');
  setText('st-hedging',    ling.hedging_frequency||'—');
  if (structChart) {
    structChart.data.datasets[0].data = [b.complexity||0,b.vocabulary_richness||0,b.information_density||0,b.cognitive_load||0,b.certainty_level||0];
    structChart.update();
  }

  // ❾ Comportemental
  setMeter('m-decision','mv-decision',beh.decision_readiness);
  setMeter('m-risk',    'mv-risk',    beh.risk_tolerance);
  setMeter('m-info',    'mv-info',    beh.information_seeking);
  setMeter('m-auth',    'mv-auth',    beh.authority_deference);
  setMeter('m-consist', 'mv-consist', beh.consistency_bias);
  renderTags('bias-tags', beh.cognitive_biases||[], 'tag-keyword');

  // ❿ Intention
  setText('intent-badge', (b.intent||'indéterminé').toUpperCase());
  renderTags('themes-tags',   b.themes||[], 'tag-theme');
  renderTags('keywords-tags', b.keywords||[], 'tag-keyword');

  // ⓫ Linguistique
  setText('lg-struct',  ling.sentence_structure||'—');
  setText('lg-voice',   ling.voice||'—');
  setText('lg-punct',   ling.punctuation_style||'—');
  setText('lg-lexdiv',  ling.lexical_diversity||'—');
  renderTags('patterns-tags', b.language_patterns||[], 'tag-pattern');
  renderTags('devices-tags',  b.rhetorical_devices||[], 'tag-device');
  renderTags('anomaly-tags',  b.anomaly_signals||[], 'tag-keyword');

  // ⓬ Meta
  if (meta) {
    setText('meta-model',   meta.model||'—');
    setText('meta-latency', meta.latency ? meta.latency+' ms' : '—');
    setText('meta-tin',     meta.tokens?.in||'—');
    setText('meta-tout',    meta.tokens?.out||'—');
    setText('meta-session', meta.session||'—');
    setText('meta-time',    new Date().toLocaleTimeString('fr-FR'));
  }

  // Flash blocks
  document.querySelectorAll('.analysis-block').forEach(el => {
    el.classList.remove('updated'); void el.offsetWidth; el.classList.add('updated');
  });
}

// ════════════════════════════════════════════════════════
// DOM HELPERS
// ════════════════════════════════════════════════════════
function setText(id, val) { const e=document.getElementById(id); if(e) e.textContent=val; }
function setWidth(id, pct) { const e=document.getElementById(id); if(e) e.style.width=(parseInt(pct)||0)+'%'; }
function setBar(fId, vId, val) { const v=parseInt(val)||0; setWidth(fId,v); setText(vId,v); }
function setMeter(fId, vId, val) { const v=parseInt(val)||0; setWidth(fId,v); if(vId) setText(vId,v); }
function setBig5(fillId, valId, val) {
  const v = parseInt(val)||0;
  const el = document.getElementById(fillId);
  if (el) el.style.height = v + '%';
  setText(valId, v);
}

function renderTags(cId, items, cls) {
  const c = document.getElementById(cId);
  if (!c) return;
  c.innerHTML = '';
  if (!Array.isArray(items)||!items.length) {
    c.innerHTML = '<span style="font-size:.58rem;color:#2a3550;font-family:\'Share Tech Mono\',monospace">—</span>';
    return;
  }
  items.slice(0,7).forEach((item,i) => {
    const t = document.createElement('span');
    t.className = 'tag '+cls;
    t.textContent = item;
    t.style.animationDelay = (i*40)+'ms';
    c.appendChild(t);
  });
}

function setAnalysisStatus(state, text) {
  const e = document.getElementById('analysis-status');
  if (e) e.innerHTML = `<span class="status-${state}">${text}</span>`;
}

function updateSidebar(meta, stats) {
  setText('total-tokens', totalTokens);
  setText('total-msgs',   totalMsgs);
  setText('last-latency', meta?.latency ? meta.latency+' ms' : '—');
  if (stats?.avg_sent) setText('avg-sentiment', Math.round(stats.avg_sent));
}

function updateInputMeta() {
  const t = msgInput?.value||'';
  const w = t.trim() ? t.trim().split(/\s+/).length : 0;
  if(charCount)   charCount.textContent   = t.length+' car.';
  if(wordCountEl) wordCountEl.textContent = w+' mots';
}

// ════════════════════════════════════════════════════════
// PAGES
// ════════════════════════════════════════════════════════
function showLoading(cId, icon, label) {
  const c = document.getElementById(cId);
  if (!c) return;
  c.innerHTML = `<div class="section-loading">
    <div class="loading-icon">${icon}</div>
    <div class="loading-text">${label}<br><span class="loading-dots"><span></span><span></span><span></span></span></div>
  </div>`;
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function loadHistory() {
  showLoading('history-content', '◎', 'CHARGEMENT HISTORIQUE');
  try {
    const data = await (await fetch('history.php')).json();
    const c = document.getElementById('history-content');
    if (!data.messages?.length) {
      c.innerHTML = '<div class="section-idle"><div class="section-idle-icon">◎</div><div class="section-idle-title">HISTORIQUE VIDE</div><div class="section-idle-sub">Aucun message dans cette session.</div></div>';
      return;
    }
    c.innerHTML = data.messages.map(m => `
      <div class="history-row ${m.role}">
        <div class="history-role">${m.role==='user'?'VOUS':'AETHER'}</div>
        <div class="history-content">${escHtml(m.content)}</div>
        <div class="history-meta">${m.created_at||''} • ${m.model_used||''} • ${((m.tokens_in||0)+(m.tokens_out||0))} tok</div>
      </div>`).join('');
  } catch(e) {
    document.getElementById('history-content').innerHTML = `<div class="error-msg">ERREUR: ${escHtml(e.message)}</div>`;
  }
}

async function loadCognitiveAnalysis() {
  showLoading('cognitive-content', '◉', 'CHARGEMENT PROFILS BIG BROTHER');
  try {
    const data = await (await fetch('stats.php')).json();
    const c = document.getElementById('cognitive-content');
    if (!data.profiles?.length) {
      c.innerHTML = '<div class="section-idle"><div class="section-idle-icon">◉</div><div class="section-idle-title">AUCUN PROFIL</div><div class="section-idle-sub">Démarrez des conversations pour générer des profils.</div></div>';
      return;
    }
    c.innerHTML = `
      <div class="bb-header">◈ BIG BROTHER — ${data.profiles.length} SESSION(S) ANALYSÉE(S)</div>
      <div class="profiles-grid">
      ${data.profiles.map((p,i) => `
        <div class="profile-card">
          <div class="profile-header">
            <span class="profile-sid">SESSION #${i+1} — ${escHtml((p.session_id||'').substring(0,10))}</span>
            <span class="profile-count">${p.msg_count} msgs • ${p.total_tokens||0} tok</span>
          </div>
          <div class="profile-meters">
            <div class="pm-item"><span>SENTIMENT</span><div class="meter-track sm"><div class="meter-fill green" style="width:${Math.round(p.avg_sent||50)}%"></div></div><span>${Math.round(p.avg_sent||50)}</span></div>
            <div class="pm-item"><span>COMPLEXITÉ</span><div class="meter-track sm"><div class="meter-fill accent" style="width:${Math.round(p.avg_cpx||50)}%"></div></div><span>${Math.round(p.avg_cpx||50)}</span></div>
            <div class="pm-item"><span>COG.LOAD</span><div class="meter-track sm"><div class="meter-fill warn" style="width:${Math.round(p.avg_cog||50)}%"></div></div><span>${Math.round(p.avg_cog||50)}</span></div>
          </div>
          <div class="profile-tags">
            ${(p.top_themes||[]).map(t=>`<span class="tag tag-theme">${escHtml(t)}</span>`).join('')}
            ${(p.top_emotions||[]).map(e=>`<span class="tag tag-keyword">${escHtml(e)}</span>`).join('')}
          </div>
        </div>`).join('')}
      </div>`;
  } catch(e) {
    document.getElementById('cognitive-content').innerHTML = `<div class="error-msg">ERREUR: ${escHtml(e.message)}</div>`;
  }
}

async function loadSystem() {
  showLoading('system-content', '⬟', 'DIAGNOSTICS EN COURS');
  try {
    const d = await (await fetch('system.php')).json();
    document.getElementById('system-content').innerHTML = `
      <div class="sys-grid">
        <div class="sys-item"><span class="sys-label">PHP</span><span class="sys-val">${d.php||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">SERVEUR</span><span class="sys-val">${d.server||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">MEM LIMIT</span><span class="sys-val">${d.memory_limit||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">MAX EXEC</span><span class="sys-val">${d.max_exec||'—'}s</span></div>
        <div class="sys-item"><span class="sys-label">DB SIZE</span><span class="sys-val">${d.db_size||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">SESSIONS</span><span class="sys-val">${d.total_sessions||0}</span></div>
        <div class="sys-item"><span class="sys-label">MESSAGES</span><span class="sys-val">${d.total_messages||0}</span></div>
        <div class="sys-item"><span class="sys-label">ANALYSES</span><span class="sys-val">${d.total_analyses||0}</span></div>
        <div class="sys-item"><span class="sys-label">CLÉS VALIDES</span><span class="sys-val">${d.keys_count||0}/3</span></div>
        <div class="sys-item"><span class="sys-label">MOD. CHAT</span><span class="sys-val">${d.model_chat||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">MOD. ANALYSE</span><span class="sys-val">${d.model_analysis||'—'}</span></div>
        <div class="sys-item"><span class="sys-label">DATE</span><span class="sys-val">${d.uptime||'—'}</span></div>
      </div>
      <div class="sys-keys-status">
        ${(d.key_status||[]).map(k=>`<div class="key-row-sys"><span class="dot ${k.ok?'dot-green':'dot-red'}"></span>${escHtml(k.role)} — ${k.ok?'OPÉRATIONNELLE':'INVALIDE'}</div>`).join('')}
      </div>`;
  } catch(e) {
    document.getElementById('system-content').innerHTML = `<div class="error-msg">ERREUR: ${escHtml(e.message)}</div>`;
  }
}

// ════════════════════════════════════════════════════════
// CONTRÔLES
// ════════════════════════════════════════════════════════
document.querySelectorAll('.mode-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMode = btn.dataset.mode;
    setText('chat-mode-label', currentMode.toUpperCase());
  });
});

if (modelSelect) {
  modelSelect.addEventListener('change', () => {
    currentModel = modelSelect.value;
    setText('chat-model-label', modelSelect.options[modelSelect.selectedIndex].text.split('·')[0].trim());
  });
}

if (msgInput) {
  msgInput.addEventListener('input', updateInputMeta);
  msgInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
}
if (sendBtn) sendBtn.addEventListener('click', sendMessage);

if (clearBtn) {
  clearBtn.addEventListener('click', async () => {
    if (!confirm('Effacer la session ?')) return;
    try { await fetch('clear.php',{method:'POST'}); } catch(e){}
    messagesEl.innerHTML = `<div class="welcome-msg"><div class="welcome-icon">⬡</div><div class="welcome-text"><strong>SESSION RÉINITIALISÉE</strong><br><span>Nouvelle session démarrée.</span></div></div>`;
    totalTokens=0; totalMsgs=0; allAnalyses=[];
    updateSidebar({},{}); setAnalysisStatus('idle','◈ EN ATTENTE');
  });
}

// Mobile NEXUS toggle
if (mobileBtn) {
  mobileBtn.addEventListener('click', () => {
    const open = analysisPanel.classList.toggle('mobile-open');
    mobileBtn.classList.toggle('active', open);
    mobileBtn.textContent = open ? '✕' : '◉';
  });
}

// Horloge
setInterval(() => setText('chat-time', new Date().toLocaleTimeString('fr-FR')), 1000);
setText('chat-time', new Date().toLocaleTimeString('fr-FR'));

// ════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════
initCharts();
loginEmail.focus();
