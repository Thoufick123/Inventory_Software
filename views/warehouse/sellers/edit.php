<?php
// Legacy edit view removed. Redirecting to modern 2-phase inward wizard (Image 2).
$sellerId = !empty($seller['id']) ? (int)$seller['id'] : (!empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0);
$redirectUrl = url('warehouse/sellers/create' . ($sellerId > 0 ? '?seller_id=' . $sellerId . '&mode=edit' : ''));
if (!headers_sent()) {
    header('Location: ' . $redirectUrl);
    exit;
}
?>
<script>
window.location.href = <?= json_encode($redirectUrl) ?>;
</script>