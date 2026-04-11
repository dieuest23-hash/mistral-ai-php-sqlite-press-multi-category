<?php
// index.php — Orchestrateur + UI : console live, articles paginés, navigation, popups
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>web-4.art | Revue de Presse Algérie - IA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=IBM+Plex+Mono:wght@400;600&family=Source+Serif+4:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
<style>
:root {
    --ink:     #1a1208;
    --paper:   #f5f0e8;
    --aged:    #e8dfc8;
    --accent:  #8b1a1a;
    --accent2: #1a4a8b;
    --gold:    #b8860b;
    --mono:    #2d4a2d;
    --border:  rgba(26,18,8,0.15);
    --console-bg: #0d1117;
    --c-info:  #58a6ff;
    --c-ok:    #3fb950;
    --c-err:   #f85149;
    --c-warn:  #d29922;
    --c-ia:    #a371f7;
    --c-db:    #39d353;
    --c-rss:   #1f6feb;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Source Serif 4', Georgia, serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
}

/* ── Masthead ─────────────────────────────────────────────────────────────── */
.masthead {
    background: var(--ink);
    color: var(--paper);
    padding: 0;
    border-bottom: 4px double var(--gold);
}
.masthead-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 2rem;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 1rem;
}
.masthead-meta {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    color: rgba(245,240,232,0.5);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.masthead-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 900;
    letter-spacing: -0.02em;
    text-align: center;
    color: var(--paper);
    text-shadow: 0 2px 12px rgba(0,0,0,0.4);
}
.masthead-subtitle {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.6rem;
    color: var(--gold);
    letter-spacing: 0.15em;
    text-transform: uppercase;
    text-align: center;
    margin-top: 0.3rem;
}
.masthead-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.3rem;
}
.status-orb {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--c-ok);
    display: inline-block;
    box-shadow: 0 0 8px var(--c-ok);
    animation: orb-pulse 2s ease-in-out infinite;
}
@keyframes orb-pulse {
    0%,100% { box-shadow: 0 0 4px var(--c-ok); }
    50%      { box-shadow: 0 0 14px var(--c-ok); }
}
.status-text {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    color: var(--gold);
    letter-spacing: 0.05em;
}

/* ── Ticker ───────────────────────────────────────────────────────────────── */
.ticker {
    background: var(--accent);
    color: var(--paper);
    padding: 0.4rem 0;
    overflow: hidden;
    white-space: nowrap;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    position: relative;
}
.ticker-inner {
    display: inline-block;
    animation: ticker-scroll 60s linear infinite;
    padding-left: 100%;
}
@keyframes ticker-scroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-100%); }
}

/* ── Layout principal ─────────────────────────────────────────────────────── */
.main-layout {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    display: grid;
    grid-template-columns: 340px 1fr;
    grid-template-rows: auto;
    gap: 0;
}

/* ── Sidebar gauche : Console ─────────────────────────────────────────────── */
.sidebar {
    border-right: 1px solid var(--border);
    position: sticky;
    top: 0;
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--console-bg);
}
.sidebar-header {
    padding: 0.8rem 1rem;
    background: #161b22;
    border-bottom: 1px solid #30363d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.sidebar-title {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.72rem;
    color: #8b949e;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.page-indicator {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    padding: 0.15rem 0.6rem;
    border-radius: 2rem;
    background: rgba(59,130,246,0.15);
    border: 1px solid rgba(59,130,246,0.3);
    color: #58a6ff;
}
.console {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    line-height: 1.7;
    scrollbar-width: thin;
    scrollbar-color: #30363d #0d1117;
}
.console::-webkit-scrollbar { width: 4px; }
.console::-webkit-scrollbar-track { background: #0d1117; }
.console::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }

.log-line { padding: 0.1rem 0.3rem; border-radius: 2px; }
.log-line:hover { background: rgba(255,255,255,0.04); }
.log-info    { color: var(--c-info); }
.log-success { color: var(--c-ok); }
.log-error   { color: var(--c-err); }
.log-warn    { color: var(--c-warn); }
.log-ia      { color: var(--c-ia); }
.log-db      { color: var(--c-db); }
.log-rss     { color: var(--c-rss); }

/* Iframe caché (pages 1-4 s'exécutent là) */
#execFrame {
    width: 1px; height: 1px;
    opacity: 0;
    pointer-events: none;
    position: absolute;
    left: -9999px;
}

/* Barre de progression page */
.progress-bar-container {
    flex-shrink: 0;
    padding: 0.6rem 1rem;
    background: #161b22;
    border-top: 1px solid #30363d;
}
.progress-steps {
    display: flex;
    gap: 0.3rem;
    margin-bottom: 0.4rem;
}
.progress-step {
    flex: 1;
    height: 4px;
    border-radius: 4px;
    background: #30363d;
    transition: background 0.4s;
    position: relative;
    overflow: hidden;
}
.progress-step.active::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: sweep 1.5s linear infinite;
}
@keyframes sweep {
    from { transform: translateX(-100%); }
    to   { transform: translateX(100%); }
}
.progress-step.done    { background: var(--c-ok); }
.progress-step.active  { background: #58a6ff; }
.progress-step.error   { background: var(--c-err); }

.progress-meta {
    display: flex;
    justify-content: space-between;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.58rem;
    color: #8b949e;
}
.loop-count {
    color: var(--gold);
    font-weight: 600;
}

/* ── Zone contenu (droite) ────────────────────────────────────────────────── */
.content-zone {
    padding: 2rem;
    min-height: 100vh;
}

/* ── Navigation catégories ────────────────────────────────────────────────── */
.cat-nav {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border);
}
.cat-nav-title {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    color: rgba(26,18,8,0.4);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 0.8rem;
}
.cat-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.cat-pill {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.62rem;
    padding: 0.25rem 0.7rem;
    border-radius: 2rem;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0.02em;
}
.cat-pill:hover  { background: var(--aged); border-color: var(--ink); }
.cat-pill.active { background: var(--ink); color: var(--paper); border-color: var(--ink); }
.cat-pill .count {
    display: inline-block;
    margin-left: 0.3rem;
    background: rgba(139,26,26,0.15);
    color: var(--accent);
    padding: 0 0.3rem;
    border-radius: 1rem;
    font-size: 0.55rem;
}
.cat-pill.active .count {
    background: rgba(245,240,232,0.2);
    color: var(--paper);
}

/* ── En-tête section articles ─────────────────────────────────────────────── */
.section-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--ink);
    border-bottom: 3px solid var(--accent);
    display: inline-block;
    padding-bottom: 0.2rem;
}
.section-count {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.65rem;
    color: rgba(26,18,8,0.4);
}

/* ── Grille articles ──────────────────────────────────────────────────────── */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.article-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 2px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.25s;
    position: relative;
}
.article-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s;
}
.article-card:hover {
    box-shadow: 0 8px 32px rgba(26,18,8,0.12);
    transform: translateY(-2px);
}
.article-card:hover::before { transform: scaleX(1); }

.card-type {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.58rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.5rem 0.8rem 0;
}
.type-multi_news  { color: var(--accent2); }
.type-single_rss  { color: var(--mono); }
.type-revue_presse { color: var(--accent); }

.card-body { padding: 0.6rem 0.8rem 0.8rem; }

.card-title {
    font-family: 'Playfair Display', serif;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.35;
    color: var(--ink);
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-excerpt {
    font-size: 0.75rem;
    color: rgba(26,18,8,0.6);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 0.6rem;
}

.card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.58rem;
    color: rgba(26,18,8,0.35);
    border-top: 1px solid var(--border);
    padding-top: 0.5rem;
    margin-top: 0.5rem;
}

.card-cat {
    background: var(--aged);
    padding: 0.1rem 0.4rem;
    border-radius: 2px;
    color: rgba(26,18,8,0.6);
}

/* ── Article vide / loading ───────────────────────────────────────────────── */
.articles-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(26,18,8,0.3);
}
.articles-empty-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-style: italic;
    margin-bottom: 0.5rem;
}
.articles-empty-sub {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.68rem;
    letter-spacing: 0.05em;
    animation: blink 1.5s step-end infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

/* ── Pagination ───────────────────────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 1.5rem 0 2rem;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.page-btn {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.7rem;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
    border-radius: 2px;
    transition: all 0.2s;
}
.page-btn:hover   { background: var(--aged); }
.page-btn.current { background: var(--ink); color: var(--paper); border-color: var(--ink); }
.page-btn.disabled { opacity: 0.3; pointer-events: none; }
.page-ellipsis { font-family: 'IBM Plex Mono', monospace; font-size: 0.7rem; color: rgba(26,18,8,0.35); padding: 0 0.3rem; }

/* ── Popup article ────────────────────────────────────────────────────────── */
.popup-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(26,18,8,0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: flex-start;
    justify-content: center;
    padding: 2rem 1rem;
    overflow-y: auto;
}
.popup-overlay.open { display: flex; }

.popup {
    background: var(--paper);
    max-width: 820px;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 2px;
    box-shadow: 0 32px 80px rgba(26,18,8,0.3);
    position: relative;
    animation: popup-in 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes popup-in {
    from { opacity:0; transform:translateY(20px) scale(0.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}

.popup-header {
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.popup-close {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border);
    background: transparent;
    cursor: pointer;
    border-radius: 2px;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: all 0.2s;
    color: var(--ink);
}
.popup-close:hover { background: var(--ink); color: var(--paper); }

.popup-type {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.6rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.5rem;
}
.popup-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
    color: var(--ink);
}
.popup-meta {
    display: flex;
    gap: 1rem;
    padding: 0.6rem 1.5rem;
    background: var(--aged);
    border-bottom: 1px solid var(--border);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.6rem;
    color: rgba(26,18,8,0.5);
    flex-wrap: wrap;
}
.popup-body {
    padding: 1.5rem;
    font-size: 0.92rem;
    line-height: 1.85;
    color: var(--ink);
    white-space: pre-wrap;
    word-break: break-word;
}
.popup-body h2, .popup-body .h2-heading {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 1.5rem 0 0.6rem;
    color: var(--accent);
    border-bottom: 1px solid rgba(139,26,26,0.2);
    padding-bottom: 0.3rem;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .main-layout {
        grid-template-columns: 1fr;
        padding: 0 1rem;
    }
    .sidebar {
        position: static;
        height: 280px;
    }
    .masthead-inner {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .masthead-meta, .masthead-status { display: none; }
}
</style>
</head>
<body>

<!-- ── Masthead ── -->
<header class="masthead">
    <div class="masthead-inner">
        <div>
            <div class="masthead-meta" id="mastDate"></div>
            <div class="masthead-meta" style="margin-top:0.2rem;">Algérie • Boucle perpétuelle • IA Mistral Large</div>
        </div>
        <div>
            <div class="masthead-title">web-4.art</div>
            <div class="masthead-subtitle">Revue de presse algérienne • Analyse critique permanente</div>
        </div>
        <div class="masthead-status">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span class="status-orb" id="statusOrb"></span>
                <span class="status-text" id="statusText">Initialisation…</span>
            </div>
            <div class="status-text" style="margin-top:0.3rem;">
                Articles : <span id="globalArticleCount" style="color:var(--c-ok)">–</span>
            </div>
        </div>
    </div>
</header>

<!-- Ticker -->
<div class="ticker">
    <div class="ticker-inner" id="tickerContent">
        Chargement du flux d'actualités…
    </div>
</div>

<!-- Layout -->
<div class="main-layout">

    <!-- Sidebar console -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Console exécution</span>
            <span class="page-indicator" id="pageIndicator">page –/4</span>
        </div>

        <div class="console" id="consoleEl">
            <div class="log-line log-info">Système prêt. Démarrage de la boucle…</div>
        </div>

        <div class="progress-bar-container">
            <div class="progress-steps">
                <div class="progress-step" id="ps1" title="Page 1 — BDD & RSS"></div>
                <div class="progress-step" id="ps2" title="Page 2 — Article multi-news"></div>
                <div class="progress-step" id="ps3" title="Page 3 — Article single RSS"></div>
                <div class="progress-step" id="ps4" title="Page 4 — Revue de presse"></div>
            </div>
            <div class="progress-meta">
                <span id="progressMsg">Démarrage…</span>
                <span>Boucle : <span class="loop-count" id="loopCount">0</span></span>
            </div>
        </div>
    </aside>

    <!-- Zone principale -->
    <main class="content-zone">

        <!-- Navigation catégories -->
        <nav class="cat-nav">
            <div class="cat-nav-title">&#9632; Catégories</div>
            <div class="cat-pills" id="catPills">
                <button class="cat-pill active" data-cat="all" onclick="filterCat(null, this)">
                    Toutes <span class="count" id="countAll">–</span>
                </button>
            </div>
        </nav>

        <!-- En-tête section -->
        <div class="section-header">
            <div>
                <div class="section-title" id="sectionTitle">Derniers articles</div>
            </div>
            <div class="section-count" id="sectionCount"></div>
        </div>

        <!-- Grille articles -->
        <div class="articles-grid" id="articlesGrid">
            <div class="articles-empty" id="emptyState">
                <div class="articles-empty-title">En attente d'articles…</div>
                <div class="articles-empty-sub">La boucle IA démarre ▋</div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="paginationEl"></div>

    </main>
</div>

<!-- Popup article -->
<div class="popup-overlay" id="popupOverlay" onclick="closePopup(event)">
    <div class="popup" id="popupEl">
        <div class="popup-header">
            <div>
                <div class="popup-type" id="popupType"></div>
                <div class="popup-title" id="popupTitle"></div>
            </div>
            <button class="popup-close" onclick="closePopup()">✕</button>
        </div>
        <div class="popup-meta" id="popupMeta"></div>
        <div class="popup-body" id="popupBody"></div>
    </div>
</div>

<!-- iframe d'exécution (invisible) -->
<iframe id="execFrame" src="about:blank" sandbox="allow-scripts allow-same-origin"></iframe>

<script>
// ═══════════════════════════════════════════════════════════════════════════
// ÉTAT GLOBAL
// ═══════════════════════════════════════════════════════════════════════════
const pages     = ['page1.php','page2.php','page3.php','page4.php'];
const PAGE_LABELS = ['BDD & RSS','Multi-news','Single RSS','Revue presse'];
let   currentPageIdx  = 0;
let   loopCount       = 0;
let   waitingForOk    = false;
let   delayTimer      = null;
let   sizeTimer       = null;
let   currentCatId    = null;
let   currentPage     = 1;
const PER_PAGE        = 40;
const MAX_LOG_LINES   = 100;
let   allLogs         = [];
let   refreshTimer    = null;

const frame        = document.getElementById('execFrame');
const consoleEl    = document.getElementById('consoleEl');
const pageInd      = document.getElementById('pageIndicator');
const progressMsg  = document.getElementById('progressMsg');
const loopCountEl  = document.getElementById('loopCount');
const statusText   = document.getElementById('statusText');
const statusOrb    = document.getElementById('statusOrb');
const articlesGrid = document.getElementById('articlesGrid');
const paginationEl = document.getElementById('paginationEl');
const catPills     = document.getElementById('catPills');
const tickerEl     = document.getElementById('tickerContent');
const emptyState   = document.getElementById('emptyState');

// ═══════════════════════════════════════════════════════════════════════════
// DATE MASTHEAD
// ═══════════════════════════════════════════════════════════════════════════
(function() {
    const d = new Date();
    document.getElementById('mastDate').textContent =
        d.toLocaleDateString('fr-FR', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
})();

// ═══════════════════════════════════════════════════════════════════════════
// CONSOLE LOGS
// ═══════════════════════════════════════════════════════════════════════════
window.addLog = function(msg, level = 'info') {
    allLogs.push({ msg, level, ts: Date.now() });
    if (allLogs.length > MAX_LOG_LINES) allLogs.shift();
    renderConsole();
};

function renderConsole() {
    consoleEl.innerHTML = allLogs.map(l =>
        `<div class="log-line log-${l.level}">${escHtml(l.msg)}</div>`
    ).join('');
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════════════════════════════════
// PROGRESS STEPS
// ═══════════════════════════════════════════════════════════════════════════
function setProgressStep(idx, state) {
    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById('ps' + i);
        el.className = 'progress-step';
        if (i < idx + 1)      el.classList.add('done');
        else if (i === idx+1) el.classList.add(state === 'error' ? 'error' : 'active');
    }
    pageInd.textContent    = `page ${idx+1}/4`;
    progressMsg.textContent = PAGE_LABELS[idx] + '…';
    statusText.textContent  = `Page ${idx+1} — ${PAGE_LABELS[idx]}`;
}

function resetProgress() {
    for (let i = 1; i <= 4; i++) document.getElementById('ps'+i).className = 'progress-step';
}

// ═══════════════════════════════════════════════════════════════════════════
// CHARGEMENT PAGES DANS L'IFRAME
// ═══════════════════════════════════════════════════════════════════════════
function loadPage(idx) {
    clearTimeout(delayTimer);
    currentPageIdx = idx;
    waitingForOk   = false;

    setProgressStep(idx, 'active');
    addLog(`[sys] Lancement ${pages[idx]}…`, 'info');

    // Reset propre
    frame.src = 'about:blank';
    setTimeout(() => { frame.src = pages[idx]; }, 60);
}

frame.addEventListener('load', () => {
    const src = frame.src || '';
    if (!src || src.endsWith('about:blank')) return;
    waitingForOk = true;
    addLog(`[sys] ${pages[currentPageIdx]} chargée — en attente réponse IA…`, 'info');

    // Injection de la fonction addLog dans l'iframe pour que les pages puissent logger
    try {
        frame.contentWindow.addLog = window.addLog;
    } catch(e) {}
});

// ═══════════════════════════════════════════════════════════════════════════
// RÉCEPTION SIGNAL "OK"
// ═══════════════════════════════════════════════════════════════════════════
function onOkReceived() {
    if (!waitingForOk) return;
    waitingForOk = false;
    clearTimeout(delayTimer);

    // Marquer étape comme done
    document.getElementById('ps' + (currentPageIdx + 1)).className = 'progress-step done';

    const nextIdx = (currentPageIdx + 1) % pages.length;

    // Nouveau loop ?
    if (nextIdx === 0) {
        loopCount++;
        loopCountEl.textContent = loopCount;
        addLog(`[sys] ════ Boucle ${loopCount} complète ════`, 'success');
        resetProgress();
        // Rafraîchir l'UI
        refreshUI();
    }

    addLog(`[sys] Délai 1s avant page suivante…`, 'info');

    delayTimer = setTimeout(() => {
        loadPage(nextIdx);
        // Rafraîchir articles périodiquement
        refreshUI();
    }, 1200);
}

window.notifyIaOk = onOkReceived;
window.addEventListener('message', e => {
    if (e.data && e.data.type === 'iaResponseOk') onOkReceived();
});

// ═══════════════════════════════════════════════════════════════════════════
// API : fetch articles depuis PHP via AJAX
// ═══════════════════════════════════════════════════════════════════════════
async function fetchArticles(page = 1, catId = null) {
    const params = new URLSearchParams({ action: 'articles', page, per_page: PER_PAGE });
    if (catId) params.set('cat_id', catId);
    try {
        const r = await fetch('api.php?' + params);
        return await r.json();
    } catch(e) { return null; }
}

async function fetchCategories() {
    try {
        const r = await fetch('api.php?action=categories');
        return await r.json();
    } catch(e) { return []; }
}

async function fetchStats() {
    try {
        const r = await fetch('api.php?action=stats');
        return await r.json();
    } catch(e) { return {}; }
}

// ═══════════════════════════════════════════════════════════════════════════
// RENDU ARTICLES
// ═══════════════════════════════════════════════════════════════════════════
function typeLabel(type) {
    return { multi_news: '■ Analyse multi-sources', single_rss: '◆ Article approfondi', revue_presse: '● Revue de presse' }[type] || type;
}

function excerpt(text, n = 120) {
    if (!text) return '';
    // Supprimer les marqueurs ## des intertitres
    const clean = text.replace(/^##\s*/gm, '').replace(/^TITRE:\s*/gm, '').trim();
    return clean.length > n ? clean.slice(0, n) + '…' : clean;
}

function formatDate(s) {
    try { return new Date(s).toLocaleDateString('fr-FR', { day:'numeric', month:'short', year:'numeric' }); }
    catch(e) { return s || ''; }
}

function renderArticles(data) {
    if (!data || !data.articles) return;
    const { articles, total, page, per_page } = data;

    // Vider
    articlesGrid.innerHTML = '';

    if (!articles.length) {
        articlesGrid.innerHTML = `
            <div class="articles-empty">
                <div class="articles-empty-title">Aucun article pour l'instant…</div>
                <div class="articles-empty-sub">La boucle IA génère du contenu ▋</div>
            </div>`;
        paginationEl.innerHTML = '';
        document.getElementById('sectionCount').textContent = '';
        return;
    }

    document.getElementById('sectionCount').textContent = `${total} article${total>1?'s':''}`;

    articles.forEach(art => {
        const card = document.createElement('div');
        card.className = 'article-card';
        card.innerHTML = `
            <div class="card-type type-${art.type}">${typeLabel(art.type)}</div>
            <div class="card-body">
                <div class="card-title">${escHtml(art.titre)}</div>
                <div class="card-excerpt">${escHtml(excerpt(art.contenu))}</div>
                <div class="card-meta">
                    <span class="card-cat">${escHtml(art.categorie_nom || '—')}</span>
                    <span>${formatDate(art.created_at)}</span>
                </div>
            </div>`;
        card.addEventListener('click', () => openArticle(art));
        articlesGrid.appendChild(card);
    });

    renderPagination(total, page, per_page);
}

// ═══════════════════════════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════════════════════════
function renderPagination(total, page, perPage) {
    const totalPages = Math.ceil(total / perPage);
    if (totalPages <= 1) { paginationEl.innerHTML = ''; return; }

    let html = '';
    const addBtn = (label, p, isCurrent = false, disabled = false) => {
        html += `<button class="page-btn ${isCurrent?'current':''} ${disabled?'disabled':''}"
                         onclick="goToPage(${p})">${label}</button>`;
    };

    addBtn('«', 1, false, page <= 1);
    addBtn('‹', page - 1, false, page <= 1);

    const range = [];
    range.push(1);
    for (let i = Math.max(2, page - 2); i <= Math.min(totalPages - 1, page + 2); i++) range.push(i);
    if (totalPages > 1) range.push(totalPages);

    let prev = 0;
    range.forEach(p => {
        if (prev && p - prev > 1) html += `<span class="page-ellipsis">…</span>`;
        addBtn(p, p, p === page);
        prev = p;
    });

    addBtn('›', page + 1, false, page >= totalPages);
    addBtn('»', totalPages, false, page >= totalPages);

    paginationEl.innerHTML = html;
}

async function goToPage(p) {
    currentPage = p;
    const data = await fetchArticles(p, currentCatId);
    renderArticles(data);
    window.scrollTo({ top: document.querySelector('.section-header').offsetTop - 80, behavior: 'smooth' });
}

// ═══════════════════════════════════════════════════════════════════════════
// NAVIGATION CATÉGORIES
// ═══════════════════════════════════════════════════════════════════════════
async function renderCatNav() {
    const cats = await fetchCategories();
    // Garder le premier pill "Toutes"
    const allPill = catPills.querySelector('[data-cat="all"]');

    // Supprimer les autres
    [...catPills.querySelectorAll('[data-cat]:not([data-cat="all"])')].forEach(e => e.remove());

    cats.filter(c => c.article_count > 0).forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'cat-pill' + (currentCatId == cat.id ? ' active' : '');
        btn.dataset.cat = cat.id;
        btn.innerHTML = escHtml(cat.nom) + ` <span class="count">${cat.article_count}</span>`;
        btn.addEventListener('click', () => filterCat(cat.id, btn));
        catPills.appendChild(btn);
    });
}

window.filterCat = async function(catId, btn) {
    currentCatId = catId;
    currentPage  = 1;

    // Active state
    catPills.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');

    // Section title
    document.getElementById('sectionTitle').textContent =
        catId ? btn?.textContent?.replace(/\d+/, '').trim() : 'Derniers articles';

    const data = await fetchArticles(1, catId);
    renderArticles(data);
};

// ═══════════════════════════════════════════════════════════════════════════
// POPUP ARTICLE
// ═══════════════════════════════════════════════════════════════════════════
function openArticle(art) {
    document.getElementById('popupType').textContent  = typeLabel(art.type);
    document.getElementById('popupTitle').textContent = art.titre;
    document.getElementById('popupMeta').innerHTML    =
        `<span>📁 ${escHtml(art.categorie_nom || '—')}</span>` +
        `<span>🗓 ${formatDate(art.created_at)}</span>` +
        `<span style="color:var(--accent);">${typeLabel(art.type)}</span>`;

    // Convertir les ## en balises visuelles
    const bodyEl = document.getElementById('popupBody');
    const html = escHtml(art.contenu).replace(
        /^##\s*(.+)$/gm,
        '<div class="h2-heading">$1</div>'
    );
    bodyEl.innerHTML = html;

    document.getElementById('popupOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

window.closePopup = function(e) {
    if (e && e.target !== document.getElementById('popupOverlay')) return;
    document.getElementById('popupOverlay').classList.remove('open');
    document.body.style.overflow = '';
};

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePopup({ target: document.getElementById('popupOverlay') });
});

// ═══════════════════════════════════════════════════════════════════════════
// TICKER
// ═══════════════════════════════════════════════════════════════════════════
async function updateTicker() {
    const data = await fetchArticles(1, null);
    if (!data || !data.articles || !data.articles.length) return;
    tickerEl.textContent = data.articles.map(a => `◆ ${a.titre}`).join('   ░░░   ');
}

// ═══════════════════════════════════════════════════════════════════════════
// RAFRAÎCHISSEMENT UI
// ═══════════════════════════════════════════════════════════════════════════
async function refreshUI() {
    // Stats globales
    const stats = await fetchStats();
    if (stats.total_articles !== undefined) {
        document.getElementById('globalArticleCount').textContent = stats.total_articles;
    }

    // Catégories + pills count
    await renderCatNav();
    const allCount = stats.total_articles || '–';
    document.getElementById('countAll').textContent = allCount;

    // Articles
    const data = await fetchArticles(currentPage, currentCatId);
    renderArticles(data);

    // Ticker
    updateTicker();
}

// ═══════════════════════════════════════════════════════════════════════════
// AUTO-REFRESH toutes les 15s (pour voir les nouveaux articles arriver)
// ═══════════════════════════════════════════════════════════════════════════
setInterval(refreshUI, 15000);

// ═══════════════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════════════
(async function boot() {
    await refreshUI();
    // Démarrer la boucle
    loadPage(0);
})();
</script>
</body>
</html>
