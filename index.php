<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Infrastructure - Nagios Live</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><div class="logo">N</div><div><div class="eyebrow">NAGIOS CORE</div><h1>Infrastructure <span>Live</span></h1></div></div>
  <div class="top-right"><div class="live"><i></i><b>LIVE</b><span id="updated">—</span></div><label>Rafraîchissement <select id="refresh"><option value="5">5 s</option><option value="10" selected>10 s</option><option value="15">15 s</option><option value="30">30 s</option></select></label></div>
</header>
<main>
  <section class="toolbar">
    <input id="search" type="search" placeholder="Rechercher un hôte…">
    <select id="group"><option value="ALL">Tous les groupes</option></select>
    <select id="state"><option value="ALL">Tous les états</option><option>UP</option><option>DOWN</option><option>OK</option><option>WARNING</option><option>CRITICAL</option></select>
    <button id="reload" title="Rafraîchir maintenant">↻</button>
  </section>

  <section id="summary" class="summary"></section>
  <nav id="tabs" class="tabs" aria-label="Groupes"></nav>

  <section class="section-head"><div><h2>Checks Nagios</h2><p>État des hôtes, services, CPU, RAM et stockage</p></div></section>
  <section id="hosts" class="host-grid"></section>
</main>
<footer>Dashboard temps réel · source : Nagios status.dat / plugin_output · aucune base historique</footer>
<script src="app.js"></script>
</body>
</html>
