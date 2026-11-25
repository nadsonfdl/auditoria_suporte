
</main>
</div>
<script>
// Datas livres: ao mudar início/fim, aplica o período "custom"
(function () {
  var f = document.getElementById('filtros'); if (!f) return;
  f.querySelectorAll('input[type=date]').forEach(function (el) {
    el.addEventListener('change', function () {
      f.querySelectorAll('input[name=periodo]').forEach(function (p) { p.disabled = true; });
      var c = document.getElementById('perCustom'); if (c) c.disabled = false;
      f.submit();
    });
  });
})();
</script>
</body>
</html>
