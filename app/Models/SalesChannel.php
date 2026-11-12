<?php

class SalesChannel {

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        if (!Database::fetchOne("SHOW COLUMNS FROM sales_channels LIKE 'logo_url'")) {
            Database::execute("ALTER TABLE sales_channels ADD COLUMN logo_url VARCHAR(500) NULL AFTER description");
        }
        if (!Database::fetchOne("SHOW COLUMNS FROM sales_channels LIKE 'address'")) {
            Database::execute("ALTER TABLE sales_channels ADD COLUMN address TEXT NULL AFTER phone");
        }
        if (!Database::fetchOne("SHOW COLUMNS FROM sales LIKE 'channel_id'")) {
            Database::execute("ALTER TABLE sales ADD COLUMN channel_id INT(10) UNSIGNED NULL AFTER shop_id");
        }

        self::$schemaEnsured = true;
    }

    public static function find(int $id): ?array {
        self::ensureSchema();
        return $id > 0
            ? Database::fetchOne("SELECT * FROM sales_channels WHERE id = ?", [$id])
            : null;
    }

    public static function getForType(string $type): array {
        self::ensureSchema();
        $type = strtoupper(trim($type));
        if (!in_array($type, ['WHATSAPP', 'WEBSITE'], true)) {
            throw new InvalidArgumentException('Unsupported sales channel type.');
        }

        $userId = (int)(Session::get('user_id') ?? 0);
        $matches = self::typeConditions($type, 'sc');
        if ($userId > 0) {
            $assigned = Database::fetchOne(
                "SELECT sc.*
                 FROM users u
                 JOIN sales_channels sc ON sc.id = u.channel_id
                 WHERE u.id = ? AND sc.is_active = 1 AND ({$matches['sql']})
                 LIMIT 1",
                array_merge([$userId], $matches['params'])
            );
            if ($assigned) {
                return $assigned;
            }
        }

        $order = $type === 'WHATSAPP' ? 'DESC' : 'ASC';
        $channel = Database::fetchOne(
            "SELECT sc.*
             FROM sales_channels sc
             WHERE sc.is_active = 1 AND ({$matches['sql']})
             ORDER BY sc.id {$order}
             LIMIT 1",
            $matches['params']
        );
        if ($channel) {
            return $channel;
        }

        return [
            'id' => null,
            'name' => $type === 'WHATSAPP' ? 'MARAKATHAI WHATSAPP' : 'MARAKATHAI',
            'code' => $type === 'WHATSAPP' ? 'WA-01' : 'WE-01',
            'channel_type' => $type,
            'logo_url' => null,
            'address' => null,
            'phone' => null,
            'gst_number' => null,
        ];
    }

    private static function typeConditions(string $type, string $alias): array {
        $legacyCode = $type === 'WHATSAPP' ? 'online 1' : 'online 2';
        $defaultCode = $type === 'WHATSAPP' ? 'wa-01' : 'we-01';
        $needle = '%' . strtolower($type) . '%';
        return [
            'sql' => "{$alias}.channel_type = ?
                      OR LOWER({$alias}.name) LIKE ?
                      OR LOWER({$alias}.code) LIKE ?
                      OR LOWER({$alias}.code) IN (?, ?)",
            'params' => [$type, $needle, $needle, $legacyCode, $defaultCode],
        ];
    }
}
