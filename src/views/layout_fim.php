
</main>
</div>
<?php require __DIR__ . '/explicacoes.php'; ?>
<script>
(function () {
  'use strict';
  var PAGINA = <?= json_encode($pagina) ?>;
  var EXPLICA = <?= json_encode($EXPLICA) ?>;
  var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var guarda = {
    ler: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    gravar: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
  };

  // ---------------------------------------------------------- datas livres no filtro
  var f = document.getElementById('filtros');
  if (f) {
    f.querySelectorAll('input[type=date]').forEach(function (el) {
      el.addEventListener('change', function () {
        f.querySelectorAll('input[name=periodo]').forEach(function (p) { p.disabled = true; });
        var c = document.getElementById('perCustom'); if (c) c.disabled = false;
        f.submit();
      });
    });
  }

  // ---------------------------------------------------------- entrada coreografada + contagem
  if (document.body.classList.contains('anima')) {
    var seq = document.querySelectorAll('.diag, .faixa, .grade>.painel, .titulo-secao, .destaques>*, .resumo-fila, .item, .principal>.painel');
    Array.prototype.forEach.call(seq, function (el, i) { el.style.setProperty('--i', Math.min(i, 12)); });

    var alvoContagem = document.querySelectorAll('.ind .val, .lacuna .top b, .dest b.num-anima');
    Array.prototype.forEach.call(alvoContagem, function (el) {
      var no = null;
      for (var k = 0; k < el.childNodes.length; k++) {
        if (el.childNodes[k].nodeType === 3 && /\d/.test(el.childNodes[k].nodeValue)) { no = el.childNodes[k]; break; }
      }
      if (!no || /h|d\b/.test(no.nodeValue)) return;            // durações como "1h 20min" não contam
      var txt = no.nodeValue, m = txt.match(/(\d{1,3}(?:\.\d{3})+|\d+)(,(\d+))?/);
      if (!m) return;
      var dec = m[3] ? m[3].length : 0;
      var alvo = parseFloat(m[1].replace(/\./g, '') + (m[3] ? '.' + m[3] : ''));
      if (!(alvo >= 2)) return;
      var antes = txt.slice(0, m.index), depois = txt.slice(m.index + m[0].length);
      var fmt = function (v) { return v.toLocaleString('pt-BR', {minimumFractionDigits: dec, maximumFractionDigits: dec}); };
      var t0 = null, dur = 900;
      no.nodeValue = antes + fmt(0) + depois;
      var passo = function (t) {
        if (t0 === null) t0 = t;
        var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        no.nodeValue = antes + fmt(alvo * e) + depois;
        if (p < 1) requestAnimationFrame(passo); else no.nodeValue = txt;
      };
      setTimeout(function () { requestAnimationFrame(passo); }, 180);
    });
  }

  // ---------------------------------------------------------- explicação dos indicadores
  var popEx = null, btEx = null;
  function fecharExplica() {
    if (popEx) { popEx.parentNode.removeChild(popEx); popEx = null; }
    if (btEx) { btEx.setAttribute('aria-expanded', 'false'); btEx.focus({preventScroll: true}); btEx = null; }
  }
  function abrirExplica(bt) {
    var e = EXPLICA[bt.getAttribute('data-explica')]; if (!e) return;
    fecharExplica();
    btEx = bt; bt.setAttribute('aria-expanded', 'true');
    popEx = document.createElement('div'); popEx.className = 'explica-pop'; popEx.setAttribute('role', 'dialog'); popEx.setAttribute('aria-label', e.titulo);
    var linha = function (rot, txt) { return txt ? '<dt>' + rot + '</dt><dd></dd>' : ''; };
    popEx.innerHTML = '<button type="button" class="x" aria-label="Fechar">×</button><h5></h5><dl>'
      + linha('O que é', e.o) + linha('Como é calculado', e.c) + linha('Como ler', e.l) + '</dl>' + (e.r ? '<div class="ref"></div>' : '');
    popEx.querySelector('h5').textContent = e.titulo;
    var dds = popEx.querySelectorAll('dd'), txts = [e.o, e.c, e.l].filter(Boolean);
    for (var k = 0; k < dds.length; k++) dds[k].textContent = txts[k];
    if (e.r) popEx.querySelector('.ref').textContent = 'Referência: ' + e.r;
    document.body.appendChild(popEx);
    var r = bt.getBoundingClientRect(), pw = popEx.offsetWidth, ph = popEx.offsetHeight;
    var top = r.bottom + 8; if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 8);
    var left = Math.max(12, Math.min(window.innerWidth - pw - 12, r.left - 20));
    popEx.style.top = top + 'px'; popEx.style.left = left + 'px';
    popEx.querySelector('.x').onclick = fecharExplica;
    popEx.querySelector('.x').focus({preventScroll: true});
  }
  document.addEventListener('click', function (ev) {
    var bt = ev.target.closest ? ev.target.closest('.explica') : null;
    if (bt) { ev.preventDefault(); if (btEx === bt) fecharExplica(); else abrirExplica(bt); return; }
    if (popEx && !popEx.contains(ev.target)) fecharExplica();
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && popEx) fecharExplica(); });
  window.addEventListener('scroll', function () { if (popEx) fecharExplica(); }, true);

  // ---------------------------------------------------------- tour guiado
  var tour = null;
  function visivel(el) { var r = el.getBoundingClientRect(); return el.offsetParent !== null && r.width > 0 && r.height > 0; }

  function abrirTour() {
    if (tour) return;
    var passos = Array.prototype.slice.call(document.querySelectorAll('[data-tour]')).filter(visivel)
      .sort(function (a, b) { return (+a.getAttribute('data-tour')) - (+b.getAttribute('data-tour')); });
    if (!passos.length) return;

    var bloqueio = document.createElement('div'); bloqueio.className = 'tour-bloqueio';
    var foco = document.createElement('div'); foco.className = 'tour-foco';
    var pop = document.createElement('div'); pop.className = 'tour-pop';
    pop.setAttribute('role', 'dialog'); pop.setAttribute('aria-modal', 'true'); pop.setAttribute('aria-labelledby', 'tourTitulo');
    document.body.appendChild(bloqueio); document.body.appendChild(foco); document.body.appendChild(pop);
    var antesFoco = document.activeElement;
    tour = {i: 0, passos: passos};

    function posicionar() {
      var el = tour.passos[tour.i], r = el.getBoundingClientRect(), pad = 8;
      var top = Math.max(r.top - pad, 6), left = Math.max(r.left - pad, 6);
      var w = Math.min(r.width + pad * 2, window.innerWidth - left - 6), hgt = Math.min(r.height + pad * 2, window.innerHeight - top - 6);
      foco.style.top = top + 'px'; foco.style.left = left + 'px'; foco.style.width = w + 'px'; foco.style.height = hgt + 'px';
      var pw = pop.offsetWidth, ph = pop.offsetHeight, gap = 14, vw = window.innerWidth, vh = window.innerHeight;
      var py, px;
      var meioY = Math.max(16, Math.min(vh - ph - 16, top + Math.min(hgt, vh) / 2 - ph / 2));
      if (top + hgt + gap + ph <= vh) { py = top + hgt + gap; px = left; }                 // abaixo
      else if (top - gap - ph >= 0) { py = top - gap - ph; px = left; }                    // acima
      else if (left + w + gap + pw <= vw - 8) { py = meioY; px = left + w + gap; }         // à direita
      else if (left - gap - pw >= 8) { py = meioY; px = left - gap - pw; }                 // à esquerda
      else { py = Math.max(16, vh - ph - 16); px = left + 16; }                            // sobreposto, no rodapé do bloco
      px = Math.max(16, Math.min(vw - pw - 16, px));
      pop.style.top = py + 'px'; pop.style.left = px + 'px';
    }

    function mostrar(i) {
      tour.i = i;
      var el = tour.passos[i], n = tour.passos.length, ultimo = i === n - 1;
      var pontos = ''; for (var k = 0; k < n; k++) pontos += '<i class="' + (k === i ? 'on' : '') + '"></i>';
      pop.classList.remove('on');
      pop.innerHTML = '<div class="passo">' + (i + 1) + ' de ' + n + '</div>'
        + '<h4 id="tourTitulo"></h4><p></p>'
        + '<div class="bts"><div class="pontos" aria-hidden="true">' + pontos + '</div>'
        + (ultimo ? '' : '<button type="button" class="pular">Pular</button>')
        + (i > 0 ? '<button type="button" class="btn voltar">Voltar</button>' : '')
        + '<button type="button" class="btn pri prox">' + (ultimo ? 'Concluir' : 'Próximo') + '</button></div>';
      pop.querySelector('h4').textContent = el.getAttribute('data-tour-titulo');
      pop.querySelector('p').textContent = el.getAttribute('data-tour-texto');
      pop.querySelector('.prox').onclick = function () { ultimo ? fechar() : mostrar(i + 1); };
      var v = pop.querySelector('.voltar'); if (v) v.onclick = function () { mostrar(i - 1); };
      var pl = pop.querySelector('.pular'); if (pl) pl.onclick = fechar;
      el.scrollIntoView({block: 'center', behavior: reduzir ? 'auto' : 'smooth'});
      setTimeout(function () { posicionar(); pop.classList.add('on'); pop.querySelector('.prox').focus({preventScroll: true}); }, reduzir ? 0 : 380);
    }

    function tecla(e) {
      if (e.key === 'Escape') { e.preventDefault(); fechar(); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); if (tour.i < tour.passos.length - 1) mostrar(tour.i + 1); else fechar(); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); if (tour.i > 0) mostrar(tour.i - 1); }
      else if (e.key === 'Tab') {                                       // mantém o foco dentro do balão
        var bts = pop.querySelectorAll('button'); if (!bts.length) return;
        var pri = bts[0], ult = bts[bts.length - 1];
        if (e.shiftKey && document.activeElement === pri) { e.preventDefault(); ult.focus(); }
        else if (!e.shiftKey && document.activeElement === ult) { e.preventDefault(); pri.focus(); }
      }
    }
    function fechar() {
      document.removeEventListener('keydown', tecla, true);
      window.removeEventListener('resize', posicionar); window.removeEventListener('scroll', posicionar, true);
      [bloqueio, foco, pop].forEach(function (x) { x.parentNode && x.parentNode.removeChild(x); });
      tour = null;
      guarda.gravar('auditoria_tour_' + PAGINA, '1');
      if (antesFoco && antesFoco.focus) antesFoco.focus({preventScroll: true});
    }
    document.addEventListener('keydown', tecla, true);
    window.addEventListener('resize', posicionar); window.addEventListener('scroll', posicionar, true);
    bloqueio.addEventListener('click', fechar);
    mostrar(0);
  }

  var btTour = document.getElementById('btTour');
  if (btTour) btTour.addEventListener('click', abrirTour);
  // primeira visita a cada tela: abre o tour sozinho, depois que a página termina de entrar
  if (!guarda.ler('auditoria_tour_' + PAGINA) && !location.hash) {
    setTimeout(abrirTour, reduzir ? 200 : 1300);
  }

  // ---------------------------------------------------------- modo apresentação
  var btAp = document.getElementById('btApresentar');
  function alternarApresentacao(ligar) {
    var on = typeof ligar === 'boolean' ? ligar : !document.body.classList.contains('apresentar');
    document.body.classList.toggle('apresentar', on);
    if (btAp) btAp.lastChild.nodeValue = on ? 'Sair da apresentação' : 'Apresentar';
    var raiz = document.documentElement;
    if (on && raiz.requestFullscreen && !document.fullscreenElement) raiz.requestFullscreen().catch(function () {});
    if (!on && document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
    window.dispatchEvent(new Event('resize'));                       // gráficos se reajustam à nova largura
  }
  if (btAp) btAp.addEventListener('click', function () { alternarApresentacao(); });
  document.addEventListener('fullscreenchange', function () {
    if (!document.fullscreenElement && document.body.classList.contains('apresentar')) alternarApresentacao(false);
  });

  // ---------------------------------------------------------- imprimir / PDF
  var btImp = document.getElementById('btImprimir');
  if (btImp) btImp.addEventListener('click', function () { window.print(); });
  // gráficos se redesenham na largura do papel e voltam depois
  var redimensionar = function () { if (window.Chart) Object.keys(Chart.instances).forEach(function (k) { Chart.instances[k].resize(); }); };
  window.addEventListener('beforeprint', redimensionar);
  window.addEventListener('afterprint', redimensionar);

  // ---------------------------------------------------------- atalhos
  document.addEventListener('keydown', function (e) {
    if (tour || e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target && e.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT') return;
    if (e.key === 't' || e.key === 'T') { e.preventDefault(); abrirTour(); }
    else if ((e.key === 'a' || e.key === 'A') && btAp) { e.preventDefault(); alternarApresentacao(); }
    else if (e.key === 'Escape' && document.body.classList.contains('apresentar')) alternarApresentacao(false);
  });
})();
</script>
</body>
</html>
