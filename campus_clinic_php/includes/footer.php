<?php if (isset($_SESSION["user"])): ?>
<footer class="app-footer">
    <strong>Campus Clinic Medicine Inventory</strong> · Iloilo Science and Technology University
</footer>
</main></div>
<?php endif; ?>
<?php include __DIR__ . '/chatbot.php'; ?>
<script src="assets/staff-ui.js?v=<?= (int)filemtime(__DIR__ . '/../assets/staff-ui.js') ?>" defer></script>
</body>
</html>