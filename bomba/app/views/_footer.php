<?php
/**
 * Variable esperada en scope: $jsFile (nombre del archivo en assets/js/ especifico de la pagina)
 */
?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="assets/js/bomba-common.js?v=<?= (int) (@filemtime(__DIR__ . '/../../assets/js/bomba-common.js') ?: time()) ?>"></script>
<?php if (!empty($jsFile)): ?>
<script src="assets/js/<?= htmlspecialchars($jsFile) ?>?v=<?= (int) (@filemtime(__DIR__ . '/../../assets/js/' . $jsFile) ?: time()) ?>"></script>
<?php endif; ?>
</body>
</html>
