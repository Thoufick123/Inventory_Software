<?php
/**
 * Permission Middleware
 * Checks if user has required module + action permission.
 */

class PermissionMiddleware {
    /**
     * Handle the permission check.
     * 
     * @param string $module Module name (e.g., 'production', 'warehouse')
     * @param string $action Action name (e.g., 'view', 'create', 'edit', 'delete', 'approve', 'print', 'export')
     */
    public function handle(string $module, string $action): void {
        if (!hasPermission($module, $action)) {
            // Allow access to seller edit mode if user has edit permission
            if ($module === 'wh_sellers' && $action === 'create') {
                $isEditReq = (!empty($_GET['mode']) && $_GET['mode'] === 'edit')
                    || !empty($_GET['seller_id'])
                    || (!empty($_POST['edit_supplier_mode']) && $_POST['edit_supplier_mode'] == '1')
                    || !empty($_POST['existing_supplier_id']);
                if ($isEditReq && hasPermission('wh_sellers', 'edit')) {
                    return;
                }
            }

            // Allow access to produce/adjust QR codes if user has create permission
            if ($module === 'wh_labels' && $action === 'edit' && hasPermission('wh_labels', 'create')) {
                return;
            }

            $readableModule = ucwords(str_replace('_', ' ', $module));
            $readableAction = strtolower($action);

            // For AJAX requests, return JSON error
            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'error'   => "Access restricted: You do not have permission to {$readableAction} in {$readableModule}."
                ], 403);
            }
            
            Response::error(403, "Your assigned role does not have permission to {$readableAction} in {$readableModule}.");
        }
    }

    /**
     * Check if request is AJAX.
     */
    private function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
