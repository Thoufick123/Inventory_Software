<?php
/**
 * Production Controller
 * Handles all production submodules: Dashboard, Sellers, Catalog, Programs,
 * Cutting, Stitching, Labels, QC, Packing Batches, Outward, and Reports.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/app/Services/ProductionService.php';
require_once BASE_PATH . '/app/Services/AuditService.php';
require_once BASE_PATH . '/app/Services/CloudinaryService.php';
require_once BASE_PATH . '/app/Services/SettingsService.php';
require_once BASE_PATH . '/app/Models/QCBatch.php';
require_once BASE_PATH . '/app/Models/QCItem.php';
require_once BASE_PATH . '/app/Models/LabelBatch.php';

class ProductionController {

    private static bool $schemaEnsured = false;
    private static bool $programRollAttributesSchemaEnsured = false;

    /**
     * Ensure database table schema has required columns like hsn_code.
     */
    public static function ensureDatabaseSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;
        try {
            $col1 = Database::fetchOne("SHOW COLUMNS FROM production_purchase_items LIKE 'hsn_code'");
            if (!$col1) {
                Database::execute("ALTER TABLE production_purchase_items ADD COLUMN hsn_code VARCHAR(20) DEFAULT NULL AFTER seller_roll_id");
            }
        } catch (\Throwable $e) {}
        try {
            $col2 = Database::fetchOne("SHOW COLUMNS FROM seller_rolls LIKE 'hsn_code'");
            if (!$col2) {
                Database::execute("ALTER TABLE seller_rolls ADD COLUMN hsn_code VARCHAR(20) DEFAULT NULL AFTER qr_code");
            }
        } catch (\Throwable $e) {}
        try {
            $col3 = Database::fetchOne("SHOW COLUMNS FROM sellers LIKE 'production_deleted_at'");
            if (!$col3) {
                Database::execute("ALTER TABLE sellers ADD COLUMN production_deleted_at DATETIME DEFAULT NULL AFTER is_active");
            }
        } catch (\Throwable $e) {
            error_log('Unable to ensure production supplier archive column: ' . $e->getMessage());
        }
        try {
            $colInwItems = Database::fetchOne("SHOW COLUMNS FROM production_purchase_items LIKE 'is_inwarded'");
            if (!$colInwItems) {
                Database::execute("ALTER TABLE production_purchase_items ADD COLUMN is_inwarded TINYINT(1) DEFAULT 0, ADD COLUMN inwarded_at DATETIME DEFAULT NULL, ADD COLUMN inwarded_by INT UNSIGNED DEFAULT NULL, ADD COLUMN product_id INT UNSIGNED DEFAULT NULL, ADD COLUMN variant_id INT UNSIGNED DEFAULT NULL");
            }
        } catch (\Throwable $e) {}
        try {
            $colInwPurchases = Database::fetchOne("SHOW COLUMNS FROM production_purchases LIKE 'is_inwarded'");
            if (!$colInwPurchases) {
                Database::execute("ALTER TABLE production_purchases ADD COLUMN is_inwarded TINYINT(1) DEFAULT 0, ADD COLUMN inwarded_at DATETIME DEFAULT NULL");
            }
        } catch (\Throwable $e) {}

        $statusColumn = Database::fetchOne("SHOW COLUMNS FROM roll_damages LIKE 'status'");
        if (!$statusColumn) {
            throw new RuntimeException('The damage status column could not be found.');
        }
        $statusType = strtoupper((string)($statusColumn['Type'] ?? ''));
        $requiredStatuses = ['RETURN_INITIATED', 'RETURNED_TO_SELLER', 'REFUND_RECEIVED'];
        $needsStatusMigration = false;
        foreach ($requiredStatuses as $requiredStatus) {
            if (strpos($statusType, "'" . $requiredStatus . "'") === false) {
                $needsStatusMigration = true;
                break;
            }
        }
        if ($needsStatusMigration) {
            Database::execute("UPDATE roll_damages SET status = 'LOGGED' WHERE status = ''");
            Database::execute(
                "ALTER TABLE roll_damages
                 MODIFY COLUMN status ENUM(
                    'LOGGED', 'INVESTIGATING', 'CLAIMED', 'SETTLED', 'REJECTED',
                    'CLOSED', 'CANCELLED', 'REPLACEMENT_RECEIVED',
                    'RETURN_INITIATED', 'RETURNED_TO_SELLER', 'REFUND_RECEIVED'
                 ) NOT NULL DEFAULT 'LOGGED'"
            );
        }
    }

    private static function ensureProgramRollAttributesSchema(): void {
        if (self::$programRollAttributesSchemaEnsured) return;

        $column = Database::fetchOne("SHOW COLUMNS FROM production_program_rolls LIKE 'attributes'");
        if (!$column) {
            Database::execute(
                "ALTER TABLE production_program_rolls
                 ADD COLUMN attributes TEXT DEFAULT NULL AFTER allocated_qr_codes"
            );
        }

        self::$programRollAttributesSchemaEnsured = true;
    }

    /**
     * Production Dashboard.
     */
    public function dashboard(): void {
        self::ensureDatabaseSchema();
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $stats = ProductionService::getDashboardStats($scopedUnitId);
        Response::view('production.dashboard', array_merge($stats, [
            'pageTitle'    => 'Production Dashboard',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-dashboard',
            'scopedUnitId' => $scopedUnitId,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Dashboard'],
            ],
        ]));
    }


    /**
     * Export Comprehensive Production Reports (CSV / Print).
     */
    public function exportReports(): void {
        require_once BASE_PATH . '/app/Services/ProductionReportService.php';

        $periodType = trim((string)($_REQUEST['period_type'] ?? 'MONTH'));
        $format = strtoupper(trim((string)($_REQUEST['format'] ?? 'CSV')));

        if ($periodType === 'MONTH') {
            $month = (int)($_REQUEST['selected_month'] ?? date('n'));
            $year = (int)($_REQUEST['selected_year'] ?? date('Y'));
            $month = max(1, min(12, $month));
            $year = max(2020, min(2035, $year));
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $periodLabel = date('F Y', strtotime($startDate));
        } else {
            $startDate = trim((string)($_REQUEST['date_from'] ?? date('Y-m-01')));
            $endDate = trim((string)($_REQUEST['date_to'] ?? date('Y-m-d')));
            $periodLabel = date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));
        }

        $allModules = ['purchases', 'rolls', 'programs', 'cuttings', 'stitching', 'qc', 'batches', 'outward', 'billing', 'returns', 'stock_requests', 'wastage', 'expenses'];
        $selectedModules = !empty($_REQUEST['modules']) && is_array($_REQUEST['modules'])
            ? array_values(array_intersect($_REQUEST['modules'], $allModules))
            : $allModules;

        if (empty($selectedModules)) {
            $selectedModules = $allModules;
        }

        $activeFacility = ProductionUnit::getActiveFacility();
        $data = \App\Services\ProductionReportService::getReportData($startDate, $endDate, $selectedModules);

        if ($format === 'PRINT') {
            Response::viewOnly('production.reports.print', [
                'periodLabel'     => $periodLabel,
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'selectedModules' => $selectedModules,
                'data'            => $data,
                'prodUnit'        => $activeFacility,
                'sellerCompany'   => SettingsService::getGroup('company'),
            ]);
            return;
        }

        \App\Services\ProductionReportService::streamCsv($periodLabel, $startDate, $endDate, $data, $activeFacility);
    }

    // =========================================================================
    // SELLERS (Material Suppliers)
    // =========================================================================

    /**
     * Validate consistency between city/district and state.
     * Prevents mismatched entry where a state is entered as city, a city is entered as state,
     * or a known city from one state is marked under another state.
     */
    private function validateCityAndState(string $city, string $state): ?string {
        $c = strtoupper(trim($city));
        $s = strtoupper(trim($state));
        $cNorm = preg_replace('/[\s\-_.]+/', '', $c);
        $sNorm = preg_replace('/[\s\-_.]+/', '', $s);

        if ($cNorm === '' || $sNorm === '') {
            return 'City/District and State are required fields.';
        }

        // All Indian States and Union Territories with normalized abbreviations
        $allIndianStates = [
            'TAMIL NADU' => ['TAMILNADU', 'TN'],
            'ANDHRA PRADESH' => ['ANDHRAPRADESH', 'AP'],
            'ARUNACHAL PRADESH' => ['ARUNACHALPRADESH', 'AR'],
            'ASSAM' => ['ASSAM', 'AS'],
            'BIHAR' => ['BIHAR', 'BR'],
            'CHHATTISGARH' => ['CHHATTISGARH', 'CG'],
            'GOA' => ['GOA', 'GA'],
            'GUJARAT' => ['GUJARAT', 'GJ'],
            'HARYANA' => ['HARYANA', 'HR'],
            'HIMACHAL PRADESH' => ['HIMACHALPRADESH', 'HP'],
            'JHARKHAND' => ['JHARKHAND', 'JH'],
            'KARNATAKA' => ['KARNATAKA', 'KA'],
            'KERALA' => ['KERALA', 'KL'],
            'MADHYA PRADESH' => ['MADHYAPRADESH', 'MP'],
            'MAHARASHTRA' => ['MAHARASHTRA', 'MH'],
            'MANIPUR' => ['MANIPUR', 'MN'],
            'MEGHALAYA' => ['MEGHALAYA', 'ML'],
            'MIZORAM' => ['MIZORAM', 'MZ'],
            'NAGALAND' => ['NAGALAND', 'NL'],
            'ODISHA' => ['ODISHA', 'ORISSA', 'OD', 'OR'],
            'PUNJAB' => ['PUNJAB', 'PB'],
            'RAJASTHAN' => ['RAJASTHAN', 'RJ'],
            'SIKKIM' => ['SIKKIM', 'SK'],
            'TELANGANA' => ['TELANGANA', 'TS', 'TG'],
            'TRIPURA' => ['TRIPURA', 'TR'],
            'UTTAR PRADESH' => ['UTTARPRADESH', 'UP'],
            'UTTARAKHAND' => ['UTTARAKHAND', 'UK', 'UA'],
            'WEST BENGAL' => ['WESTBENGAL', 'WB'],
            'DELHI' => ['DELHI', 'DL', 'NEWDELHI'],
            'JAMMU AND KASHMIR' => ['JAMMUANDKASHMIR', 'JAMMUKASHMIR', 'JK'],
            'LADAKH' => ['LADAKH', 'LA'],
            'PUDUCHERRY' => ['PUDUCHERRY', 'PONDICHERRY', 'PY'],
            'CHANDIGARH' => ['CHANDIGARH', 'CH'],
            'DADRA AND NAGAR HAVELI AND DAMAN AND DIU' => ['DADRAANDNAGARHAVELIANDDAMANANDDIU', 'DAMAN', 'DIU', 'DNH'],
            'ANDAMAN AND NICOBAR ISLANDS' => ['ANDAMANANDNICOBARISLANDS', 'ANDAMAN', 'AN'],
            'LAKSHADWEEP' => ['LAKSHADWEEP', 'LD']
        ];

        // Comprehensive list of Tamil Nadu districts and prominent industrial towns
        $tnCities = [
            'ARIYALUR', 'CHENGALPATTU', 'CHENGALPET', 'CHENNAI', 'MADRAS', 'COIMBATORE', 'KOVAI',
            'CUDDALORE', 'DHARMAPURI', 'DINDIGUL', 'ERODE', 'KALLAKURICHI', 'KANCHIPURAM',
            'KANCHEEPURAM', 'KANYAKUMARI', 'NAGERCOIL', 'KARUR', 'KRISHNAGIRI', 'MADURAI',
            'MAYILADUTHURAI', 'NAGAPATTINAM', 'NAMAKKAL', 'NILGIRIS', 'UDHAGAMANDALAM', 'OOTY',
            'COONOOR', 'PERAMBALUR', 'PUDUKKOTTAI', 'RAMANATHAPURAM', 'RAMNAD', 'RANIPET',
            'SALEM', 'SIVAGANGA', 'SIVAGANGAI', 'TENKASI', 'THANJAVUR', 'TANJORE', 'THENI',
            'THOOTHUKUDI', 'TUTICORIN', 'TIRUCHIRAPPALLI', 'TIRUCHIRAPALLI', 'TRICHY', 'TIRUCHI',
            'TIRUNELVELI', 'NELLAI', 'TIRUPATHUR', 'TIRUPATTUR', 'TIRUPPUR', 'TIRUPUR',
            'TIRUVALLUR', 'THIRUVALLUR', 'TIRUVANNAMALAI', 'THIRUVANNAMALAI', 'TIRUVARUR',
            'THIRUVARUR', 'VELLORE', 'VILUPPURAM', 'VILLUPURAM', 'VIRUDHUNAGAR', 'POLLACHI',
            'METTUPALAYAM', 'AVINASHI', 'PALLADAM', 'KANGEYAM', 'KANGAYAM', 'DHARAPURAM',
            'UDUMALAIPETTAI', 'UDUMALPET', 'GOBICHETTIPALAYAM', 'GOBI', 'SATHYAMANGALAM', 'SATHY',
            'BHAVANI', 'KOMARAPALAYAM', 'KUMARAPALAYAM', 'TIRUCHENGODE', 'RASIPURAM', 'AMBUR',
            'VANIYAMBADI', 'GUDIYATHAM', 'ARAKKONAM', 'HOSUR', 'RAJAPALAYAM', 'SIVAKASI',
            'SRIVILLIPUTHUR', 'ARUPPUKKOTTAI', 'KOVILPATTI', 'KUMBAKONAM', 'PATTUKKOTTAI',
            'MANNARGUDI', 'SIRKAZHI', 'CHIDAMBARAM', 'NEYVELI', 'PANRUTI', 'TINDIVANAM',
            'ARANI', 'CHEYYAR', 'TAMBARAM', 'AVADI', 'AMBATTUR', 'SRIPERUMBUDUR', 'KARAIKUDI',
            'DEVAKOTTAI', 'PARAMAKUDI', 'SANKARANKOVIL', 'AMBASAMUDRAM', 'BODINAYAKANUR',
            'CUMBUM', 'PERIYAKULAM'
        ];

        // Known prominent cities in other states
        $otherStateCities = [
            'BENGALURU' => 'KARNATAKA', 'BANGALORE' => 'KARNATAKA', 'MYSORE' => 'KARNATAKA', 'MYSURU' => 'KARNATAKA',
            'MANGALORE' => 'KARNATAKA', 'MANGALURU' => 'KARNATAKA', 'HUBLI' => 'KARNATAKA', 'HUBBALLI' => 'KARNATAKA',
            'BELGAUM' => 'KARNATAKA', 'BELAGAVI' => 'KARNATAKA', 'DAVANAGERE' => 'KARNATAKA', 'BELLARY' => 'KARNATAKA',
            'SHIMOGA' => 'KARNATAKA', 'SHIVAMOGGA' => 'KARNATAKA', 'TUMKUR' => 'KARNATAKA', 'UDUPI' => 'KARNATAKA',
            'HASSAN' => 'KARNATAKA', 'GULBARGA' => 'KARNATAKA',
            'KOCHI' => 'KERALA', 'COCHIN' => 'KERALA', 'THIRUVANANTHAPURAM' => 'KERALA', 'TRIVANDRUM' => 'KERALA',
            'KOZHIKODE' => 'KERALA', 'CALICUT' => 'KERALA', 'THRISSUR' => 'KERALA', 'TRICHUR' => 'KERALA',
            'KOLLAM' => 'KERALA', 'PALAKKAD' => 'KERALA', 'ALAPPUZHA' => 'KERALA', 'KANNUR' => 'KERALA',
            'KOTTAYAM' => 'KERALA', 'MALAPPURAM' => 'KERALA', 'KASARAGOD' => 'KERALA',
            'HYDERABAD' => 'TELANGANA', 'SECUNDERABAD' => 'TELANGANA', 'WARANGAL' => 'TELANGANA', 'NIZAMABAD' => 'TELANGANA',
            'VISAKHAPATNAM' => 'ANDHRA PRADESH', 'VIZAG' => 'ANDHRA PRADESH', 'VIJAYAWADA' => 'ANDHRA PRADESH',
            'GUNTUR' => 'ANDHRA PRADESH', 'NELLORE' => 'ANDHRA PRADESH', 'KURNOOL' => 'ANDHRA PRADESH',
            'RAJAHMUNDRY' => 'ANDHRA PRADESH', 'TIRUPATI' => 'ANDHRA PRADESH', 'KAKINADA' => 'ANDHRA PRADESH',
            'MUMBAI' => 'MAHARASHTRA', 'BOMBAY' => 'MAHARASHTRA', 'PUNE' => 'MAHARASHTRA', 'NAGPUR' => 'MAHARASHTRA',
            'THANE' => 'MAHARASHTRA', 'NASHIK' => 'MAHARASHTRA', 'AURANGABAD' => 'MAHARASHTRA', 'KOLHAPUR' => 'MAHARASHTRA',
            'AHMEDABAD' => 'GUJARAT', 'SURAT' => 'GUJARAT', 'VADODARA' => 'GUJARAT', 'BARODA' => 'GUJARAT',
            'RAJKOT' => 'GUJARAT', 'BHAVNAGAR' => 'GUJARAT', 'JAMNAGAR' => 'GUJARAT', 'GANDHINAGAR' => 'GUJARAT',
            'DELHI' => 'DELHI', 'NEW DELHI' => 'DELHI', 'NOIDA' => 'UTTAR PRADESH', 'GURGAON' => 'HARYANA', 'GURUGRAM' => 'HARYANA',
            'LUCKNOW' => 'UTTAR PRADESH', 'KANPUR' => 'UTTAR PRADESH', 'AGRA' => 'UTTAR PRADESH', 'VARANASI' => 'UTTAR PRADESH',
            'KOLKATA' => 'WEST BENGAL', 'CALCUTTA' => 'WEST BENGAL', 'HOWRAH' => 'WEST BENGAL',
            'JAIPUR' => 'RAJASTHAN', 'JODHPUR' => 'RAJASTHAN', 'UDAIPUR' => 'RAJASTHAN', 'KOTA' => 'RAJASTHAN',
            'INDORE' => 'MADHYA PRADESH', 'BHOPAL' => 'MADHYA PRADESH', 'GWALIOR' => 'MADHYA PRADESH',
            'PATNA' => 'BIHAR', 'LUDHIANA' => 'PUNJAB', 'AMRITSAR' => 'PUNJAB', 'CHANDIGARH' => 'CHANDIGARH'
        ];

        // 1. Check if City/District is mistakenly entered as an Indian State
        foreach ($allIndianStates as $stateName => $aliases) {
            $stNorm = preg_replace('/[\s\-_.]+/', '', $stateName);
            if ($cNorm === $stNorm || in_array($cNorm, $aliases, true)) {
                return "Invalid City/District: '{$city}' is an Indian State name, not a city or district. Please enter a valid district (e.g. COIMBATORE, TIRUPPUR, ERODE, CHENNAI).";
            }
        }

        // 2. Check if State is mistakenly entered as a known City/District
        foreach ($tnCities as $tnCity) {
            $tnNorm = preg_replace('/[\s\-_.]+/', '', $tnCity);
            if ($sNorm === $tnNorm || $s === $tnCity) {
                return "Invalid State: '{$state}' is a city or district in Tamil Nadu, not a state. Please enter TAMIL NADU as the state.";
            }
        }
        foreach ($otherStateCities as $otherCity => $expectedState) {
            $otherNorm = preg_replace('/[\s\-_.]+/', '', $otherCity);
            if ($sNorm === $otherNorm || $s === $otherCity) {
                return "Invalid State: '{$state}' is a city in {$expectedState}, not a state. Please enter {$expectedState} as the state.";
            }
        }

        // 3. Check if State is a recognized Indian State or UT
        $matchedStateName = null;
        foreach ($allIndianStates as $stateName => $aliases) {
            $stNorm = preg_replace('/[\s\-_.]+/', '', $stateName);
            if ($sNorm === $stNorm || in_array($sNorm, $aliases, true)) {
                $matchedStateName = $stateName;
                break;
            }
        }
        if (!$matchedStateName) {
            return "Invalid State: '{$state}' is not a recognized Indian State or Union Territory. Please enter a valid State (e.g. TAMIL NADU, KARNATAKA, KERALA).";
        }

        // 4. Check City-State Location Consistency
        $isTnCity = false;
        foreach ($tnCities as $tnCity) {
            $tnNorm = preg_replace('/[\s\-_.]+/', '', $tnCity);
            if ($cNorm === $tnNorm || str_contains($cNorm, $tnNorm) || str_contains($tnNorm, $cNorm)) {
                $isTnCity = true;
                break;
            }
        }

        if ($isTnCity && $matchedStateName !== 'TAMIL NADU') {
            return "Location Mismatch: '{$city}' is located in Tamil Nadu, but the State is entered as '{$state}'. Please set the state to TAMIL NADU.";
        }

        foreach ($otherStateCities as $otherCity => $expectedState) {
            $otherNorm = preg_replace('/[\s\-_.]+/', '', $otherCity);
            if ($cNorm === $otherNorm || str_contains($cNorm, $otherNorm) || str_contains($otherNorm, $cNorm)) {
                $expNorm = preg_replace('/[\s\-_.]+/', '', $expectedState);
                if ($matchedStateName !== $expectedState && $sNorm !== $expNorm) {
                    return "Location Mismatch: '{$city}' is located in {$expectedState}, but the State is entered as '{$state}'. Please set the state to {$expectedState}.";
                }
                break;
            }
        }

        return null;
    }

    private function storeSupplierProfile(): void {
        self::ensureDatabaseSchema();
        $name = mb_strtoupper(trim((string)($_POST['name'] ?? '')));
        $company = mb_strtoupper(trim((string)($_POST['company'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $city = mb_strtoupper(trim((string)($_POST['city'] ?? '')));
        $state = mb_strtoupper(trim((string)($_POST['state'] ?? '')));
        $address = mb_strtoupper(trim((string)($_POST['address'] ?? '')));
        $gstNumber = mb_strtoupper(trim((string)($_POST['gst_number'] ?? '')));

        if ($name === '') {
            Session::flash('error', 'Supplier / Contact Person Name is required.');
            Response::back();
            return;
        }

        if ($company === '') {
            $company = $name;
        }

        $cleanPhone = preg_replace('/\D/', '', $phone);
        if ($cleanPhone !== '') {
            $phone = substr($cleanPhone, 0, 15);
            $dupPhone = Database::fetchOne(
                "SELECT id, name, company FROM sellers WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL",
                ['%' . substr($cleanPhone, -10)]
            );
            if ($dupPhone) {
                $dupName = !empty($dupPhone['company']) ? $dupPhone['company'] . ' (' . $dupPhone['name'] . ')' : $dupPhone['name'];
                Session::flash('error', "Phone number is already registered to material supplier '{$dupName}'. Duplicate phone is not allowed.");
                Response::back();
                return;
            }
        }

        if ($gstNumber !== '') {
            $dupGst = Database::fetchOne(
                "SELECT id, name, company FROM sellers WHERE UPPER(gst_number) = ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL",
                [$gstNumber]
            );
            if ($dupGst) {
                $dupName = !empty($dupGst['company']) ? $dupGst['company'] . ' (' . $dupGst['name'] . ')' : $dupGst['name'];
                Session::flash('error', "GSTIN is already registered to material supplier '{$dupName}'. Duplicate GSTIN is not allowed.");
                Response::back();
                return;
            }
        }

        $allIndianStateNames = ['TAMIL NADU', 'ANDHRA PRADESH', 'KARNATAKA', 'KERALA', 'MAHARASHTRA', 'GUJARAT', 'TELANGANA', 'DELHI', 'HARYANA', 'MADHYA PRADESH', 'PUNJAB', 'RAJASTHAN', 'UTTAR PRADESH', 'WEST BENGAL', 'ODISHA', 'BIHAR', 'ASSAM', 'GOA'];
        if (in_array($city, $allIndianStateNames, true) && !in_array($state, $allIndianStateNames, true) && $state !== '') {
            $tmp = $city;
            $city = $state;
            $state = $tmp;
        }

        if ($state === '') {
            $state = 'TAMIL NADU';
        }

        Database::execute(
            "INSERT INTO sellers (name, company, phone, email, gst_number, city, state, address, seller_type, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'MATERIAL_SUPPLIER', 1)",
            [$name, $company, $phone ?: null, $email !== '' ? $email : null, $gstNumber !== '' ? $gstNumber : null,
                $city ?: null, $state ?: null, $address ?: null]
        );
        $sellerId = (int)Database::lastInsertId();
        AuditService::log('production', 'CREATE', 'sellers', $sellerId, null, [
            'Supplier Name' => $name, 'Company / Mill' => $company, 'Phone' => $phone,
            'Email' => $email, 'City' => $city, 'State' => $state, 'Address' => $address,
        ]);
        Session::flash('success', 'Supplier details saved. Record fabric and other purchases in Production Purchases.');
        Response::redirect(url('production/sellers'));
    }

    private function updateSupplierProfile(int $sellerId): void {
        self::ensureDatabaseSchema();
        $existing = Database::fetchOne(
            "SELECT * FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL",
            [$sellerId]
        );
        if (!$existing) {
            Session::flash('error', 'Supplier not found.');
            Response::redirect(url('production/sellers'));
            return;
        }

        $name = mb_strtoupper(trim((string)($_POST['name'] ?? '')));
        $company = mb_strtoupper(trim((string)($_POST['company'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $city = mb_strtoupper(trim((string)($_POST['city'] ?? '')));
        $state = mb_strtoupper(trim((string)($_POST['state'] ?? '')));
        $address = mb_strtoupper(trim((string)($_POST['address'] ?? '')));
        $gstNumber = mb_strtoupper(trim((string)($_POST['gst_number'] ?? '')));

        if ($name === '') {
            Session::flash('error', 'Supplier / Contact Person Name is required.');
            Response::back();
            return;
        }

        if ($company === '') {
            $company = $name;
        }

        $cleanPhone = preg_replace('/\D/', '', $phone);
        if ($cleanPhone !== '') {
            $phone = substr($cleanPhone, 0, 15);
            $dupPhone = Database::fetchOne(
                "SELECT id, name, company FROM sellers WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL AND id != ?",
                ['%' . substr($cleanPhone, -10), $sellerId]
            );
            if ($dupPhone) {
                $dupName = !empty($dupPhone['company']) ? $dupPhone['company'] . ' (' . $dupPhone['name'] . ')' : $dupPhone['name'];
                Session::flash('error', "Phone number is already registered to material supplier '{$dupName}'. Duplicate phone is not allowed.");
                Response::back();
                return;
            }
        }

        if ($gstNumber !== '') {
            $dupGst = Database::fetchOne(
                "SELECT id, name, company FROM sellers WHERE UPPER(gst_number) = ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL AND id != ?",
                [$gstNumber, $sellerId]
            );
            if ($dupGst) {
                $dupName = !empty($dupGst['company']) ? $dupGst['company'] . ' (' . $dupGst['name'] . ')' : $dupGst['name'];
                Session::flash('error', "GSTIN is already registered to material supplier '{$dupName}'. Duplicate GSTIN is not allowed.");
                Response::back();
                return;
            }
        }

        $allIndianStateNames = ['TAMIL NADU', 'ANDHRA PRADESH', 'KARNATAKA', 'KERALA', 'MAHARASHTRA', 'GUJARAT', 'TELANGANA', 'DELHI', 'HARYANA', 'MADHYA PRADESH', 'PUNJAB', 'RAJASTHAN', 'UTTAR PRADESH', 'WEST BENGAL', 'ODISHA', 'BIHAR', 'ASSAM', 'GOA'];
        if (in_array($city, $allIndianStateNames, true) && !in_array($state, $allIndianStateNames, true) && $state !== '') {
            $tmp = $city;
            $city = $state;
            $state = $tmp;
        }

        if ($state === '') {
            $state = 'TAMIL NADU';
        }

        Database::execute(
            "UPDATE sellers SET name = ?, company = ?, phone = ?, email = ?, gst_number = ?, city = ?, state = ?,
                    address = ?, is_active = 1, updated_at = NOW()
             WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER'",
            [$name, $company, $phone ?: null, $email !== '' ? $email : null, $gstNumber !== '' ? $gstNumber : null,
                $city ?: null, $state ?: null, $address ?: null, $sellerId]
        );
        AuditService::log('production', 'UPDATE', 'sellers', $sellerId, $existing, [
            'name' => $name, 'company' => $company, 'phone' => $phone, 'email' => $email,
            'gst_number' => $gstNumber, 'city' => $city, 'state' => $state, 'address' => $address,
        ]);
        Session::flash('success', 'Supplier details updated successfully.');
        Response::redirect(url('production/sellers'));
    }

    public function sellerList(): void {
        self::ensureDatabaseSchema();
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $city = trim($_GET['city'] ?? '');

        $sql = "SELECT s.* FROM sellers s WHERE s.seller_type = 'MATERIAL_SUPPLIER' AND (s.is_active = 1 OR s.is_active IS NULL) AND s.production_deleted_at IS NULL";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (s.name LIKE ? OR s.company LIKE ? OR s.phone LIKE ? OR s.city LIKE ?
                OR s.state LIKE ? OR s.gst_number LIKE ? OR s.address LIKE ?)";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s]);
        }

        if ($city !== '') {
            $sql .= " AND s.city = ?";
            $params[] = $city;
        }

        $sql .= " ORDER BY s.created_at DESC";
        $sellers = Database::fetchAll($sql, $params);

        $cities = Database::fetchAll("SELECT DISTINCT city FROM sellers WHERE seller_type = 'MATERIAL_SUPPLIER' AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL AND city IS NOT NULL AND TRIM(city) != '' ORDER BY city ASC");

        Response::view('production.sellers.index', [
            'pageTitle'      => 'Production Suppliers',
            'activeModule'   => 'production',
            'activeMenu'     => 'prod-sellers',
            'sellers'        => $sellers,
            'search'         => $search,
            'status'         => $status,
            'city'           => $city,
            'cities'         => $cities,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Suppliers'],
            ],
        ]);
    }

    public function createSeller(): void {
        self::ensureDatabaseSchema();
        Response::view('production.sellers.profile_create', [
            'pageTitle'    => 'Add Material Supplier',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-sellers',
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Suppliers', 'url' => url('production/sellers')],
                ['label' => 'Add Supplier'],
            ],
        ]);
    }

    public function viewSeller(string $id): void {
        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER'", [(int)$id]);
        if (!$seller) {
            Response::error(404, 'Material Supplier not found.');
        }

        Response::view('production.sellers.profile_view', [
            'pageTitle'    => 'Supplier Details — ' . $seller['name'],
            'activeModule' => 'production',
            'activeMenu'   => 'prod-sellers',
            'seller'       => $seller,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Suppliers', 'url' => url('production/sellers')],
                ['label' => $seller['name']],
            ],
        ]);
    }

    public function storeSeller(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }
        $this->storeSupplierProfile();
    }

    public function editSeller(string $id): void {
        self::ensureDatabaseSchema();
        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL", [(int)$id]);
        if (!$seller) {
            Response::error(404, 'Supplier not found.');
        }

        Response::view('production.sellers.profile_edit', [
            'pageTitle'    => 'Edit Material Supplier',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-sellers',
            'seller'       => $seller,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Suppliers', 'url' => url('production/sellers')],
                ['label' => 'Edit Supplier'],
            ],
        ]);
    }

    public function updateSeller(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }
        $this->updateSupplierProfile((int)$id);
    }

    /**
     * List historical records retained when a production supplier is retired.
     */
    public static function getSupplierConnectedRecords(int $sellerId): array {
        $reasons = [];
        $counts  = [];

        // 1. Production Purchases
        try {
            $purchases = Database::fetchAll(
                "SELECT id, purchase_number, invoice_number FROM production_purchases WHERE seller_id = ?",
                [$sellerId]
            );
            $cnt = count($purchases);
            if ($cnt > 0) {
                $counts['production_purchases'] = $cnt;
                $sample = implode(', ', array_slice(array_column($purchases, 'purchase_number'), 0, 3));
                $reasons[] = "{$cnt} Production Purchase(s) (e.g. {$sample})";
            }
        } catch (\Throwable $e) {}

        // 2. Purchase Orders
        try {
            $pos = Database::fetchAll(
                "SELECT id, po_number FROM purchase_orders WHERE seller_id = ? OR warehouse_supplier_id = ?",
                [$sellerId, $sellerId]
            );
            $cnt = count($pos);
            if ($cnt > 0) {
                $counts['purchase_orders'] = $cnt;
                $sample = implode(', ', array_slice(array_column($pos, 'po_number'), 0, 3));
                $reasons[] = "{$cnt} Purchase Order(s) (e.g. {$sample})";
            }
        } catch (\Throwable $e) {}

        // 3. Products in Catalog
        try {
            $products = Database::fetchAll(
                "SELECT id, name FROM products WHERE seller_id = ? OR warehouse_supplier_id = ?",
                [$sellerId, $sellerId]
            );
            $cnt = count($products);
            if ($cnt > 0) {
                $counts['products'] = $cnt;
                $sample = implode(', ', array_slice(array_column($products, 'name'), 0, 3));
                $reasons[] = "{$cnt} Catalog Product(s) (e.g. {$sample})";
            }
        } catch (\Throwable $e) {}

        // 4. Fabric / Material Rolls (seller_rolls)
        try {
            $rolls = Database::fetchAll(
                "SELECT id, roll_number, color FROM seller_rolls WHERE seller_id = ?",
                [$sellerId]
            );
            $cnt = count($rolls);
            if ($cnt > 0) {
                $counts['seller_rolls'] = $cnt;
                $sample = implode(', ', array_slice(array_column($rolls, 'roll_number'), 0, 3));
                $reasons[] = "{$cnt} Fabric/Material Roll(s) (e.g. Roll #{$sample})";
            }
        } catch (\Throwable $e) {}

        // 5. Production Programs (direct or via rolls)
        try {
            $programs = Database::fetchAll(
                "SELECT DISTINCT id, program_number FROM production_programs WHERE seller_id = ?",
                [$sellerId]
            );
            if (empty($programs)) {
                $programs = Database::fetchAll(
                    "SELECT DISTINCT pp.id, pp.program_number FROM production_program_rolls ppr JOIN production_programs pp ON pp.id = ppr.program_id WHERE ppr.seller_id = ?",
                    [$sellerId]
                );
            }
            $cnt = count($programs);
            if ($cnt > 0) {
                $counts['production_programs'] = $cnt;
                $sample = implode(', ', array_slice(array_column($programs, 'program_number'), 0, 3));
                $reasons[] = "{$cnt} Production Program(s) (e.g. {$sample})";
            }
        } catch (\Throwable $e) {}

        // 6. Cutting Batches
        try {
            $cuttings = Database::fetchAll(
                "SELECT DISTINCT cb.id, cb.batch_number FROM cutting_batch_rolls cbr JOIN cutting_batches cb ON cb.id = cbr.cutting_batch_id WHERE cbr.seller_id = ?",
                [$sellerId]
            );
            $cnt = count($cuttings);
            if ($cnt > 0) {
                $counts['cutting_batches'] = $cnt;
                $sample = implode(', ', array_slice(array_column($cuttings, 'batch_number'), 0, 3));
                $reasons[] = "{$cnt} Cutting Batch(es) (e.g. {$sample})";
            }
        } catch (\Throwable $e) {}

        // 7. Roll Damages
        try {
            $damages = Database::fetchAll(
                "SELECT id FROM roll_damages WHERE seller_id = ?",
                [$sellerId]
            );
            $cnt = count($damages);
            if ($cnt > 0) {
                $counts['roll_damages'] = $cnt;
                $reasons[] = "{$cnt} Roll Damage Record(s)";
            }
        } catch (\Throwable $e) {}

        $total = array_sum($counts);
        return [
            'can_delete' => true,
            'reasons'    => $reasons,
            'counts'     => $counts,
            'total'      => $total,
        ];
    }

    /**
     * Dedicated delete confirmation page for a supplier.
     */
    public function confirmDeleteSeller(string $id): void {
        self::ensureDatabaseSchema();
        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL", [(int)$id]);
        if (!$seller) {
            Session::flash('error', 'Supplier not found.');
            Response::redirect(url('production/sellers'));
            return;
        }

        $connections = self::getSupplierConnectedRecords((int)$id);

        Response::view('production.sellers.delete_confirm', [
            'pageTitle'    => 'Confirm Supplier Deletion — ' . $seller['name'],
            'activeModule' => 'production',
            'activeMenu'   => 'prod-sellers',
            'seller'       => $seller,
            'connections'  => $connections,
            'canDelete'    => $connections['can_delete'],
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Suppliers', 'url' => url('production/sellers')],
                ['label' => 'Delete Supplier'],
            ],
        ]);
    }

    /**
     * Real-time AJAX check for a production supplier retirement.
     */
    public function ajaxCheckSupplierDeletable(): void {
        self::ensureDatabaseSchema();
        $id = (int)($_GET['id'] ?? 0);
        $seller = Database::fetchOne("SELECT id, name, company FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL", [$id]);
        if (!$seller) {
            Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
            return;
        }

        $connections = self::getSupplierConnectedRecords($id);
        Response::json([
            'success'    => true,
            'can_delete' => true,
            'message'    => 'Supplier will be removed from active production suppliers. Historical records will be preserved.',
            'reasons'    => $connections['reasons'],
            'counts'     => $connections['counts'],
        ]);
    }

    public function deleteSeller(string $id): void {
        self::ensureDatabaseSchema();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (!CSRF::validateRequest()) {
            if ($isAjax) {
                Response::json(['success' => false, 'message' => 'Invalid security token. Please try again.'], 403);
                return;
            }
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL", [(int)$id]);
        if (!$seller) {
            if ($isAjax) {
                Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
                return;
            }
            Session::flash('error', 'Supplier not found.');
            Response::redirect(url('production/sellers'));
            return;
        }

        $delSnapshot = [
            'Supplier Name'  => $seller['name'] ?? '',
            'Company / Mill' => $seller['company'] ?? '',
            'Phone'          => $seller['phone'] ?? '',
            'Email'          => $seller['email'] ?? '',
            'Material Type'  => $seller['material_type'] ?? '',
            'City'           => $seller['city'] ?? '',
            'Total Rolls'    => (int)($seller['roll_count'] ?? 0),
            'Total Price'    => '₹' . number_format((float)($seller['roll_price'] ?? 0), 2),
        ];

        try {
            Database::execute(
                "UPDATE sellers SET production_deleted_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL",
                [(int)$id]
            );
        } catch (\Throwable $e) {
            error_log('Production supplier could not be retired: ' . $e->getMessage());
            if ($isAjax) {
                Response::json(['success' => false, 'message' => 'Supplier could not be removed from active production suppliers. Please try again.'], 500);
                return;
            }
            Session::flash('error', 'Supplier could not be removed from active production suppliers. Please try again.');
            Response::redirect(url('production/sellers'));
            return;
        }

        AuditService::log('production', 'DELETE', 'sellers', (int)$id, $delSnapshot, null);

        if ($isAjax) {
            Response::json(['success' => true, 'message' => "Supplier '{$seller['name']}' removed from active production suppliers. Historical records were preserved."]);
            return;
        }

        Session::flash('success', "Supplier '{$seller['name']}' removed from active production suppliers. Historical records were preserved.");
        Response::redirect(url('production/sellers'));
    }

    public function productionPurchaseList(): void {
        $purchases = Database::fetchAll(
            "SELECT pp.*, s.name AS seller_name, s.company
             FROM production_purchases pp
             JOIN sellers s ON s.id = pp.seller_id
             ORDER BY pp.purchase_date DESC, pp.id DESC"
        );
        foreach ($purchases as &$purchase) {
            $purchase['items'] = Database::fetchAll(
                "SELECT ppi.*, sr.start_code, sr.end_code
                 FROM production_purchase_items ppi
                 LEFT JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
                 WHERE ppi.purchase_id = ?
                 ORDER BY ppi.id",
                [(int)$purchase['id']]
            );
        }
        unset($purchase);

        $totalPurchaseValue = (float)(Database::fetchOne(
            "SELECT COALESCE(SUM(total_amount), 0) AS total FROM production_purchases"
        )['total'] ?? 0);
        $supplyStock = Database::fetchAll(
            "SELECT item_name, unit, SUM(quantity) AS total_quantity, COUNT(*) AS purchase_lines
             FROM production_purchase_items
             WHERE item_type = 'GENERAL_SUPPLY'
             GROUP BY item_name, unit
             ORDER BY item_name, unit"
        );
        $fabricRollInventory = Database::fetchAll(
            "SELECT sr.*, s.name AS seller_name, s.company,
                    COALESCE(sr.material_type, s.material_type, 'FABRIC') AS fabric_description,
                    COALESCE(u.used_quantity, 0) AS used_quantity,
                    COALESCE(dmg.damaged_quantity, 0) AS damaged_quantity,
                    GREATEST(0, sr.quantity - COALESCE(u.used_quantity, 0) - COALESCE(dmg.damaged_quantity, 0)) AS available_quantity
             FROM seller_rolls sr
             JOIN sellers s ON s.id = sr.seller_id
             LEFT JOIN (
                 SELECT ppr.seller_roll_id, SUM(ppr.roll_qty) AS used_quantity
                 FROM production_program_rolls ppr
                 JOIN production_programs pp ON pp.id = ppr.production_program_id
                 WHERE pp.is_active = 1 AND pp.status != 'CANCELLED'
                 GROUP BY ppr.seller_roll_id
             ) u ON u.seller_roll_id = sr.id
             LEFT JOIN (
                 SELECT rd.seller_roll_id, SUM(rd.damaged_quantity) AS damaged_quantity
                 FROM roll_damages rd
                 WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                 GROUP BY rd.seller_roll_id
             ) dmg ON dmg.seller_roll_id = sr.id
             WHERE s.seller_type = 'MATERIAL_SUPPLIER'
             ORDER BY sr.id DESC"
        );

        Response::view('production.purchases.index', [
            'pageTitle' => 'Production Purchases',
            'activeModule' => 'production',
            'activeMenu' => 'prod-purchases',
            'purchases' => $purchases,
            'totalPurchaseValue' => $totalPurchaseValue,
            'supplyStock' => $supplyStock,
            'fabricRollInventory' => $fabricRollInventory,
            'breadcrumb' => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Purchases'],
            ],
        ]);
    }

    private function refreshSupplierRollTotals(int $sellerId): void {
        $totals = Database::fetchOne(
            "SELECT COALESCE(SUM(quantity), 0) AS roll_count, COALESCE(SUM(weight), 0) AS roll_weight,
                    COALESCE(SUM(price), 0) AS roll_price
             FROM seller_rolls
             WHERE seller_id = ?",
            [$sellerId]
        );
        Database::execute(
            "UPDATE sellers SET roll_count = ?, roll_weight = ?, roll_price = ? WHERE id = ?",
            [(int)$totals['roll_count'], (float)$totals['roll_weight'], (float)$totals['roll_price'], $sellerId]
        );
    }

    public function printProductionPurchase(string $id): void {
        $purchase = Database::fetchOne(
            "SELECT pp.*, s.name AS seller_name, s.company AS seller_company, s.phone AS seller_phone,
                    s.email AS seller_email, s.gst_number AS seller_gst_number, s.city AS seller_city,
                    s.state AS seller_state, s.address AS seller_address, s.payment_terms AS seller_payment_terms
             FROM production_purchases pp
             JOIN sellers s ON s.id = pp.seller_id
             WHERE pp.id = ?",
            [(int)$id]
        );
        if (!$purchase) {
            Response::error(404, 'Purchase record not found.');
            return;
        }

        $items = Database::fetchAll(
            "SELECT item_type, item_name, quantity, unit, weight_kg, unit_price, total_price,
                    tax_input_type, gst_type, sgst_value, cgst_value, igst_value,
                    sgst_amount, cgst_amount, igst_amount, roll_number, color, design_name, hsn_code
             FROM production_purchase_items
             WHERE purchase_id = ?
             ORDER BY id",
            [(int)$purchase['id']]
        );
        $subtotal = 0.0;
        $taxes = ['SGST' => 0.0, 'CGST' => 0.0, 'IGST' => 0.0];
        foreach ($items as &$item) {
            $base = (float)$item['unit_price'] * ($item['item_type'] === 'GENERAL_SUPPLY' ? (float)$item['quantity'] : 1);
            $item['base_amount'] = round($base, 2);
            $subtotal += $item['base_amount'];
            foreach (['SGST' => 'sgst_amount', 'CGST' => 'cgst_amount', 'IGST' => 'igst_amount'] as $type => $field) {
                $taxes[$type] += (float)($item[$field] ?? 0);
            }
        }
        unset($item);

        $company = SettingsService::getAllGrouped()['company'] ?? [];
        Response::viewOnly('production.purchases.print', [
            'purchase' => $purchase,
            'items' => $items,
            'company' => $company,
            'prodUnit' => ProductionUnit::getActiveFacility($purchase['production_unit_id'] ?? null),
            'subtotal' => round($subtotal, 2),
            'taxes' => array_map(static fn(float $amount): float => round($amount, 2), $taxes),
            'totalTax' => round(array_sum($taxes), 2),
            'overallTotal' => (float)$purchase['total_amount'],
        ]);
    }

    private function getProductionPurchase(int $purchaseId): ?array {
        return Database::fetchOne(
            "SELECT pp.*, s.name AS seller_name, s.company AS seller_company, s.phone AS seller_phone,
                    s.email AS seller_email, s.gst_number AS seller_gst_number, s.city AS seller_city,
                    s.state AS seller_state, s.address AS seller_address, s.payment_terms AS seller_payment_terms
             FROM production_purchases pp
             JOIN sellers s ON s.id = pp.seller_id
             WHERE pp.id = ?",
            [$purchaseId]
        );
    }

    public function viewProductionPurchase(string $id): void {
        $purchase = $this->getProductionPurchase((int)$id);
        if (!$purchase) {
            Response::error(404, 'Purchase record not found.');
            return;
        }
        $items = Database::fetchAll(
            "SELECT ppi.*, sr.start_code, sr.end_code, sr.photo_url
             FROM production_purchase_items ppi
             LEFT JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
             WHERE ppi.purchase_id = ?
             ORDER BY ppi.id",
            [(int)$purchase['id']]
        );
        Response::view('production.purchases.view', [
            'pageTitle' => 'Purchase ' . $purchase['purchase_number'],
            'activeModule' => 'production',
            'activeMenu' => 'prod-purchases',
            'purchase' => $purchase,
            'items' => $items,
            'breadcrumb' => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Purchases', 'url' => url('production/purchases')],
                ['label' => $purchase['purchase_number']],
            ],
        ]);
    }

    public function editProductionPurchase(string $id): void {
        $purchase = $this->getProductionPurchase((int)$id);
        if (!$purchase) {
            Response::error(404, 'Purchase record not found.');
            return;
        }
        $items = Database::fetchAll(
            "SELECT ppi.*, sr.photo_url, sr.start_seq, sr.end_seq, sr.start_code, sr.end_code
             FROM production_purchase_items ppi
             LEFT JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
             WHERE ppi.purchase_id = ?
             ORDER BY ppi.id",
            [(int)$purchase['id']]
        );
        $hsnCodes = Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC");
        Response::view('production.purchases.edit', [
            'pageTitle' => 'Edit Purchase ' . $purchase['purchase_number'],
            'activeModule' => 'production',
            'activeMenu' => 'prod-purchases',
            'purchase' => $purchase,
            'items' => $items,
            'hsnCodes' => $hsnCodes,
            'breadcrumb' => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Purchases', 'url' => url('production/purchases')],
                ['label' => 'Edit ' . $purchase['purchase_number']],
            ],
        ]);
    }

    public function updateProductionPurchase(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $purchaseId = (int)$id;
        $purchase = Database::fetchOne("SELECT * FROM production_purchases WHERE id = ?", [$purchaseId]);
        if (!$purchase) {
            Session::flash('error', 'Purchase record not found.');
            Response::redirect(url('production/purchases'));
            return;
        }

        $purchaseDate = trim((string)($_POST['purchase_date'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $purchaseDate);
        if (!$date || $date->format('Y-m-d') !== $purchaseDate) {
            Session::flash('error', 'Enter a valid purchase date.');
            Response::back();
            return;
        }

        $submittedItems = [];
        if (!empty($_POST['items_payload']) && is_string($_POST['items_payload'])) {
            $decoded = json_decode($_POST['items_payload'], true);
            if (is_array($decoded)) {
                $submittedItems = $decoded;
            }
        }
        if (empty($submittedItems)) {
            $submittedItems = $_POST['items'] ?? [];
            if (is_string($submittedItems)) {
                $submittedItems = json_decode($submittedItems, true) ?: [];
            }
        }
        if (!is_array($submittedItems)) {
            Session::flash('error', 'Invalid purchase items submitted.');
            Response::back();
            return;
        }
        $existingItems = Database::fetchAll(
            "SELECT ppi.*, sr.photo_url
             FROM production_purchase_items ppi
             LEFT JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
             WHERE ppi.purchase_id = ?
             ORDER BY ppi.id",
            [$purchaseId]
        );
        $existingById = [];
        foreach ($existingItems as $existingItem) {
            $existingById[(int)$existingItem['id']] = $existingItem;
        }

        $seenIds = [];
        $updatedItems = [];
        $calculateTaxAmount = static function (float $base, string $mode, float $value): float {
            return round($mode === 'AMOUNT' ? $value : ($base * $value / 100), 2);
        };
        foreach ($submittedItems as $key => $submitted) {
            if (!is_array($submitted)) {
                continue;
            }
            $itemId = (int)($submitted['id'] ?? 0);
            if ($itemId > 0 && isset($seenIds[$itemId])) {
                Session::flash('error', 'A purchase item was submitted more than once.');
                Response::back();
                return;
            }
            $oldItem = $itemId > 0 ? ($existingById[$itemId] ?? null) : null;
            if ($itemId > 0 && !$oldItem) {
                Session::flash('error', 'A submitted item does not belong to this purchase.');
                Response::back();
                return;
            }
            $itemType = strtoupper(trim((string)($submitted['item_type'] ?? '')));
            if ($oldItem && $itemType !== $oldItem['item_type']) {
                Session::flash('error', 'Purchase item types cannot be changed while editing.');
                Response::back();
                return;
            }
            if (!$oldItem && $itemType !== 'GENERAL_SUPPLY') {
                Session::flash('error', 'Fabric rolls cannot be added through purchase editing.');
                Response::back();
                return;
            }
            if (!empty($submitted['remove_item'])) {
                if (!$oldItem || $oldItem['item_type'] !== 'GENERAL_SUPPLY') {
                    Session::flash('error', 'Only general supply items can be removed from an existing purchase.');
                    Response::back();
                    return;
                }
                $seenIds[$itemId] = true;
                $updatedItems[] = ['delete_id' => $itemId];
                continue;
            }

            $gstType = strtoupper(trim((string)($submitted['gst_type'] ?? 'EXEMPTED')));
            $taxMode = strtoupper(trim((string)($submitted['tax_mode'] ?? 'PERCENT')));
            $taxValueInput = $submitted['tax_value'] ?? '0';
            $cgstValueInput = $submitted['cgst_tax_value'] ?? '0';
            if (!in_array($gstType, ['SGST', 'CGST', 'IGST', 'BOTH', 'EXEMPTED'], true)
                || !in_array($taxMode, ['PERCENT', 'AMOUNT'], true)
                || !is_numeric($taxValueInput) || (float)$taxValueInput < 0
                || !is_numeric($cgstValueInput) || (float)$cgstValueInput < 0
                || ($taxMode === 'PERCENT' && (
                    (float)$taxValueInput > 100 || ($gstType === 'BOTH' && (float)$cgstValueInput > 100)
                ))) {
                Session::flash('error', 'Enter valid GST values for each item.');
                Response::back();
                return;
            }
            $taxValue = $gstType === 'EXEMPTED' ? 0.0 : (float)$taxValueInput;
            $cgstTaxValue = $gstType === 'BOTH' ? (float)$cgstValueInput : 0.0;

            $itemName = trim((string)($submitted['item_name'] ?? $oldItem['item_name'] ?? ''));
            $quantity = is_numeric($submitted['quantity'] ?? null) && (float)$submitted['quantity'] > 0
                ? (float)$submitted['quantity']
                : ($oldItem ? (float)$oldItem['quantity'] : 0);
            $unitPrice = is_numeric($submitted['unit_price'] ?? null) ? (float)$submitted['unit_price'] : -1;
            $unit = strtoupper(trim((string)($submitted['unit'] ?? $oldItem['unit'] ?? '')));
            if ($unit === 'OTHER') {
                $unit = strtoupper(trim((string)($submitted['custom_unit'] ?? '')));
            }
            $allowedUnits = ['PCS', 'KG', 'METRE', 'LITRE', 'BOX', 'PACK', 'PAIR', 'SET', 'BUNDLE'];
            if ($itemName === '' || mb_strlen($itemName) > 255 || $quantity <= 0 || $unitPrice < 0
                || $unit === '' || mb_strlen($unit) > 30
                || (!in_array($unit, $allowedUnits, true) && strtoupper(trim((string)($submitted['unit'] ?? ''))) !== 'OTHER')) {
                Session::flash('error', 'Each purchase item needs a valid description, quantity, unit, and non-negative price.');
                Response::back();
                return;
            }

            $baseAmount = round($itemType === 'FABRIC_ROLL' ? $unitPrice : $quantity * $unitPrice, 2);
            if ($itemType === 'FABRIC_ROLL') {
                $rollNumber = mb_strtoupper(trim((string)($submitted['roll_number'] ?? $oldItem['roll_number'] ?? '')));
                $color = mb_strtoupper(trim((string)($submitted['color'] ?? $oldItem['color'] ?? '')));
                $design = mb_strtoupper(trim((string)($submitted['design_name'] ?? $oldItem['design_name'] ?? '')));
                $designCount = (int)($submitted['design_count'] ?? $oldItem['design_count'] ?? 0);
                $weight = (float)($submitted['weight_kg'] ?? $oldItem['weight_kg'] ?? 0);
                if ($rollNumber === '' || mb_strlen($rollNumber) > 50 || $color === '' || mb_strlen($color) > 100
                    || $design === '' || mb_strlen($design) > 150 || $designCount < 1 || $weight <= 0) {
                    Session::flash('error', 'Fabric roll details must be complete and valid.');
                    Response::back();
                    return;
                }
                $itemName = trim((string)($submitted['item_name'] ?? $oldItem['item_name']));
                $unit = 'PCS';
                $photoUrl = $oldItem['photo_url'] ?? null;
                $photo = $_FILES['roll_photo_' . $itemId] ?? null;
                if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
                    $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
                    if ($photo['error'] !== UPLOAD_ERR_OK || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
                        || $photo['size'] > 5 * 1024 * 1024) {
                        Session::flash('error', 'Roll photos must be JPG, PNG, or WEBP files up to 5 MB.');
                        Response::back();
                        return;
                    }
                    $photoUrl = CloudinaryService::upload($photo, 'grm_inventory/sellers/rolls', 'image');
                    if (!$photoUrl) {
                        Session::flash('error', 'A roll photo could not be uploaded. Please try again.');
                        Response::back();
                        return;
                    }
                }
            } else {
                $rollNumber = $color = $design = null;
                $designCount = $weight = null;
                $photoUrl = null;
            }

            $hsnCode = trim((string)($submitted['hsn_code'] ?? $oldItem['hsn_code'] ?? ''));
            $sgstAmount = in_array($gstType, ['SGST', 'BOTH'], true) ? $calculateTaxAmount($baseAmount, $taxMode, $taxValue) : 0.0;
            $cgstAmount = $gstType === 'CGST'
                ? $calculateTaxAmount($baseAmount, $taxMode, $taxValue)
                : ($gstType === 'BOTH' ? $calculateTaxAmount($baseAmount, $taxMode, $cgstTaxValue) : 0.0);
            $igstAmount = $gstType === 'IGST' ? $calculateTaxAmount($baseAmount, $taxMode, $taxValue) : 0.0;
            $updatedItems[] = [
                'id' => $itemId, 'type' => $itemType, 'item_name' => mb_strtoupper($itemName),
                'quantity' => $quantity, 'unit' => $unit, 'unit_price' => $unitPrice,
                'base_amount' => $baseAmount, 'weight' => $weight, 'roll_number' => $rollNumber,
                'color' => $color, 'design_name' => $design, 'design_count' => $designCount,
                'hsn_code' => $hsnCode !== '' ? mb_strtoupper($hsnCode) : null,
                'gst_type' => $gstType, 'tax_mode' => $taxMode, 'tax_value' => $taxValue,
                'cgst_tax_value' => $cgstTaxValue, 'sgst_amount' => $sgstAmount,
                'cgst_amount' => $cgstAmount, 'igst_amount' => $igstAmount,
                'total' => $baseAmount + $sgstAmount + $cgstAmount + $igstAmount,
                'seller_roll_id' => (int)($oldItem['seller_roll_id'] ?? 0),
                'photo_url' => $photoUrl,
            ];
            if ($itemId > 0) {
                $seenIds[$itemId] = true;
            }
        }

        foreach ($existingItems as $existingItem) {
            $existingId = (int)$existingItem['id'];
            if ($existingItem['item_type'] === 'FABRIC_ROLL' && !isset($seenIds[$existingId])) {
                Session::flash('error', 'Fabric roll purchase lines cannot be removed while editing.');
                Response::back();
                return;
            }
            if ($existingItem['item_type'] === 'GENERAL_SUPPLY' && !isset($seenIds[$existingId])) {
                $updatedItems[] = ['delete_id' => $existingId];
            }
        }
        $remainingItems = array_filter($updatedItems, static fn(array $item): bool => !isset($item['delete_id']));
        if (!$remainingItems) {
            Session::flash('error', 'A purchase must contain at least one item.');
            Response::back();
            return;
        }

        $totalAmount = 0.0;
        foreach ($updatedItems as $item) {
            if (!isset($item['delete_id'])) {
                $totalAmount += $item['total'];
            }
        }
        $documentUrl = null;
        if (!empty($_FILES['document']['name'])) {
            $file = $_FILES['document'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK
                || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)
                || $file['size'] > 5 * 1024 * 1024) {
                Session::flash('error', 'Invoice must be a JPG, PNG, WEBP, or PDF file up to 5 MB.');
                Response::back();
                return;
            }
            $documentUrl = CloudinaryService::upload($file, 'grm_inventory/production/purchases', 'auto');
            if (!$documentUrl) {
                Session::flash('error', 'Invoice upload failed. Please try again.');
                Response::back();
                return;
            }
        }

        try {
            self::ensureDatabaseSchema();
            $hasRollHsn = false;
            $hasItemHsn = false;
            try {
                $hasRollHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM seller_rolls LIKE 'hsn_code'");
                $hasItemHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM production_purchase_items LIKE 'hsn_code'");
            } catch (\Throwable $e) {}

            Database::beginTransaction();
            foreach ($updatedItems as $item) {
                if (isset($item['delete_id'])) {
                    Database::execute("DELETE FROM production_purchase_items WHERE id = ? AND purchase_id = ?", [$item['delete_id'], $purchaseId]);
                    continue;
                }
                if ($item['id'] > 0) {
                    if ($hasItemHsn) {
                        Database::execute(
                            "UPDATE production_purchase_items
                             SET item_name = ?, quantity = ?, unit = ?, weight_kg = ?, unit_price = ?, total_price = ?,
                                 gst_type = ?, tax_input_type = ?, sgst_value = ?, cgst_value = ?, igst_value = ?,
                                 sgst_amount = ?, cgst_amount = ?, igst_amount = ?, roll_number = ?, color = ?,
                                 design_name = ?, design_count = ?, hsn_code = ?
                             WHERE id = ? AND purchase_id = ?",
                            [$item['item_name'], $item['quantity'], $item['unit'], $item['weight'], $item['unit_price'], $item['total'],
                                $item['gst_type'], $item['tax_mode'], in_array($item['gst_type'], ['SGST', 'BOTH'], true) ? $item['tax_value'] : 0,
                                $item['gst_type'] === 'CGST' ? $item['tax_value'] : $item['cgst_tax_value'],
                                $item['gst_type'] === 'IGST' ? $item['tax_value'] : 0,
                                $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount'], $item['roll_number'], $item['color'],
                                $item['design_name'], $item['design_count'], $item['hsn_code'], $item['id'], $purchaseId]
                        );
                    } else {
                        Database::execute(
                            "UPDATE production_purchase_items
                             SET item_name = ?, quantity = ?, unit = ?, weight_kg = ?, unit_price = ?, total_price = ?,
                                 gst_type = ?, tax_input_type = ?, sgst_value = ?, cgst_value = ?, igst_value = ?,
                                 sgst_amount = ?, cgst_amount = ?, igst_amount = ?, roll_number = ?, color = ?,
                                 design_name = ?, design_count = ?
                             WHERE id = ? AND purchase_id = ?",
                            [$item['item_name'], $item['quantity'], $item['unit'], $item['weight'], $item['unit_price'], $item['total'],
                                $item['gst_type'], $item['tax_mode'], in_array($item['gst_type'], ['SGST', 'BOTH'], true) ? $item['tax_value'] : 0,
                                $item['gst_type'] === 'CGST' ? $item['tax_value'] : $item['cgst_tax_value'],
                                $item['gst_type'] === 'IGST' ? $item['tax_value'] : 0,
                                $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount'], $item['roll_number'], $item['color'],
                                $item['design_name'], $item['design_count'], $item['id'], $purchaseId]
                        );
                    }
                    if ($item['type'] === 'FABRIC_ROLL' && $item['seller_roll_id'] > 0) {
                        $sellerRoll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [$item['seller_roll_id']]);
                        $startSeq = (int)($sellerRoll['start_seq'] ?? 0);
                        $startCode = (string)($sellerRoll['start_code'] ?? '');
                        $prefix = '';
                        $padLen = 2;
                        if (!empty($startCode) && preg_match('/^([A-Za-z]{2}\d{4})(\d+)$/', $startCode, $m)) {
                            $prefix = $m[1];
                            $padLen = max(2, strlen($m[2]));
                            if ($startSeq <= 0) $startSeq = (int)$m[2];
                        } elseif (!empty($startCode) && preg_match('/^([A-Za-z0-9_-]+?)(\d+)$/', $startCode, $m)) {
                            $prefix = $m[1];
                            $padLen = max(2, strlen($m[2]));
                            if ($startSeq <= 0) $startSeq = (int)$m[2];
                        } else {
                            $prefix = 'FB' . date('my');
                            $padLen = 2;
                        }
                        if ($startSeq <= 0) $startSeq = 1;
                        $rollQty = max(1, (int)$item['quantity']);
                        $endSeq = $startSeq + $rollQty - 1;
                        $endCode = sprintf('%s%0' . $padLen . 'd', $prefix, $endSeq);
                        $rollQrCode = $rollQty > 1 ? ($startCode . '-' . $endCode) : $startCode;

                        if ($hasRollHsn) {
                            Database::execute(
                                "UPDATE seller_rolls SET roll_number = ?, color = ?, design_name = ?, design_count = ?,
                                         quantity = ?, weight = ?, price = ?, material_type = ?, photo_url = ?, hsn_code = ?,
                                         end_seq = ?, end_code = ?, qr_code = ?
                                 WHERE id = ?",
                                [$item['roll_number'], $item['color'], $item['design_name'], $item['design_count'],
                                    $rollQty, $item['weight'], $item['unit_price'], $item['item_name'], $item['photo_url'], $item['hsn_code'],
                                    $endSeq, $endCode, $rollQrCode, $item['seller_roll_id']]
                            );
                        } else {
                            Database::execute(
                                "UPDATE seller_rolls SET roll_number = ?, color = ?, design_name = ?, design_count = ?,
                                         quantity = ?, weight = ?, price = ?, material_type = ?, photo_url = ?,
                                         end_seq = ?, end_code = ?, qr_code = ?
                                 WHERE id = ?",
                                [$item['roll_number'], $item['color'], $item['design_name'], $item['design_count'],
                                    $rollQty, $item['weight'], $item['unit_price'], $item['item_name'], $item['photo_url'],
                                    $endSeq, $endCode, $rollQrCode, $item['seller_roll_id']]
                            );
                        }
                    }
                    continue;
                }
                if ($hasItemHsn) {
                    Database::execute(
                        "INSERT INTO production_purchase_items
                            (purchase_id, item_type, item_name, quantity, unit, unit_price, total_price,
                             gst_type, tax_input_type, sgst_value, cgst_value, igst_value,
                             sgst_amount, cgst_amount, igst_amount, hsn_code)
                         VALUES (?, 'GENERAL_SUPPLY', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$purchaseId, $item['item_name'], $item['quantity'], $item['unit'], $item['unit_price'], $item['total'],
                            $item['gst_type'], $item['tax_mode'],
                            in_array($item['gst_type'], ['SGST', 'BOTH'], true) ? $item['tax_value'] : 0,
                            $item['gst_type'] === 'CGST' ? $item['tax_value'] : $item['cgst_tax_value'],
                            $item['gst_type'] === 'IGST' ? $item['tax_value'] : 0,
                            $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount'], $item['hsn_code']]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO production_purchase_items
                            (purchase_id, item_type, item_name, quantity, unit, unit_price, total_price,
                             gst_type, tax_input_type, sgst_value, cgst_value, igst_value,
                             sgst_amount, cgst_amount, igst_amount)
                         VALUES (?, 'GENERAL_SUPPLY', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$purchaseId, $item['item_name'], $item['quantity'], $item['unit'], $item['unit_price'], $item['total'],
                            $item['gst_type'], $item['tax_mode'],
                            in_array($item['gst_type'], ['SGST', 'BOTH'], true) ? $item['tax_value'] : 0,
                            $item['gst_type'] === 'CGST' ? $item['tax_value'] : $item['cgst_tax_value'],
                            $item['gst_type'] === 'IGST' ? $item['tax_value'] : 0,
                            $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount']]
                    );
                }
            }
            $this->refreshSupplierRollTotals((int)$purchase['seller_id']);
            Database::execute(
                "UPDATE production_purchases
                 SET purchase_date = ?, invoice_number = ?, document_url = COALESCE(?, document_url),
                     notes = ?, total_amount = ?, updated_at = NOW()
                 WHERE id = ?",
                [$purchaseDate, trim((string)($_POST['invoice_number'] ?? '')) ?: null, $documentUrl,
                    trim((string)($_POST['notes'] ?? '')) ?: null, round($totalAmount, 2), $purchaseId]
            );
            Database::commit();
            AuditService::log('production', 'UPDATE', 'production_purchases', $purchaseId, $purchase, [
                'purchase_number' => $purchase['purchase_number'], 'total_amount' => round($totalAmount, 2),
            ]);
            Session::flash('success', 'Purchase updated successfully.');
            Response::redirect(url('production/purchases'));
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            error_log('Production purchase could not be updated: ' . $e->getMessage());
            Session::flash('error', 'Purchase could not be updated: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteProductionPurchase(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }
        $purchaseId = (int)$id;
        $purchase = Database::fetchOne("SELECT * FROM production_purchases WHERE id = ?", [$purchaseId]);
        if (!$purchase) {
            Session::flash('error', 'Purchase record not found.');
            Response::redirect(url('production/purchases'));
            return;
        }

        $linkedRoll = Database::fetchOne(
            "SELECT sr.id, sr.roll_number
             FROM production_purchase_items ppi
             JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
             LEFT JOIN production_program_rolls ppr ON ppr.seller_roll_id = sr.id
             LEFT JOIN production_programs pp ON pp.seller_roll_id = sr.id
             LEFT JOIN cutting_batches cb ON cb.seller_roll_id = sr.id
             LEFT JOIN cutting_batch_rolls cbr ON cbr.seller_roll_id = sr.id
             LEFT JOIN roll_damages rd ON rd.seller_roll_id = sr.id
             WHERE ppi.purchase_id = ?
               AND (ppr.id IS NOT NULL OR pp.id IS NOT NULL OR cb.id IS NOT NULL OR cbr.id IS NOT NULL OR rd.id IS NOT NULL)
             LIMIT 1",
            [$purchaseId]
        );
        if ($linkedRoll) {
            Session::flash('error', "Cannot delete purchase {$purchase['purchase_number']}; roll {$linkedRoll['roll_number']} is referenced by Production, Cutting, or Damage records.");
            Response::redirect(url('production/purchases'));
            return;
        }

        try {
            Database::beginTransaction();
            Database::execute(
                "DELETE sr FROM seller_rolls sr
                 JOIN production_purchase_items ppi ON ppi.seller_roll_id = sr.id
                 WHERE ppi.purchase_id = ?",
                [$purchaseId]
            );
            $this->refreshSupplierRollTotals((int)$purchase['seller_id']);
            Database::execute("DELETE FROM production_purchases WHERE id = ?", [$purchaseId]);
            Database::commit();
            AuditService::log('production', 'DELETE', 'production_purchases', $purchaseId, $purchase, null);
            Session::flash('success', "Purchase {$purchase['purchase_number']} deleted.");
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            error_log('Production purchase could not be deleted: ' . $e->getMessage());
            Session::flash('error', 'Purchase could not be deleted because related records still depend on it.');
        }
        Response::redirect(url('production/purchases'));
    }

    public function createProductionPurchase(): void {
        self::ensureDatabaseSchema();
        $sellers = Database::fetchAll(
            "SELECT id, name, company, phone, email, gst_number, city, state, address
             FROM sellers
             WHERE seller_type = 'MATERIAL_SUPPLIER' AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL
             ORDER BY company, name"
        );
        $hsnCodes = Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC");
        Response::view('production.purchases.create', [
            'pageTitle' => 'Record Production Purchase',
            'activeModule' => 'production',
            'activeMenu' => 'prod-purchases',
            'sellers' => $sellers,
            'hsnCodes' => $hsnCodes,
            'breadcrumb' => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Purchases', 'url' => url('production/purchases')],
                ['label' => 'Record Purchase'],
            ],
        ]);
    }

    public function storeProductionPurchase(): void {
        self::ensureDatabaseSchema();
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $sellerId = (int)($_POST['seller_id'] ?? 0);
        $seller = Database::fetchOne(
            "SELECT id, name, company FROM sellers
             WHERE id = ? AND seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL",
            [$sellerId]
        );
        if (!$seller) {
            Session::flash('error', 'Select a valid registered supplier.');
            Response::back();
            return;
        }

        $purchaseDate = trim((string)($_POST['purchase_date'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $purchaseDate);
        if (!$date || $date->format('Y-m-d') !== $purchaseDate) {
            Session::flash('error', 'Enter a valid purchase date.');
            Response::back();
            return;
        }

        $submittedItems = [];
        if (!empty($_POST['items_payload']) && is_string($_POST['items_payload'])) {
            $decoded = json_decode($_POST['items_payload'], true);
            if (is_array($decoded)) {
                $submittedItems = $decoded;
            }
        }
        if (empty($submittedItems)) {
            $submittedItems = $_POST['items'] ?? [];
            if (is_string($submittedItems)) {
                $submittedItems = json_decode($submittedItems, true) ?: [];
            }
        }
        if (!is_array($submittedItems)) {
            $submittedItems = [];
        }
        $items = [];
        $allowedUnits = ['PCS', 'KG', 'METRE', 'LITRE', 'BOX', 'PACK', 'PAIR', 'SET', 'BUNDLE'];
        $fabricDescription = mb_strtoupper(trim((string)($_POST['fabric_description'] ?? '')));
        $calculateTaxAmount = static function (float $base, string $mode, float $value): float {
            return round($mode === 'AMOUNT' ? $value : ($base * $value / 100), 2);
        };
        foreach ($submittedItems as $index => $submitted) {
            if (!is_array($submitted)) {
                continue;
            }
            $type = strtoupper(trim((string)($submitted['item_type'] ?? '')));
            $quantityInput = $submitted['quantity'] ?? null;
            $unitPriceInput = $submitted['unit_price'] ?? null;
            $quantity = is_numeric($quantityInput) ? (float)$quantityInput : 0;
            $unitPrice = is_numeric($unitPriceInput) ? (float)$unitPriceInput : -1;
            $gstType = strtoupper(trim((string)($submitted['gst_type'] ?? 'EXEMPTED')));
            $taxMode = strtoupper(trim((string)($submitted['tax_mode'] ?? 'PERCENT')));
            $taxInput = $submitted['tax_value'] ?? '0';
            $cgstTaxInput = $submitted['cgst_tax_value'] ?? '0';
            if (!in_array($type, ['FABRIC_ROLL', 'GENERAL_SUPPLY'], true) || $quantity <= 0 || $unitPrice < 0) {
                Session::flash('error', 'Each purchase line needs a valid item type, quantity, and non-negative price.');
                Response::back();
                return;
            }
            if (!in_array($gstType, ['SGST', 'IGST', 'CGST', 'BOTH', 'EXEMPTED'], true)
                || !in_array($taxMode, ['PERCENT', 'AMOUNT'], true)
                || !is_numeric($taxInput) || (float)$taxInput < 0
                || !is_numeric($cgstTaxInput) || (float)$cgstTaxInput < 0
                || ($gstType !== 'EXEMPTED' && $taxMode === 'PERCENT' && (
                    (float)$taxInput > 100 || ($gstType === 'BOTH' && (float)$cgstTaxInput > 100)
                ))) {
                Session::flash('error', 'Choose a valid GST type and enter a non-negative tax value; percentage tax cannot exceed 100%.');
                Response::back();
                return;
            }
            $taxValue = $gstType === 'EXEMPTED' ? 0.0 : (float)$taxInput;
            $cgstTaxValue = $gstType === 'BOTH' ? (float)$cgstTaxInput : 0.0;

            if ($type === 'FABRIC_ROLL') {
                $rollNumber = mb_strtoupper(trim((string)($submitted['roll_number'] ?? '')));
                $hsnCode = trim((string)($submitted['hsn_code'] ?? ''));
                $color = mb_strtoupper(trim((string)($submitted['color'] ?? '')));
                $design = mb_strtoupper(trim((string)($submitted['design_name'] ?? '')));
                $designCount = (int)($submitted['design_count'] ?? 0);
                $weight = (float)($submitted['weight_kg'] ?? 0);
                if ($fabricDescription === '' || mb_strlen($fabricDescription) > 150
                    || $rollNumber === '' || mb_strlen($rollNumber) > 50
                    || $color === '' || mb_strlen($color) > 100
                    || $design === '' || mb_strlen($design) > 150
                    || $designCount < 1 || $quantity < 1 || floor($quantity) !== $quantity
                    || $weight <= 0 || $unitPrice <= 0) {
                    Session::flash('error', 'Fabric roll lines require fabric description, roll number, color, design, designs count, RQ, weight, and a price greater than zero.');
                    Response::back();
                    return;
                }
                $quantity = (int)$quantity;
                $baseTotal = round($unitPrice, 2);
                $sgstAmount = in_array($gstType, ['SGST', 'BOTH'], true)
                    ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue) : 0.0;
                $cgstAmount = $gstType === 'CGST'
                    ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue)
                    : ($gstType === 'BOTH' ? $calculateTaxAmount($baseTotal, $taxMode, $cgstTaxValue) : 0.0);
                $igstAmount = $gstType === 'IGST' ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue) : 0.0;
                $taxAmount = $sgstAmount + $cgstAmount + $igstAmount;
                $items[] = [
                    'type' => $type, 'item_name' => $fabricDescription, 'quantity' => $quantity,
                    'unit' => 'PCS', 'weight' => $weight, 'unit_price' => $unitPrice,
                    'hsn_code' => $hsnCode !== '' ? mb_strtoupper($hsnCode) : null,
                    'gst_type' => $gstType, 'tax_mode' => $taxMode, 'tax_value' => $taxValue,
                    'cgst_tax_value' => $cgstTaxValue,
                    'tax_amount' => $taxAmount,
                    'sgst_value' => in_array($gstType, ['SGST', 'BOTH'], true) ? $taxValue : 0,
                    'cgst_value' => $gstType === 'CGST' ? $taxValue : $cgstTaxValue,
                    'igst_value' => $gstType === 'IGST' ? $taxValue : 0,
                    'sgst_amount' => $sgstAmount,
                    'cgst_amount' => $cgstAmount,
                    'igst_amount' => $igstAmount,
                    'line_total' => $baseTotal + $taxAmount,
                    'roll_number' => $rollNumber, 'color' => $color,
                    'design_name' => $design, 'design_count' => $designCount,
                    'form_index' => (string)$index, 'photo_url' => null,
                ];
                continue;
            }

            $itemName = trim((string)($submitted['item_name'] ?? ''));
            $hsnCode = trim((string)($submitted['hsn_code'] ?? ''));
            $unit = strtoupper(trim((string)($submitted['unit'] ?? '')));
            if ($unit === 'OTHER') {
                $unit = strtoupper(trim((string)($submitted['custom_unit'] ?? '')));
            }
            if ($itemName === '' || mb_strlen($itemName) > 255 || $unit === '' || mb_strlen($unit) > 30
                || (!in_array($unit, $allowedUnits, true) && strtoupper(trim((string)($submitted['unit'] ?? ''))) !== 'OTHER')) {
                Session::flash('error', 'General supply lines require an item name and a valid unit.');
                Response::back();
                return;
            }
            $baseTotal = round($quantity * $unitPrice, 2);
            $sgstAmount = in_array($gstType, ['SGST', 'BOTH'], true)
                ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue) : 0.0;
            $cgstAmount = $gstType === 'CGST'
                ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue)
                : ($gstType === 'BOTH' ? $calculateTaxAmount($baseTotal, $taxMode, $cgstTaxValue) : 0.0);
            $igstAmount = $gstType === 'IGST' ? $calculateTaxAmount($baseTotal, $taxMode, $taxValue) : 0.0;
            $taxAmount = $sgstAmount + $cgstAmount + $igstAmount;
            $items[] = [
                'type' => $type, 'item_name' => mb_strtoupper($itemName), 'quantity' => $quantity,
                'unit' => $unit, 'weight' => null, 'unit_price' => $unitPrice,
                'hsn_code' => $hsnCode !== '' ? mb_strtoupper($hsnCode) : null,
                'gst_type' => $gstType, 'tax_mode' => $taxMode, 'tax_value' => $taxValue,
                'cgst_tax_value' => $cgstTaxValue,
                'tax_amount' => $taxAmount,
                'sgst_value' => in_array($gstType, ['SGST', 'BOTH'], true) ? $taxValue : 0,
                'cgst_value' => $gstType === 'CGST' ? $taxValue : $cgstTaxValue,
                'igst_value' => $gstType === 'IGST' ? $taxValue : 0,
                'sgst_amount' => $sgstAmount,
                'cgst_amount' => $cgstAmount,
                'igst_amount' => $igstAmount,
                'line_total' => $baseTotal + $taxAmount, 'roll_number' => null,
                'color' => null, 'design_name' => null, 'design_count' => null, 'photo_url' => null,
            ];
        }

        if (!$items) {
            Session::flash('error', 'Add at least one valid purchase item.');
            Response::back();
            return;
        }

        $rollPhotoFiles = [];
        foreach ($items as &$item) {
            if ($item['type'] !== 'FABRIC_ROLL') {
                continue;
            }
            $photoField = 'roll_photo_' . $item['form_index'];
            $photo = $_FILES[$photoField] ?? null;
            if (!$photo || $photo['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            if ($photo['error'] !== UPLOAD_ERR_OK || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
                || $photo['size'] > 5 * 1024 * 1024) {
                Session::flash('error', 'Roll photos must be JPG, PNG, or WEBP files up to 5 MB.');
                Response::back();
                return;
            }
            $rollPhotoFiles[(string)$item['form_index']] = $photo;
        }
        unset($item);

        $documentUrl = null;
        $documentFile = null;
        if (!empty($_FILES['document']['name'])) {
            $file = $_FILES['document'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) || $file['size'] > 5 * 1024 * 1024) {
                Session::flash('error', 'Invoice must be a JPG, PNG, WEBP, or PDF file up to 5 MB.');
                Response::back();
                return;
            }
            $documentFile = $file;
        }

        $totalAmount = array_sum(array_column($items, 'line_total'));
        $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $userId = (int)(Session::get('user_id') ?? 0);

        try {
            Database::beginTransaction();
            $temporaryNumber = 'TMP-PP-' . bin2hex(random_bytes(12));
            Database::execute(
                "INSERT INTO production_purchases
                    (purchase_number, seller_id, purchase_date, invoice_number, document_url, notes, total_amount, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$temporaryNumber, $sellerId, $purchaseDate, $invoiceNumber !== '' ? $invoiceNumber : null,
                    $documentUrl, $notes !== '' ? $notes : null, $totalAmount, $userId ?: null]
            );
            $purchaseId = (int)Database::lastInsertId();
            $purchaseNumber = 'PP-' . date('Y', strtotime($purchaseDate)) . '-' . sprintf('%06d', $purchaseId);
            Database::execute(
                "UPDATE production_purchases SET purchase_number = ? WHERE id = ?",
                [$purchaseNumber, $purchaseId]
            );

            $currentSeqData = null;
            $currentEndSeq = 0;
            $prefix = '';
            $rollPhotoTargets = [];

            self::ensureDatabaseSchema();
            $hasRollHsn = false;
            $hasItemHsn = false;
            try {
                $hasRollHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM seller_rolls LIKE 'hsn_code'");
                $hasItemHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM production_purchase_items LIKE 'hsn_code'");
            } catch (\Throwable $e) {}

            foreach ($items as $item) {
                $sellerRollId = null;
                if ($item['type'] === 'FABRIC_ROLL') {
                    $rollQty = max(1, (int)$item['quantity']);
                    if ($currentSeqData === null) {
                        $currentSeqData = ProductionService::getNextFabricRollSequence($rollQty, $purchaseDate);
                        $startSeq = (int)$currentSeqData['start_seq'];
                        $endSeq = (int)$currentSeqData['end_seq'];
                        $prefix = $currentSeqData['prefix'];
                        $currentEndSeq = $endSeq;
                    } else {
                        $startSeq = $currentEndSeq + 1;
                        $endSeq = $startSeq + $rollQty - 1;
                        $currentEndSeq = $endSeq;
                    }
                    $startCode = sprintf('%s%02d', $prefix, $startSeq);
                    $endCode = sprintf('%s%02d', $prefix, $endSeq);
                    $rollQrCode = $rollQty > 1 ? ($startCode . '-' . $endCode) : $startCode;

                    if ($hasRollHsn) {
                        Database::execute(
                            "INSERT INTO seller_rolls
                                (seller_id, roll_number, color, design_name, design_count, quantity, weight, price,
                                 material_type, photo_url, start_seq, end_seq, start_code, end_code, qr_code, hsn_code)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$sellerId, $item['roll_number'], $item['color'], $item['design_name'], $item['design_count'],
                                $item['quantity'], $item['weight'], $item['unit_price'], $item['item_name'], $item['photo_url'],
                                $startSeq, $endSeq, $startCode, $endCode, $rollQrCode, $item['hsn_code']]
                        );
                    } else {
                        Database::execute(
                            "INSERT INTO seller_rolls
                                (seller_id, roll_number, color, design_name, design_count, quantity, weight, price,
                                 material_type, photo_url, start_seq, end_seq, start_code, end_code, qr_code)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$sellerId, $item['roll_number'], $item['color'], $item['design_name'], $item['design_count'],
                                $item['quantity'], $item['weight'], $item['unit_price'], $item['item_name'], $item['photo_url'],
                                $startSeq, $endSeq, $startCode, $endCode, $rollQrCode]
                        );
                    }
                    $sellerRollId = (int)Database::lastInsertId();
                    if (isset($rollPhotoFiles[(string)$item['form_index']])) {
                        $rollPhotoTargets[(string)$item['form_index']] = $sellerRollId;
                    }
                }

                if ($hasItemHsn) {
                    Database::execute(
                        "INSERT INTO production_purchase_items
                            (purchase_id, item_type, item_name, quantity, unit, weight_kg, unit_price, total_price,
                             gst_type, tax_input_type, sgst_value, cgst_value, igst_value,
                             sgst_amount, cgst_amount, igst_amount,
                             roll_number, color, design_name, design_count, seller_roll_id, hsn_code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$purchaseId, $item['type'], $item['item_name'], $item['quantity'], $item['unit'], $item['weight'],
                            $item['unit_price'], $item['line_total'], $item['gst_type'], $item['tax_mode'],
                            $item['sgst_value'], $item['cgst_value'], $item['igst_value'],
                            $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount'],
                            $item['roll_number'], $item['color'],
                            $item['design_name'], $item['design_count'], $sellerRollId, $item['hsn_code']]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO production_purchase_items
                            (purchase_id, item_type, item_name, quantity, unit, weight_kg, unit_price, total_price,
                             gst_type, tax_input_type, sgst_value, cgst_value, igst_value,
                             sgst_amount, cgst_amount, igst_amount,
                             roll_number, color, design_name, design_count, seller_roll_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$purchaseId, $item['type'], $item['item_name'], $item['quantity'], $item['unit'], $item['weight'],
                            $item['unit_price'], $item['line_total'], $item['gst_type'], $item['tax_mode'],
                            $item['sgst_value'], $item['cgst_value'], $item['igst_value'],
                            $item['sgst_amount'], $item['cgst_amount'], $item['igst_amount'],
                            $item['roll_number'], $item['color'],
                            $item['design_name'], $item['design_count'], $sellerRollId]
                    );
                }
            }

            Database::commit();

            AuditService::log('production', 'CREATE', 'production_purchases', $purchaseId, null, [
                'purchase_number' => $purchaseNumber,
                'seller_id' => $sellerId,
                'total_amount' => $totalAmount,
                'item_count' => count($items),
            ]);
            $this->refreshSupplierRollTotals($sellerId);
            $failedAttachmentCount = 0;
            if ($rollPhotoFiles) {
                try {
                    $photoUrls = CloudinaryService::uploadMany($rollPhotoFiles, 'grm_inventory/sellers/rolls', 'image');
                    foreach ($rollPhotoTargets as $formIndex => $sellerRollId) {
                        $photoUrl = $photoUrls[$formIndex] ?? null;
                        if (!$photoUrl) {
                            $failedAttachmentCount++;
                            continue;
                        }
                        try {
                            Database::execute(
                                "UPDATE seller_rolls SET photo_url = ? WHERE id = ? AND seller_id = ?",
                                [$photoUrl, $sellerRollId, $sellerId]
                            );
                        } catch (\Throwable $e) {
                            error_log('Production purchase roll photo URL could not be saved: ' . $e->getMessage());
                            $failedAttachmentCount++;
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('Production purchase roll photo upload failed after purchase was saved: ' . $e->getMessage());
                    $failedAttachmentCount = count($rollPhotoFiles);
                }
            }
            if ($documentFile) {
                try {
                    $documentUrl = CloudinaryService::upload($documentFile, 'grm_inventory/production/purchases', 'auto');
                    if (!$documentUrl) {
                        $failedAttachmentCount++;
                    } else {
                        Database::execute(
                            "UPDATE production_purchases SET document_url = ? WHERE id = ?",
                            [$documentUrl, $purchaseId]
                        );
                    }
                } catch (\Throwable $e) {
                    error_log('Production purchase invoice upload failed after purchase was saved: ' . $e->getMessage());
                    $failedAttachmentCount++;
                }
            }
            Session::flash('success', "Purchase {$purchaseNumber} recorded successfully.");
            if ($failedAttachmentCount > 0) {
                Session::flash('warning', "Purchase and roll data were saved, but {$failedAttachmentCount} attachment(s) could not be uploaded or linked.");
            }
            Response::redirect(url('production/purchases'));
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            error_log('Production purchase could not be saved: ' . $e->getMessage());
            Session::flash('error', 'Purchase could not be saved: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteSellerRoll(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $purchaseItem = Database::fetchOne(
            "SELECT ppi.id, pp.purchase_number
             FROM production_purchase_items ppi
             JOIN production_purchases pp ON pp.id = ppi.purchase_id
             WHERE ppi.seller_roll_id = ? LIMIT 1",
            [(int)$id]
        );
        if ($purchaseItem) {
            Session::flash('error', 'This fabric roll is part of purchase ' . $purchaseItem['purchase_number'] . '. It cannot be deleted from stock; retain its purchase history.');
            Response::redirect(url('production/purchases'));
            return;
        }

        $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [(int)$id]);
        if (!$roll) {
            Session::flash('error', 'Fabric roll not found.');
            Response::back();
            return;
        }

        $sellerId = (int)$roll['seller_id'];

        // Check if this roll is assigned in any active production programs
        $used = Database::fetchOne(
            "SELECT pp.program_number, pp.name as program_name 
             FROM production_program_rolls ppr
             JOIN production_programs pp ON ppr.production_program_id = pp.id
             WHERE ppr.seller_roll_id = ? AND (pp.is_active = 1 OR pp.is_active IS NULL) AND pp.status != 'CANCELLED'
             LIMIT 1",
            [(int)$id]
        );

        if (!$used) {
            $used = Database::fetchOne(
                "SELECT pp.program_number, pp.name as program_name 
                 FROM production_programs pp
                 WHERE pp.seller_roll_id = ? AND (pp.is_active = 1 OR pp.is_active IS NULL) AND pp.status != 'CANCELLED'
                 LIMIT 1",
                [(int)$id]
            );
        }

        if ($used) {
            $progLabel = $used['program_number'] . (!empty($used['program_name']) ? " ({$used['program_name']})" : '');
            Session::flash('error', "Cannot delete Roll '{$roll['roll_number']}' because it is assigned to active Program {$progLabel}.");
            Response::back();
            return;
        }

        // Delete roll
        Database::execute("DELETE FROM seller_rolls WHERE id = ?", [(int)$id]);

        // Recalculate seller summary totals
        $remainingRolls = Database::fetchAll("SELECT * FROM seller_rolls WHERE seller_id = ?", [$sellerId]);
        $computedRollCount = count($remainingRolls);
        $computedTotalWeight = array_sum(array_column($remainingRolls, 'weight'));
        $computedTotalPrice = array_sum(array_column($remainingRolls, 'price'));
        $uniqueColors = array_unique(array_filter(array_map('trim', array_column($remainingRolls, 'color'))));
        $uniqueDesigns = array_unique(array_filter(array_map('trim', array_column($remainingRolls, 'design_name'))));

        Database::execute(
            "UPDATE sellers SET 
                roll_count = ?, 
                color_count = ?, 
                design_count = ?, 
                color_design_count = ?, 
                roll_weight = ?, 
                roll_price = ?, 
                colors = ?
             WHERE id = ?",
            [
                $computedRollCount,
                count($uniqueColors),
                count($uniqueDesigns),
                count($uniqueColors) + count($uniqueDesigns),
                $computedTotalWeight,
                $computedTotalPrice,
                !empty($uniqueColors) ? implode(', ', $uniqueColors) : '',
                $sellerId
            ]
        );

        $delRollSnapshot = [
            'Roll Number'  => $roll['roll_number'] ?? '',
            'Color'        => $roll['color'] ?? '',
            'Design'       => $roll['design_name'] ?? '',
            'Quantity'     => (int)($roll['quantity'] ?? 0),
            'Weight'       => number_format((float)($roll['weight'] ?? 0), 2) . ' kg',
            'Price'        => '₹' . number_format((float)($roll['price'] ?? 0), 2),
        ];

        AuditService::log('production', 'DELETE', 'seller_rolls', (int)$id, $delRollSnapshot, null);

        Session::flash('success', "Fabric Roll '{$roll['roll_number']}' deleted successfully.");
        Response::back();
    }

    public function ajaxSellerRolls(string $id): void {
        $sellerId = (int)$id;
        $excludeDamageId = max(0, (int)($_GET['exclude_damage_id'] ?? 0));
        $rolls = Database::fetchAll(
            "SELECT sr.id, sr.roll_number, sr.color, sr.design_name, sr.quantity, sr.weight, sr.price, sr.photo_url,
                    COALESCE(u.used_quantity, 0) as used_quantity,
                    COALESCE(u.used_weight, 0) as used_weight,
                    COALESCE(dmg.damaged_quantity, 0) as damaged_quantity,
                    COALESCE(dmg.damaged_weight, 0) as damaged_weight,
                    GREATEST(0, sr.quantity - COALESCE(dmg.damaged_quantity, 0)) as available_quantity,
                    GREATEST(0.0, sr.weight - COALESCE(dmg.damaged_weight, 0)) as available_weight
             FROM seller_rolls sr
             LEFT JOIN (
                 SELECT ppr.seller_roll_id, 
                        SUM(ppr.roll_qty) as used_quantity,
                        SUM(ppr.roll_weight) as used_weight
                 FROM production_program_rolls ppr
                 JOIN production_programs pp ON ppr.production_program_id = pp.id
                 WHERE pp.is_active = 1 AND pp.status != 'CANCELLED'
                 GROUP BY ppr.seller_roll_id
             ) u ON sr.id = u.seller_roll_id
             LEFT JOIN (
                 SELECT rd.seller_roll_id,
                        SUM(rd.damaged_quantity) as damaged_quantity,
                        SUM(rd.damaged_weight) as damaged_weight
                 FROM roll_damages rd
                 WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                   AND (? = 0 OR rd.id != ?)
                 GROUP BY rd.seller_roll_id
             ) dmg ON sr.id = dmg.seller_roll_id
             WHERE sr.seller_id = ?
             ORDER BY sr.id ASC",
            [$excludeDamageId, $excludeDamageId, $sellerId]
        );

        Response::json([
            'success' => true,
            'rolls' => $rolls
        ]);
    }

    public function ajaxLookupDamageRoll(): void {
        $rawScan = trim((string)($_GET['code'] ?? ''));
        $scanCode = strtoupper(trim(explode('|', $rawScan, 2)[0]));
        $scanCode = ltrim($scanCode, '#');
        $scanCode = trim($scanCode);

        if ($scanCode === '') {
            Response::json(['success' => false, 'message' => 'Please provide a valid QR label or barcode.'], 400);
            return;
        }

        // 1. Check if already recorded in Production Damage Management
        $existingDamage = Database::fetchOne(
            "SELECT damage_number, damage_reason, status FROM roll_damages WHERE qr_code = ? LIMIT 1",
            [$scanCode]
        );
        if ($existingDamage) {
            Response::json([
                'success' => false,
                'is_damaged' => true,
                'message' => "QR label {$scanCode} is already recorded in damage report {$existingDamage['damage_number']} ({$existingDamage['damage_reason']}). Damaged items cannot be re-scanned as damage!",
            ], 409);
            return;
        }

        $this->lookupDamageRollForScan($scanCode);
    }

    public function ajaxLookupEditDamageRoll(): void {
        $damageId = max(0, (int)($_GET['damage_id'] ?? 0));
        if ($damageId === 0 || !Database::fetchOne("SELECT id FROM roll_damages WHERE id = ?", [$damageId])) {
            Response::json(['success' => false, 'message' => 'Damage record not found.'], 404);
            return;
        }

        $rawScan = trim((string)($_GET['code'] ?? ''));
        $scanCode = strtoupper(trim(explode('|', $rawScan, 2)[0]));
        $scanCode = trim(ltrim($scanCode, '#'));
        if (!preg_match('/^([A-Z]{2}\d{4})(\d+)$/', $scanCode, $matches)) {
            Response::json(['success' => false, 'message' => 'Scan a valid fabric roll QR label.'], 400);
            return;
        }

        $prefix = $matches[1];
        $sequence = (int)$matches[2];
        $candidates = Database::fetchAll(
            "SELECT sr.id, sr.seller_id, sr.roll_number, sr.start_code, sr.end_code, sr.start_seq, sr.end_seq,
                    s.name AS seller_name, s.company AS seller_company
             FROM seller_rolls sr
             JOIN sellers s ON s.id = sr.seller_id
             WHERE sr.start_code LIKE ? AND sr.end_code LIKE ?",
            [$prefix . '%', $prefix . '%']
        );
        $matchingRolls = array_values(array_filter($candidates, static function (array $candidate) use ($prefix, $sequence): bool {
            return strtoupper(substr((string)$candidate['start_code'], 0, 6)) === $prefix
                && strtoupper(substr((string)$candidate['end_code'], 0, 6)) === $prefix
                && $sequence >= (int)$candidate['start_seq']
                && $sequence <= (int)$candidate['end_seq'];
        }));
        if (count($matchingRolls) !== 1) {
            Response::json(['success' => false, 'message' => 'This QR label does not identify one fabric roll.'], 404);
            return;
        }

        $roll = $matchingRolls[0];
        $deletedLabel = Database::fetchOne(
            "SELECT id FROM seller_roll_deleted_labels WHERE seller_roll_id = ? AND `sequence` = ?",
            [(int)$roll['id'], $sequence]
        );
        if ($deletedLabel) {
            Response::json(['success' => false, 'message' => 'This QR label was deleted and cannot select a fabric roll.'], 404);
            return;
        }

        Response::json([
            'success' => true,
            'item_type' => 'FABRIC_ROLL',
            'seller_id' => (int)$roll['seller_id'],
            'seller_name' => $roll['seller_company'] ?: $roll['seller_name'],
            'roll_id' => (int)$roll['id'],
            'roll_number' => $roll['roll_number'],
            'code' => $scanCode,
        ]);
    }

    private function lookupDamageRollForScan(string $scanCode): void {
        // 2. Check if already recorded in Warehouse Damage Management
        $existingWhDamage = Database::fetchOne(
            "SELECT damage_number, damage_reason, status FROM warehouse_damages WHERE tag_code = ? LIMIT 1",
            [$scanCode]
        );
        if ($existingWhDamage) {
            Response::json([
                'success' => false,
                'is_damaged' => true,
                'message' => "QR label {$scanCode} is already recorded in warehouse damage report {$existingWhDamage['damage_number']} ({$existingWhDamage['damage_reason']}).",
            ], 409);
            return;
        }

        // 3. Check if roll or piece has already been outwarded to warehouse
        $outwardedTag = Database::fetchOne(
            "SELECT po.outward_number, po.dispatch_date, pb.batch_number, w.name as warehouse_name
             FROM production_batch_items pbi
             JOIN production_batches pb ON pb.id = pbi.production_batch_id
             JOIN production_outwards po ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
             WHERE (pbi.piece_tags LIKE ? OR pbi.received_qr_codes LIKE ?)
               AND (po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED'))
             LIMIT 1",
            ['%' . $scanCode . '%', '%' . $scanCode . '%']
        );
        if ($outwardedTag) {
            $whName = $outwardedTag['warehouse_name'] ?: 'Warehouse';
            Response::json([
                'success' => false,
                'is_outwarded' => true,
                'message' => "QR label {$scanCode} has already been outwarded to {$whName} under Outward #{$outwardedTag['outward_number']} (Batch {$outwardedTag['batch_number']}). Items already outwarded to warehouse cannot be reported as production damage!",
            ], 409);
            return;
        }

        $warehouseTag = Database::fetchOne(
            "SELECT wq.qr_code, wq.inwarded_at, w.name as warehouse_name
             FROM warehouse_qr_codes wq
             LEFT JOIN warehouses w ON w.id = wq.warehouse_id
             WHERE wq.qr_code = ? AND wq.inwarded_at IS NOT NULL
             LIMIT 1",
            [$scanCode]
        );
        if ($warehouseTag) {
            $whName = $warehouseTag['warehouse_name'] ?: 'Warehouse';
            Response::json([
                'success' => false,
                'is_outwarded' => true,
                'message' => "QR label {$scanCode} is already located in {$whName}. Items already in the warehouse cannot be reported as production damage!",
            ], 409);
            return;
        }

        // 4. Check if piece has already been billed / sold
        $billedTag = Database::fetchOne(
            "SELECT pb.bill_number, pb.created_at
             FROM production_bill_item_tags pbit
             JOIN production_bills pb ON pb.id = pbit.bill_id
             WHERE pbit.tag_code = ?
             LIMIT 1",
            [$scanCode]
        );
        if ($billedTag) {
            Response::json([
                'success' => false,
                'is_damaged' => true,
                'message' => "QR label {$scanCode} has already been sold and invoiced under Bill #{$billedTag['bill_number']}. Sold items cannot be reported as damage!",
            ], 409);
            return;
        }

        // =========================================================
        // CASE A: Fabric Roll Lookup
        // =========================================================
        if (preg_match('/^([A-Z]{2}\d{4})(\d+)$/', $scanCode, $matches)) {
            $prefix = $matches[1];
            $sequence = (int)$matches[2];
            $rolls = Database::fetchAll(
                "SELECT sr.id, sr.seller_id, sr.roll_number, sr.color, sr.design_name, sr.quantity, sr.weight, sr.price,
                        sr.start_seq, sr.end_seq, sr.start_code, sr.end_code,
                        s.name as seller_name, s.company as seller_company
                 FROM seller_rolls sr
                 JOIN sellers s ON s.id = sr.seller_id
                 WHERE sr.start_code LIKE ? AND sr.end_code LIKE ?
                 ORDER BY sr.id ASC",
                [$prefix . '%', $prefix . '%']
            );

            $matchesForCode = array_values(array_filter($rolls, static function (array $roll) use ($prefix, $sequence): bool {
                return strtoupper(substr((string)$roll['start_code'], 0, 6)) === $prefix
                    && strtoupper(substr((string)$roll['end_code'], 0, 6)) === $prefix
                    && $sequence >= (int)$roll['start_seq']
                    && $sequence <= (int)$roll['end_seq'];
            }));

            if (count($matchesForCode) === 1) {
                $roll = $matchesForCode[0];
                $deletedLabel = Database::fetchOne(
                    "SELECT id FROM seller_roll_deleted_labels WHERE seller_roll_id = ? AND `sequence` = ?",
                    [(int)$roll['id'], $sequence]
                );
                if ($deletedLabel) {
                    Response::json(['success' => false, 'message' => 'This QR label was deleted and cannot be used to report damage.'], 404);
                    return;
                }

                // Check if roll outwarded
                $outwardedRoll = Database::fetchOne(
                    "SELECT po.outward_number, po.dispatch_date, pb.batch_number, w.name as warehouse_name
                     FROM production_program_rolls ppr
                     JOIN production_programs pp ON pp.id = ppr.production_program_id
                     JOIN production_batches pb ON pb.program_id = pp.id
                     JOIN production_outwards po ON po.production_batch_id = pb.id
                     LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
                     WHERE ppr.seller_roll_id = ?
                       AND (po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED'))
                     LIMIT 1",
                    [(int)$roll['id']]
                );
                if ($outwardedRoll) {
                    $whName = $outwardedRoll['warehouse_name'] ?: 'Warehouse';
                    Response::json([
                        'success' => false,
                        'is_outwarded' => true,
                        'message' => "Fabric roll {$roll['roll_number']} (QR: {$scanCode}) has already been outwarded to {$whName} under Outward #{$outwardedRoll['outward_number']} (Batch {$outwardedRoll['batch_number']}). Items already outwarded to warehouse cannot be reported as production damage!",
                    ], 409);
                    return;
                }

                $totQty = max(1, (int)$roll['quantity']);
                $rollWeight = (float)$roll['weight'];
                $rollPrice = (float)$roll['price'];
                $pieceWeight = round($rollWeight / $totQty, 2);
                $pieceLoss = round($pieceWeight * ($rollWeight > 0 ? $rollPrice / $rollWeight : 0), 2);
                if ($pieceLoss <= 0) {
                    $pieceLoss = round($rollPrice / $totQty, 2);
                }

                Response::json([
                    'success' => true,
                    'item_type' => 'FABRIC_ROLL',
                    'seller_id' => (int)$roll['seller_id'],
                    'seller_name' => $roll['seller_company'] ?: $roll['seller_name'],
                    'roll_id' => (int)$roll['id'],
                    'roll_number' => $roll['roll_number'],
                    'color' => $roll['color'],
                    'design_name' => $roll['design_name'],
                    'quantity' => $totQty,
                    'weight' => $pieceWeight,
                    'price' => $pieceLoss,
                    'loss' => $pieceLoss,
                    'piece_weight' => $pieceWeight,
                    'piece_loss' => $pieceLoss,
                    'total_roll_weight' => $rollWeight,
                    'total_roll_price' => $rollPrice,
                    'code' => $scanCode,
                ]);
                return;
            }
        }

        // =========================================================
        // CASE B: Finished Product / Garment Piece Lookup
        // =========================================================
        $prodItem = null;

        // 1. Check QC Items
        $qcItem = Database::fetchOne(
            "SELECT qi.*, qb.batch_number as qc_batch_number,
                    p.id as product_id, p.name as product_name, p.code as product_code,
                    pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                    COALESCE(qi.size_name, sz.name) as size_name,
                    COALESCE(qi.color_name, cl.name) as color_name
             FROM qc_items qi
             JOIN qc_batches qb ON qb.id = qi.qc_batch_id
             LEFT JOIN product_variants pv ON pv.id = qi.variant_id
             LEFT JOIN products p ON (p.id = pv.product_id OR p.category_id = qi.category_id)
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors cl ON cl.id = pv.color_id
             WHERE qi.tag_code = ?
             LIMIT 1",
            [$scanCode]
        );
        if ($qcItem) {
            $prodItem = $qcItem;
        }

        // 2. Check Production Batch Items
        if (!$prodItem) {
            $batchItem = Database::fetchOne(
                "SELECT pbi.*, pb.batch_number,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                        sz.name as size_name, cl.name as color_name
                 FROM production_batch_items pbi
                 JOIN production_batches pb ON pb.id = pbi.production_batch_id
                 LEFT JOIN product_variants pv ON pv.id = pbi.variant_id
                 LEFT JOIN products p ON p.id = pv.product_id
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 WHERE pbi.piece_tags LIKE ? OR pbi.received_qr_codes LIKE ?
                 LIMIT 1",
                ['%' . $scanCode . '%', '%' . $scanCode . '%']
            );
            if ($batchItem) {
                $prodItem = $batchItem;
            }
        }

        // 3. Check Warehouse QR Codes
        if (!$prodItem) {
            $whQr = Database::fetchOne(
                "SELECT wq.*,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                        sz.name as size_name, cl.name as color_name
                 FROM warehouse_qr_codes wq
                 LEFT JOIN product_variants pv ON pv.id = wq.variant_id
                 LEFT JOIN products p ON p.id = pv.product_id
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 WHERE wq.qr_code = ?
                 LIMIT 1",
                [$scanCode]
            );
            if ($whQr) {
                $prodItem = $whQr;
            }
        }

        // 4. Check Product Variants by SKU or QR
        if (!$prodItem) {
            $supplyItemId = max(0, (int)($_GET['supply_item_id'] ?? 0));
            $labelLookup = $this->resolveDamageLabelCode($scanCode, $supplyItemId ?: null);
            if ($labelLookup['status'] === 'ambiguous') {
                Response::json([
                    'success' => true,
                    'needs_supply_selection' => true,
                    'code' => $scanCode,
                    'candidates' => $labelLookup['candidates'],
                ]);
                return;
            }
            $prodItem = $labelLookup['item'];
        }

        // 5. Check Product Variants by SKU or QR
        if (!$prodItem) {
            $varItem = Database::fetchOne(
                "SELECT pv.*,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                        sz.name as size_name, cl.name as color_name
                 FROM product_variants pv
                 JOIN products p ON p.id = pv.product_id
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 WHERE pv.sku = ? OR pv.qr_code = ?
                 LIMIT 1",
                [$scanCode, $scanCode]
            );
            if ($varItem) {
                $prodItem = $varItem;
            }
        }

        if ($prodItem) {
            $isSupply = !empty($prodItem['_damage_supply']);
            if ($isSupply) {
                $loss = (float)$prodItem['damage_cost'];
                $weight = (float)$prodItem['damage_weight'];
            } else {
                $loss = (float)(!empty($prodItem['selling_price']) && $prodItem['selling_price'] > 0
                    ? $prodItem['selling_price']
                    : (!empty($prodItem['mrp']) && $prodItem['mrp'] > 0
                        ? $prodItem['mrp']
                        : (!empty($prodItem['base_price']) && $prodItem['base_price'] > 0
                            ? $prodItem['base_price']
                            : (!empty($prodItem['discount_price']) && $prodItem['discount_price'] > 0
                                ? $prodItem['discount_price']
                                : (!empty($prodItem['production_cost']) && $prodItem['production_cost'] > 0
                                    ? $prodItem['production_cost']
                                    : 500.00)))));

                $weight = (float)(!empty($prodItem['variant_weight']) && $prodItem['variant_weight'] > 0
                    ? $prodItem['variant_weight']
                    : (!empty($prodItem['weight']) && $prodItem['weight'] > 0
                        ? $prodItem['weight']
                        : 0.25));
            }

            $prodName = $prodItem['product_name'] ?: 'Garment Product';
            $sizeStr = $prodItem['size_name'] ?? '';
            $colorStr = $prodItem['color_name'] ?? '';
            $rollDesc = $isSupply
                ? $prodName
                : $prodName . ($sizeStr || $colorStr ? " ({$sizeStr}" . ($colorStr ? "/{$colorStr}" : '') . ")" : '');

            Response::json([
                'success' => true,
                'item_type' => 'PRODUCT',
                'damage_kind' => $isSupply ? 'SUPPLY' : 'PRODUCT',
                'supply_item_id' => $isSupply ? (int)$prodItem['id'] : null,
                'product_id' => !empty($prodItem['product_id']) ? (int)$prodItem['product_id'] : null,
                'variant_id' => !empty($prodItem['variant_id']) ? (int)$prodItem['variant_id'] : null,
                'product_name' => $prodName,
                'sku' => $prodItem['sku'] ?? $scanCode,
                'quantity_label' => $prodItem['damage_quantity_label'] ?? null,
                'purchase_number' => $prodItem['purchase_number'] ?? null,
                'size' => $sizeStr,
                'color' => $colorStr,
                'seller_id' => null,
                'seller_name' => $isSupply ? ($prodItem['supplier_name'] ?: 'Purchased Supply') : 'Own Production',
                'roll_id' => null,
                'roll_number' => $rollDesc,
                'quantity' => 1,
                'weight' => $weight,
                'price' => $loss,
                'loss' => $loss,
                'piece_weight' => $weight,
                'piece_loss' => $loss,
                'code' => $scanCode,
            ]);
            return;
        }

        Response::json([
            'success' => false,
            'message' => "QR code {$scanCode} was not found as a fabric roll or finished product in the system.",
        ], 404);
    }

    private function findDamageProductByLabelCode(string $scanCode): ?array {
        if (!preg_match('/^([A-Z]{1,4}\d{4})(\d+)$/', strtoupper(trim($scanCode)), $codeParts)) {
            return null;
        }

        $labelPrefix = $codeParts[1];
        $sequence = (int)$codeParts[2];
        $candidates = Database::fetchAll(
            "SELECT li.start_code, li.end_code, li.start_seq, li.end_seq,
                    li.base_price, li.discount_price,
                    p.id AS product_id, p.name AS product_name,
                    pv.id AS variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost,
                    pv.weight AS variant_weight,
                    COALESCE(li.size_name, sz.name) AS size_name,
                    COALESCE(li.color_name, cl.name) AS color_name
             FROM label_items li
             JOIN product_variants pv ON pv.id = li.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors cl ON cl.id = pv.color_id
             WHERE li.start_code LIKE ? AND li.end_code LIKE ?
               AND li.start_seq <= ? AND li.end_seq >= ?
             ORDER BY li.id DESC",
            [$labelPrefix . '%', $labelPrefix . '%', $sequence, $sequence]
        );

        $matches = array_values(array_filter($candidates, static function (array $candidate) use ($labelPrefix): bool {
            return strcasecmp(substr((string)$candidate['start_code'], 0, strlen($labelPrefix)), $labelPrefix) === 0
                && strcasecmp(substr((string)$candidate['end_code'], 0, strlen($labelPrefix)), $labelPrefix) === 0;
        }));

        if (count($matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private function resolveDamageLabelCode(string $scanCode, ?int $supplyItemId = null): array {
        if ($supplyItemId !== null) {
            return $this->findDamageSupplyLabelByCode($scanCode, $supplyItemId);
        }

        $product = $this->findDamageProductByLabelCode($scanCode);
        if ($product) {
            return ['status' => 'found', 'item' => $product];
        }

        return $this->findDamageSupplyLabelByCode($scanCode);
    }

    private function findDamageSupplyLabelByCode(string $scanCode, ?int $selectedItemId = null): array {
        if (!preg_match('/^SP(\d{2})(\d{2})(\d{2,})$/', strtoupper(trim($scanCode)), $codeParts)) {
            return ['status' => 'not_found', 'item' => null];
        }

        $month = (int)$codeParts[1];
        if ($month < 1 || $month > 12) {
            return ['status' => 'not_found', 'item' => null];
        }

        $dateCode = $codeParts[1] . $codeParts[2];
        $sequence = (int)$codeParts[3];
        $supplySql =
            "SELECT ppi.id, ppi.purchase_id, ppi.item_name, ppi.quantity, ppi.unit,
                    ppi.weight_kg, ppi.unit_price, ppi.total_price,
                    pp.purchase_number, s.name AS supplier_name, s.company AS supplier_company
             FROM production_purchase_items ppi
             JOIN production_purchases pp ON pp.id = ppi.purchase_id
             LEFT JOIN sellers s ON s.id = pp.seller_id
             WHERE ppi.item_type = 'GENERAL_SUPPLY'
                AND DATE_FORMAT(pp.purchase_date, '%m%y') = ?";
           $supplyParams = [$dateCode];
           $supplySql .= " ORDER BY pp.id ASC, ppi.id ASC";
           $supplyRows = Database::fetchAll($supplySql, $supplyParams);

        $pieceSequenceByPurchase = [];
        $packageSequenceByPurchase = [];
        $matches = [];
        foreach ($supplyRows as $supply) {
            $purchaseId = (int)$supply['purchase_id'];
            $quantity = max(0.0, (float)$supply['quantity']);
            $isWholeQuantity = floor($quantity) === $quantity;
            $wholeQuantity = (int)$quantity;
            $pieceCount = $isWholeQuantity && $wholeQuantity > 1 && $wholeQuantity <= 100
                ? $wholeQuantity
                : 1;

            $pieceStart = $pieceSequenceByPurchase[$purchaseId] ?? 1;
            $pieceEnd = $pieceStart + $pieceCount - 1;
            $pieceSequenceByPurchase[$purchaseId] = $pieceEnd + 1;

            $packageSequence = $packageSequenceByPurchase[$purchaseId] ?? 1;
            $packageSequenceByPurchase[$purchaseId] = $packageSequence + 1;

            $selectedItemMatch = $sequence >= 1 && $sequence <= $pieceCount;
            $fullPurchasePieceMatch = $sequence >= $pieceStart && $sequence <= $pieceEnd;
            $fullPurchasePackageMatch = $sequence === $packageSequence;
            if (!$selectedItemMatch && !$fullPurchasePieceMatch && !$fullPurchasePackageMatch) {
                continue;
            }

            $supply['_package_label'] = $fullPurchasePackageMatch
                && !$selectedItemMatch
                && !$fullPurchasePieceMatch;
            $matches[(int)$supply['id']] = $supply;
        }

        if ($selectedItemId !== null) {
            if (!isset($matches[$selectedItemId])) {
                return ['status' => 'not_found', 'item' => null];
            }
            $matches = [$selectedItemId => $matches[$selectedItemId]];
        }

        if (count($matches) > 1) {
            $options = [];
            foreach ($matches as $match) {
                $options[] = [
                    'id' => (int)$match['id'],
                    'label' => trim(($match['item_name'] ?? 'Supply') . ' | ' . ($match['purchase_number'] ?? 'Purchase') . ' | ' . ($match['supplier_company'] ?: $match['supplier_name']) . ' | ' . $match['quantity'] . ' ' . $match['unit']),
                ];
            }
            return ['status' => 'ambiguous', 'item' => null, 'candidates' => $options];
        }
        if (!$matches) {
            return ['status' => 'not_found', 'item' => null];
        }

        $supply = reset($matches);
        $quantity = max(0.0, (float)$supply['quantity']);
        $isPackage = !empty($supply['_package_label'])
            || floor($quantity) !== $quantity
            || $quantity <= 1
            || $quantity > 100;
        $unit = trim((string)$supply['unit']);
        $supply['product_id'] = null;
        $supply['variant_id'] = null;
        $supply['product_name'] = $supply['item_name'];
        $supply['supplier_name'] = $supply['supplier_company'] ?: $supply['supplier_name'];
        $supply['sku'] = $supply['purchase_number'] . '-ITEM-' . $supply['id'];
        $supply['_damage_supply'] = true;
        $supply['damage_quantity_label'] = $isPackage
            ? rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') . ' ' . $unit
            : '1 ' . $unit;
        $supply['damage_cost'] = $isPackage
            ? (float)$supply['total_price']
            : (float)$supply['unit_price'];
        $supply['damage_weight'] = $isPackage
            ? (float)($supply['weight_kg'] ?? 0)
            : ($quantity > 0 ? (float)($supply['weight_kg'] ?? 0) / $quantity : 0.0);

        return ['status' => 'found', 'item' => $supply];
    }

    public function downloadBill(string $id): void {
        $sellerId = (int)$id;
        $seller = Database::fetchOne(
            "SELECT id, name, document_url FROM sellers WHERE id = ?",
            [$sellerId]
        );

        if (!$seller || empty($seller['document_url'])) {
            Session::flash('error', 'No invoice or bill document found for this supplier.');
            Response::back();
            return;
        }

        $documentUrl = $seller['document_url'];
        $cleanName = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($seller['name'] ?? 'Supplier'));
        $ext = strtolower(pathinfo(parse_url($documentUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'png';
        $downloadFilename = 'Supplier_Bill_' . $cleanName . '_' . $seller['id'] . '.' . $ext;

        // 1. Cloudinary hosted URL - redirect to attachment transformation
        if (str_contains($documentUrl, 'res.cloudinary.com')) {
            $attachmentUrl = downloadUrl($documentUrl, $downloadFilename);
            header('Location: ' . $attachmentUrl);
            exit;
        }

        // 2. Local file path
        if (!str_starts_with($documentUrl, 'http://') && !str_starts_with($documentUrl, 'https://')) {
            $cleanPath = ltrim(parse_url($documentUrl, PHP_URL_PATH) ?? '', '/');
            if (str_starts_with($cleanPath, 'public/')) {
                $cleanPath = substr($cleanPath, 7);
            }
            $localPath = BASE_PATH . '/public/' . $cleanPath;
            if (!file_exists($localPath)) {
                $localPath = BASE_PATH . '/' . $cleanPath;
            }

            if (file_exists($localPath) && is_file($localPath)) {
                $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                header('Content-Length: ' . filesize($localPath));
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                if (ob_get_level()) {
                    ob_end_clean();
                }
                flush();
                readfile($localPath);
                exit;
            }
        }

        // 3. Remote URL fallback via cURL streaming
        if (str_starts_with($documentUrl, 'http://') || str_starts_with($documentUrl, 'https://')) {
            $ch = curl_init($documentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $fileData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode === 200 && $fileData !== false) {
                header('Content-Description: File Transfer');
                header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                header('Content-Length: ' . strlen($fileData));
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                if (ob_get_level()) {
                    ob_end_clean();
                }
                flush();
                echo $fileData;
                exit;
            }
        }

        Response::redirect($documentUrl);
    }

    /**
     * Download or stream fabric roll photo.
     */
    public function downloadRollPhoto(string $id): void {
        $rollId = (int)$id;
        $roll = Database::fetchOne(
            "SELECT sr.id, sr.roll_number, sr.photo_url, s.name as seller_name 
             FROM seller_rolls sr 
             LEFT JOIN sellers s ON sr.seller_id = s.id 
             WHERE sr.id = ?",
            [$rollId]
        );

        if (!$roll || empty($roll['photo_url'])) {
            Session::flash('error', 'No photo found for this roll.');
            Response::back();
            return;
        }

        $photoUrl = $roll['photo_url'];
        $cleanRollNum = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($roll['roll_number'] ?? 'Roll'));
        $ext = strtolower(pathinfo(parse_url($photoUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        $downloadFilename = 'Roll_Photo_' . $cleanRollNum . '_' . $roll['id'] . '.' . $ext;

        if (str_contains($photoUrl, 'res.cloudinary.com')) {
            $attachmentUrl = downloadUrl($photoUrl, $downloadFilename);
            header('Location: ' . $attachmentUrl);
            exit;
        }

        if (!str_starts_with($photoUrl, 'http://') && !str_starts_with($photoUrl, 'https://')) {
            $cleanPath = ltrim(parse_url($photoUrl, PHP_URL_PATH) ?? '', '/');
            if (str_starts_with($cleanPath, 'public/')) {
                $cleanPath = substr($cleanPath, 7);
            }
            $localPath = BASE_PATH . '/public/' . $cleanPath;
            if (!file_exists($localPath)) {
                $localPath = BASE_PATH . '/' . $cleanPath;
            }

            if (file_exists($localPath) && is_file($localPath)) {
                $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                header('Content-Length: ' . filesize($localPath));
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                if (ob_get_level()) {
                    ob_end_clean();
                }
                flush();
                readfile($localPath);
                exit;
            }
        }

        Response::redirect($photoUrl);
    }

    /**
     * Preview and print all fabric roll labels for a seller lot.
     */
    public function printSellerLabels(string $id): void {
        $sellerId = (int)$id;
        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [$sellerId]);
        if (!$seller) {
            Response::error(404, 'Material supplier not found.');
            return;
        }

        $rolls = Database::fetchAll("SELECT * FROM seller_rolls WHERE seller_id = ? ORDER BY id ASC", [$sellerId]);
        $stickers = $this->buildStickersForRolls($seller, $rolls);

        Response::viewOnly('production.sellers.labels', [
            'seller'     => $seller,
            'rolls'      => $rolls,
            'stickers'   => $stickers,
            'isLot'      => true,
            'pageTitle'  => 'Fabric Roll Labels — ' . ($seller['company'] ?: $seller['name']),
        ]);
    }

    /**
     * Preview and print labels for a single fabric roll.
     */
    public function printSellerRollLabel(string $id): void {
        $rollId = (int)$id;
        $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [$rollId]);
        if (!$roll) {
            Response::error(404, 'Fabric roll not found.');
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [(int)$roll['seller_id']]);
        $stickers = $this->buildStickersForRolls($seller, [$roll]);

        Response::viewOnly('production.sellers.labels', [
            'seller'     => $seller,
            'rolls'      => [$roll],
            'stickers'   => $stickers,
            'isLot'      => false,
            'singleRoll' => $roll,
            'pageTitle'  => 'Fabric Roll Label — ' . $roll['roll_number'],
        ]);
    }

    /**
     * AJAX endpoint to fetch fabric roll stickers JSON.
     */
    public function ajaxSellerRollLabels(string $id): void {
        $rollId = !empty($_GET['roll_id']) ? (int)$_GET['roll_id'] : 0;
        if ($rollId > 0) {
            $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [$rollId]);
            if (!$roll) {
                Response::json(['success' => false, 'message' => 'Fabric roll not found.'], 404);
                return;
            }
            $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [(int)$roll['seller_id']]);
            $rolls = [$roll];
        } else {
            $sellerId = (int)$id;
            $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [$sellerId]);
            if (!$seller) {
                Response::json(['success' => false, 'message' => 'Material supplier not found.'], 404);
                return;
            }
            $rolls = Database::fetchAll("SELECT * FROM seller_rolls WHERE seller_id = ? ORDER BY id ASC", [$sellerId]);
        }

        $stickers = $this->buildStickersForRolls($seller, $rolls);

        Response::json([
            'success'  => true,
            'seller'   => $seller,
            'rolls'    => $rolls,
            'stickers' => $stickers,
        ]);
    }

    /**
     * AJAX endpoint for live next sequence codes preview.
     */
    /**
     * Generate extra continuation label(s) for a seller roll.
     * Continues from the previous sequence code and updates quantity/end_code.
     */
    public function addExtraRollLabel(string $id): void {
        if (!CSRF::validateRequest()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Invalid security token or session expired.'], 403);
                return;
            }
            Session::flash('error', 'Invalid security token or session expired.');
            Response::back();
            return;
        }

        $rollId = (int)$id;
        $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [$rollId]);
        if (!$roll) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Fabric roll not found.'], 404);
                return;
            }
            Session::flash('error', 'Fabric roll not found.');
            Response::back();
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [(int)$roll['seller_id']]);
        if (!$seller) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Material supplier not found.'], 404);
                return;
            }
            Session::flash('error', 'Material supplier not found.');
            Response::back();
            return;
        }

        $count = max(1, min(50, (int)($_POST['count'] ?? 1)));
        $currentQty = max(1, (int)($roll['quantity'] ?? 1));
        $currentEndSeq = (int)($roll['end_seq'] ?? 0);
        $prefix = !empty($roll['start_code']) ? substr($roll['start_code'], 0, 6) : ('FB' . date('my'));

        if ($currentEndSeq <= 0) {
            $seqInfo = ProductionService::getNextFabricRollSequence($currentQty, $roll['created_at'] ?? null);
            $currentEndSeq = (int)$seqInfo['end_seq'];
            $prefix = $seqInfo['prefix'];
        }

        $targetStartSeq = $currentEndSeq + 1;
        $conflict = Database::fetchOne(
            "SELECT id, roll_number FROM seller_rolls WHERE id != ? AND (
                (? BETWEEN start_seq AND end_seq) OR 
                start_code = ? OR end_code = ?
            ) LIMIT 1",
            [$rollId, $targetStartSeq, sprintf('%s%02d', $prefix, $targetStartSeq), sprintf('%s%02d', $prefix, $targetStartSeq)]
        );

        if ($conflict) {
            $globalSeq = ProductionService::getNextFabricRollSequence($count);
            $newStartSeq = (int)$globalSeq['start_seq'];
            $newEndSeq = (int)$globalSeq['end_seq'];
        } else {
            $newStartSeq = $targetStartSeq;
            $newEndSeq = $targetStartSeq + $count - 1;
        }

        $newQty = $currentQty + $count;
        $startCode = !empty($roll['start_code']) ? $roll['start_code'] : sprintf('%s%02d', $prefix, (int)($roll['start_seq'] ?? 1));
        $newEndCode = sprintf('%s%02d', $prefix, $newEndSeq);
        $newQrCode = ($newQty > 1) ? "{$startCode}-{$newEndCode}" : $startCode;

        Database::execute(
            "UPDATE seller_rolls SET 
                quantity = ?, 
                end_seq = ?, 
                end_code = ?, 
                qr_code = ? 
             WHERE id = ?",
            [$newQty, $newEndSeq, $newEndCode, $newQrCode, $rollId]
        );

        // Recalculate seller summary totals
        $allRolls = Database::fetchAll("SELECT * FROM seller_rolls WHERE seller_id = ?", [$seller['id']]);
        $computedRollCount = count($allRolls);
        $computedTotalWeight = array_sum(array_column($allRolls, 'weight'));
        $computedTotalPrice = array_sum(array_column($allRolls, 'price'));

        Database::execute(
            "UPDATE sellers SET 
                roll_count = ?, 
                roll_weight = ?, 
                roll_price = ? 
             WHERE id = ?",
            [$computedRollCount, $computedTotalWeight, $computedTotalPrice, $seller['id']]
        );

        $supplierName = mb_strtoupper(trim($seller['company'] ?: ($seller['name'] ?? 'GRM FABRIC')));
        $materialType = mb_strtoupper(trim($seller['material_type'] ?? 'STANDARD FABRIC'));
        $rollNum = $roll['roll_number'] ?: 'ROLL';
        $color = mb_strtoupper(trim($roll['color'] ?? ''));
        $design = mb_strtoupper(trim($roll['design_name'] ?? ''));
        $rollWeight = (float)($roll['weight'] ?? 0);
        $pieceWeight = $newQty > 0 ? round($rollWeight / $newQty, 2) : 0.0;
        $price = (float)($roll['price'] ?? 0);
        $photo = !empty($roll['photo_url']) ? $roll['photo_url'] : ($seller['roll_photo'] ?? null);

        $newStickers = [];
        for ($i = 0; $i < $count; $i++) {
            $seq = $newStartSeq + $i;
            $code = sprintf('%s%02d', $prefix, $seq);
            $payload = $code;
            $pieceIndex = $currentQty + $i + 1;

            $newStickers[] = [
                'code'          => $code,
                'seq'           => $seq,
                'piece_index'   => $pieceIndex,
                'total_qty'     => $newQty,
                'roll_id'       => $rollId,
                'roll_number'   => $rollNum,
                'color'         => $color,
                'design_name'   => $design,
                'weight'        => $pieceWeight,
                'roll_weight'   => $rollWeight,
                'price'         => $price,
                'photo_url'     => $photo,
                'supplier_name' => $supplierName,
                'material_type' => $materialType,
                'qr_payload'    => $payload,
                'date'          => date('d M Y'),
            ];
        }

        AuditService::log('production', 'UPDATE', 'seller_rolls', $rollId, [
            'action'        => 'add_extra_labels',
            'roll_number'   => $rollNum,
            'added_count'   => $count,
            'new_quantity'  => $newQty,
            'new_end_code'  => $newEndCode
        ]);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
            Response::json([
                'success'      => true,
                'message'      => "Generated {$count} extra label(s) for {$rollNum} (Code: {$newEndCode}).",
                'new_stickers' => $newStickers,
                'roll'         => [
                    'id'        => $rollId,
                    'quantity'  => $newQty,
                    'end_seq'   => $newEndSeq,
                    'end_code'  => $newEndCode,
                    'qr_code'   => $newQrCode,
                ]
            ]);
            return;
        }

        Session::flash('success', "Generated {$count} extra continuation label(s) for Roll {$rollNum} (ending at {$newEndCode}).");
        Response::back();
    }

    /**
     * Delete / remove extra label(s) from a seller roll.
     * Decreases quantity and adjusts end_seq/end_code accordingly.
     */
    public function deleteExtraRollLabel(string $id): void {
        if (!CSRF::validateRequest()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Invalid security token or session expired.'], 403);
                return;
            }
            Session::flash('error', 'Invalid security token or session expired.');
            Response::back();
            return;
        }

        $rollId = (int)$id;
        $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ?", [$rollId]);
        if (!$roll) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Fabric roll not found.'], 404);
                return;
            }
            Session::flash('error', 'Fabric roll not found.');
            Response::back();
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM sellers WHERE id = ?", [(int)$roll['seller_id']]);
        if (!$seller) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
                Response::json(['success' => false, 'message' => 'Material supplier not found.'], 404);
                return;
            }
            Session::flash('error', 'Material supplier not found.');
            Response::back();
            return;
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax']);
        $currentQty = max(1, (int)($roll['quantity'] ?? 1));
        $startSeq = (int)($roll['start_seq'] ?? 1);
        $endSeq = (int)($roll['end_seq'] ?? 0);
        if ($endSeq < $startSeq) {
            $endSeq = $startSeq + $currentQty - 1;
        }

        $requestedSequences = $_POST['sequences'] ?? [];
        if (!is_array($requestedSequences) || empty($requestedSequences)) {
            $deleteCount = max(1, (int)($_POST['count'] ?? 1));
            $existingDeleted = array_map(
                'intval',
                array_column(
                    Database::fetchAll("SELECT `sequence` FROM seller_roll_deleted_labels WHERE seller_roll_id = ?", [$rollId]),
                    'sequence'
                )
            );
            $deletedLookup = array_fill_keys($existingDeleted, true);
            $requestedSequences = [];
            for ($sequence = $endSeq; $sequence >= $startSeq && count($requestedSequences) < $deleteCount; $sequence--) {
                if (!isset($deletedLookup[$sequence])) {
                    $requestedSequences[] = $sequence;
                }
            }
        }

        $requestedSequences = array_values(array_unique(array_filter(
            array_map('intval', $requestedSequences),
            static fn(int $sequence): bool => $sequence > 0
        )));
        if (empty($requestedSequences)) {
            $message = 'No valid label sequence was selected for deletion.';
            if ($isAjax) {
                Response::json(['success' => false, 'message' => $message], 400);
                return;
            }
            Session::flash('error', $message);
            Response::back();
            return;
        }

        try {
            Database::beginTransaction();
            $lockedRoll = Database::fetchOne("SELECT quantity, start_seq, end_seq FROM seller_rolls WHERE id = ? FOR UPDATE", [$rollId]);
            if (!$lockedRoll) {
                Database::rollback();
                Response::json(['success' => false, 'message' => 'Fabric roll not found.'], 404);
                return;
            }
            $currentQty = max(1, (int)($lockedRoll['quantity'] ?? 1));
            $startSeq = (int)($lockedRoll['start_seq'] ?? 1);
            $endSeq = (int)($lockedRoll['end_seq'] ?? 0);
            if ($endSeq < $startSeq) {
                $endSeq = $startSeq + $currentQty - 1;
            }

            $existingDeleted = array_map(
                'intval',
                array_column(
                    Database::fetchAll("SELECT `sequence` FROM seller_roll_deleted_labels WHERE seller_roll_id = ?", [$rollId]),
                    'sequence'
                )
            );
            $deletedLookup = array_fill_keys($existingDeleted, true);
            foreach ($requestedSequences as $sequence) {
                if ($sequence < $startSeq || $sequence > $endSeq || isset($deletedLookup[$sequence])) {
                    Database::rollback();
                    Response::json(['success' => false, 'message' => 'A selected label is no longer available. Refresh the page and try again.'], 409);
                    return;
                }
            }

            if ($currentQty - count($requestedSequences) < 1) {
                Database::rollback();
                $message = 'Cannot delete the only label for this roll. Minimum 1 label is required.';
                if ($isAjax) {
                    Response::json(['success' => false, 'message' => $message], 400);
                    return;
                }
                Session::flash('error', $message);
                Response::back();
                return;
            }

            foreach ($requestedSequences as $sequence) {
                Database::execute(
                    "INSERT INTO seller_roll_deleted_labels (seller_roll_id, `sequence`) VALUES (?, ?)",
                    [$rollId, $sequence]
                );
            }

            $deleteCount = count($requestedSequences);
            $newQty = $currentQty - $deleteCount;
            Database::execute("UPDATE seller_rolls SET quantity = ? WHERE id = ?", [$newQty, $rollId]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }

        // Recalculate seller summary totals
        $allRolls = Database::fetchAll("SELECT * FROM seller_rolls WHERE seller_id = ?", [$seller['id']]);
        $computedRollCount = count($allRolls);
        $computedTotalWeight = array_sum(array_column($allRolls, 'weight'));
        $computedTotalPrice = array_sum(array_column($allRolls, 'price'));

        Database::execute(
            "UPDATE sellers SET 
                roll_count = ?, 
                roll_weight = ?, 
                roll_price = ? 
             WHERE id = ?",
            [$computedRollCount, $computedTotalWeight, $computedTotalPrice, $seller['id']]
        );

        AuditService::log('production', 'UPDATE', 'seller_rolls', $rollId, [
            'action'        => 'delete_extra_labels',
            'roll_number'   => $roll['roll_number'],
            'removed_count' => $deleteCount,
            'deleted_sequences' => $requestedSequences,
            'new_quantity'      => $newQty
        ]);

        if ($isAjax) {
            Response::json([
                'success'      => true,
                'message'      => "Removed {$deleteCount} label(s). Roll now has {$newQty} label(s).",
                'removed_count'=> $deleteCount,
                'new_quantity' => $newQty,
                'deleted_sequences' => $requestedSequences,
                'roll'         => [
                    'id'        => $rollId,
                    'quantity'  => $newQty,
                    'end_seq'   => $endSeq,
                    'end_code'  => $roll['end_code'],
                    'qr_code'   => $roll['qr_code'],
                ]
            ]);
            return;
        }

        Session::flash('success', "Removed {$deleteCount} label(s). Roll now has {$newQty} piece(s).");
        Response::back();
    }

    public function ajaxNextFabricRollCodes(): void {
        $qty = max(1, (int)($_GET['qty'] ?? 1));
        $seqData = ProductionService::getNextFabricRollSequence($qty);
        Response::json([
            'success' => true,
            'data'    => $seqData,
        ]);
    }

    /**
     * Helper to build sticker array for fabric rolls.
     */
    private function buildStickersForRolls(array $seller, array $rolls): array {
        $stickers = [];
        $supplierName = mb_strtoupper(trim($seller['company'] ?: ($seller['name'] ?? 'GRM FABRIC')));
        foreach ($rolls as &$r) {
            $qty = max(1, (int)($r['quantity'] ?? 1));
            
            // Auto-heal missing code if needed
            if (empty($r['start_code'])) {
                $seqInfo = ProductionService::getNextFabricRollSequence($qty, $r['created_at'] ?? null);
                Database::execute(
                    "UPDATE seller_rolls SET start_seq = ?, end_seq = ?, start_code = ?, end_code = ?, qr_code = ? WHERE id = ?",
                    [$seqInfo['start_seq'], $seqInfo['end_seq'], $seqInfo['start_code'], $seqInfo['end_code'], $seqInfo['qr_code'], $r['id']]
                );
                $r['start_seq'] = $seqInfo['start_seq'];
                $r['end_seq'] = $seqInfo['end_seq'];
                $r['start_code'] = $seqInfo['start_code'];
                $r['end_code'] = $seqInfo['end_code'];
                $r['qr_code'] = $seqInfo['qr_code'];
            }

            $startSeq = (int)($r['start_seq'] ?? 1);
            $endSeq = (int)($r['end_seq'] ?? 0);
            if ($endSeq < $startSeq) {
                $endSeq = $startSeq + $qty - 1;
            }
            $prefix = substr($r['start_code'], 0, 6);

            $rollNum = $r['roll_number'] ?: 'ROLL';
            $color = mb_strtoupper(trim($r['color'] ?? ''));
            $design = mb_strtoupper(trim($r['design_name'] ?? ''));
            $materialType = mb_strtoupper(trim($r['material_type'] ?? $seller['material_type'] ?? 'STANDARD FABRIC'));
            $weight = (float)($r['weight'] ?? 0);
            $price = (float)($r['price'] ?? 0);
            $photo = !empty($r['photo_url']) ? $r['photo_url'] : ($seller['roll_photo'] ?? null);
            $deletedSequences = array_fill_keys(
                array_map(
                    'intval',
                    array_column(
                        Database::fetchAll("SELECT `sequence` FROM seller_roll_deleted_labels WHERE seller_roll_id = ?", [(int)$r['id']]),
                        'sequence'
                    )
                ),
                true
            );
            $damagedCodes = array_fill_keys(
                array_map(
                    static fn(array $damage): string => strtoupper(trim((string)$damage['qr_code'])),
                    Database::fetchAll(
                        "SELECT qr_code FROM roll_damages
                         WHERE seller_roll_id = ? AND qr_code IS NOT NULL AND TRIM(qr_code) <> ''",
                        [(int)$r['id']]
                    )
                ),
                true
            );
            $activeSequences = [];
            for ($seq = $startSeq; $seq <= $endSeq; $seq++) {
                if (!isset($deletedSequences[$seq])) {
                    $activeSequences[] = $seq;
                }
            }
            $pieceWeight = count($activeSequences) > 0 ? round($weight / count($activeSequences), 2) : 0.0;

            $poNumber = null;
            if (!empty($r['id'])) {
                try {
                    $pRow = Database::fetchOne(
                        "SELECT pp.purchase_number
                         FROM production_purchase_items ppi
                         JOIN production_purchases pp ON pp.id = ppi.purchase_id
                         WHERE ppi.seller_roll_id = ?
                         LIMIT 1",
                        [(int)$r['id']]
                    );
                    if (!empty($pRow['purchase_number'])) {
                        $poNumber = $pRow['purchase_number'];
                    }
                } catch (\Throwable $e) {}
            }
            if (!$poNumber) {
                $poNumber = $rollNum;
            }

            foreach ($activeSequences as $i => $seq) {
                $code = sprintf('%s%02d', $prefix, $seq);
                $payload = $code;

                $stickers[] = [
                    'code'          => $code,
                    'seq'           => $seq,
                    'piece_index'   => $i + 1,
                    'total_qty'     => count($activeSequences),
                    'roll_id'       => $r['id'] ?? 0,
                    'roll_number'   => $rollNum,
                    'po_number'     => $poNumber,
                    'color'         => $color,
                    'design_name'   => $design,
                    'weight'        => $pieceWeight,
                    'roll_weight'   => $weight,
                    'price'         => $price,
                    'photo_url'     => $photo,
                    'supplier_name' => $supplierName,
                    'material_type' => $materialType,
                    'qr_payload'    => $payload,
                    'in_damage_module' => isset($damagedCodes[strtoupper($code)]),
                    'date'          => !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : date('d M Y'),
                ];
            }
        }
        unset($r);

        return $stickers;
    }

    // =========================================================================
    // FABRIC & ROLL DAMAGES / DEFECT MANAGEMENT
    // =========================================================================

    private function getDamageReasonOptions(): array {
        $legacyReasons = [
            'Fabric Stain / Oil Dirt',
            'Weaving / Knitting Defect',
            'Color Bleed / Shade Variation',
            'Torn / Hole / Cut Fabric',
            'Moisture / Water Damage',
            'Needle / Machinery Tear',
            'Shortage / Misprint Defect',
            'Other Fabric Damage',
        ];
        $savedReasons = Database::fetchAll(
            "SELECT reason AS damage_reason FROM roll_damage_reasons
             UNION
             SELECT DISTINCT TRIM(damage_reason) AS damage_reason
             FROM roll_damages
             WHERE damage_reason IS NOT NULL AND TRIM(damage_reason) <> ''
             ORDER BY damage_reason ASC"
        );

        $reasonsByNormalizedName = [];
        foreach (array_merge($legacyReasons, array_column($savedReasons, 'damage_reason')) as $reason) {
            $reason = trim((string)$reason);
            if ($reason !== '') {
                $reasonsByNormalizedName[mb_strtolower($reason)] = $reason;
            }
        }

        $reasons = array_values($reasonsByNormalizedName);
        natcasesort($reasons);
        return array_map(
            static fn(string $reason): array => ['damage_reason' => $reason],
            array_values($reasons)
        );
    }

    public function damageList(): void {
        self::ensureDatabaseSchema();
        $search = trim($_GET['search'] ?? '');
        $sellerId = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : null;
        $reason = trim($_GET['reason'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $resolutionType = trim($_GET['resolution_type'] ?? '');
        $damageReasons = $this->getDamageReasonOptions();

        $sql = "SELECT rd.*, 
                       s.name as seller_name, 
                       s.company as seller_company,
                       sr.roll_number, 
                       sr.color as roll_color, 
                       sr.design_name as roll_design,
                       sr.quantity as initial_roll_quantity,
                       sr.weight as initial_roll_weight,
                       sr.price as initial_roll_price,
                       sr.photo_url as roll_photo_url,
                       sr.qr_code as roll_qr_code,
                       sr.start_code as roll_start_code,
                       sr.end_code as roll_end_code,
                       sr.hsn_code as roll_hsn_code,
                       sr.design_count as roll_design_count,
                       sr.material_type as roll_material_type,
                       p.name as catalog_product_name,
                       pv.sku as variant_sku,
                       sz.name as variant_size_name,
                       cl.name as variant_color_name,
                       u_rep.full_name as reported_by_name,
                       u_res.full_name as resolved_by_name,
                       repl_sr.roll_number as replacement_roll_code,
                       repl_sr.color as replacement_roll_color,
                       repl_sr.design_name as replacement_roll_design,
                       repl_sr.design_count as replacement_roll_design_count,
                       repl_sr.quantity as replacement_roll_quantity,
                       repl_sr.weight as replacement_roll_weight,
                       repl_sr.price as replacement_roll_price,
                       repl_sr.hsn_code as replacement_roll_hsn_code,
                       repl_sr.photo_url as replacement_roll_photo_url,
                       (SELECT ppi.gst_type FROM production_purchase_items ppi WHERE ppi.seller_roll_id = repl_sr.id ORDER BY ppi.id DESC LIMIT 1) as replacement_gst_type,
                       (SELECT ppi.gst_type FROM production_purchase_items ppi WHERE ppi.seller_roll_id = sr.id ORDER BY ppi.id DESC LIMIT 1) as source_roll_gst_type,
                       (SELECT ppi.hsn_code FROM production_purchase_items ppi WHERE ppi.seller_roll_id = sr.id ORDER BY ppi.id DESC LIMIT 1) as source_roll_purchase_hsn_code
                FROM roll_damages rd
                LEFT JOIN sellers s ON rd.seller_id = s.id
                LEFT JOIN seller_rolls sr ON rd.seller_roll_id = sr.id
                LEFT JOIN product_variants pv ON rd.variant_id = pv.id
                LEFT JOIN products p ON (rd.product_id = p.id OR pv.product_id = p.id)
                LEFT JOIN sizes sz ON pv.size_id = sz.id
                LEFT JOIN colors cl ON pv.color_id = cl.id
                LEFT JOIN users u_rep ON rd.reported_by = u_rep.id
                LEFT JOIN users u_res ON rd.resolved_by = u_res.id
                LEFT JOIN seller_rolls repl_sr ON rd.replacement_roll_id = repl_sr.id
                WHERE 1=1";
        
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                rd.damage_number LIKE ?
                OR rd.qr_code LIKE ?
                OR s.name LIKE ?
                OR s.company LIKE ?
                OR sr.roll_number LIKE ?
                OR sr.color LIKE ?
                OR sr.design_name LIKE ?
                OR rd.product_name LIKE ?
                OR p.name LIKE ?
                OR pv.sku LIKE ?
                OR rd.damage_reason LIKE ?
                OR rd.defect_notes LIKE ?
                OR rd.debit_note_number LIKE ?
                OR rd.replacement_roll_number LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        if ($sellerId) {
            $sql .= " AND rd.seller_id = ?";
            $params[] = $sellerId;
        }

        if ($reason !== '') {
            $sql .= " AND rd.damage_reason = ?";
            $params[] = $reason;
        }

        if ($status !== '') {
            $sql .= " AND rd.status = ?";
            $params[] = $status;
        }

        if ($resolutionType !== '') {
            $sql .= " AND rd.resolution_type = ?";
            $params[] = $resolutionType;
        }

        $sql .= " ORDER BY rd.created_at DESC";
        $damages = Database::fetchAll($sql, $params);

        // Overall KPI stats
        $allDamages = Database::fetchAll(
            "SELECT rd.status, rd.resolution_type, rd.damaged_quantity, rd.damaged_weight, rd.loss_amount, rd.refund_amount
             FROM roll_damages rd"
        );

        $stats = [
            'total_damaged_records'  => count($allDamages),
            'total_damaged_weight'   => (float)array_sum(array_column($allDamages, 'damaged_weight')),
            'total_loss_amount'      => (float)array_sum(array_column($allDamages, 'loss_amount')),
            'total_refund_received'  => (float)array_sum(array_column($allDamages, 'refund_amount')),
            'count_logged'           => count(array_filter($allDamages, fn($d) => $d['status'] === 'LOGGED')),
            'count_return_initiated' => count(array_filter($allDamages, fn($d) => in_array($d['status'], ['RETURN_INITIATED', 'RETURNED_TO_SELLER']))),
            'count_refunded'         => count(array_filter($allDamages, fn($d) => $d['status'] === 'REFUND_RECEIVED')),
            'count_replacement'      => count(array_filter($allDamages, fn($d) => $d['status'] === 'REPLACEMENT_RECEIVED')),
            'count_closed'           => count(array_filter($allDamages, fn($d) => $d['status'] === 'CLOSED')),
        ];

        // Suppliers list for dropdown
        $sellers = Database::fetchAll("SELECT id, name, company FROM sellers WHERE seller_type = 'MATERIAL_SUPPLIER' AND production_deleted_at IS NULL ORDER BY name ASC");
        $hsnCodes = Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC");

        Response::view('production.damages.index', [
            'pageTitle'      => 'Fabric & Roll Damage Management',
            'activeModule'   => 'production',
            'activeMenu'     => 'prod-damages',
            'damages'        => $damages,
            'stats'          => $stats,
            'sellers'        => $sellers,
            'hsnCodes'       => $hsnCodes,
            'search'         => $search,
            'sellerId'       => $sellerId,
            'reason'         => $reason,
            'status'         => $status,
            'resolutionType' => $resolutionType,
            'damageReasons'  => $damageReasons,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Damages'],
            ],
        ]);
    }

    public function storeDamageReason(): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid security token or session expired.'], 403);
        }

        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            Response::json(['success' => false, 'message' => 'Please enter a damage reason.'], 400);
        }
        if (mb_strlen($reason) > 255) {
            Response::json(['success' => false, 'message' => 'Damage reason must not exceed 255 characters.'], 400);
        }

        try {
            Database::execute("INSERT IGNORE INTO roll_damage_reasons (reason) VALUES (?)", [$reason]);
            $savedReason = Database::fetchOne(
                "SELECT reason FROM roll_damage_reasons WHERE reason = ?",
                [$reason]
            );
            if (!$savedReason) {
                throw new RuntimeException('Saved damage reason could not be read back.');
            }

            Response::json(['success' => true, 'reason' => $savedReason['reason']]);
        } catch (\Throwable $e) {
            error_log('Unable to save Production damage reason: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Unable to save this damage reason. Please try again.'], 500);
        }
    }

    public function storeDamage(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $submittedQrCodes = $_POST['qr_codes'] ?? [];
        if (!is_array($submittedQrCodes)) {
            $submittedQrCodes = [];
        }
        $submittedSupplyItemIds = $_POST['damage_supply_item_ids'] ?? [];
        if (!is_array($submittedSupplyItemIds)) {
            $submittedSupplyItemIds = [];
        }
        if (empty($submittedQrCodes) && !empty($_POST['qr_code'])) {
            $submittedQrCodes = [$_POST['qr_code']];
        }
        $qrEntries = [];
        $seenQrCodes = [];
        foreach ($submittedQrCodes as $scanIndex => $submittedCode) {
            $code = strtoupper(trim(explode('|', trim((string)$submittedCode), 2)[0]));
            $code = ltrim($code, '#');
            $code = trim($code);
            if ($code !== '' && !isset($seenQrCodes[$code])) {
                $seenQrCodes[$code] = true;
                $qrEntries[] = [
                    'code' => $code,
                    'scan_index' => (string)$scanIndex,
                    'supply_item_id' => max(0, (int)($submittedSupplyItemIds[$scanIndex] ?? 0)),
                ];
            }
        }
        $submittedReasons = $_POST['damage_reasons'] ?? [];
        if (!is_array($submittedReasons)) {
            $submittedReasons = [];
        }
        $legacyDamageReason = trim($_POST['damage_reason'] ?? '');
        $defectNotes = trim($_POST['defect_notes'] ?? '');

        if (empty($qrEntries)) {
            Session::flash('error', 'Scan at least one valid fabric roll or finished product QR label before recording damage.');
            Response::back();
            return;
        }

        $damageItems = [];
        $newDamageCountByRoll = [];
        foreach ($qrEntries as $qrEntry) {
            $qrCode = $qrEntry['code'];
            $existingQrDamage = Database::fetchOne(
                "SELECT damage_number FROM roll_damages WHERE qr_code = ? LIMIT 1",
                [$qrCode]
            );
            if ($existingQrDamage) {
                Session::flash('error', "QR label {$qrCode} is already recorded in damage report {$existingQrDamage['damage_number']}.");
                Response::back();
                return;
            }

            // A. Check Fabric Roll
            $isRoll = false;
            if (preg_match('/^([A-Z]{2}\d{4})(\d+)$/', $qrCode, $qrMatches)) {
                $qrPrefix = $qrMatches[1];
                $qrSequence = (int)$qrMatches[2];
                $candidates = Database::fetchAll(
                    "SELECT sr.*, s.name AS seller_name, s.company AS seller_company
                     FROM seller_rolls sr
                     JOIN sellers s ON s.id = sr.seller_id
                     WHERE sr.start_code LIKE ? AND sr.end_code LIKE ?",
                    [$qrPrefix . '%', $qrPrefix . '%']
                );
                $matchingRolls = array_values(array_filter($candidates, static function (array $candidate) use ($qrPrefix, $qrSequence): bool {
                    return strtoupper(substr((string)$candidate['start_code'], 0, 6)) === $qrPrefix
                        && strtoupper(substr((string)$candidate['end_code'], 0, 6)) === $qrPrefix
                        && $qrSequence >= (int)$candidate['start_seq']
                        && $qrSequence <= (int)$candidate['end_seq'];
                }));

                if (count($matchingRolls) === 1) {
                    $roll = $matchingRolls[0];
                    $sellerRollId = (int)$roll['id'];
                    $deletedLabel = Database::fetchOne(
                        "SELECT id FROM seller_roll_deleted_labels WHERE seller_roll_id = ? AND `sequence` = ?",
                        [$sellerRollId, $qrSequence]
                    );
                    if ($deletedLabel) {
                        Session::flash('error', "QR label {$qrCode} was deleted and cannot be used to report damage.");
                        Response::back();
                        return;
                    }

                    $rollQty = max(1, (int)($roll['quantity'] ?? 1));
                    $rollWeight = (float)$roll['weight'];
                    $rollPrice = (float)$roll['price'];
                    $damagedWeight = round($rollWeight / $rollQty, 2);
                    $ratePerKg = $rollWeight > 0 ? ($rollPrice / $rollWeight) : 0.0;
                    $lossAmount = round($damagedWeight * $ratePerKg, 2);
                    if ($lossAmount <= 0) {
                        $lossAmount = round($rollPrice / $rollQty, 2);
                    }

                    $damageItems[] = [
                        'item_type' => 'FABRIC_ROLL',
                        'qr_code' => $qrCode,
                        'scan_index' => $qrEntry['scan_index'],
                        'seller_id' => (int)$roll['seller_id'],
                        'seller_roll_id' => $sellerRollId,
                        'product_id' => null,
                        'variant_id' => null,
                        'product_name' => null,
                        'variant_info' => null,
                        'roll_number' => $roll['roll_number'],
                        'damaged_weight' => $damagedWeight,
                        'loss_amount' => $lossAmount,
                    ];
                    $newDamageCountByRoll[$sellerRollId] = ($newDamageCountByRoll[$sellerRollId] ?? 0) + 1;
                    $isRoll = true;
                }
            }

            // B. Check Finished Products if not a roll
            if (!$isRoll) {
                $prodItem = Database::fetchOne(
                    "SELECT qi.*, p.id as product_id, p.name as product_name, pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                            COALESCE(qi.size_name, sz.name) as size_name, COALESCE(qi.color_name, cl.name) as color_name
                     FROM qc_items qi
                     JOIN qc_batches qb ON qb.id = qi.qc_batch_id
                     LEFT JOIN product_variants pv ON pv.id = qi.variant_id
                     LEFT JOIN products p ON (p.id = pv.product_id OR p.category_id = qi.category_id)
                     LEFT JOIN sizes sz ON sz.id = pv.size_id
                     LEFT JOIN colors cl ON cl.id = pv.color_id
                     WHERE qi.tag_code = ? LIMIT 1",
                    [$qrCode]
                );

                if (!$prodItem) {
                    $prodItem = Database::fetchOne(
                        "SELECT pbi.*, p.id as product_id, p.name as product_name, pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                                sz.name as size_name, cl.name as color_name
                         FROM production_batch_items pbi
                         JOIN production_batches pb ON pb.id = pbi.production_batch_id
                         LEFT JOIN product_variants pv ON pv.id = pbi.variant_id
                         LEFT JOIN products p ON p.id = pv.product_id
                         LEFT JOIN sizes sz ON sz.id = pv.size_id
                         LEFT JOIN colors cl ON cl.id = pv.color_id
                         WHERE pbi.piece_tags LIKE ? OR pbi.received_qr_codes LIKE ? LIMIT 1",
                        ['%' . $qrCode . '%', '%' . $qrCode . '%']
                    );
                }

                if (!$prodItem) {
                    $prodItem = Database::fetchOne(
                        "SELECT wq.*, p.id as product_id, p.name as product_name, pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                                sz.name as size_name, cl.name as color_name
                         FROM warehouse_qr_codes wq
                         LEFT JOIN product_variants pv ON pv.id = wq.variant_id
                         LEFT JOIN products p ON p.id = pv.product_id
                         LEFT JOIN sizes sz ON sz.id = pv.size_id
                         LEFT JOIN colors cl ON cl.id = pv.color_id
                         WHERE wq.qr_code = ? LIMIT 1",
                        [$qrCode]
                    );
                }

                if (!$prodItem) {
                    $labelLookup = $this->resolveDamageLabelCode($qrCode, $qrEntry['supply_item_id'] ?: null);
                    if ($labelLookup['status'] === 'ambiguous') {
                        Session::flash('error', "QR code {$qrCode} matches multiple Other Garment Supplies labels. The code is not unique, so damage was not saved.");
                        Response::back();
                        return;
                    }
                    $prodItem = $labelLookup['item'];
                }

                if (!$prodItem) {
                    $prodItem = Database::fetchOne(
                        "SELECT pv.*, p.id as product_id, p.name as product_name, pv.id as variant_id, pv.sku, pv.mrp, pv.selling_price, pv.production_cost, pv.weight as variant_weight,
                                sz.name as size_name, cl.name as color_name
                         FROM product_variants pv
                         JOIN products p ON p.id = pv.product_id
                         LEFT JOIN sizes sz ON sz.id = pv.size_id
                         LEFT JOIN colors cl ON cl.id = pv.color_id
                         WHERE pv.sku = ? OR pv.qr_code = ? LIMIT 1",
                        [$qrCode, $qrCode]
                    );
                }

                if (!$prodItem) {
                    Session::flash('error', "QR code {$qrCode} is not recognized as a fabric roll or finished garment.");
                    Response::back();
                    return;
                }

                $isSupply = !empty($prodItem['_damage_supply']);
                if ($isSupply) {
                    $lossAmount = (float)$prodItem['damage_cost'];
                    $weight = (float)$prodItem['damage_weight'];
                } else {
                    $lossAmount = (float)(!empty($prodItem['selling_price']) && $prodItem['selling_price'] > 0
                        ? $prodItem['selling_price']
                        : (!empty($prodItem['mrp']) && $prodItem['mrp'] > 0
                            ? $prodItem['mrp']
                            : (!empty($prodItem['base_price']) && $prodItem['base_price'] > 0
                                ? $prodItem['base_price']
                                : (!empty($prodItem['discount_price']) && $prodItem['discount_price'] > 0
                                    ? $prodItem['discount_price']
                                    : 500.00))));

                    $weight = (float)(!empty($prodItem['variant_weight']) && $prodItem['variant_weight'] > 0
                        ? $prodItem['variant_weight']
                        : (!empty($prodItem['weight']) && $prodItem['weight'] > 0
                            ? $prodItem['weight']
                            : 0.25));
                }

                $damageItems[] = [
                    'item_type' => 'PRODUCT',
                    'qr_code' => $qrCode,
                    'scan_index' => $qrEntry['scan_index'],
                    'seller_id' => null,
                    'seller_roll_id' => null,
                    'product_id' => !empty($prodItem['product_id']) ? (int)$prodItem['product_id'] : null,
                    'variant_id' => !empty($prodItem['variant_id']) ? (int)$prodItem['variant_id'] : null,
                    'product_name' => $prodItem['product_name'] ?: 'Garment Product',
                    'variant_info' => $isSupply
                        ? trim('Purchase ' . ($prodItem['purchase_number'] ?? '') . ' ' . ($prodItem['damage_quantity_label'] ?? ''))
                        : trim(($prodItem['size_name'] ?? '') . ' ' . ($prodItem['color_name'] ?? '') . ' ' . ($prodItem['sku'] ?? '')),
                    'roll_number' => $isSupply
                        ? $prodItem['product_name'] . ' (' . $prodItem['damage_quantity_label'] . ')'
                        : $prodItem['product_name'] . (!empty($prodItem['size_name']) ? " ({$prodItem['size_name']})" : ''),
                    'damaged_weight' => $weight,
                    'loss_amount' => $lossAmount,
                ];
            }
        }

        foreach ($newDamageCountByRoll as $sellerRollId => $newCount) {
            $roll = Database::fetchOne(
                "SELECT sr.roll_number, sr.quantity - COALESCE(dmg.damaged_quantity, 0) AS available_quantity
                 FROM seller_rolls sr
                 LEFT JOIN (
                     SELECT seller_roll_id, SUM(damaged_quantity) AS damaged_quantity
                     FROM roll_damages
                     WHERE status NOT IN ('CLOSED', 'CANCELLED')
                     GROUP BY seller_roll_id
                 ) dmg ON dmg.seller_roll_id = sr.id
                 WHERE sr.id = ?",
                [$sellerRollId]
            );
            $availableQuantity = max(0, (int)($roll['available_quantity'] ?? 0));
            if (!$roll || $newCount > $availableQuantity) {
                $rollNumber = $roll['roll_number'] ?? (string)$sellerRollId;
                Session::flash('error', "The scanned labels for roll {$rollNumber} exceed its available damageable balance ({$availableQuantity} pcs).");
                Response::back();
                return;
            }
        }

        $submittedPhotoFiles = $_FILES['damage_photos'] ?? [];
        $hasPerItemPhotos = isset($submittedPhotoFiles['name']) && is_array($submittedPhotoFiles['name']);
        foreach ($damageItems as $index => $item) {
            $scanIndex = $item['scan_index'];
            $damageReason = trim((string)($submittedReasons[$scanIndex] ?? $legacyDamageReason));
            if ($damageReason === '') {
                Session::flash('error', "Please select a Damage / Defect Reason for QR label {$item['qr_code']}.");
                Response::back();
                return;
            }
            if (mb_strlen($damageReason) > 255) {
                Session::flash('error', "Damage / Defect Reason for QR label {$item['qr_code']} must not exceed 255 characters.");
                Response::back();
                return;
            }
            $damageItems[$index]['damage_reason'] = $damageReason;

            if ($hasPerItemPhotos) {
                $photoName = $submittedPhotoFiles['name'][$scanIndex] ?? '';
                $photoError = (int)($submittedPhotoFiles['error'][$scanIndex] ?? UPLOAD_ERR_NO_FILE);
                $photoTmpName = $submittedPhotoFiles['tmp_name'][$scanIndex] ?? '';
                if ($photoName === '' || $photoError !== UPLOAD_ERR_OK || $photoTmpName === '') {
                    Session::flash('error', "A photo / evidence file is required for QR label {$item['qr_code']}.");
                    Response::back();
                    return;
                }
                $damageItems[$index]['photo_file'] = [
                    'name' => $photoName,
                    'type' => $submittedPhotoFiles['type'][$scanIndex] ?? '',
                    'tmp_name' => $photoTmpName,
                    'error' => $photoError,
                    'size' => $submittedPhotoFiles['size'][$scanIndex] ?? 0,
                ];
            }
        }

        $sharedPhotoUrl = null;
        if (!$hasPerItemPhotos) {
            $legacyPhoto = $_FILES['photo'] ?? [];
            if (empty($legacyPhoto['name']) || ($legacyPhoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Defect Photo / Evidence is required for every scanned label.');
                Response::back();
                return;
            }
            $sharedPhotoUrl = CloudinaryService::upload($legacyPhoto, 'grm_inventory/damages', 'image');
            if (!$sharedPhotoUrl) {
                Session::flash('error', 'Failed to upload Defect Photo. Please ensure Cloudinary is configured and a valid image is provided.');
                Response::back();
                return;
            }
        } else {
            $photosToUpload = [];
            foreach ($damageItems as $index => $item) {
                $photosToUpload[$index] = $item['photo_file'];
            }
            $uploadedPhotoUrls = CloudinaryService::uploadMany($photosToUpload, 'grm_inventory/damages', 'image');
            foreach ($damageItems as $index => $item) {
                $photoUrl = $uploadedPhotoUrls[$index] ?? null;
                if (!$photoUrl) {
                    Session::flash('error', "Failed to upload the photo for QR label {$item['qr_code']}. Please ensure Cloudinary is configured and the file is valid.");
                    Response::back();
                    return;
                }
                $damageItems[$index]['photo_url'] = $photoUrl;
                unset($damageItems[$index]['photo_file']);
            }
        }
        if (!$hasPerItemPhotos) {
            foreach ($damageItems as $index => $item) {
                $damageItems[$index]['photo_url'] = $sharedPhotoUrl;
            }
        }

        $userId = (int)(Session::get('user_id') ?? 1);
        $totalLossAmount = 0.0;

        try {
            Database::beginTransaction();
            $createdAt = date('Y-m-d H:i:s');
            foreach ($damageItems as $item) {
                $tempPrefix = ($item['item_type'] === 'PRODUCT') ? 'TMP-PRD-' : 'TMP-ROL-';
                $temporaryDamageNumber = $tempPrefix . bin2hex(random_bytes(16));
                Database::execute(
                    "INSERT INTO roll_damages
                        (damage_number, item_type, seller_id, seller_roll_id, product_id, variant_id, product_name, variant_info, qr_code, damaged_quantity, damaged_weight, loss_amount, damage_reason, defect_notes, photo_url, status, resolution_type, reported_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'LOGGED', 'NONE', ?, ?, ?)",
                    [
                        $temporaryDamageNumber,
                        $item['item_type'],
                        $item['seller_id'],
                        $item['seller_roll_id'],
                        $item['product_id'],
                        $item['variant_id'],
                        $item['product_name'],
                        $item['variant_info'],
                        $item['qr_code'],
                        $item['damaged_weight'],
                        $item['loss_amount'],
                        $item['damage_reason'],
                        $defectNotes ?: null,
                        $item['photo_url'],
                        $userId,
                        $createdAt,
                        $createdAt
                    ]
                );
                $damageId = (int)Database::lastInsertId();
                $prefix = ($item['item_type'] === 'PRODUCT') ? 'DMG-PRD-' : 'DMG-ROL-';
                $damageNumber = $prefix . date('Y') . '-' . sprintf('%06d', $damageId) . '-ID' . $damageId;
                Database::execute(
                    "UPDATE roll_damages SET damage_number = ? WHERE id = ?",
                    [$damageNumber, $damageId]
                );
                $totalLossAmount += (float)$item['loss_amount'];

                AuditService::log('production', 'create', 'roll_damages', $damageId, null, [
                    'damage_number' => $damageNumber,
                    'item_type' => $item['item_type'],
                    'qr_code' => $item['qr_code'],
                    'loss_amount' => $item['loss_amount'],
                    'reason' => $item['damage_reason']
                ]);
            }
            Database::commit();
            $redirectUrl = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : url('production/damages');
            Session::flash('success', count($damageItems) . ' scanned item(s) (rolls & products) recorded as damage. Total loss valuation: ₹' . number_format($totalLossAmount, 2));
            Response::redirect($redirectUrl);
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            Session::flash('error', 'Error logging damage: ' . $e->getMessage());
            Response::back();
        }
    }

    public function updateDamage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $damageId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM roll_damages WHERE id = ?", [$damageId]);
        if (!$existing) {
            Session::flash('error', 'Damage record not found.');
            Response::back();
            return;
        }

        $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : (int)$existing['seller_id'];
        $sellerRollId = !empty($_POST['seller_roll_id']) ? (int)$_POST['seller_roll_id'] : (int)$existing['seller_roll_id'];
        $damageReason = trim($_POST['damage_reason'] ?? $existing['damage_reason']);
        $defectNotes = trim($_POST['defect_notes'] ?? '');
        $damagedQty = max(1, (int)($_POST['damaged_quantity'] ?? 1));

        $isPurchasedSupplyDamage = (
            empty($existing['seller_roll_id']) ||
            ($existing['item_type'] ?? '') === 'PRODUCT' ||
            preg_match('/^SP\d+/i', trim((string)($existing['qr_code'] ?? '')))
        );

        if ($damageReason === '') {
            Session::flash('error', 'Please enter a Damage / Defect Reason.');
            Response::back();
            return;
        }
        if (mb_strlen($damageReason) > 255) {
            Session::flash('error', 'Damage / Defect Reason must not exceed 255 characters.');
            Response::back();
            return;
        }

        if ($isPurchasedSupplyDamage) {
            $photoUrl = $existing['photo_url'];
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploaded = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/damages', 'image');
                    if ($uploaded) {
                        $photoUrl = $uploaded;
                    }
                } catch (\Throwable $uploadErr) {
                    error_log('Damage photo upload error: ' . $uploadErr->getMessage());
                }
            }

            $lossAmount = isset($_POST['loss_amount']) && is_numeric($_POST['loss_amount'])
                ? round((float)$_POST['loss_amount'], 2)
                : (float)($existing['loss_amount'] ?? 0.0);

            $damagedWeight = isset($_POST['damaged_weight']) && is_numeric($_POST['damaged_weight'])
                ? round((float)$_POST['damaged_weight'], 2)
                : (float)($existing['damaged_weight'] ?? 0.0);

            try {
                Database::beginTransaction();

                Database::execute(
                    "UPDATE roll_damages SET 
                        damage_reason = ?, 
                        defect_notes = ?, 
                        photo_url = ?, 
                        damaged_quantity = ?,
                        damaged_weight = ?,
                        loss_amount = ?,
                        updated_at = NOW() 
                     WHERE id = ?",
                    [
                        $damageReason, 
                        $defectNotes !== '' ? $defectNotes : null, 
                        $photoUrl, 
                        $damagedQty,
                        $damagedWeight,
                        $lossAmount,
                        $damageId
                    ]
                );

                AuditService::log('production', 'update', 'roll_damages', $damageId, $existing, [
                    'damage_reason'  => $damageReason,
                    'defect_notes'   => $defectNotes,
                    'photo_url'      => $photoUrl,
                    'damaged_qty'    => $damagedQty,
                    'damaged_weight' => $damagedWeight,
                    'loss_amount'    => $lossAmount,
                ]);

                Database::commit();

                Session::flash('success', "Damage record #{$existing['damage_number']} updated successfully.");
                Response::redirect(url('production/damages'));
            } catch (\Throwable $e) {
                if (Database::inTransaction()) {
                    Database::rollback();
                }
                Session::flash('error', 'Error updating damage record: ' . $e->getMessage());
                Response::back();
            }
            return;
        }

        if (!empty($existing['qr_code'])) {
            if ($sellerId !== (int)$existing['seller_id'] || $sellerRollId !== (int)$existing['seller_roll_id']) {
                Session::flash('error', 'A QR-linked damage record must remain assigned to its scanned supplier and roll.');
                Response::back();
                return;
            }
            $damagedQty = 1;
        }

        if (!$sellerId || !$sellerRollId) {
            Session::flash('error', 'Please select a Material Supplier and Defective Fabric Roll.');
            Response::back();
            return;
        }

        $roll = Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ? AND seller_id = ?", [$sellerRollId, $sellerId]);
        if (!$roll) {
            Session::flash('error', 'Selected Fabric Roll not found for this supplier.');
            Response::back();
            return;
        }

        // Allocated rolls can still be damaged; only other active damage records reduce this limit.
        $existingDamages = Database::fetchOne(
            "SELECT COALESCE(SUM(damaged_quantity), 0) as dmg_qty, COALESCE(SUM(damaged_weight), 0.0) as dmg_weight 
             FROM roll_damages WHERE seller_roll_id = ? AND status NOT IN ('CLOSED', 'CANCELLED') AND id != ?",
            [$sellerRollId, $damageId]
        );
        $damagedExistingQty = (int)($existingDamages['dmg_qty'] ?? 0);
        $availQty = max(0, (int)$roll['quantity'] - $damagedExistingQty);

        if ($damagedQty > $availQty) {
            Session::flash('error', "Damaged Quantity ({$damagedQty} pcs) cannot exceed the available roll balance ({$availQty} pcs).");
            Response::back();
            return;
        }

        // Recalculate auto-calculated weight and financial loss valuation
        $rollWeight = (float)$roll['weight'];
        $rollPrice = (float)$roll['price'];
        $rollQty = max(1, (int)($roll['quantity'] ?? 1));

        $weightPerPc = $rollQty > 0 ? ($rollWeight / $rollQty) : 0.0;
        $damagedWeight = round($damagedQty * $weightPerPc, 2);

        $ratePerKg = $rollWeight > 0 ? ($rollPrice / $rollWeight) : 0.0;
        $lossAmount = round($damagedWeight * $ratePerKg, 2);
        if ($lossAmount <= 0 && $rollQty > 0) {
            $lossAmount = round(($rollPrice / $rollQty) * $damagedQty, 2);
        }

        // Handle photo upload (Cloudinary Cloud Storage)
        $photoUrl = $existing['photo_url'];
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploaded = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/damages', 'image');
            if ($uploaded) {
                $photoUrl = $uploaded;
            }
        }

        if (empty($photoUrl)) {
            Session::flash('error', 'Defect Photo / Evidence is required.');
            Response::back();
            return;
        }

        try {
            Database::beginTransaction();

            Database::execute(
                "UPDATE roll_damages SET
                    seller_id = ?,
                    seller_roll_id = ?,
                    damaged_quantity = ?,
                    damaged_weight = ?,
                    loss_amount = ?,
                    damage_reason = ?,
                    defect_notes = ?,
                    photo_url = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $sellerId,
                    $sellerRollId,
                    $damagedQty,
                    $damagedWeight,
                    $lossAmount,
                    $damageReason,
                    $defectNotes ?: null,
                    $photoUrl,
                    $damageId
                ]
            );

            AuditService::log('production', 'update', 'roll_damages', $damageId, $existing, [
                'seller_id' => $sellerId,
                'seller_roll_id' => $sellerRollId,
                'damaged_weight' => $damagedWeight,
                'loss_amount' => $lossAmount,
                'reason' => $damageReason
            ]);

            Database::commit();

            Session::flash('success', "Damage record #{$existing['damage_number']} updated successfully.");
            Response::redirect(url('production/damages'));
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            Session::flash('error', 'Error updating damage record: ' . $e->getMessage());
            Response::back();
        }
    }

    public function resolveDamage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        self::ensureDatabaseSchema();
        $damageId = (int)$id;
        $status = trim($_POST['status'] ?? '');
        $resolutionType = trim($_POST['resolution_type'] ?? '');
        $refundAmount = max(0.0, (float)($_POST['refund_amount'] ?? 0.0));
        $debitNoteNumber = trim($_POST['debit_note_number'] ?? '');
        $resolutionNotes = trim($_POST['resolution_notes'] ?? '');

        $allowedStatuses = ['LOGGED', 'RETURN_INITIATED', 'RETURNED_TO_SELLER', 'REFUND_RECEIVED', 'REPLACEMENT_RECEIVED', 'CLOSED'];
        $allowedResolutionTypes = ['SUPPLIER_REFUND', 'SUPPLIER_REPLACEMENT', 'RATE_CONCESSION', 'SCRAP_WRITE_OFF'];
        if (!in_array($status, $allowedStatuses, true)) {
            Session::flash('error', 'Select a valid damage status.');
            Response::back();
            return;
        }
        if (!in_array($resolutionType, $allowedResolutionTypes, true)) {
            Session::flash('error', 'Select a valid resolution method.');
            Response::back();
            return;
        }
        if ($status === 'REPLACEMENT_RECEIVED' && $resolutionType !== 'SUPPLIER_REPLACEMENT') {
            Session::flash('error', 'Set the resolution method to Supplier Replacement Roll for a replacement received status.');
            Response::back();
            return;
        }

        $userId = max(0, (int)(Session::get('user_id') ?? 1));
        $isReplacement = $resolutionType === 'SUPPLIER_REPLACEMENT' || $status === 'REPLACEMENT_RECEIVED';

        try {
            if (!Database::beginTransaction()) {
                throw new RuntimeException('Could not start the damage resolution transaction.');
            }
            $damage = Database::fetchOne("SELECT * FROM roll_damages WHERE id = ? FOR UPDATE", [$damageId]);
            if (!$damage) {
                Database::rollback();
                Session::flash('error', 'Damage record not found.');
                Response::back();
                return;
            }

            $replacementRollId = !empty($damage['replacement_roll_id']) ? (int)$damage['replacement_roll_id'] : null;
            $replacementRollNumber = $damage['replacement_roll_number'] ?? null;
            $purchaseNumber = null;
            if ($isReplacement) {
                $replacement = $this->saveSupplierReplacementRoll($damage, $userId);
                $replacementRollId = $replacement['roll_id'];
                $replacementRollNumber = $replacement['roll_number'];
                $purchaseNumber = $replacement['purchase_number'];
            }

            Database::execute(
                "UPDATE roll_damages SET
                    status = ?,
                    resolution_type = ?,
                    refund_amount = ?,
                    debit_note_number = ?,
                    replacement_roll_number = ?,
                    replacement_roll_id = ?,
                    resolution_notes = ?,
                    resolved_by = ?,
                    resolved_at = NOW(),
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $status,
                    $resolutionType,
                    $refundAmount,
                    $debitNoteNumber ?: null,
                    $replacementRollNumber ?: null,
                    $replacementRollId ?: $damage['replacement_roll_id'],
                    $resolutionNotes ?: null,
                    $userId,
                    $damageId
                ]
            );

            AuditService::log('production', 'update', 'roll_damages', $damageId, $damage, [
                'status' => $status,
                'resolution_type' => $resolutionType,
                'refund_amount' => $refundAmount,
                'replacement_roll_id' => $replacementRollId,
                'purchase_number' => $purchaseNumber,
            ]);

            if (!Database::commit()) {
                throw new RuntimeException('Could not save the damage resolution.');
            }
            $successMessage = "Damage record #{$damage['damage_number']} updated successfully. Status: {$status}";
            if ($isReplacement) {
                $successMessage .= " Replacement roll {$replacementRollNumber} is available in Production Purchases ({$purchaseNumber}).";
            }
            Session::flash('success', $successMessage);
            Response::redirect(url($isReplacement ? 'production/purchases' : 'production/damages'));
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            error_log('Damage resolution failed: ' . $e->getMessage());
            Session::flash('error', 'Error updating damage resolution: ' . $e->getMessage());
            Response::back();
        }
    }

    private function saveSupplierReplacementRoll(array $damage, int $userId): array {
        $sellerId = (int)($damage['seller_id'] ?? 0);
        if ($sellerId < 1) {
            throw new RuntimeException('A supplier is required for a replacement roll.');
        }

        $origRoll = null;
        if (!empty($damage['seller_roll_id'])) {
            $origRoll = Database::fetchOne(
                "SELECT * FROM seller_rolls WHERE id = ? AND seller_id = ? FOR UPDATE",
                [(int)$damage['seller_roll_id'], $sellerId]
            );
            if (!$origRoll) {
                throw new RuntimeException('The damaged fabric roll could not be found for this supplier.');
            }
        }

        $replacementRollId = !empty($damage['replacement_roll_id']) ? (int)$damage['replacement_roll_id'] : 0;
        $existingReplacement = $replacementRollId
            ? Database::fetchOne("SELECT * FROM seller_rolls WHERE id = ? AND seller_id = ? FOR UPDATE", [$replacementRollId, $sellerId])
            : null;
        if ($replacementRollId && !$existingReplacement) {
            throw new RuntimeException('The previously saved replacement roll could not be found.');
        }

        $rollNumber = mb_strtoupper(trim((string)($_POST['replacement_roll_number'] ?? '')));
        if ($rollNumber === '') {
            $savedRollNumber = trim((string)($existingReplacement['roll_number'] ?? ''));
            $sourceRollNumber = trim((string)($origRoll['roll_number'] ?? ''));
            $rollNumber = $savedRollNumber !== ''
                ? $savedRollNumber
                : (($sourceRollNumber !== '' ? $sourceRollNumber : 'ROLL') . '-REPL');
        }
        $color = mb_strtoupper(trim((string)($_POST['replacement_color'] ?? '')));
        if ($color === '') {
            $color = trim((string)($existingReplacement['color'] ?? ''));
            if ($color === '') {
                $color = mb_strtoupper(trim((string)($origRoll['color'] ?? ''))) ?: 'STANDARD';
            }
        }
        $designName = mb_strtoupper(trim((string)($_POST['replacement_design_name'] ?? '')));
        if ($designName === '') {
            $designName = trim((string)($existingReplacement['design_name'] ?? ''));
            if ($designName === '') {
                $designName = mb_strtoupper(trim((string)($origRoll['design_name'] ?? ''))) ?: 'PLAIN';
            }
        }
        $designCount = (int)($_POST['replacement_design_count'] ?? 0);
        if ($designCount < 1) {
            $designCount = max(1, (int)($existingReplacement['design_count'] ?? ($origRoll['design_count'] ?? 1)));
        }
        $quantity = (int)($_POST['replacement_quantity'] ?? 0);
        if ($quantity < 1) {
            $quantity = max(1, (int)($existingReplacement['quantity'] ?? ($origRoll['quantity'] ?? ($damage['damaged_quantity'] ?? 1))));
        }

        $weightInput = trim((string)($_POST['replacement_weight'] ?? ''));
        if ($weightInput !== '' && !is_numeric($weightInput)) {
            throw new RuntimeException('Enter a valid replacement roll weight.');
        }
        $weight = $weightInput !== '' && (float)$weightInput > 0 ? (float)$weightInput : 0.0;
        if ($weight <= 0) {
            $weight = (float)($existingReplacement['weight'] ?? 0);
        }
        if ($weight <= 0) {
            $weight = (float)($origRoll['weight'] ?? 0);
        }
        if ($weight <= 0) {
            $weight = (float)($damage['damaged_weight'] ?? 0);
        }
        if ($weight <= 0) {
            throw new RuntimeException('Replacement roll weight must be greater than zero.');
        }

        $priceInput = trim((string)($_POST['replacement_price'] ?? ''));
        if ($priceInput !== '' && (!is_numeric($priceInput) || (float)$priceInput < 0)) {
            throw new RuntimeException('Enter a valid non-negative replacement roll price.');
        }
        if ($priceInput !== '') {
            $price = (float)$priceInput;
        } elseif ($existingReplacement) {
            $price = (float)($existingReplacement['price'] ?? 0);
        } elseif ((float)($origRoll['price'] ?? 0) > 0) {
            $price = (float)$origRoll['price'];
        } else {
            $price = (float)($damage['loss_amount'] ?? 0);
        }
        if ($rollNumber === '' || mb_strlen($rollNumber) > 50
            || $color === '' || mb_strlen($color) > 100
            || $designName === '' || mb_strlen($designName) > 150
            || $designCount < 1 || $quantity < 1 || $price < 0) {
            throw new RuntimeException('Complete the replacement roll number, color, design, quantity, weight, and price fields with valid values.');
        }

        $hsnCode = mb_strtoupper(trim((string)($_POST['replacement_hsn_code'] ?? '')));
        if ($hsnCode === '') {
            $hsnCode = (string)($existingReplacement['hsn_code'] ?? ($origRoll['hsn_code'] ?? ''));
        }
        $gstType = strtoupper(trim((string)($_POST['replacement_gst_type'] ?? 'EXEMPTED')));
        if (!in_array($gstType, ['SGST', 'IGST', 'CGST', 'BOTH', 'EXEMPTED'], true)) {
            throw new RuntimeException('Select a valid replacement roll GST type.');
        }

        $replacementPhoto = $existingReplacement['photo_url'] ?? ($origRoll['photo_url'] ?? null);
        $photoUpload = $_FILES['replacement_photo'] ?? null;
        if ($photoUpload && !empty($photoUpload['name'])) {
            if (($photoUpload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('The replacement roll photo could not be uploaded.');
            }
            $uploadedPhoto = CloudinaryService::upload($photoUpload, 'grm_inventory/sellers/rolls', 'image');
            if (!$uploadedPhoto) {
                throw new RuntimeException('The replacement roll photo upload failed. Please try again.');
            }
            $replacementPhoto = $uploadedPhoto;
        }

        $materialType = $existingReplacement['material_type']
            ?? ($origRoll['material_type'] ?? 'STANDARD FABRIC');
        $rollNotes = $existingReplacement['notes']
            ?? ('Supplier replacement roll for damaged #' . $damage['damage_number']
                . ($origRoll ? ' (' . $origRoll['roll_number'] . ')' : ''));
        $hasRollHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM seller_rolls LIKE 'hsn_code'");
        $sequence = null;
        if (!$existingReplacement || (int)$existingReplacement['quantity'] !== $quantity) {
            $sequence = ProductionService::getNextFabricRollSequence($quantity);
        }

        if ($existingReplacement) {
            $assignments = [
                'roll_number = ?',
                'color = ?',
                'design_name = ?',
                'design_count = ?',
                'quantity = ?',
                'weight = ?',
                'price = ?',
                'material_type = ?',
                'photo_url = ?',
            ];
            $rollParams = [$rollNumber, $color, $designName, $designCount, $quantity, $weight, $price, $materialType, $replacementPhoto];
            if ($hasRollHsn) {
                $assignments[] = 'hsn_code = ?';
                $rollParams[] = $hsnCode !== '' ? $hsnCode : null;
            }
            $assignments[] = 'notes = ?';
            $rollParams[] = $rollNotes;
            if ($sequence) {
                $assignments[] = 'start_seq = ?';
                $assignments[] = 'end_seq = ?';
                $assignments[] = 'start_code = ?';
                $assignments[] = 'end_code = ?';
                $assignments[] = 'qr_code = ?';
                array_push(
                    $rollParams,
                    (int)$sequence['start_seq'],
                    (int)$sequence['end_seq'],
                    (string)$sequence['start_code'],
                    (string)$sequence['end_code'],
                    (string)$sequence['qr_code']
                );
            }
            $assignments[] = 'updated_at = NOW()';
            $rollParams[] = $replacementRollId;
            $rollParams[] = $sellerId;
            Database::execute(
                'UPDATE seller_rolls SET ' . implode(', ', $assignments) . ' WHERE id = ? AND seller_id = ?',
                $rollParams
            );
        } else {
            $sequence = $sequence ?? ProductionService::getNextFabricRollSequence($quantity);
            $startSeq = (int)$sequence['start_seq'];
            $endSeq = (int)$sequence['end_seq'];
            $startCode = (string)$sequence['start_code'];
            $endCode = (string)$sequence['end_code'];
            $qrCode = (string)$sequence['qr_code'];
            if ($hasRollHsn) {
                Database::execute(
                    "INSERT INTO seller_rolls
                        (seller_id, roll_number, color, design_name, design_count, quantity, weight, price,
                         material_type, photo_url, start_seq, end_seq, start_code, end_code, qr_code, hsn_code, notes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$sellerId, $rollNumber, $color, $designName, $designCount, $quantity, $weight, $price,
                        $materialType, $replacementPhoto, $startSeq, $endSeq, $startCode, $endCode, $qrCode,
                        $hsnCode !== '' ? $hsnCode : null, $rollNotes]
                );
            } else {
                Database::execute(
                    "INSERT INTO seller_rolls
                        (seller_id, roll_number, color, design_name, design_count, quantity, weight, price,
                         material_type, photo_url, start_seq, end_seq, start_code, end_code, qr_code, notes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$sellerId, $rollNumber, $color, $designName, $designCount, $quantity, $weight, $price,
                        $materialType, $replacementPhoto, $startSeq, $endSeq, $startCode, $endCode, $qrCode, $rollNotes]
                );
            }
            $replacementRollId = (int)Database::lastInsertId();
            if ($replacementRollId < 1) {
                throw new RuntimeException('The replacement roll could not be saved.');
            }
        }

        $purchaseItem = Database::fetchOne(
            "SELECT ppi.*, pp.purchase_number
             FROM production_purchase_items ppi
             JOIN production_purchases pp ON pp.id = ppi.purchase_id
             WHERE ppi.seller_roll_id = ?
             ORDER BY ppi.id DESC LIMIT 1 FOR UPDATE",
            [$replacementRollId]
        );
        $purchaseId = $purchaseItem ? (int)$purchaseItem['purchase_id'] : 0;
        $purchaseNumber = $purchaseItem['purchase_number'] ?? null;
        if (!$purchaseId) {
            $purchaseNumber = 'TMP-PP-' . bin2hex(random_bytes(6));
            $purchaseDate = date('Y-m-d');
            $purchaseNotes = 'Supplier Replacement Inward for Damage #' . $damage['damage_number'];
            Database::execute(
                "INSERT INTO production_purchases (purchase_number, seller_id, purchase_date, notes, total_amount, created_by)
                 VALUES (?, ?, ?, ?, 0, ?)",
                [$purchaseNumber, $sellerId, $purchaseDate, $purchaseNotes, $userId ?: null]
            );
            $purchaseId = (int)Database::lastInsertId();
            if ($purchaseId < 1) {
                throw new RuntimeException('The replacement purchase record could not be created.');
            }
            $purchaseNumber = 'PP-' . date('Y') . '-' . sprintf('%06d', $purchaseId);
            Database::execute("UPDATE production_purchases SET purchase_number = ? WHERE id = ?", [$purchaseNumber, $purchaseId]);
        }

        $taxRate = 0.0;
        if ($gstType !== 'EXEMPTED' && $hsnCode !== '') {
            $hsnRow = Database::fetchOne("SELECT gst_rate FROM hsn_codes WHERE hsn_code = ?", [$hsnCode]);
            if ($hsnRow) {
                $taxRate = (float)$hsnRow['gst_rate'];
            }
        }
        $taxAmount = round($price * ($taxRate / 100), 2);
        $sgstAmount = in_array($gstType, ['SGST', 'BOTH'], true)
            ? round($taxAmount / ($gstType === 'BOTH' ? 2 : 1), 2)
            : 0.0;
        $cgstAmount = in_array($gstType, ['CGST', 'BOTH'], true)
            ? round($taxAmount / ($gstType === 'BOTH' ? 2 : 1), 2)
            : 0.0;
        $igstAmount = $gstType === 'IGST' ? $taxAmount : 0.0;
        $totalPrice = $price + $sgstAmount + $cgstAmount + $igstAmount;
        $sgstValue = in_array($gstType, ['SGST', 'BOTH'], true)
            ? ($gstType === 'BOTH' ? $taxRate / 2 : $taxRate)
            : 0.0;
        $cgstValue = in_array($gstType, ['CGST', 'BOTH'], true)
            ? ($gstType === 'BOTH' ? $taxRate / 2 : $taxRate)
            : 0.0;
        $igstValue = $gstType === 'IGST' ? $taxRate : 0.0;
        $itemName = $materialType !== '' ? $materialType : 'STANDARD FABRIC';
        $hasItemHsn = (bool)Database::fetchOne("SHOW COLUMNS FROM production_purchase_items LIKE 'hsn_code'");

        if ($purchaseItem) {
            $itemAssignments = [
                'item_type = ?',
                'item_name = ?',
                'quantity = ?',
                'unit = ?',
                'weight_kg = ?',
                'unit_price = ?',
                'total_price = ?',
                'gst_type = ?',
                'tax_input_type = ?',
                'sgst_value = ?',
                'cgst_value = ?',
                'igst_value = ?',
                'sgst_amount = ?',
                'cgst_amount = ?',
                'igst_amount = ?',
                'roll_number = ?',
                'color = ?',
                'design_name = ?',
                'design_count = ?',
            ];
            $itemParams = ['FABRIC_ROLL', $itemName, $quantity, 'PCS', $weight, $price, $totalPrice, $gstType, 'PERCENT',
                $sgstValue, $cgstValue, $igstValue, $sgstAmount, $cgstAmount, $igstAmount, $rollNumber, $color, $designName, $designCount];
            if ($hasItemHsn) {
                $itemAssignments[] = 'hsn_code = ?';
                $itemParams[] = $hsnCode !== '' ? $hsnCode : null;
            }
            $itemAssignments[] = 'seller_roll_id = ?';
            $itemParams[] = $replacementRollId;
            $itemParams[] = (int)$purchaseItem['id'];
            $itemParams[] = $purchaseId;
            Database::execute(
                'UPDATE production_purchase_items SET ' . implode(', ', $itemAssignments) . ' WHERE id = ? AND purchase_id = ?',
                $itemParams
            );
        } elseif ($hasItemHsn) {
            Database::execute(
                "INSERT INTO production_purchase_items
                    (purchase_id, item_type, item_name, quantity, unit, weight_kg, unit_price, total_price,
                     gst_type, tax_input_type, sgst_value, cgst_value, igst_value, sgst_amount, cgst_amount, igst_amount,
                     roll_number, color, design_name, design_count, hsn_code, seller_roll_id)
                 VALUES (?, 'FABRIC_ROLL', ?, ?, 'PCS', ?, ?, ?, ?, 'PERCENT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$purchaseId, $itemName, $quantity, $weight, $price, $totalPrice, $gstType, $sgstValue, $cgstValue, $igstValue,
                    $sgstAmount, $cgstAmount, $igstAmount, $rollNumber, $color, $designName, $designCount,
                    $hsnCode !== '' ? $hsnCode : null, $replacementRollId]
            );
        } else {
            Database::execute(
                "INSERT INTO production_purchase_items
                    (purchase_id, item_type, item_name, quantity, unit, weight_kg, unit_price, total_price,
                     gst_type, tax_input_type, sgst_value, cgst_value, igst_value, sgst_amount, cgst_amount, igst_amount,
                     roll_number, color, design_name, design_count, seller_roll_id)
                 VALUES (?, 'FABRIC_ROLL', ?, ?, 'PCS', ?, ?, ?, ?, 'PERCENT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$purchaseId, $itemName, $quantity, $weight, $price, $totalPrice, $gstType, $sgstValue, $cgstValue, $igstValue,
                    $sgstAmount, $cgstAmount, $igstAmount, $rollNumber, $color, $designName, $designCount, $replacementRollId]
            );
        }

        $sumRow = Database::fetchOne(
            "SELECT COALESCE(SUM(total_price), 0) AS total FROM production_purchase_items WHERE purchase_id = ?",
            [$purchaseId]
        );
        Database::execute(
            "UPDATE production_purchases SET total_amount = ? WHERE id = ?",
            [(float)$sumRow['total'], $purchaseId]
        );
        $this->refreshSupplierRollTotals($sellerId);

        return [
            'roll_id' => $replacementRollId,
            'roll_number' => $rollNumber,
            'purchase_number' => $purchaseNumber,
        ];
    }

    public function deleteDamage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $damageId = (int)$id;
        $damage = Database::fetchOne("SELECT * FROM roll_damages WHERE id = ?", [$damageId]);
        if (!$damage) {
            Session::flash('error', 'Damage record not found.');
            Response::back();
            return;
        }

        try {
            Database::execute("DELETE FROM roll_damages WHERE id = ?", [$damageId]);
            AuditService::log('production', 'delete', 'roll_damages', $damageId, $damage, null);
            Session::flash('success', "Damage record #{$damage['damage_number']} deleted and roll inventory restored.");
            Response::redirect(url('production/damages'));
        } catch (Exception $e) {
            Session::flash('error', 'Error deleting damage record: ' . $e->getMessage());
            Response::back();
        }
    }

    // =========================================================================
    // CATALOG (In-House Manufactured Products)
    // =========================================================================

    public function catalogList(): void {
        $search = trim($_GET['search'] ?? '');
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $subcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;

        $sql = "SELECT p.*, c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name, b.name as brand_name,
                       COALESCE(pv_summary.variant_count, 0) as variant_count,
                       COALESCE(pv_summary.min_prod_cost, 0) as min_prod_cost,
                       COALESCE(pv_summary.max_prod_cost, 0) as max_prod_cost,
                       COALESCE(pv_summary.min_selling_price, 0) as min_selling_price,
                       COALESCE(pv_summary.max_selling_price, 0) as max_selling_price,
                       COALESCE(pv_summary.min_mrp, 0) as min_mrp,
                       COALESCE(pv_summary.max_mrp, 0) as max_mrp
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
                LEFT JOIN sub_subcategories ssc ON p.sub_subcategory_id = ssc.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN (
                    SELECT pv.product_id,
                           COUNT(pv.id) as variant_count,
                           MIN(COALESCE(pv.production_cost, pv.purchase_price, 0)) as min_prod_cost,
                           MAX(COALESCE(pv.production_cost, pv.purchase_price, 0)) as max_prod_cost,
                           MIN(pv.selling_price) as min_selling_price,
                           MAX(pv.selling_price) as max_selling_price,
                           MIN(pv.mrp) as min_mrp,
                           MAX(pv.mrp) as max_mrp
                    FROM product_variants pv
                    WHERE pv.is_active = 1
                    GROUP BY pv.product_id
                ) pv_summary ON pv_summary.product_id = p.id
                WHERE (p.source_type IN ('OWN_PRODUCTION', 'MANUFACTURED', 'IN_HOUSE') OR p.source_type IS NULL) 
                  AND p.is_active = 1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (
                p.name LIKE ? 
                OR p.code LIKE ? 
                OR p.product_code LIKE ?
                OR p.material LIKE ? 
                OR p.description LIKE ? 
                OR p.hsn_code LIKE ? 
                OR c.name LIKE ? 
                OR sc.name LIKE ? 
                OR ssc.name LIKE ? 
                OR b.name LIKE ?
                OR EXISTS (
                    SELECT 1 FROM product_variants pv2 
                    WHERE pv2.product_id = p.id 
                    AND (pv2.sku LIKE ? OR pv2.qr_code LIKE ?)
                )
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        if ($categoryId) {
            $sql .= " AND (p.category_id = ? OR sc.category_id = ?)";
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        if ($subcategoryId) {
            $sql .= " AND p.subcategory_id = ?";
            $params[] = $subcategoryId;
        }

        $sql .= " ORDER BY p.created_at DESC";
        $products = Database::fetchAll($sql, $params);

        // Fetch categories and subcategories for filtering
        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $subcategories = Database::fetchAll("SELECT * FROM subcategories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

        Response::view('production.catalog.index', [
            'pageTitle'      => 'Production Catalog',
            'activeModule'   => 'production',
            'activeMenu'     => 'prod-catalog',
            'products'       => $products,
            'categories'     => $categories,
            'subcategories'  => $subcategories,
            'search'         => $search,
            'categoryId'     => $categoryId,
            'subcategoryId'  => $subcategoryId,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Catalog'],
            ],
        ]);
    }

    public function createCatalog(): void {
        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $brands = Database::fetchAll("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $sizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $colors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");
        $hsnCodes = Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC");

        // Generate auto style code suggestion
        $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE source_type = 'OWN_PRODUCTION'")['c'] ?? 0;
        $suggestedCode = 'GRM-STY-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        Response::view('production.catalog.create', [
            'pageTitle'     => 'Add Product Style | Production Catalog',
            'activeModule'  => 'production',
            'activeMenu'    => 'prod-catalog',
            'categories'    => $categories,
            'brands'        => $brands,
            'sizes'         => $sizes,
            'colors'        => $colors,
            'hsnCodes'      => $hsnCodes,
            'suggestedCode' => $suggestedCode,
            'breadcrumb'    => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Catalog', 'url' => url('production/catalog')],
                ['label' => 'Add Style Form'],
            ],
        ]);
    }

    public function storeCatalog(): void {
        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $material = mb_strtoupper(trim($_POST['material'] ?? ''));
        $hsnCode = mb_strtoupper(trim($_POST['hsn_code'] ?? ''));
        $gstPercentage = max(0.0, (float)($_POST['gst_percentage'] ?? 0));
        $minStock = max(0, (int)($_POST['min_stock'] ?? 10));
        $maxStock = max(0, (int)($_POST['max_stock'] ?? 1000));
        $description = trim($_POST['description'] ?? '');

        // Validations
        if ($categoryId <= 0) {
            Session::flash('error', 'Please select a valid Category.');
            Response::redirect(url('production/catalog/create'));
            return;
        }

        if (empty($name)) {
            $catNames = [];
            if ($subSubcategoryId) {
                $ssc = Database::fetchOne("SELECT name FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]);
                if ($ssc) $catNames[] = $ssc['name'];
            }
            if ($subcategoryId) {
                $sc = Database::fetchOne("SELECT name FROM subcategories WHERE id = ?", [$subcategoryId]);
                if ($sc) $catNames[] = $sc['name'];
            }
            $c = Database::fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]);
            if ($c) $catNames[] = $c['name'];

            $name = !empty($catNames) ? implode(' - ', array_reverse($catNames)) : 'PRODUCT STYLE';
        }

        if (empty($code)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE source_type = 'OWN_PRODUCTION'")['c'] ?? 0;
            $code = 'GRM-STY-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
            $existing = Database::fetchOne("SELECT id FROM products WHERE code = ?", [$code]);
            if ($existing) {
                $code .= '-' . rand(10, 99);
            }
        }

        // Cloudinary Image Upload
        $imageUrl = null;
        $cloudinaryPublicId = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $cloudUrl = CloudinaryService::upload($_FILES['image'], 'grm_inventory/catalog', 'image');
            if ($cloudUrl) {
                $imageUrl = $cloudUrl;
                $cloudinaryPublicId = CloudinaryService::extractPublicId($cloudUrl) ?? ('catalog_' . time());
            }
        }

        $categoryCode = mb_strtoupper(trim($_POST['category_code'] ?? ''));
        $subcategoryCode = mb_strtoupper(trim($_POST['subcategory_code'] ?? ''));
        $subSubcategoryCode = mb_strtoupper(trim($_POST['sub_subcategory_code'] ?? ''));
        $qrCodePrefix = mb_strtoupper(trim($_POST['qr_code_prefix'] ?? ''));

        if (!empty($qrCodePrefix)) {
            $existingQr = Database::fetchOne(
                "SELECT id, name FROM products WHERE qr_code_prefix = ? LIMIT 1",
                [$qrCodePrefix]
            );
            if ($existingQr) {
                Session::flash('error', "The Whole Code for QR Code '{$qrCodePrefix}' already exists for Style '{$existingQr['name']}'. Please customize this code to make it unique.");
                Response::redirect(url('production/catalog/create'));
                return;
            }
        }

        Database::execute(
            "INSERT INTO products (name, code, category_id, subcategory_id, sub_subcategory_id, category_code, subcategory_code, sub_subcategory_code, qr_code_prefix, brand_id, source_type, material, description, hsn_code, gst_percentage, min_stock, max_stock, image_url, cloudinary_public_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'OWN_PRODUCTION', ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $name,
                $code,
                $categoryId,
                $subcategoryId,
                $subSubcategoryId,
                $categoryCode,
                $subcategoryCode,
                $subSubcategoryCode,
                $qrCodePrefix,
                $brandId,
                $material,
                $description,
                $hsnCode,
                $gstPercentage,
                $minStock,
                $maxStock,
                $imageUrl,
                $cloudinaryPublicId
            ]
        );

        $productId = (int)Database::lastInsertId();

        // Process variants matrix or attributes
        $selectedSizes = !empty($_POST['sizes']) && is_array($_POST['sizes']) ? array_filter(array_map('intval', $_POST['sizes'])) : [];
        $selectedColors = !empty($_POST['colors']) && is_array($_POST['colors']) ? array_filter(array_map('intval', $_POST['colors'])) : [];

        $variantCount = 0;
        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $v) {
                $sizeId = !empty($v['size_id']) ? (int)$v['size_id'] : null;
                $colorId = !empty($v['color_id']) ? (int)$v['color_id'] : null;
                $sku = mb_strtoupper(trim($v['sku'] ?? ''));
                $prodCost = max(0.0, (float)($v['production_cost'] ?? 0));
                $mrp = max(0.0, (float)($v['mrp'] ?? 0));
                $sellingPrice = max(0.0, (float)($v['selling_price'] ?? 0));

                if (empty($sku)) {
                    $sku = $code;
                    if ($sizeId) {
                        $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? $sizeId;
                        $sku .= '-' . $sCode;
                    }
                    if ($colorId) {
                        $cName = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId])['name'] ?? $colorId;
                        $sku .= '-' . substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cName)), 0, 4);
                    }
                }

                Database::execute(
                    "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [
                        $productId,
                        $sizeId,
                        $colorId,
                        $sku,
                        $mrp,
                        $prodCost,
                        $prodCost,
                        $sellingPrice
                    ]
                );
                $variantCount++;
            }
        } elseif (!empty($selectedSizes) || !empty($selectedColors)) {
            $sizesList = !empty($selectedSizes) ? $selectedSizes : [null];
            $colorsList = !empty($selectedColors) ? $selectedColors : [null];

            foreach ($sizesList as $sizeId) {
                $sCode = '';
                if ($sizeId) {
                    $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? ('SZ' . $sizeId);
                }

                foreach ($colorsList as $colorId) {
                    $cCode = '';
                    if ($colorId) {
                        $cRow = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId]);
                        $cCode = $cRow ? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cRow['name'])), 0, 4) : ('COL' . $colorId);
                    }

                    $skuParts = [$code];
                    if ($sCode) $skuParts[] = $sCode;
                    if ($cCode) $skuParts[] = $cCode;
                    $sku = implode('-', $skuParts);

                    Database::execute(
                        "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
                         VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 1)",
                        [
                            $productId,
                            $sizeId,
                            $colorId,
                            $sku
                        ]
                    );
                    $variantCount++;
                }
            }
        } else {
            // Default variant
            $defaultCost = max(0.0, (float)($_POST['default_production_cost'] ?? 0));
            $defaultMrp = max(0.0, (float)($_POST['default_mrp'] ?? 0));
            $defaultSelling = max(0.0, (float)($_POST['default_selling_price'] ?? 0));

            Database::execute(
                "INSERT INTO product_variants (product_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                [
                    $productId,
                    $code . '-STD',
                    $defaultMrp,
                    $defaultCost,
                    $defaultCost,
                    $defaultSelling
                ]
            );
            $variantCount = 1;
        }

        AuditService::log('production', 'create', 'products', (int)$productId, null, ['name' => $name, 'code' => $code, 'variants' => $variantCount]);

        Session::flash('success', "Product style '{$name}' with {$variantCount} variant(s) created successfully.");
        Response::redirect(url('production/catalog'));
    }

    public function viewCatalog(int $id): void {
        $product = Database::fetchOne(
            "SELECT p.*, c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name, b.name as brand_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
             LEFT JOIN sub_subcategories ssc ON p.sub_subcategory_id = ssc.id
             LEFT JOIN brands b ON p.brand_id = b.id
             WHERE p.id = ?",
            [$id]
        );

        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('production/catalog'));
            return;
        }

        // Clean up / deactivate duplicate 0-price placeholder variants if priced/color variants exist for the same size
        try {
            Database::execute(
                "UPDATE product_variants pv_empty
                 JOIN product_variants pv_real 
                   ON pv_empty.product_id = pv_real.product_id 
                  AND pv_empty.size_id = pv_real.size_id 
                  AND pv_empty.id != pv_real.id
                 SET pv_empty.is_active = 0
                 WHERE pv_empty.product_id = ?
                   AND pv_empty.is_active = 1
                   AND (pv_empty.color_id IS NULL OR pv_empty.color_id = 0)
                   AND (pv_empty.mrp = 0 OR pv_empty.mrp IS NULL)
                   AND (pv_empty.selling_price = 0 OR pv_empty.selling_price IS NULL)
                   AND (pv_real.is_active = 1 AND (pv_real.mrp > 0 OR pv_real.color_id IS NOT NULL))",
                [$id]
            );
        } catch (\Throwable $e) {}

        $rawVariants = Database::fetchAll(
            "SELECT pv.*, s.name as size_name, s.code as size_code, col.name as color_name, col.hex_code as color_hex
             FROM product_variants pv
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             WHERE pv.product_id = ? AND pv.is_active = 1
             ORDER BY s.sort_order ASC, (pv.selling_price > 0 OR pv.mrp > 0) DESC, pv.id DESC",
            [$id]
        );

        // Deduplicate variants so each size appears exactly once (prioritizing priced/configured variants)
        $variants = [];
        $seenSizes = [];
        foreach ($rawVariants as $v) {
            $szKey = !empty($v['size_id']) ? (int)$v['size_id'] : ($v['size_code'] ?? $v['sku']);
            if (!isset($seenSizes[$szKey])) {
                $seenSizes[$szKey] = true;
                $variants[] = $v;
            }
        }

        $programs = Database::fetchAll(
            "SELECT pp.*, u.name as unit_name
             FROM production_programs pp
             LEFT JOIN production_units u ON pp.production_unit_id = u.id
             WHERE pp.product_id = ?
             ORDER BY pp.created_at DESC LIMIT 5",
            [$id]
        );

        Response::view('production.catalog.view', [
            'pageTitle'    => $product['name'] . ' | Production Catalog',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-catalog',
            'product'      => $product,
            'variants'     => $variants,
            'programs'     => $programs,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Catalog', 'url' => url('production/catalog')],
                ['label' => $product['code']],
            ],
        ]);
    }

    public function editCatalog(int $id): void {
        $product = Database::fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('production/catalog'));
            return;
        }

        // Clean up / deactivate duplicate 0-price placeholder variants if priced/color variants exist for the same size
        try {
            Database::execute(
                "UPDATE product_variants pv_empty
                 JOIN product_variants pv_real 
                   ON pv_empty.product_id = pv_real.product_id 
                  AND pv_empty.size_id = pv_real.size_id 
                  AND pv_empty.id != pv_real.id
                 SET pv_empty.is_active = 0
                 WHERE pv_empty.product_id = ?
                   AND pv_empty.is_active = 1
                   AND (pv_empty.color_id IS NULL OR pv_empty.color_id = 0)
                   AND (pv_empty.mrp = 0 OR pv_empty.mrp IS NULL)
                   AND (pv_empty.selling_price = 0 OR pv_empty.selling_price IS NULL)
                   AND (pv_real.is_active = 1 AND (pv_real.mrp > 0 OR pv_real.color_id IS NOT NULL))",
                [$id]
            );
        } catch (\Throwable $e) {}

        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $subcategories = $product['category_id'] ? Database::fetchAll("SELECT * FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC", [$product['category_id']]) : [];
        $subSubcategories = $product['subcategory_id'] ? Database::fetchAll("SELECT * FROM sub_subcategories WHERE subcategory_id = ? AND is_active = 1 ORDER BY name ASC", [$product['subcategory_id']]) : [];
        $brands = Database::fetchAll("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $sizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $colors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");

        $rawVariants = Database::fetchAll(
            "SELECT pv.*, s.name as size_name, s.code as size_code, col.name as color_name, col.hex_code as color_hex
             FROM product_variants pv
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             WHERE pv.product_id = ? AND pv.is_active = 1
             ORDER BY s.sort_order ASC, (pv.selling_price > 0 OR pv.mrp > 0) DESC, pv.id DESC",
            [$id]
        );

        // Deduplicate variants so each size appears exactly once
        $variants = [];
        $seenSizes = [];
        foreach ($rawVariants as $v) {
            $szKey = !empty($v['size_id']) ? (int)$v['size_id'] : ($v['size_code'] ?? $v['sku']);
            if (!isset($seenSizes[$szKey])) {
                $seenSizes[$szKey] = true;
                $variants[] = $v;
            }
        }

        Response::view('production.catalog.edit', [
            'pageTitle'        => 'Edit ' . $product['name'] . ' | Catalog',
            'activeModule'     => 'production',
            'activeMenu'       => 'prod-catalog',
            'product'          => $product,
            'variants'         => $variants,
            'categories'       => $categories,
            'subcategories'    => $subcategories,
            'subSubcategories' => $subSubcategories,
            'brands'           => $brands,
            'sizes'            => $sizes,
            'colors'           => $colors,
            'hsnCodes'         => Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC"),
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Catalog', 'url' => url('production/catalog')],
                ['label' => 'Edit ' . $product['code']],
            ],
        ]);
    }

    public function updateCatalog(int $id): void {
        $product = Database::fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('production/catalog'));
            return;
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $material = mb_strtoupper(trim($_POST['material'] ?? ''));
        $hsnCode = mb_strtoupper(trim($_POST['hsn_code'] ?? ''));
        $gstPercentage = max(0.0, (float)($_POST['gst_percentage'] ?? 0));
        $minStock = max(0, (int)($_POST['min_stock'] ?? 10));
        $maxStock = max(0, (int)($_POST['max_stock'] ?? 1000));
        $description = trim($_POST['description'] ?? '');

        if ($categoryId <= 0) {
            Session::flash('error', 'Category is required.');
            Response::redirect(url('production/catalog/edit/' . $id));
            return;
        }

        if (empty($name)) {
            $catNames = [];
            if ($subSubcategoryId) {
                $ssc = Database::fetchOne("SELECT name FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]);
                if ($ssc) $catNames[] = $ssc['name'];
            }
            if ($subcategoryId) {
                $sc = Database::fetchOne("SELECT name FROM subcategories WHERE id = ?", [$subcategoryId]);
                if ($sc) $catNames[] = $sc['name'];
            }
            $c = Database::fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]);
            if ($c) $catNames[] = $c['name'];

            $name = !empty($catNames) ? implode(' - ', array_reverse($catNames)) : ($product['name'] ?? 'PRODUCT STYLE');
        }

        if (empty($code)) {
            $code = $product['code'];
        }

        $imageUrl = $product['image_url'];
        $cloudinaryPublicId = $product['cloudinary_public_id'];
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $cloudUrl = CloudinaryService::upload($_FILES['image'], 'grm_inventory/catalog', 'image');
            if ($cloudUrl) {
                $imageUrl = $cloudUrl;
                $cloudinaryPublicId = CloudinaryService::extractPublicId($cloudUrl) ?? ('catalog_' . time());
            }
        }

        $categoryCode = mb_strtoupper(trim($_POST['category_code'] ?? ''));
        $subcategoryCode = mb_strtoupper(trim($_POST['subcategory_code'] ?? ''));
        $subSubcategoryCode = mb_strtoupper(trim($_POST['sub_subcategory_code'] ?? ''));
        $qrCodePrefix = mb_strtoupper(trim($_POST['qr_code_prefix'] ?? ''));

        if (!empty($qrCodePrefix)) {
            $existingQr = Database::fetchOne(
                "SELECT id, name FROM products WHERE qr_code_prefix = ? AND id != ? LIMIT 1",
                [$qrCodePrefix, $id]
            );
            if ($existingQr) {
                Session::flash('error', "The Whole Code for QR Code '{$qrCodePrefix}' already exists for Style '{$existingQr['name']}'. Please customize this code to make it unique.");
                Response::redirect(url('production/catalog/edit/' . $id));
                return;
            }
        }

        Database::execute(
            "UPDATE products SET name = ?, code = ?, category_id = ?, subcategory_id = ?, sub_subcategory_id = ?, category_code = ?, subcategory_code = ?, sub_subcategory_code = ?, qr_code_prefix = ?, brand_id = ?, material = ?, description = ?, hsn_code = ?, gst_percentage = ?, min_stock = ?, max_stock = ?, image_url = ?, cloudinary_public_id = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $name,
                $code,
                $categoryId,
                $subcategoryId,
                $subSubcategoryId,
                $categoryCode,
                $subcategoryCode,
                $subSubcategoryCode,
                $qrCodePrefix,
                $brandId,
                $material,
                $description,
                $hsnCode,
                $gstPercentage,
                $minStock,
                $maxStock,
                $imageUrl,
                $cloudinaryPublicId,
                $id
            ]
        );

        // Sync variants from submitted variants or selected attributes
        $selectedSizes = !empty($_POST['sizes']) && is_array($_POST['sizes']) ? array_filter(array_map('intval', $_POST['sizes'])) : [];
        $selectedColors = !empty($_POST['colors']) && is_array($_POST['colors']) ? array_filter(array_map('intval', $_POST['colors'])) : [];

        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            try {
                Database::execute("DELETE FROM product_variants WHERE product_id = ?", [$id]);
            } catch (Exception $e) {
                Database::execute("UPDATE product_variants SET is_active = 0 WHERE product_id = ?", [$id]);
            }

            foreach ($_POST['variants'] as $v) {
                $sizeId = !empty($v['size_id']) ? (int)$v['size_id'] : null;
                $colorId = !empty($v['color_id']) ? (int)$v['color_id'] : null;
                $sku = mb_strtoupper(trim($v['sku'] ?? ''));
                $prodCost = max(0.0, (float)($v['production_cost'] ?? 0));
                $mrp = max(0.0, (float)($v['mrp'] ?? 0));
                $sellingPrice = max(0.0, (float)($v['selling_price'] ?? 0));

                if (empty($sku)) {
                    $sku = $code . ($sizeId ? '-' . $sizeId : '') . ($colorId ? '-' . $colorId : '');
                }

                Database::execute(
                    "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE product_id=VALUES(product_id), size_id=VALUES(size_id), color_id=VALUES(color_id), mrp=VALUES(mrp), purchase_price=VALUES(purchase_price), production_cost=VALUES(production_cost), selling_price=VALUES(selling_price), is_active=1",
                    [
                        $id,
                        $sizeId,
                        $colorId,
                        $sku,
                        $mrp,
                        $prodCost,
                        $prodCost,
                        $sellingPrice
                    ]
                );
            }
        } elseif (!empty($selectedSizes) || !empty($selectedColors)) {
            // First, if specific sizes were submitted, deactivate any variant belonging to deselected sizes
            if (!empty($selectedSizes)) {
                $placeholders = implode(',', array_fill(0, count($selectedSizes), '?'));
                Database::execute("UPDATE product_variants SET is_active = 0 WHERE product_id = ? AND size_id IS NOT NULL AND size_id NOT IN ($placeholders)", array_merge([$id], $selectedSizes));
            }

            // Clean up / deactivate duplicate 0-price placeholder variants if priced/color variants exist for the same size
            try {
                Database::execute(
                    "UPDATE product_variants pv_empty
                     JOIN product_variants pv_real 
                       ON pv_empty.product_id = pv_real.product_id 
                      AND pv_empty.size_id = pv_real.size_id 
                      AND pv_empty.id != pv_real.id
                     SET pv_empty.is_active = 0
                     WHERE pv_empty.product_id = ?
                       AND pv_empty.is_active = 1
                       AND (pv_empty.color_id IS NULL OR pv_empty.color_id = 0)
                       AND (pv_empty.mrp = 0 OR pv_empty.mrp IS NULL)
                       AND (pv_empty.selling_price = 0 OR pv_empty.selling_price IS NULL)
                       AND (pv_real.is_active = 1 AND (pv_real.mrp > 0 OR pv_real.color_id IS NOT NULL))",
                    [$id]
                );
            } catch (\Throwable $e) {}

            $sizesList = !empty($selectedSizes) ? $selectedSizes : [null];
            $colorsList = !empty($selectedColors) ? $selectedColors : [null];

            foreach ($sizesList as $sizeId) {
                // If an active variant already exists for this size, keep it active without inserting a redundant placeholder
                if ($sizeId) {
                    $existingVar = Database::fetchOne(
                        "SELECT id FROM product_variants WHERE product_id = ? AND size_id = ? AND is_active = 1 ORDER BY (selling_price > 0 OR mrp > 0) DESC, id DESC",
                        [$id, $sizeId]
                    );
                    if ($existingVar) {
                        continue;
                    }
                }

                $sCode = '';
                if ($sizeId) {
                    $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? ('SZ' . $sizeId);
                }

                foreach ($colorsList as $colorId) {
                    $cCode = '';
                    if ($colorId) {
                        $cRow = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId]);
                        $cCode = $cRow ? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cRow['name'])), 0, 4) : ('COL' . $colorId);
                    }

                    $skuParts = [$code];
                    if ($sCode) $skuParts[] = $sCode;
                    if ($cCode) $skuParts[] = $cCode;
                    $sku = implode('-', $skuParts);

                    Database::execute(
                        "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
                         VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 1)
                         ON DUPLICATE KEY UPDATE product_id=VALUES(product_id), size_id=VALUES(size_id), color_id=VALUES(color_id), is_active=1",
                        [
                            $id,
                            $sizeId,
                            $colorId,
                            $sku
                        ]
                    );
                }
            }
        }

        AuditService::log('production', 'update', 'products', (int)$id, null, ['name' => $name, 'code' => $code]);
        Session::flash('success', "Product style '{$name}' updated successfully.");
        Response::redirect(url('production/catalog'));
    }

    public function deleteCatalog(int $id): void {
        $ts = time();
        Database::execute("UPDATE products SET is_active = 0, code = CONCAT(code, '_DEL_', ?) WHERE id = ?", [$ts, $id]);
        Database::execute("UPDATE product_variants SET is_active = 0, sku = CONCAT(sku, '_DEL_', ?), qr_code = CONCAT(qr_code, '_DEL_', ?) WHERE product_id = ?", [$ts, $ts, $id]);
        AuditService::log('production', 'delete', 'products', (int)$id, null, ['is_active' => 0]);
        Session::flash('success', 'Product style removed from catalog.');
        Response::redirect(url('production/catalog'));
    }

    // =========================================================================
    // AJAX CASCADING & QUICK-ADD ENDPOINTS
    // =========================================================================

    public function ajaxCheckQRCode(): void {
        try {
            $code = mb_strtoupper(trim($_GET['code'] ?? ''));
            $excludeId = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

            if (empty($code)) {
                Response::json(['exists' => false, 'code' => '']);
                return;
            }

            $query = "SELECT p.id, p.name, p.code, p.qr_code_prefix,
                             c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name
                      FROM products p
                      LEFT JOIN categories c ON c.id = p.category_id
                      LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
                      LEFT JOIN sub_subcategories ssc ON ssc.id = p.sub_subcategory_id
                      WHERE p.qr_code_prefix = ?";
            $params = [$code];
            if ($excludeId > 0) {
                $query .= " AND p.id != ?";
                $params[] = $excludeId;
            }
            $query .= " LIMIT 1";

            $existing = Database::fetchOne($query, $params);

            if ($existing) {
                $hierarchy = array_filter([$existing['category_name'], $existing['subcategory_name'], $existing['sub_subcategory_name']]);
                $hierarchyText = !empty($hierarchy) ? implode(' > ', $hierarchy) : '';

                Response::json([
                    'exists' => true,
                    'code' => $code,
                    'message' => "The Whole Code for QR Code '{$code}' already exists for Style: '{$existing['name']}'" . ($hierarchyText ? " ({$hierarchyText})" : "") . ". Please customize this code to avoid duplicates.",
                    'product' => [
                        'id' => (int)$existing['id'],
                        'name' => $existing['name'],
                        'code' => $existing['code'],
                        'hierarchy' => $hierarchyText
                    ]
                ]);
                return;
            }

            Response::json(['exists' => false, 'code' => $code]);
        } catch (\Throwable $e) {
            Response::json(['exists' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function ajaxCategories(): void {
        try {
            $sql = "SELECT c.id, c.name, c.slug FROM categories c WHERE c.is_active = 1";
            if (!empty($_GET['catalog_only'])) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM products p
                    WHERE p.category_id = c.id
                      AND p.is_active = 1
                      AND (p.source_type IN ('OWN_PRODUCTION', 'MANUFACTURED', 'IN_HOUSE') OR p.source_type IS NULL)
                )";
            }
            $sql .= " ORDER BY c.sort_order ASC, c.name ASC";
            $categories = Database::fetchAll($sql);
            Response::json($categories);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddCategory(): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Main Category name is required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, is_active FROM categories WHERE name = ? OR slug = ?", [$name, $slug]);
            if ($existing) {
                Database::execute("UPDATE categories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name]);
                return;
            }

            $maxSort = Database::fetchOne("SELECT MAX(sort_order) as m FROM categories")['m'] ?? 0;
            
            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'CAT';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM categories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO categories (name, code, slug, sort_order, is_active) VALUES (?, ?, ?, ?, 1)",
                [$name, $code, $slug, ((int)$maxSort + 1)]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateCategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Main Category name is required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM categories WHERE (name = ? OR slug = ?) AND id != ?",
                [$name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Category '{$existing['name']}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE categories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteCategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE category_id = ? AND is_active = 1", [$id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this category because {$count} product style(s) are linked to it."], 400);
                return;
            }

            Database::execute("UPDATE categories SET is_active = 0 WHERE id = ?", [$id]);
            Database::execute("UPDATE subcategories SET is_active = 0 WHERE category_id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxSubcategories(int $categoryId): void {
        try {
            $sql = "SELECT sc.id, sc.name, sc.slug FROM subcategories sc
                    WHERE sc.category_id = ? AND sc.is_active = 1";
            if (!empty($_GET['catalog_only'])) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM products p
                    WHERE p.subcategory_id = sc.id
                      AND p.is_active = 1
                      AND (p.source_type IN ('OWN_PRODUCTION', 'MANUFACTURED', 'IN_HOUSE') OR p.source_type IS NULL)
                )";
            }
            $sql .= " ORDER BY sc.sort_order ASC, sc.name ASC";
            $subcats = Database::fetchAll(
                $sql,
                [$categoryId]
            );
            Response::json($subcats);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddSubcategory(): void {
        try {
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if ($categoryId <= 0 || empty($name)) {
                Response::json(['success' => false, 'message' => 'Category and Subcategory name are required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, is_active FROM subcategories WHERE category_id = ? AND (name = ? OR slug = ?)", [$categoryId, $name, $slug]);
            if ($existing) {
                Database::execute("UPDATE subcategories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name]);
                return;
            }

            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'SUB';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM subcategories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO subcategories (category_id, name, code, slug, is_active) VALUES (?, ?, ?, ?, 1)",
                [$categoryId, $name, $code, $slug]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateSubcategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Subcategory name is required.'], 400);
                return;
            }
            $subcat = Database::fetchOne("SELECT category_id FROM subcategories WHERE id = ?", [$id]);
            if (!$subcat) {
                Response::json(['success' => false, 'message' => 'Subcategory not found.'], 404);
                return;
            }
            $categoryId = (int)$subcat['category_id'];
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM subcategories WHERE category_id = ? AND (name = ? OR slug = ?) AND id != ?",
                [$categoryId, $name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Subcategory '{$existing['name']}' already exists in this category."], 400);
                return;
            }

            Database::execute(
                "UPDATE subcategories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteSubcategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE subcategory_id = ? AND is_active = 1", [$id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this subcategory because {$count} product style(s) are linked to it."], 400);
                return;
            }
            Database::execute("UPDATE subcategories SET is_active = 0 WHERE id = ?", [$id]);
            Database::execute("UPDATE sub_subcategories SET is_active = 0 WHERE subcategory_id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxSubSubcategories(int $subcategoryId): void {
        try {
            $sql = "SELECT ssc.id, ssc.name, ssc.slug FROM sub_subcategories ssc
                    WHERE ssc.subcategory_id = ? AND ssc.is_active = 1";
            if (!empty($_GET['catalog_only'])) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM products p
                    WHERE p.sub_subcategory_id = ssc.id
                      AND p.is_active = 1
                      AND (p.source_type IN ('OWN_PRODUCTION', 'MANUFACTURED', 'IN_HOUSE') OR p.source_type IS NULL)
                )";
            }
            $sql .= " ORDER BY ssc.name ASC";
            $subSubcats = Database::fetchAll(
                $sql,
                [$subcategoryId]
            );
            Response::json($subSubcats);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddSubSubcategory(): void {
        try {
            $subcategoryId = (int)($_POST['subcategory_id'] ?? 0);
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if ($subcategoryId <= 0 || empty($name)) {
                Response::json(['success' => false, 'message' => 'Subcategory and Sub-subcategory name are required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, is_active FROM sub_subcategories WHERE subcategory_id = ? AND (name = ? OR slug = ?)", [$subcategoryId, $name, $slug]);
            if ($existing) {
                Database::execute("UPDATE sub_subcategories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name]);
                return;
            }

            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'SSC';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM sub_subcategories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO sub_subcategories (subcategory_id, name, code, slug, is_active) VALUES (?, ?, ?, ?, 1)",
                [$subcategoryId, $name, $code, $slug]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateSubSubcategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Sub-subcategory name is required.'], 400);
                return;
            }
            $subSubcat = Database::fetchOne("SELECT subcategory_id FROM sub_subcategories WHERE id = ?", [$id]);
            if (!$subSubcat) {
                Response::json(['success' => false, 'message' => 'Sub-subcategory not found.'], 404);
                return;
            }
            $subcategoryId = (int)$subSubcat['subcategory_id'];
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM sub_subcategories WHERE subcategory_id = ? AND (name = ? OR slug = ?) AND id != ?",
                [$subcategoryId, $name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Sub-subcategory '{$existing['name']}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE sub_subcategories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteSubSubcategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE sub_subcategory_id = ? AND is_active = 1", [$id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this sub-subcategory because {$count} product style(s) are linked to it."], 400);
                return;
            }
            Database::execute("UPDATE sub_subcategories SET is_active = 0 WHERE id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Get category attributes for dynamic catalog hierarchy specifications.
     * Matches exact hierarchy with multi-level scoping and cross-catalog synchronization.
     */
    public function ajaxGetCategoryAttributes(int $categoryId): void {
        try {
            $subcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
            $subSubcategoryId = !empty($_GET['sub_subcategory_id']) ? (int)$_GET['sub_subcategory_id'] : null;

            // 1. Resolve Category IDs across both `categories` and `warehouse_categories` by name and ID
            $allCatIds = [$categoryId];
            $catRow = Database::fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]);
            if (!$catRow) {
                $catRow = Database::fetchOne("SELECT name FROM warehouse_categories WHERE id = ?", [$categoryId]);
            }
            if ($catRow && !empty($catRow['name'])) {
                $catName = trim($catRow['name']);
                $cIds = Database::fetchAll("SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(?)", [$catName]);
                $wcIds = Database::fetchAll("SELECT id FROM warehouse_categories WHERE LOWER(TRIM(name)) = LOWER(?)", [$catName]);
                $allCatIds = array_values(array_unique(array_filter(array_merge($allCatIds, array_column($cIds, 'id'), array_column($wcIds, 'id')))));
            }

            // 2. Resolve Subcategory IDs across both `subcategories` and `warehouse_subcategories`
            $allSubcatIds = $subcategoryId ? [$subcategoryId] : [];
            if ($subcategoryId) {
                $subcatRow = Database::fetchOne("SELECT name FROM subcategories WHERE id = ?", [$subcategoryId]);
                if (!$subcatRow) {
                    $subcatRow = Database::fetchOne("SELECT name FROM warehouse_subcategories WHERE id = ?", [$subcategoryId]);
                }
                if ($subcatRow && !empty($subcatRow['name'])) {
                    $subName = trim($subcatRow['name']);
                    $sIds = Database::fetchAll("SELECT id FROM subcategories WHERE LOWER(TRIM(name)) = LOWER(?)", [$subName]);
                    $wsIds = Database::fetchAll("SELECT id FROM warehouse_subcategories WHERE LOWER(TRIM(name)) = LOWER(?)", [$subName]);
                    $allSubcatIds = array_values(array_unique(array_filter(array_merge($allSubcatIds, array_column($sIds, 'id'), array_column($wsIds, 'id')))));
                }
            }

            // 3. Resolve Sub-Subcategory IDs across both `sub_subcategories` and `warehouse_sub_subcategories`
            $allSubSubcatIds = $subSubcategoryId ? [$subSubcategoryId] : [];
            if ($subSubcategoryId) {
                $subSubRow = Database::fetchOne("SELECT name FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]);
                if (!$subSubRow) {
                    $subSubRow = Database::fetchOne("SELECT name FROM warehouse_sub_subcategories WHERE id = ?", [$subSubcategoryId]);
                }
                if ($subSubRow && !empty($subSubRow['name'])) {
                    $sscName = trim($subSubRow['name']);
                    $ssIds = Database::fetchAll("SELECT id FROM sub_subcategories WHERE LOWER(TRIM(name)) = LOWER(?)", [$sscName]);
                    $wssIds = Database::fetchAll("SELECT id FROM warehouse_sub_subcategories WHERE LOWER(TRIM(name)) = LOWER(?)", [$sscName]);
                    $allSubSubcatIds = array_values(array_unique(array_filter(array_merge($allSubSubcatIds, array_column($ssIds, 'id'), array_column($wssIds, 'id')))));
                }
            }

            // 4. Query matching attributes from `wh_category_attributes`
            $catPlaceholders = implode(',', array_fill(0, count($allCatIds), '?'));
            $sql = "SELECT * FROM wh_category_attributes WHERE category_id IN ($catPlaceholders) AND is_active = 1";
            $rawAttributes = Database::fetchAll($sql, array_values($allCatIds));

            // Hierarchy matching & override logic:
            // Level 1: Category root (subcategory_id is NULL or 0)
            // Level 2: Subcategory (subcategory_id in $allSubcatIds and (sub_subcategory_id is NULL or 0))
            // Level 3: Sub-subcategory (sub_subcategory_id in $allSubSubcatIds)
            // More specific level overrides less specific level for attributes with the same name
            $scopedAttrs = [];

            foreach ($rawAttributes as $row) {
                $rSubcat = !empty($row['subcategory_id']) ? (int)$row['subcategory_id'] : 0;
                $rSubSubcat = !empty($row['sub_subcategory_id']) ? (int)$row['sub_subcategory_id'] : 0;

                $level = 0;
                if (!empty($allSubSubcatIds) && $rSubSubcat > 0 && in_array($rSubSubcat, $allSubSubcatIds, true)) {
                    $level = 3;
                } elseif (!empty($allSubcatIds) && $rSubcat > 0 && in_array($rSubcat, $allSubcatIds, true) && $rSubSubcat === 0) {
                    $level = 2;
                } elseif ($rSubcat === 0 && $rSubSubcat === 0) {
                    $level = 1;
                }

                if ($level > 0) {
                    $rawName = trim($row['attr_name']);
                    $attrKey = mb_strtoupper($rawName);

                    // Normalize Size & Color attribute names and keys to avoid duplicate cards (e.g. SIZE vs SIZES)
                    if (in_array($attrKey, ['SIZE', 'SIZES', 'SIZE ATTRIBUTE', 'SIZE ATTRIBUTES', 'SIZES ATTRIBUTE', 'SIZES ATTRIBUTES'], true)) {
                        $attrKey = 'SIZE';
                        $row['attr_name'] = 'SIZE';
                    } elseif (in_array($attrKey, ['COLOR', 'COLORS', 'COLOR ATTRIBUTE', 'COLOR ATTRIBUTES'], true)) {
                        $attrKey = 'COLOR';
                        $row['attr_name'] = 'COLOR';
                    }

                    if (!isset($scopedAttrs[$attrKey]) || $level >= $scopedAttrs[$attrKey]['level']) {
                        $scopedAttrs[$attrKey] = [
                            'level' => $level,
                            'data'  => $row
                        ];
                    }
                }
            }

            // 5. Query matching product style(s) and their variants from catalog (products & product_variants)
            $catalogSizes = [];
            $catalogColors = [];

            $matchingProducts = [];
            if (!empty($allSubSubcatIds)) {
                $subSubPlaceholders = implode(',', array_fill(0, count($allSubSubcatIds), '?'));
                $matchingProducts = Database::fetchAll(
                    "SELECT id, name, code FROM products WHERE sub_subcategory_id IN ($subSubPlaceholders) AND is_active = 1 ORDER BY id DESC",
                    array_values($allSubSubcatIds)
                );
            }
            if (empty($matchingProducts) && !empty($allSubcatIds)) {
                $subPlaceholders = implode(',', array_fill(0, count($allSubcatIds), '?'));
                $matchingProducts = Database::fetchAll(
                    "SELECT id, name, code FROM products WHERE subcategory_id IN ($subPlaceholders) AND is_active = 1 ORDER BY id DESC",
                    array_values($allSubcatIds)
                );
            }
            if (empty($matchingProducts) && !empty($allCatIds)) {
                $cPlaceholders = implode(',', array_fill(0, count($allCatIds), '?'));
                $matchingProducts = Database::fetchAll(
                    "SELECT id, name, code FROM products WHERE category_id IN ($cPlaceholders) AND is_active = 1 ORDER BY id DESC",
                    array_values($allCatIds)
                );
            }

            if (!empty($matchingProducts)) {
                $pIds = array_column($matchingProducts, 'id');
                $pPlaceholders = implode(',', array_fill(0, count($pIds), '?'));
                $pvRows = Database::fetchAll(
                    "SELECT pv.*, s.name as size_name, s.code as size_code, c.name as color_name 
                     FROM product_variants pv
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors c ON pv.color_id = c.id
                     WHERE pv.product_id IN ($pPlaceholders) AND pv.is_active = 1
                     ORDER BY pv.id ASC",
                    $pIds
                );

                foreach ($pvRows as $pv) {
                    $sName = !empty($pv['size_code']) ? $pv['size_code'] : (!empty($pv['size_name']) ? $pv['size_name'] : '');
                    if (!empty($sName)) {
                        foreach (explode(',', $sName) as $sp) {
                            $sp = trim($sp);
                            if ($sp !== '' && !in_array($sp, $catalogSizes, true)) {
                                $catalogSizes[] = $sp;
                            }
                        }
                    }

                    $cName = !empty($pv['color_name']) ? trim($pv['color_name']) : '';
                    if (!empty($cName)) {
                        foreach (explode(',', $cName) as $cp) {
                            $cp = trim($cp);
                            if ($cp !== '' && !in_array($cp, $catalogColors, true)) {
                                $catalogColors[] = $cp;
                            }
                        }
                    }
                }
            }

            // Master active sizes as fallback if no catalog sizes or configured sizes exist
            $masterSizes = Database::fetchAll("SELECT id, name, code FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            $defaultSizeCodes = !empty($masterSizes) ? array_values(array_unique(array_column($masterSizes, 'code'))) : ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

            $finalAttributes = [];
            $hasSizeAttr = false;
            $hasColorAttr = false;

            foreach ($scopedAttrs as $item) {
                $attr = $item['data'];
                $attrNameUpper = mb_strtoupper(trim($attr['attr_name']));

                if (in_array($attrNameUpper, ['SIZE', 'SIZES', 'SIZE ATTRIBUTE', 'SIZE ATTRIBUTES', 'SIZES ATTRIBUTE', 'SIZES ATTRIBUTES'], true)) {
                    $hasSizeAttr = true;
                    $attr['attr_name'] = 'SIZE';

                    // If catalog product style exists and has defined sizes, prioritize catalog style's sizes so they match exactly
                    if (!empty($catalogSizes)) {
                        $opts = $catalogSizes;
                    } else {
                        $opts = !empty($attr['attr_options']) ? array_map('trim', explode(',', $attr['attr_options'])) : [];
                        $opts = array_values(array_unique(array_filter($opts)));
                        if (empty($opts)) {
                            $opts = $defaultSizeCodes;
                        }
                    }
                    $attr['attr_options'] = implode(',', $opts);
                    $attr['attr_type'] = 'MULTI_SELECT';
                    $attr['sort_order'] = 1;
                } elseif (in_array($attrNameUpper, ['COLOR', 'COLORS', 'COLOR ATTRIBUTE', 'COLOR ATTRIBUTES'], true)) {
                    // In apparel roll management, fabric roll colors are tracked at the individual roll card level.
                    // Do not inject or require separate COLOR attribute checkboxes in cutting specifications.
                    continue;
                } else {
                    // Suppress empty select/multi_select attributes with zero options
                    if (in_array($attr['attr_type'], ['SELECT', 'MULTI_SELECT'], true) && empty(trim($attr['attr_options'] ?? ''))) {
                        continue;
                    }
                }

                $finalAttributes[] = $attr;
            }

            // If no SIZE attribute was defined in wh_category_attributes, provide it dynamically
            if (!$hasSizeAttr) {
                $sizeOptions = !empty($catalogSizes) ? $catalogSizes : $defaultSizeCodes;
                $finalAttributes[] = [
                    'id'                 => 0,
                    'category_id'        => $categoryId,
                    'subcategory_id'     => $subcategoryId,
                    'sub_subcategory_id' => $subSubcategoryId,
                    'attr_name'          => 'SIZE',
                    'attr_type'          => 'MULTI_SELECT',
                    'attr_options'       => implode(',', $sizeOptions),
                    'is_required'        => 0,
                    'sort_order'         => 1,
                    'is_active'          => 1,
                ];
            }

            // Sort attributes by sort_order
            usort($finalAttributes, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

            Response::json([
                'success'      => true,
                'attributes'   => $finalAttributes,
                'master_sizes' => $masterSizes
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Add a new option to an existing category attribute dynamically (e.g. adding XXL, XXXL).
     */
    public function ajaxAddCategoryAttributeOption(): void {
        try {
            $attrId = (int)($_POST['attribute_id'] ?? 0);
            $attrName = mb_strtoupper(trim($_POST['attr_name'] ?? ''));
            if (in_array($attrName, ['SIZE', 'SIZES', 'SIZE ATTRIBUTE', 'SIZE ATTRIBUTES', 'SIZES ATTRIBUTE', 'SIZES ATTRIBUTES'], true)) {
                $attrName = 'SIZE';
            } elseif (in_array($attrName, ['COLOR', 'COLORS', 'COLOR ATTRIBUTE', 'COLOR ATTRIBUTES'], true)) {
                $attrName = 'COLOR';
            }
            $optionsInput = trim($_POST['options'] ?? '');

            if (empty($optionsInput)) {
                Response::json(['success' => false, 'message' => 'Please enter at least one option.'], 400);
                return;
            }

            $attr = null;
            if ($attrId > 0) {
                $attr = Database::fetchOne("SELECT * FROM wh_category_attributes WHERE id = ?", [$attrId]);
            }
            if (!$attr && !empty($attrName)) {
                $attr = Database::fetchOne("SELECT * FROM wh_category_attributes WHERE attr_name = ? ORDER BY id DESC LIMIT 1", [$attrName]);
            }

            if (!$attr) {
                Response::json(['success' => false, 'message' => 'Attribute not found.'], 404);
                return;
            }

            $currentOpts = !empty($attr['attr_options']) ? array_map('trim', explode(',', $attr['attr_options'])) : [];
            $currentOpts = array_filter($currentOpts, fn($v) => $v !== '');
            $currentOptsUpper = array_map('mb_strtoupper', $currentOpts);

            $newOptsInput = array_map('trim', explode(',', $optionsInput));
            $newOptsInput = array_filter($newOptsInput, fn($v) => $v !== '');
            $addedOpts = [];

            foreach ($newOptsInput as $rawOpt) {
                $optUpper = mb_strtoupper($rawOpt);
                if (!in_array($optUpper, $currentOptsUpper, true)) {
                    $currentOpts[] = $optUpper;
                    $currentOptsUpper[] = $optUpper;
                    $addedOpts[] = $optUpper;
                }
            }

            $updatedOptsStr = implode(',', $currentOpts);
            Database::execute("UPDATE wh_category_attributes SET attr_options = ?, attr_type = 'MULTI_SELECT', updated_at = NOW() WHERE id = ?", [$updatedOptsStr, $attr['id']]);

            // If attribute is SIZE, also add each added option to sizes table if not present
            if ($attr['attr_name'] === 'SIZE') {
                foreach ($addedOpts as $szCode) {
                    $existsSize = Database::fetchOne("SELECT id FROM sizes WHERE code = ?", [$szCode]);
                    if (!$existsSize) {
                        Database::execute("INSERT INTO sizes (name, code, is_active, created_at) VALUES (?, ?, 1, NOW())", [$szCode, $szCode]);
                    }
                }
            }

            Response::json([
                'success'     => true,
                'new_options' => $addedOpts,
                'all_options' => $currentOpts
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Remove an option from a category attribute dynamically.
     */
    public function ajaxDeleteCategoryAttributeOption(): void {
        try {
            $attrId = (int)($_POST['attribute_id'] ?? 0);
            $attrName = mb_strtoupper(trim($_POST['attr_name'] ?? ''));
            if (in_array($attrName, ['SIZE', 'SIZES', 'SIZE ATTRIBUTE', 'SIZE ATTRIBUTES', 'SIZES ATTRIBUTE', 'SIZES ATTRIBUTES'], true)) {
                $attrName = 'SIZE';
            } elseif (in_array($attrName, ['COLOR', 'COLORS', 'COLOR ATTRIBUTE', 'COLOR ATTRIBUTES'], true)) {
                $attrName = 'COLOR';
            }
            $optionToDelete = mb_strtoupper(trim($_POST['option'] ?? ''));

            if (empty($optionToDelete)) {
                Response::json(['success' => false, 'message' => 'Option value is required.'], 400);
                return;
            }

            $attr = null;
            if ($attrId > 0) {
                $attr = Database::fetchOne("SELECT * FROM wh_category_attributes WHERE id = ?", [$attrId]);
            }
            if (!$attr && !empty($attrName)) {
                $attr = Database::fetchOne("SELECT * FROM wh_category_attributes WHERE attr_name = ? ORDER BY id DESC LIMIT 1", [$attrName]);
            }

            if (!$attr) {
                Response::json(['success' => false, 'message' => 'Attribute not found.'], 404);
                return;
            }

            $currentOpts = !empty($attr['attr_options']) ? array_map('trim', explode(',', $attr['attr_options'])) : [];
            $filteredOpts = array_filter($currentOpts, fn($v) => mb_strtoupper(trim($v)) !== $optionToDelete);
            $updatedOptsStr = implode(',', $filteredOpts);

            Database::execute("UPDATE wh_category_attributes SET attr_options = ?, updated_at = NOW() WHERE id = ?", [$updatedOptsStr, $attr['id']]);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Quick Add category attribute dynamically without page reload.
     */
    public function ajaxQuickAddCategoryAttribute(): void {
        try {
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
            $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
            $attrName = mb_strtoupper(trim($_POST['attr_name'] ?? ''));
            if (in_array($attrName, ['SIZE', 'SIZES', 'SIZE ATTRIBUTE', 'SIZE ATTRIBUTES', 'SIZES ATTRIBUTE', 'SIZES ATTRIBUTES'], true)) {
                $attrName = 'SIZE';
            } elseif (in_array($attrName, ['COLOR', 'COLORS', 'COLOR ATTRIBUTE', 'COLOR ATTRIBUTES'], true)) {
                $attrName = 'COLOR';
            }
            $attrType = in_array(strtoupper(trim($_POST['attr_type'] ?? '')), ['TEXT', 'NUMBER', 'SELECT', 'MULTI_SELECT']) ? strtoupper(trim($_POST['attr_type'])) : 'SELECT';
            if ($attrName === 'SIZE' || $attrName === 'COLOR') {
                $attrType = 'MULTI_SELECT';
            }
            $attrOptions = trim($_POST['attr_options'] ?? '');
            $isRequired = !empty($_POST['is_required']) ? 1 : 0;

            if ($categoryId <= 0) {
                Response::json(['success' => false, 'message' => 'Please select a Main Category first.'], 400);
                return;
            }
            if (empty($attrName)) {
                Response::json(['success' => false, 'message' => 'Attribute Name is required.'], 400);
                return;
            }
            if (($attrType === 'SELECT' || $attrType === 'MULTI_SELECT') && empty($attrOptions)) {
                Response::json(['success' => false, 'message' => 'Please enter at least one option for the attribute.'], 400);
                return;
            }

            // Clean options: split by comma, trim, uppercase
            $formattedOptions = null;
            if (!empty($attrOptions)) {
                $opts = array_map('trim', explode(',', $attrOptions));
                $opts = array_filter($opts, function($v) { return $v !== ''; });
                $opts = array_map('mb_strtoupper', $opts);
                $formattedOptions = implode(',', $opts);
            }

            // Check if attribute with same name already exists for this scope
            $existingSql = "SELECT id FROM wh_category_attributes WHERE category_id = ? AND attr_name = ?";
            $existingParams = [$categoryId, $attrName];
            if ($subSubcategoryId) {
                $existingSql .= " AND sub_subcategory_id = ?";
                $existingParams[] = $subSubcategoryId;
            } elseif ($subcategoryId) {
                $existingSql .= " AND subcategory_id = ? AND (sub_subcategory_id IS NULL OR sub_subcategory_id = 0)";
                $existingParams[] = $subcategoryId;
            } else {
                $existingSql .= " AND (subcategory_id IS NULL OR subcategory_id = 0) AND (sub_subcategory_id IS NULL OR sub_subcategory_id = 0)";
            }
            $existing = Database::fetchOne($existingSql, $existingParams);
            if ($existing) {
                Database::execute(
                    "UPDATE wh_category_attributes SET attr_type = ?, attr_options = ?, is_required = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                    [$attrType, $formattedOptions, $isRequired, $existing['id']]
                );
                $attrId = (int)$existing['id'];
            } else {
                $maxSort = (int)(Database::fetchOne("SELECT MAX(sort_order) as m FROM wh_category_attributes WHERE category_id = ?", [$categoryId])['m'] ?? 0);
                Database::execute(
                    "INSERT INTO wh_category_attributes (category_id, subcategory_id, sub_subcategory_id, attr_name, attr_type, attr_options, is_required, sort_order, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                    [$categoryId, $subcategoryId, $subSubcategoryId, $attrName, $attrType, $formattedOptions, $isRequired, $maxSort + 1]
                );
                $attrId = (int)Database::lastInsertId();
            }

            $savedAttr = Database::fetchOne("SELECT * FROM wh_category_attributes WHERE id = ?", [$attrId]);
            Response::json([
                'success'   => true,
                'message'   => "Attribute '{$attrName}' saved successfully.",
                'attribute' => $savedAttr
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error saving attribute: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddBrand(): void {
        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Brand name is required.'], 400);
            return;
        }
        Database::execute("INSERT INTO brands (name, is_active) VALUES (?, 1)", [$name]);
        $id = (int)Database::lastInsertId();
        Response::json(['success' => true, 'id' => $id, 'name' => $name]);
    }

    public function ajaxQuickAddSize(): void {
        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        $name = mb_strtoupper(trim($_POST['name'] ?? $code));
        if (empty($code)) {
            Response::json(['success' => false, 'message' => 'Size code is required.'], 400);
            return;
        }
        Database::execute("INSERT INTO sizes (name, code, is_active) VALUES (?, ?, 1)", [$name, $code]);
        $id = (int)Database::lastInsertId();
        Response::json(['success' => true, 'id' => $id, 'code' => $code, 'name' => $name]);
    }

    public function ajaxDeleteSize(int $id): void {
        Database::execute("UPDATE sizes SET is_active = 0 WHERE id = ?", [$id]);
        Response::json(['success' => true, 'id' => $id]);
    }

    public function ajaxQuickAddColor(): void {
        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $hex = trim($_POST['hex_code'] ?? '#000000');
        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Color name is required.'], 400);
            return;
        }
        // Check if color already exists (case-insensitive)
        $existing = Database::fetchOne("SELECT id, name, hex_code FROM colors WHERE name = ?", [$name]);
        if ($existing) {
            // Re-activate if inactive and return existing
            Database::execute("UPDATE colors SET is_active = 1, hex_code = ? WHERE id = ?", [$hex, $existing['id']]);
            Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name, 'hex_code' => $hex]);
            return;
        }
        Database::execute("INSERT INTO colors (name, hex_code, is_active) VALUES (?, ?, 1)", [$name, $hex]);
        $id = (int)Database::lastInsertId();
        Response::json(['success' => true, 'id' => $id, 'name' => $name, 'hex_code' => $hex]);
    }

    public function ajaxDeleteColor(int $id): void {
        Database::execute("UPDATE colors SET is_active = 0 WHERE id = ?", [$id]);
        Response::json(['success' => true, 'id' => $id]);
    }

    public function searchProducts(): void {
        $q = trim($_GET['q'] ?? '');
        $products = Database::fetchAll(
            "SELECT p.id, p.name, p.code, p.material, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = 1 AND (p.name LIKE ? OR p.code LIKE ?)
             LIMIT 20",
            ['%' . $q . '%', '%' . $q . '%']
        );
        Response::json($products);
    }

    public function searchVariants(): void {
        $q = trim($_GET['q'] ?? '');
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.production_cost, pv.selling_price, pv.mrp, p.name as product_name, s.code as size_code, col.name as color_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             WHERE pv.is_active = 1 AND (pv.sku LIKE ? OR p.name LIKE ?)
             LIMIT 20",
            ['%' . $q . '%', '%' . $q . '%']
        );
        Response::json($variants);
    }

    public function checkStock(int $variantId): void {
        $balance = Database::fetchOne(
            "SELECT COALESCE(SUM(quantity), 0) as total_qty FROM stock_balances WHERE variant_id = ?",
            [$variantId]
        );
        Response::json(['variant_id' => $variantId, 'quantity' => (int)($balance['total_qty'] ?? 0)]);
    }

    // =========================================================================
    // PRODUCTION PROGRAMS
    // =========================================================================

    public function programList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $unitFilter = trim($_GET['unit_id'] ?? ($_GET['production_unit_id'] ?? ''));
        if ($scopedUnitId !== null) {
            $unitFilter = (string)$scopedUnitId;
        }

        $filters = [
            'status'             => trim($_GET['status'] ?? ''),
            'production_unit_id' => $unitFilter,
            'priority'           => trim($_GET['priority'] ?? ''),
            'search'             => trim($_GET['search'] ?? ''),
        ];

        $programs = ProductionProgram::getAll($filters);
        $units = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : Database::fetchAll("SELECT * FROM production_units WHERE is_active = 1 ORDER BY name ASC");

        $programRollsMap = [];
        foreach ($programs as $prog) {
            $programRollsMap[$prog['id']] = ProductionProgram::getRolls((int)$prog['id']);
        }

        Response::view('production.programs.index', [
            'pageTitle'        => 'Production Programs',
            'activeModule'     => 'production',
            'activeMenu'       => 'programs',
            'programs'         => $programs,
            'programRollsMap'  => $programRollsMap,
            'units'            => $units,
            'filters'          => $filters,
            'scopedUnitId'     => $scopedUnitId,
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Programs'],
            ],
        ]);
    }

    private function getAvailableSellerRollsWithPieces(int $excludeProgramId = 0): array {
        $rolls = Database::fetchAll(
            "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type,
                    COALESCE(u.used_quantity, 0) as used_quantity,
                    COALESCE(u.used_weight, 0) as used_weight,
                    COALESCE(u.used_price, 0) as used_price,
                    COALESCE(dmg.damaged_quantity, 0) as damaged_quantity,
                    COALESCE(dmg.damaged_weight, 0) as damaged_weight,
                    COALESCE(dmg.damaged_price, 0) as damaged_price,
                    GREATEST(0, sr.quantity - COALESCE(u.used_quantity, 0) - COALESCE(dmg.damaged_quantity, 0)) as available_quantity,
                    GREATEST(0.0, sr.weight - COALESCE(u.used_weight, 0) - COALESCE(dmg.damaged_weight, 0)) as available_weight,
                    GREATEST(0.0, sr.price - COALESCE(u.used_price, 0) - COALESCE(dmg.damaged_price, 0)) as available_price,
                    dmg.damage_reason,
                    dmg.damage_status
             FROM seller_rolls sr
             JOIN sellers s ON sr.seller_id = s.id
             LEFT JOIN (
                 SELECT ppr.seller_roll_id, 
                        SUM(ppr.roll_qty) as used_quantity,
                        SUM(ppr.roll_weight) as used_weight,
                        SUM(ppr.roll_price) as used_price
                 FROM production_program_rolls ppr
                 JOIN production_programs pp ON ppr.production_program_id = pp.id
                 WHERE pp.is_active = 1 AND pp.status != 'CANCELLED'" . ($excludeProgramId > 0 ? " AND pp.id != " . (int)$excludeProgramId : "") . "
                 GROUP BY ppr.seller_roll_id
             ) u ON sr.id = u.seller_roll_id
             LEFT JOIN (
                 SELECT rd.seller_roll_id,
                        SUM(rd.damaged_quantity) as damaged_quantity,
                        SUM(rd.damaged_weight) as damaged_weight,
                        SUM(rd.loss_amount) as damaged_price,
                        MAX(rd.damage_reason) as damage_reason,
                        MAX(rd.status) as damage_status
                 FROM roll_damages rd
                 WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                 GROUP BY rd.seller_roll_id
             ) dmg ON sr.id = dmg.seller_roll_id
             WHERE s.is_active = 1
             ORDER BY sr.id DESC"
        );

        $deletedLabels = Database::fetchAll("SELECT seller_roll_id, sequence FROM seller_roll_deleted_labels");
        $deletedMap = [];
        foreach ($deletedLabels as $dl) {
            $deletedMap[$dl['seller_roll_id'] . '_' . $dl['sequence']] = true;
        }

        $damages = Database::fetchAll("SELECT seller_roll_id, qr_code, damage_number FROM roll_damages WHERE status NOT IN ('CLOSED', 'CANCELLED')");
        $damageMap = [];
        foreach ($damages as $d) {
            if (!empty($d['qr_code'])) {
                $damageMap[strtoupper(trim($d['qr_code']))] = $d['damage_number'] ?: 'DAMAGED';
            }
        }

        $progRollsSql = "SELECT ppr.*, pp.program_number 
                         FROM production_program_rolls ppr
                         JOIN production_programs pp ON pp.id = ppr.production_program_id
                         WHERE pp.is_active = 1 AND pp.status != 'CANCELLED'";
        if ($excludeProgramId > 0) {
            $progRollsSql .= " AND pp.id != " . (int)$excludeProgramId;
        }
        $progRolls = Database::fetchAll($progRollsSql);

        $allocatedMap = [];
        $legacyUsedQty = [];
        foreach ($progRolls as $pr) {
            $rollId = (int)$pr['seller_roll_id'];
            if (!empty($pr['allocated_qr_codes'])) {
                $codes = array_filter(array_map('trim', explode(',', $pr['allocated_qr_codes'])));
                foreach ($codes as $c) {
                    $allocatedMap[strtoupper($c)] = $pr['program_number'] ?: ('Prog #' . $pr['production_program_id']);
                }
            } else {
                $legacyUsedQty[$rollId] = ($legacyUsedQty[$rollId] ?? 0) + (int)($pr['roll_qty'] ?? 1);
            }
        }

        foreach ($rolls as &$roll) {
            $rollId = (int)$roll['id'];
            $startSeq = (int)($roll['start_seq'] ?: 1);
            $endSeq = (int)($roll['end_seq'] ?: max(1, (int)$roll['quantity']));
            $startCode = trim($roll['start_code'] ?? '');
            $prefix = '';
            $seqDigits = 2;

            if (preg_match('/^([A-Za-z]{2}\d{4})(\d+)$/', $startCode, $m)) {
                $prefix = strtoupper($m[1]);
                $seqDigits = strlen($m[2]);
            } elseif (preg_match('/^([A-Za-z0-9_-]+?)(\d+)$/', $startCode, $m)) {
                $prefix = strtoupper($m[1]);
                $seqDigits = strlen($m[2]);
            } else {
                $prefix = 'FB' . date('my');
                $seqDigits = 2;
            }

            $pieces = [];
            $availCodes = [];
            $legacyToSkip = $legacyUsedQty[$rollId] ?? 0;

            for ($seq = $startSeq; $seq <= $endSeq; $seq++) {
                $code = $prefix . sprintf('%0' . $seqDigits . 'd', $seq);
                $status = 'AVAILABLE';
                $meta = '';

                if (isset($deletedMap[$rollId . '_' . $seq])) {
                    $status = 'DELETED';
                    $meta = 'Deleted Label';
                } elseif (isset($damageMap[strtoupper($code)])) {
                    $status = 'DAMAGED';
                    $meta = 'Damage ' . $damageMap[strtoupper($code)];
                } elseif (isset($allocatedMap[strtoupper($code)])) {
                    $status = 'USED';
                    $meta = 'Allocated to ' . $allocatedMap[strtoupper($code)];
                } elseif ($legacyToSkip > 0) {
                    $status = 'USED';
                    $meta = 'Used in Program';
                    $legacyToSkip--;
                }

                if ($status === 'AVAILABLE') {
                    $availCodes[] = $code;
                }

                $pieces[] = [
                    'seq' => $seq,
                    'code' => $code,
                    'status' => $status,
                    'meta' => $meta,
                ];
            }

            $rangeSummary = '';
            if (!empty($availCodes)) {
                $rangeSummary = (count($availCodes) === 1) ? $availCodes[0] : ($availCodes[0] . ' → ' . end($availCodes));
            }

            $roll['pieces'] = $pieces;
            $roll['available_codes'] = $availCodes;
            $roll['range_summary'] = $rangeSummary;
            $roll['available_quantity'] = count($availCodes);
        }
        unset($roll);

        return $rolls;
    }

    private function validateProgramRollQrAllocations(array $submittedRolls, int $excludeProgramId = 0): ?string {
        $hasQrAllocations = false;
        foreach ($submittedRolls as $roll) {
            if (is_array($roll) && trim((string)($roll['allocated_qr_codes'] ?? '')) !== '') {
                $hasQrAllocations = true;
                break;
            }
        }
        if (!$hasQrAllocations) {
            return null;
        }

        $availableCodesByRoll = [];
        foreach ($this->getAvailableSellerRollsWithPieces($excludeProgramId) as $roll) {
            $availableCodesByRoll[(int)$roll['id']] = array_fill_keys(
                array_map('strtoupper', $roll['available_codes'] ?? []),
                true
            );
        }

        $seenCodes = [];
        foreach ($submittedRolls as $index => $roll) {
            if (!is_array($roll)) {
                continue;
            }

            $rollId = (int)($roll['seller_roll_id'] ?? 0);
            $rawCodes = trim((string)($roll['allocated_qr_codes'] ?? ''));
            if ($rollId <= 0 || $rawCodes === '') {
                continue;
            }

            $codes = array_values(array_filter(array_map(
                static fn($code) => strtoupper(trim($code)),
                explode(',', $rawCodes)
            ), static fn($code) => $code !== ''));
            $uniqueCodes = array_values(array_unique($codes));
            if (count($codes) !== count($uniqueCodes)) {
                return 'Roll row ' . ($index + 1) . ' contains duplicate QR labels.';
            }

            $quantity = max(0, (int)($roll['roll_qty'] ?? ($roll['quantity'] ?? 0)));
            if ($quantity !== count($codes)) {
                return 'Roll row ' . ($index + 1) . ' quantity must match the number of selected QR labels.';
            }

            if (!isset($availableCodesByRoll[$rollId])) {
                return 'Roll row ' . ($index + 1) . ' is no longer available.';
            }

            foreach ($codes as $code) {
                $key = $rollId . ':' . $code;
                if (isset($seenCodes[$key])) {
                    return 'A QR label cannot be assigned more than once to this program.';
                }
                if (!isset($availableCodesByRoll[$rollId][$code])) {
                    return 'QR label ' . $code . ' is unavailable for the selected roll.';
                }
                $seenCodes[$key] = true;
            }
        }

        return null;
    }

    public function createProgram(): void {
        self::ensureProgramRollAttributesSchema();
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $units = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : Database::fetchAll("SELECT * FROM production_units WHERE is_active = 1 ORDER BY name ASC");
        $categories = Database::fetchAll(
            "SELECT c.* FROM categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order ASC, c.name ASC"
        );
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");

        // Fetch raw material rolls from seller_rolls with individual piece QR codes breakdown and live availability
        $rolls = $this->getAvailableSellerRollsWithPieces();

        // Auto-generate suggested program code PRG-YYYYMMDD-0001
        $count = Database::fetchOne("SELECT COUNT(*) as c FROM production_programs")['c'] ?? 0;
        $suggestedProgramNumber = 'PRG-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        $allSubcategories = Database::fetchAll(
            "SELECT id, category_id, name, slug FROM subcategories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        $allSubSubcategories = Database::fetchAll(
            "SELECT id, subcategory_id, name, slug FROM sub_subcategories WHERE is_active = 1 ORDER BY name ASC"
        );

        Response::view('production.programs.create', [
            'pageTitle'              => 'Create Production Program',
            'activeModule'           => 'production',
            'activeMenu'             => 'programs',
            'units'                  => $units,
            'categories'             => $categories,
            'allSubcategories'       => $allSubcategories,
            'allSubSubcategories'    => $allSubSubcategories,
            'rolls'                  => $rolls,
            'users'                  => $users,
            'scopedUnitId'           => $scopedUnitId,
            'suggestedProgramNumber' => $suggestedProgramNumber,
            'breadcrumb'             => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Programs', 'url' => url('production/programs')],
                ['label' => 'Create Program'],
            ],
        ]);
    }

    public function storeProgram(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        self::ensureProgramRollAttributesSchema();

        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        if ($scopedUnitId !== null) {
            $_POST['production_unit_id'] = $scopedUnitId;
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $personName = trim((string)($_POST['person_name'] ?? ''));
        $programNumber = mb_strtoupper(trim($_POST['program_number'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : (!empty($_POST['target_date']) ? $_POST['target_date'] : date('Y-m-d', strtotime('+14 days')));
        $unitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : $scopedUnitId;
        $priority = $_POST['priority'] ?? 'MEDIUM';
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Program Name is required.');
            Response::back();
            return;
        }

        if (empty($personName)) {
            Session::flash('error', 'Person Name (Planner / Creator) is required.');
            Response::back();
            return;
        }

        // Process multiple rolls from $_POST['rolls']
        $submittedRolls = !empty($_POST['rolls']) && is_array($_POST['rolls']) ? $_POST['rolls'] : [];
        $qrAllocationError = $this->validateProgramRollQrAllocations($submittedRolls);
        if ($qrAllocationError !== null) {
            Session::flash('error', $qrAllocationError);
            Response::back();
            return;
        }
        $validRolls = [];
        $totalRollWeight = 0.0;
        $totalRollPrice = 0.0;
        $totalRollQty = 0;

        foreach ($submittedRolls as $r) {
            $rId = !empty($r['seller_roll_id']) ? (int)$r['seller_roll_id'] : 0;
            if ($rId <= 0) continue;

            $rQty = max(0, (int)($r['roll_qty'] ?? ($r['quantity'] ?? 0)));
            $rWeight = max(0.0, (float)($r['roll_weight'] ?? 0));
            $rPrice = max(0.0, (float)($r['roll_price'] ?? 0));
            $rCategoryId = !empty($r['category_id']) ? (int)$r['category_id'] : (!empty($_POST['category_id']) ? (int)$_POST['category_id'] : null);
            $rSubcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : (!empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null);
            $rSubSubcatId = !empty($r['sub_subcategory_id']) ? (int)$r['sub_subcategory_id'] : (!empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null);

            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name, quantity FROM seller_rolls WHERE id = ?", [$rId]);

            if ($sellerData) {
                if ($rQty <= 0 && !empty($sellerData['quantity'])) $rQty = (int)$sellerData['quantity'];
                if ($rWeight <= 0) $rWeight = (float)$sellerData['weight'];
                if ($rPrice <= 0) $rPrice = (float)$sellerData['price'];
                $validRolls[] = [
                    'seller_roll_id'     => $rId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'category_id'        => $rCategoryId,
                    'subcategory_id'     => $rSubcatId,
                    'sub_subcategory_id' => $rSubSubcatId,
                    'roll_qty'           => $rQty,
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'allocated_qr_codes' => !empty($r['allocated_qr_codes']) ? $r['allocated_qr_codes'] : null,
                    'attributes'         => !empty($r['attributes']) ? $r['attributes'] : null,
                ];
                $totalRollQty += $rQty;
                $totalRollWeight += $rWeight;
                $totalRollPrice += $rPrice;
            }
        }

        // Fallback to single roll selection if rolls array was empty
        if (empty($validRolls) && !empty($_POST['seller_roll_id'])) {
            $singleRollId = (int)$_POST['seller_roll_id'];
            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name, quantity FROM seller_rolls WHERE id = ?", [$singleRollId]);
            if ($sellerData) {
                $rQty = max(0, (int)($_POST['roll_qty'] ?? ($sellerData['quantity'] ?? 0)));
                $rWeight = max(0.0, (float)($_POST['roll_weight'] ?? $sellerData['weight']));
                $rPrice = max(0.0, (float)($_POST['roll_price'] ?? $sellerData['price']));
                $validRolls[] = [
                    'seller_roll_id'     => $singleRollId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'category_id'        => $categoryId,
                    'subcategory_id'     => $subcategoryId,
                    'sub_subcategory_id' => $subSubcategoryId,
                    'roll_qty'           => $rQty,
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'allocated_qr_codes' => !empty($r['allocated_qr_codes']) ? $r['allocated_qr_codes'] : null,
                    'attributes'         => !empty($r['attributes']) ? $r['attributes'] : null,
                ];
                $totalRollQty = $rQty;
                $totalRollWeight = $rWeight;
                $totalRollPrice = $rPrice;
            }
        }

        if (empty($validRolls)) {
            Session::flash('error', 'Please select at least one Raw Material Roll from sellers.');
            Response::back();
            return;
        }

        // Verify total requested roll quantity does not exceed available stock
        $rollUsageMap = [];
        foreach ($validRolls as $vr) {
            $rid = (int)$vr['seller_roll_id'];
            $rollUsageMap[$rid] = ($rollUsageMap[$rid] ?? 0) + (int)$vr['roll_qty'];
        }

        foreach ($rollUsageMap as $rid => $reqQty) {
            $sellerRoll = Database::fetchOne(
                "SELECT sr.roll_number, sr.quantity,
                        GREATEST(0, sr.quantity - COALESCE(u.used_quantity, 0) - COALESCE(dmg.damaged_quantity, 0)) as available_quantity
                 FROM seller_rolls sr
                 LEFT JOIN (
                     SELECT ppr.seller_roll_id, SUM(ppr.roll_qty) as used_quantity
                     FROM production_program_rolls ppr
                     JOIN production_programs pp ON ppr.production_program_id = pp.id
                     WHERE pp.is_active = 1 AND pp.status != 'CANCELLED'
                     GROUP BY ppr.seller_roll_id
                 ) u ON sr.id = u.seller_roll_id
                 LEFT JOIN (
                     SELECT rd.seller_roll_id, SUM(rd.damaged_quantity) as damaged_quantity
                     FROM roll_damages rd
                     WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                     GROUP BY rd.seller_roll_id
                 ) dmg ON sr.id = dmg.seller_roll_id
                 WHERE sr.id = ?",
                [$rid]
            );
            if ($sellerRoll) {
                $maxStock = (int)($sellerRoll['available_quantity'] ?? $sellerRoll['quantity']);
                if ($reqQty > $maxStock) {
                    Session::flash('error', "Total requested quantity ({$reqQty} pcs) for Roll '{$sellerRoll['roll_number']}' exceeds available stock ({$maxStock} pcs after deducting damages and active programs). Please adjust quantities.");
                    Response::back();
                    return;
                }
            }
        }

        $primaryRoll = $validRolls[0];
        $sellerRollId = $primaryRoll['seller_roll_id'];
        $sellerId = $primaryRoll['seller_id'];

        if (!$categoryId && !empty($primaryRoll['category_id'])) {
            $categoryId = $primaryRoll['category_id'];
            $subcategoryId = $primaryRoll['subcategory_id'];
            $subSubcategoryId = $primaryRoll['sub_subcategory_id'];
        }

        // Optional: find or link product_id for backward compatibility
        $productId = null;
        if ($subSubcategoryId) {
            $p = Database::fetchOne("SELECT id FROM products WHERE sub_subcategory_id = ? AND is_active = 1 LIMIT 1", [$subSubcategoryId]);
            if ($p) $productId = (int)$p['id'];
        }
        if (!$productId && $subcategoryId) {
            $p = Database::fetchOne("SELECT id FROM products WHERE subcategory_id = ? AND is_active = 1 LIMIT 1", [$subcategoryId]);
            if ($p) $productId = (int)$p['id'];
        }
        if (!$productId && $categoryId) {
            $p = Database::fetchOne("SELECT id FROM products WHERE category_id = ? AND is_active = 1 LIMIT 1", [$categoryId]);
            if ($p) $productId = (int)$p['id'];
        }

        if (empty($programNumber)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM production_programs")['c'] ?? 0;
            $programNumber = 'PRG-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        }

        try {
            $userId = Session::userId();
            $programId = ProductionProgram::create([
                'name'               => $name,
                'program_number'     => $programNumber,
                'person_name'        => $personName,
                'production_unit_id' => $unitId,
                'product_id'         => $productId,
                'seller_roll_id'     => $sellerRollId,
                'seller_id'          => $sellerId,
                'roll_weight'        => $totalRollWeight,
                'roll_price'         => $totalRollPrice,
                'category_id'        => $categoryId,
                'subcategory_id'     => $subcategoryId,
                'sub_subcategory_id' => $subSubcategoryId,
                'planned_quantity'   => $totalRollQty,
                'completed_quantity' => 0,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'target_date'        => $endDate,
                'priority'           => $priority,
                'assigned_to'        => $assignedTo,
                'status'             => 'APPROVED',
                'approved_by'        => $userId,
                'approved_at'        => date('Y-m-d H:i:s'),
                'notes'              => $notes,
                'created_by'         => $userId,
            ]);

            // Sync all assigned rolls
            ProductionProgram::syncRolls($programId, $validRolls);

            AuditService::log('production', 'create', 'production_programs', $programId, null, ['name' => $name, 'program_number' => $programNumber, 'roll_count' => count($validRolls)]);
            Session::flash('success', "Production Program '{$name}' created with " . count($validRolls) . " fabric roll(s).");
            Response::redirect(url('production/programs'));
        } catch (Exception $e) {
            Session::flash('error', 'Error creating program: ' . $e->getMessage());
            Response::back();
        }
    }

    public function editProgram(string $id): void {
        self::ensureProgramRollAttributesSchema();
        $program = ProductionProgram::find((int)$id);
        if (!$program) {
            Response::error(404, 'Program not found.');
        }

        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $units = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : Database::fetchAll("SELECT * FROM production_units WHERE is_active = 1 ORDER BY name ASC");
        $categories = Database::fetchAll(
            "SELECT c.* FROM categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order ASC, c.name ASC"
        );
        $subcategories = $program['category_id'] ? Database::fetchAll("SELECT * FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC", [$program['category_id']]) : [];
        $subSubcategories = $program['subcategory_id'] ? Database::fetchAll("SELECT * FROM sub_subcategories WHERE subcategory_id = ? AND is_active = 1 ORDER BY name ASC", [$program['subcategory_id']]) : [];
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $rolls = Database::fetchAll(
            "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type,
                    COALESCE(u.used_quantity, 0) as used_quantity,
                    COALESCE(u.used_weight, 0) as used_weight,
                    COALESCE(u.used_price, 0) as used_price,
                    COALESCE(dmg.damaged_quantity, 0) as damaged_quantity,
                    COALESCE(dmg.damaged_weight, 0) as damaged_weight,
                    COALESCE(dmg.damaged_price, 0) as damaged_price,
                    GREATEST(0, sr.quantity - COALESCE(u.used_quantity, 0) - COALESCE(dmg.damaged_quantity, 0)) as available_quantity,
                    GREATEST(0.0, sr.weight - COALESCE(u.used_weight, 0) - COALESCE(dmg.damaged_weight, 0)) as available_weight,
                    GREATEST(0.0, sr.price - COALESCE(u.used_price, 0) - COALESCE(dmg.damaged_price, 0)) as available_price,
                    dmg.damage_reason,
                    dmg.damage_status
             FROM seller_rolls sr
             JOIN sellers s ON sr.seller_id = s.id
             LEFT JOIN (
                 SELECT ppr.seller_roll_id, 
                        SUM(ppr.roll_qty) as used_quantity,
                        SUM(ppr.roll_weight) as used_weight,
                        SUM(ppr.roll_price) as used_price
                 FROM production_program_rolls ppr
                 JOIN production_programs pp ON ppr.production_program_id = pp.id
                 WHERE pp.is_active = 1 AND pp.status != 'CANCELLED' AND pp.id != ?
                 GROUP BY ppr.seller_roll_id
             ) u ON sr.id = u.seller_roll_id
             LEFT JOIN (
                 SELECT rd.seller_roll_id,
                        SUM(rd.damaged_quantity) as damaged_quantity,
                        SUM(rd.damaged_weight) as damaged_weight,
                        SUM(rd.loss_amount) as damaged_price,
                        MAX(rd.damage_reason) as damage_reason,
                        MAX(rd.status) as damage_status
                 FROM roll_damages rd
                 WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                 GROUP BY rd.seller_roll_id
             ) dmg ON sr.id = dmg.seller_roll_id
             WHERE s.is_active = 1
             ORDER BY sr.id DESC",
            [(int)$id]
        );
        $programRolls = ProductionProgram::getRolls((int)$id);

        $allSubcategories = Database::fetchAll(
            "SELECT id, category_id, name, slug FROM subcategories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        $allSubSubcategories = Database::fetchAll(
            "SELECT id, subcategory_id, name, slug FROM sub_subcategories WHERE is_active = 1 ORDER BY name ASC"
        );

        Response::view('production.programs.edit', [
            'pageTitle'           => 'Edit Program — ' . ($program['name'] ?: $program['program_number']),
            'activeModule'        => 'production',
            'activeMenu'          => 'programs',
            'program'             => $program,
            'programRolls'        => $programRolls,
            'units'               => $units,
            'categories'          => $categories,
            'subcategories'       => $subcategories,
            'subSubcategories'    => $subSubcategories,
            'allSubcategories'    => $allSubcategories,
            'allSubSubcategories' => $allSubSubcategories,
            'rolls'               => $rolls,
            'users'               => $users,
            'scopedUnitId'        => $scopedUnitId,
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Programs', 'url' => url('production/programs')],
                ['label' => $program['program_number'], 'url' => url('production/programs/view/' . $id)],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function updateProgram(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        self::ensureProgramRollAttributesSchema();

        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        if ($scopedUnitId !== null) {
            $_POST['production_unit_id'] = $scopedUnitId;
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $personName = trim((string)($_POST['person_name'] ?? ''));
        $programNumber = mb_strtoupper(trim($_POST['program_number'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $unitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : $scopedUnitId;
        $priority = $_POST['priority'] ?? 'MEDIUM';
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Program Name is required.');
            Response::back();
            return;
        }

        if (empty($personName)) {
            Session::flash('error', 'Person Name (Planner / Creator) is required.');
            Response::back();
            return;
        }

        // Process multiple rolls from $_POST['rolls']
        $submittedRolls = !empty($_POST['rolls']) && is_array($_POST['rolls']) ? $_POST['rolls'] : [];
        $qrAllocationError = $this->validateProgramRollQrAllocations($submittedRolls, (int)$id);
        if ($qrAllocationError !== null) {
            Session::flash('error', $qrAllocationError);
            Response::back();
            return;
        }
        $validRolls = [];
        $totalRollWeight = 0.0;
        $totalRollPrice = 0.0;
        $totalRollQty = 0;

        foreach ($submittedRolls as $r) {
            $rId = !empty($r['seller_roll_id']) ? (int)$r['seller_roll_id'] : 0;
            if ($rId <= 0) continue;

            $rQty = max(0, (int)($r['roll_qty'] ?? ($r['quantity'] ?? 0)));
            $rWeight = max(0.0, (float)($r['roll_weight'] ?? 0));
            $rPrice = max(0.0, (float)($r['roll_price'] ?? 0));
            $rCategoryId = !empty($r['category_id']) ? (int)$r['category_id'] : (!empty($_POST['category_id']) ? (int)$_POST['category_id'] : null);
            $rSubcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : (!empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null);
            $rSubSubcatId = !empty($r['sub_subcategory_id']) ? (int)$r['sub_subcategory_id'] : (!empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null);

            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name, quantity FROM seller_rolls WHERE id = ?", [$rId]);

            if ($sellerData) {
                if ($rQty <= 0 && !empty($sellerData['quantity'])) $rQty = (int)$sellerData['quantity'];
                if ($rWeight <= 0) $rWeight = (float)$sellerData['weight'];
                if ($rPrice <= 0) $rPrice = (float)$sellerData['price'];
                $validRolls[] = [
                    'seller_roll_id'     => $rId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'category_id'        => $rCategoryId,
                    'subcategory_id'     => $rSubcatId,
                    'sub_subcategory_id' => $rSubSubcatId,
                    'roll_qty'           => $rQty,
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'allocated_qr_codes' => !empty($r['allocated_qr_codes']) ? $r['allocated_qr_codes'] : null,
                    'attributes'         => !empty($r['attributes']) ? $r['attributes'] : null,
                ];
                $totalRollQty += $rQty;
                $totalRollWeight += $rWeight;
                $totalRollPrice += $rPrice;
            }
        }

        if (empty($validRolls) && !empty($_POST['seller_roll_id'])) {
            $singleRollId = (int)$_POST['seller_roll_id'];
            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name, quantity FROM seller_rolls WHERE id = ?", [$singleRollId]);
            if ($sellerData) {
                $rQty = max(0, (int)($_POST['roll_qty'] ?? ($sellerData['quantity'] ?? 0)));
                $rWeight = max(0.0, (float)($_POST['roll_weight'] ?? $sellerData['weight']));
                $rPrice = max(0.0, (float)($_POST['roll_price'] ?? $sellerData['price']));
                $validRolls[] = [
                    'seller_roll_id'     => $singleRollId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'category_id'        => $categoryId,
                    'subcategory_id'     => $subcategoryId,
                    'sub_subcategory_id' => $subSubcategoryId,
                    'roll_qty'           => $rQty,
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'allocated_qr_codes' => !empty($r['allocated_qr_codes']) ? $r['allocated_qr_codes'] : null,
                    'attributes'         => !empty($r['attributes']) ? $r['attributes'] : null,
                ];
                $totalRollQty = $rQty;
                $totalRollWeight = $rWeight;
                $totalRollPrice = $rPrice;
            }
        }

        if (empty($validRolls)) {
            Session::flash('error', 'Please select at least one Raw Material Roll.');
            Response::back();
            return;
        }

        // Verify total requested roll quantity does not exceed available stock
        $rollUsageMap = [];
        foreach ($validRolls as $vr) {
            $rid = (int)$vr['seller_roll_id'];
            $rollUsageMap[$rid] = ($rollUsageMap[$rid] ?? 0) + (int)$vr['roll_qty'];
        }

        foreach ($rollUsageMap as $rid => $reqQty) {
            $sellerRoll = Database::fetchOne(
                "SELECT sr.roll_number, sr.quantity,
                        GREATEST(0, sr.quantity - COALESCE(u.used_quantity, 0) - COALESCE(dmg.damaged_quantity, 0)) as available_quantity
                 FROM seller_rolls sr
                 LEFT JOIN (
                     SELECT ppr.seller_roll_id, SUM(ppr.roll_qty) as used_quantity
                     FROM production_program_rolls ppr
                     JOIN production_programs pp ON ppr.production_program_id = pp.id
                     WHERE pp.is_active = 1 AND pp.status != 'CANCELLED' AND pp.id != ?
                     GROUP BY ppr.seller_roll_id
                 ) u ON sr.id = u.seller_roll_id
                 LEFT JOIN (
                     SELECT rd.seller_roll_id, SUM(rd.damaged_quantity) as damaged_quantity
                     FROM roll_damages rd
                     WHERE rd.status NOT IN ('CLOSED', 'CANCELLED')
                     GROUP BY rd.seller_roll_id
                 ) dmg ON sr.id = dmg.seller_roll_id
                 WHERE sr.id = ?",
                [(int)$id, $rid]
            );
            if ($sellerRoll) {
                $maxStock = (int)($sellerRoll['available_quantity'] ?? $sellerRoll['quantity']);
                if ($reqQty > $maxStock) {
                    Session::flash('error', "Total requested quantity ({$reqQty} pcs) for Roll '{$sellerRoll['roll_number']}' exceeds available stock ({$maxStock} pcs after deducting damages and other active programs). Please adjust quantities.");
                    Response::back();
                    return;
                }
            }
        }

        $primaryRoll = $validRolls[0];
        $sellerRollId = $primaryRoll['seller_roll_id'];
        $sellerId = $primaryRoll['seller_id'];

        if (!$categoryId && !empty($primaryRoll['category_id'])) {
            $categoryId = $primaryRoll['category_id'];
            $subcategoryId = $primaryRoll['subcategory_id'];
            $subSubcategoryId = $primaryRoll['sub_subcategory_id'];
        }

        try {
            ProductionProgram::update((int)$id, [
                'name'               => $name,
                'program_number'     => $programNumber,
                'person_name'        => $personName,
                'production_unit_id' => $unitId,
                'seller_roll_id'     => $sellerRollId,
                'seller_id'          => $sellerId,
                'roll_weight'        => $totalRollWeight,
                'roll_price'         => $totalRollPrice,
                'planned_quantity'   => $totalRollQty,
                'category_id'        => $categoryId,
                'subcategory_id'     => $subcategoryId,
                'sub_subcategory_id' => $subSubcategoryId,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'target_date'        => $endDate,
                'priority'           => $priority,
                'assigned_to'        => $assignedTo,
                'notes'              => $notes,
            ]);

            // Sync rolls in production_program_rolls
            ProductionProgram::syncRolls((int)$id, $validRolls);

            AuditService::log('production', 'UPDATE', 'production_programs', (int)$id, null, [
                'Program Name'   => $name,
                'Program Number' => $programNumber,
                'Priority'       => $priority,
                'Rolls Count'    => count($validRolls),
                'Total Quantity' => $totalRollQty,
                'Total Weight'   => number_format($totalRollWeight, 2) . ' kg',
                'Total Price'    => '₹' . number_format($totalRollPrice, 2),
            ]);

            Session::flash('success', "Program '{$name}' updated successfully.");
            Response::redirect(url('production/programs/view/' . $id));
        } catch (Exception $e) {
            Session::flash('error', 'Error updating program: ' . $e->getMessage());
            Response::back();
        }
    }

    public function viewProgram(string $id): void {
        $timeline = ProductionService::getProgramTimeline((int)$id);
        if (!$timeline['program']) {
            Response::error(404, 'Program not found.');
        }

        $programRolls = ProductionProgram::getRolls((int)$id);

        Response::view('production.programs.view', array_merge($timeline, [
            'pageTitle'    => 'Program Details — ' . $timeline['program']['program_number'],
            'activeModule' => 'production',
            'activeMenu'   => 'programs',
            'programRolls' => $programRolls,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Programs', 'url' => url('production/programs')],
                ['label' => $timeline['program']['program_number']],
            ],
        ]));
    }

    public function approveProgram(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        try {
            $userId = Session::userId();
            ProductionService::approveProgram((int)$id, $userId);
            Session::flash('success', 'Production program approved successfully.');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::back();
    }

    public function updateProgramStatus(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        $status = $_POST['status'] ?? '';
        $allowed = ['DRAFT', 'APPROVED', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'HOLD', 'CANCELLED'];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Invalid status.');
            Response::back();
        }

        ProductionProgram::updateStatus((int)$id, $status);
        Session::flash('success', "Program status changed to $status.");
        Response::back();
    }

    public function deleteProgram(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $program = ProductionProgram::find((int)$id);
        if (!$program) {
            Session::flash('error', 'Program not found.');
            Response::redirect(url('production/programs'));
            return;
        }

        ProductionProgram::delete((int)$id);
        AuditService::log('production', 'delete', 'production_programs', (int)$id, null, ['status' => 'CANCELLED', 'is_active' => 0]);

        $name = $program['name'] ?: $program['program_number'];
        Session::flash('success', "Production Program '{$name}' deleted successfully.");
        Response::redirect(url('production/programs'));
    }

    // =========================================================================
    // CUTTING
    // =========================================================================

    public function cuttingList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $filters = [
            'status'             => trim($_GET['status'] ?? ''),
            'program_id'         => trim($_GET['program_id'] ?? ''),
            'production_unit_id' => $scopedUnitId ? (string)$scopedUnitId : trim($_GET['production_unit_id'] ?? ''),
            'search'             => trim($_GET['search'] ?? ''),
        ];

        $batches = CuttingBatch::getAll($filters);
        $cuttingRollsMap = [];
        foreach ($batches as $b) {
            $cuttingRollsMap[$b['id']] = CuttingBatch::getRolls((int)$b['id']);
        }

        Response::view('production.cutting.index', [
            'pageTitle'       => 'Cutting Batches',
            'activeModule'    => 'production',
            'activeMenu'      => 'cutting',
            'batches'         => $batches,
            'cuttingRollsMap' => $cuttingRollsMap,
            'filters'         => $filters,
            'scopedUnitId'    => $scopedUnitId,
            'breadcrumb'      => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Cutting'],
            ],
        ]);
    }

    public function ajaxProgramDetails(int $id): void {
        $program = ProductionProgram::find((int)$id);
        if (!$program) {
            Response::json(['success' => false, 'message' => 'Program not found.'], 404);
            return;
        }

        // Fetch assigned program rolls
        $assignedRolls = ProductionProgram::getRolls($id);
        $sellerRolls = !empty($assignedRolls) ? $assignedRolls : [];

        // Fetch primary roll details
        $roll = null;
        if (!empty($sellerRolls)) {
            $roll = $sellerRolls[0];
        } elseif (!empty($program['seller_roll_id'])) {
            $roll = Database::fetchOne(
                "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
                 FROM seller_rolls sr
                 JOIN sellers s ON sr.seller_id = s.id
                 WHERE sr.id = ?",
                [$program['seller_roll_id']]
            );
        }

        // Fetch all rolls from the same seller or all active rolls for dropdown switching if none assigned
        if (empty($sellerRolls) && !empty($program['seller_id'])) {
            $sellerRolls = Database::fetchAll(
                "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
                 FROM seller_rolls sr
                 JOIN sellers s ON sr.seller_id = s.id
                 WHERE sr.seller_id = ?
                 ORDER BY sr.id ASC",
                [$program['seller_id']]
            );
        }
        if (empty($sellerRolls)) {
            $sellerRolls = Database::fetchAll(
                "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
                 FROM seller_rolls sr
                 JOIN sellers s ON sr.seller_id = s.id
                 WHERE s.is_active = 1
                 ORDER BY sr.id DESC"
            );
        }

        // Find linked product from program or catalog hierarchy
        $product = null;
        if (!empty($program['product_id'])) {
            $product = Database::fetchOne("SELECT * FROM products WHERE id = ?", [$program['product_id']]);
        }
        if (!$product && !empty($program['sub_subcategory_id'])) {
            $product = Database::fetchOne("SELECT * FROM products WHERE sub_subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$program['sub_subcategory_id']]);
        }
        if (!$product && !empty($program['subcategory_id'])) {
            $product = Database::fetchOne("SELECT * FROM products WHERE subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$program['subcategory_id']]);
        }
        if (!$product && !empty($program['category_id'])) {
            $product = Database::fetchOne("SELECT * FROM products WHERE category_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$program['category_id']]);
        }

        // Enrich program with product category hierarchy if program fields were not explicitly set
        if ($product) {
            if (empty($program['category_id']) && !empty($product['category_id'])) {
                $program['category_id'] = $product['category_id'];
            }
            if (empty($program['subcategory_id']) && !empty($product['subcategory_id'])) {
                $program['subcategory_id'] = $product['subcategory_id'];
            }
            if (empty($program['sub_subcategory_id']) && !empty($product['sub_subcategory_id'])) {
                $program['sub_subcategory_id'] = $product['sub_subcategory_id'];
            }
            if (empty($program['category_name']) && !empty($program['category_id'])) {
                $c = Database::fetchOne("SELECT name FROM categories WHERE id = ?", [$program['category_id']]);
                if ($c) $program['category_name'] = $c['name'];
            }
            if (empty($program['subcategory_name']) && !empty($program['subcategory_id'])) {
                $sc = Database::fetchOne("SELECT name FROM subcategories WHERE id = ?", [$program['subcategory_id']]);
                if ($sc) $program['subcategory_name'] = $sc['name'];
            }
            if (empty($program['sub_subcategory_name']) && !empty($program['sub_subcategory_id'])) {
                $ssc = Database::fetchOne("SELECT name FROM sub_subcategories WHERE id = ?", [$program['sub_subcategory_id']]);
                if ($ssc) $program['sub_subcategory_name'] = $ssc['name'];
            }
        }

        // Build 3-tier catalog hierarchy string
        $hierarchyParts = array_filter([
            $program['category_name'] ?? '',
            $program['subcategory_name'] ?? '',
            $program['sub_subcategory_name'] ?? ''
        ]);
        $catalogHierarchy = !empty($hierarchyParts) ? implode(' › ', $hierarchyParts) : 'General Apparel';

        $parsedSizes = [];
        $parsedColors = [];

        if ($product) {
            $pvRows = Database::fetchAll(
                "SELECT pv.*, s.name as size_name, s.code as size_code, c.name as color_name, c.hex_code as color_hex
                 FROM product_variants pv
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE pv.product_id = ? AND pv.is_active = 1",
                [$product['id']]
            );

            foreach ($pvRows as $pv) {
                $sName = $pv['size_name'] ?: ($pv['size_code'] ?: '');
                if (!empty($sName)) {
                    if (str_contains($sName, ',')) {
                        foreach (explode(',', $sName) as $sp) {
                            $sp = trim($sp);
                            if ($sp !== '' && !in_array($sp, $parsedSizes, true)) $parsedSizes[] = $sp;
                        }
                    } else {
                        if (!in_array($sName, $parsedSizes, true)) $parsedSizes[] = $sName;
                    }
                }

                $cName = $pv['color_name'] ?: '';
                if (!empty($cName)) {
                    if (str_contains($cName, ',')) {
                        foreach (explode(',', $cName) as $cp) {
                            $cp = trim($cp);
                            if ($cp !== '' && !in_array($cp, $parsedColors, true)) $parsedColors[] = $cp;
                        }
                    } else {
                        if (!in_array($cName, $parsedColors, true)) $parsedColors[] = $cName;
                    }
                }
            }
        }

        // Also parse attributes from assigned rolls for sizes
        if (!empty($assignedRolls)) {
            foreach ($assignedRolls as $ar) {
                if (!empty($ar['attributes'])) {
                    $dec = is_string($ar['attributes']) ? json_decode($ar['attributes'], true) : $ar['attributes'];
                    if (is_array($dec)) {
                        foreach ($dec as $k => $v) {
                            if (in_array(strtoupper($k), ['SIZE', 'SIZES'], true) && is_array($v)) {
                                foreach ($v as $sz) {
                                    $sz = trim((string)$sz);
                                    if ($sz !== '' && !in_array($sz, $parsedSizes, true)) {
                                        $parsedSizes[] = $sz;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Check active sizes and colors in database as fallback
        if (empty($parsedSizes)) {
            $rawSizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            foreach ($rawSizes as $s) {
                if (str_contains($s['name'], ',')) {
                    foreach (explode(',', $s['name']) as $sp) {
                        $sp = trim($sp);
                        if ($sp !== '' && !in_array($sp, $parsedSizes, true)) $parsedSizes[] = $sp;
                    }
                } else {
                    if (!in_array($s['name'], $parsedSizes, true)) $parsedSizes[] = $s['name'];
                }
            }
        }
        if (empty($parsedSizes)) {
            $parsedSizes = ['M', 'L', 'XL', 'XXL', '3XL'];
        }

        if (empty($parsedColors)) {
            $rawColors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");
            foreach ($rawColors as $c) {
                if (str_contains($c['name'], ',')) {
                    foreach (explode(',', $c['name']) as $cp) {
                        $cp = trim($cp);
                        if ($cp !== '' && !in_array($cp, $parsedColors, true)) $parsedColors[] = $cp;
                    }
                } else {
                    if (!in_array($c['name'], $parsedColors, true)) $parsedColors[] = $c['name'];
                }
            }
        }
        if (empty($parsedColors)) {
            $parsedColors = ['BLACK', 'BLUE', 'RED'];
        }

        $prgCode = $program['program_number'] ?: ($product ? $product['code'] : 'PRG');
        $variants = [];
        foreach ($parsedSizes as $sz) {
            foreach ($parsedColors as $col) {
                $variants[] = [
                    'id'              => null,
                    'sku'             => $prgCode . '-' . strtoupper(substr($sz, 0, 3)) . '-' . strtoupper(substr($col, 0, 3)),
                    'size_id'         => null,
                    'size_name'       => $sz,
                    'size_code'       => $sz,
                    'color_id'        => null,
                    'color_name'      => $col,
                    'color_hex'       => '#0d6efd',
                    'production_cost' => 0.00,
                ];
            }
        }

        $allRolls = Database::fetchAll(
            "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
             FROM seller_rolls sr
             JOIN sellers s ON sr.seller_id = s.id
             WHERE s.is_active = 1
             ORDER BY sr.id DESC"
        );

        Response::json([
            'success'          => true,
            'program'          => $program,
            'product'          => $product,
            'roll'             => $roll,
            'assignedRolls'    => $assignedRolls,
            'sellerRolls'      => $sellerRolls,
            'allRolls'         => $allRolls,
            'catalogHierarchy' => $catalogHierarchy,
            'variants'         => $variants,
            'sizes'            => $parsedSizes,
            'colors'           => $parsedColors,
        ]);
    }

    public function ajaxCuttingDetails(int $id): void {
        $batch = CuttingBatch::find((int)$id);
        if (!$batch) {
            Response::json(['success' => false, 'message' => 'Cutting batch not found.'], 404);
            return;
        }

        $items = CuttingBatch::getItems((int)$id);
        $program = ProductionProgram::find((int)$batch['program_id']);

        // Load rolls from cutting batch or program
        $rolls = CuttingBatch::getRolls((int)$id);
        if (empty($rolls) && $program) {
            $rolls = ProductionProgram::getRolls((int)$program['id']);
        }

        // Default hierarchy if no rolls found
        $defaultHierarchyParts = array_filter([
            $batch['category_name'] ?? ($program['category_name'] ?? ''),
            $batch['subcategory_name'] ?? ($program['subcategory_name'] ?? ''),
            $batch['sub_subcategory_name'] ?? ($program['sub_subcategory_name'] ?? '')
        ]);
        $defaultCatalogHierarchy = !empty($defaultHierarchyParts) ? implode(' › ', $defaultHierarchyParts) : ($batch['product_name'] ?? 'General Apparel');

        // If cutting batch has no specific items, fallback to program variants
        if (empty($items) && $program) {
            $parsedSizes = ['M', 'L', 'XL', 'XXL'];
            $parsedColors = ['MAROON', 'NAVY BLUE', 'BLACK'];
            $prgCode = $program['program_number'] ?? 'PRG';
            foreach ($parsedSizes as $sz) {
                foreach ($parsedColors as $col) {
                    $items[] = [
                        'variant_id'   => null,
                        'size_name'    => $sz,
                        'color_name'   => $col,
                        'cut_qty'      => 0,
                        'planned_qty'  => 0,
                        'sku'          => $prgCode . '-' . substr($sz, 0, 3) . '-' . substr($col, 0, 3),
                    ];
                }
            }
        }

        $usedWeight = (float)($batch['used_weight'] > 0 ? $batch['used_weight'] : max(0.0, (float)($batch['roll_weight'] ?? 0) - (float)($batch['wastage_weight'] ?? 0)));
        $batch['used_weight'] = $usedWeight;

        $totalCutQty = 0;
        foreach ($items as $it) {
            $totalCutQty += (int)($it['cut_qty'] ?? 0);
        }
        if ($totalCutQty <= 0 && !empty($batch['cut_qty'])) {
            $totalCutQty = (int)$batch['cut_qty'];
        }

        $defaultUnitWeight = ($usedWeight > 0 && $totalCutQty > 0) ? round($usedWeight / $totalCutQty, 3) : 0.000;

        $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
            'name'               => $defaultCatalogHierarchy,
            'category_id'        => $batch['category_id'] ?? ($program['category_id'] ?? null),
            'subcategory_id'     => $batch['subcategory_id'] ?? ($program['subcategory_id'] ?? null),
            'sub_subcategory_id' => $batch['sub_subcategory_id'] ?? ($program['sub_subcategory_id'] ?? null),
        ], 'cut_qty');

        // Enhance items with prices and default unit weight derived from cutting used weight
        foreach ($items as &$item) {
            $itemCutQty = (int)($item['cut_qty'] ?? 0);
            if ($defaultUnitWeight > 0) {
                $item['weight_per_piece'] = $defaultUnitWeight;
                if ($itemCutQty > 0) {
                    $item['weight'] = round($itemCutQty * $defaultUnitWeight, 2);
                }
            }
            if (empty($item['base_price']) || (float)$item['base_price'] <= 0) {
                if (!empty($item['variant_mrp']) && (float)$item['variant_mrp'] > 0) {
                    $item['base_price'] = (float)$item['variant_mrp'];
                }
            }
            if (empty($item['discount_price']) || (float)$item['discount_price'] <= 0) {
                if (!empty($item['variant_selling_price']) && (float)$item['variant_selling_price'] > 0) {
                    $item['discount_price'] = (float)$item['variant_selling_price'];
                } elseif (!empty($item['base_price']) && (float)$item['base_price'] > 0) {
                    $item['discount_price'] = (float)$item['base_price'];
                }
            }
        }
        unset($item);

        Response::json([
            'success'          => true,
            'cuttingBatch'     => $batch,
            'program'          => $program,
            'catalogHierarchy' => $defaultCatalogHierarchy,
            'catalogs'         => array_values($distinctCatalogs),
            'items'            => $items,
        ]);
    }

    public function ajaxStitchingDetails(int $id): void {
        $batch = StitchingBatch::find((int)$id);
        if (!$batch) {
            Response::json(['success' => false, 'message' => 'Stitching batch not found.'], 404);
            return;
        }

        $items = StitchingBatch::getItems((int)$id);
        $program = ProductionProgram::find((int)$batch['program_id']);

        // Load rolls from cutting batch or program
        $rolls = !empty($batch['cutting_batch_id']) ? CuttingBatch::getRolls((int)$batch['cutting_batch_id']) : [];
        if (empty($rolls) && $program) {
            $rolls = ProductionProgram::getRolls((int)$program['id']);
        }

        $defaultHierarchyParts = array_filter([
            $batch['category_name'] ?? ($program['category_name'] ?? ''),
            $batch['subcategory_name'] ?? ($program['subcategory_name'] ?? ''),
            $batch['sub_subcategory_name'] ?? ($program['sub_subcategory_name'] ?? '')
        ]);
        $defaultCatalogHierarchy = !empty($defaultHierarchyParts) ? implode(' › ', $defaultHierarchyParts) : ($batch['product_name'] ?? 'General Apparel');

        $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
            'name'               => $defaultCatalogHierarchy,
            'category_id'        => $batch['category_id'] ?? ($program['category_id'] ?? null),
            'subcategory_id'     => $batch['subcategory_id'] ?? ($program['subcategory_id'] ?? null),
            'sub_subcategory_id' => $batch['sub_subcategory_id'] ?? ($program['sub_subcategory_id'] ?? null),
        ], 'stitched_qty');

        $catalogSequences = [];
        foreach ($distinctCatalogs as $cName => &$catData) {
            $lastSeq = LabelItem::getLastSequenceForCatalog(
                $catData['category_id'] ?? null,
                $catData['subcategory_id'] ?? null,
                $catData['sub_subcategory_id'] ?? null,
                $catData['name'] ?? null
            );
            $catData['last_sequence'] = $lastSeq;
            $catData['next_sequence'] = $lastSeq + 1;

            $cKey = ($catData['category_id'] ?? 0) . '_' . ($catData['subcategory_id'] ?? 0) . '_' . ($catData['sub_subcategory_id'] ?? 0);
            $seqInfo = [
                'last_sequence'      => $lastSeq,
                'next_sequence'      => $lastSeq + 1,
                'category_id'        => $catData['category_id'] ?? null,
                'subcategory_id'     => $catData['subcategory_id'] ?? null,
                'sub_subcategory_id' => $catData['sub_subcategory_id'] ?? null,
                'name'               => $catData['name'] ?? null,
            ];
            $catalogSequences[$cKey] = $seqInfo;
            if (!empty($catData['name'])) {
                $catalogSequences[$catData['name']] = $seqInfo;
            }
        }
        unset($catData);

        foreach ($items as &$it) {
            $cKey = ($it['category_id'] ?? 0) . '_' . ($it['subcategory_id'] ?? 0) . '_' . ($it['sub_subcategory_id'] ?? 0);
            $seqInfo = $catalogSequences[$cKey] ?? ($catalogSequences[$it['catalog_hierarchy'] ?? ''] ?? null);
            $it['last_sequence'] = $seqInfo ? $seqInfo['last_sequence'] : 0;
            $it['next_sequence'] = $seqInfo ? $seqInfo['next_sequence'] : 1;
        }
        unset($it);

        Response::json([
            'success'          => true,
            'stitchingBatch'   => $batch,
            'program'          => $program,
            'catalogHierarchy' => $defaultCatalogHierarchy,
            'catalogs'         => array_values($distinctCatalogs),
            'catalogSequences' => $catalogSequences,
            'items'            => $items,
        ]);
    }

    /**
     * Map variant items to distinct catalog hierarchies using roll queues and size cycles.
     */
    private function resolveItemsCatalogHierarchy(array &$items, array $rolls, array $defaultHierarchyData, string $qtyField = 'cut_qty'): array {
        $rollColorQueues = [];
        $distinctCatalogs = [];

        foreach ($rolls as $r) {
            $hParts = array_filter([
                $r['category_name'] ?? '',
                $r['subcategory_name'] ?? '',
                $r['sub_subcategory_name'] ?? ''
            ]);
            $hStr = !empty($hParts) ? implode(' › ', $hParts) : 'General Apparel';
            $rColor = strtoupper(trim($r['roll_color'] ?? ''));
            if ($rColor) {
                $rollColorQueues[$rColor][] = [
                    'category_id'        => $r['category_id'] ?? null,
                    'subcategory_id'     => $r['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $r['sub_subcategory_id'] ?? null,
                    'catalog_hierarchy'  => $hStr,
                ];
            }
            if (!isset($distinctCatalogs[$hStr])) {
                $distinctCatalogs[$hStr] = [
                    'name'               => $hStr,
                    'category_id'        => $r['category_id'] ?? null,
                    'subcategory_id'     => $r['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $r['sub_subcategory_id'] ?? null,
                    'item_count'         => 0,
                    'total_qty'          => 0,
                ];
            }
        }

        $defaultCatalogHierarchy = $defaultHierarchyData['name'] ?? 'General Apparel';
        if (empty($distinctCatalogs)) {
            $distinctCatalogs[$defaultCatalogHierarchy] = [
                'name'               => $defaultCatalogHierarchy,
                'category_id'        => $defaultHierarchyData['category_id'] ?? null,
                'subcategory_id'     => $defaultHierarchyData['subcategory_id'] ?? null,
                'sub_subcategory_id' => $defaultHierarchyData['sub_subcategory_id'] ?? null,
                'item_count'         => 0,
                'total_qty'          => 0,
            ];
        }

        $colorTracker = [];

        foreach ($items as &$item) {
            $itemHierarchy = '';
            $catId = !empty($item['category_id']) ? (int)$item['category_id'] : null;
            $subcatId = !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null;
            $subSubcatId = !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null;

            $itemParts = array_filter([
                $item['category_name'] ?? '',
                $item['subcategory_name'] ?? '',
                $item['sub_subcategory_name'] ?? ''
            ]);
            if (!empty($itemParts)) {
                $itemHierarchy = implode(' › ', $itemParts);
            }

            if (empty($itemHierarchy) && !empty($item['catalog_hierarchy'])) {
                $itemHierarchy = $item['catalog_hierarchy'];
            }

            $cName = strtoupper(trim(!empty($item['color_name']) ? $item['color_name'] : ($item['display_color'] ?? '')));
            $sName = strtoupper(trim(!empty($item['size_name']) ? $item['size_name'] : ($item['display_size'] ?? '')));

            if (empty($itemHierarchy) && $cName && !empty($rollColorQueues[$cName])) {
                if (!isset($colorTracker[$cName])) {
                    $colorTracker[$cName] = ['index' => 0, 'seen_sizes' => []];
                }
                if (in_array($sName, $colorTracker[$cName]['seen_sizes'], true)) {
                    if (isset($rollColorQueues[$cName][$colorTracker[$cName]['index'] + 1])) {
                        $colorTracker[$cName]['index']++;
                        $colorTracker[$cName]['seen_sizes'] = [];
                    }
                }
                $colorTracker[$cName]['seen_sizes'][] = $sName;
                $matchedRoll = $rollColorQueues[$cName][$colorTracker[$cName]['index']];

                $itemHierarchy = $matchedRoll['catalog_hierarchy'];
                if (!$catId) $catId = $matchedRoll['category_id'];
                if (!$subcatId) $subcatId = $matchedRoll['subcategory_id'];
                if (!$subSubcatId) $subSubcatId = $matchedRoll['sub_subcategory_id'];
            }

            if (empty($itemHierarchy)) {
                $itemHierarchy = $defaultCatalogHierarchy;
                if (!$catId) $catId = $defaultHierarchyData['category_id'] ?? null;
                if (!$subcatId) $subcatId = $defaultHierarchyData['subcategory_id'] ?? null;
                if (!$subSubcatId) $subSubcatId = $defaultHierarchyData['sub_subcategory_id'] ?? null;
            }

            $item['catalog_hierarchy'] = $itemHierarchy;
            $item['category_id'] = $catId;
            $item['subcategory_id'] = $subcatId;
            $item['sub_subcategory_id'] = $subSubcatId;

            if (!isset($distinctCatalogs[$itemHierarchy])) {
                $distinctCatalogs[$itemHierarchy] = [
                    'name'               => $itemHierarchy,
                    'category_id'        => $catId,
                    'subcategory_id'     => $subcatId,
                    'sub_subcategory_id' => $subSubcatId,
                    'item_count'         => 0,
                    'total_qty'          => 0,
                ];
            }
            $distinctCatalogs[$itemHierarchy]['item_count']++;
            $distinctCatalogs[$itemHierarchy]['total_qty'] += (int)($item[$qtyField] ?? ($item['cut_qty'] ?? ($item['stitched_qty'] ?? 0)));
        }
        unset($item);

        return $distinctCatalogs;
    }

    public function ajaxCatalogVariants(): void {
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $subcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
        $subSubcategoryId = !empty($_GET['sub_subcategory_id']) ? (int)$_GET['sub_subcategory_id'] : null;
        $rollId = !empty($_GET['roll_id']) ? (int)$_GET['roll_id'] : null;
        $programId = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;

        // Resolve product matching hierarchy
        $product = null;
        if ($subSubcategoryId) {
            $product = Database::fetchOne("SELECT * FROM products WHERE sub_subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$subSubcategoryId]);
        }
        if (!$product && $subcategoryId) {
            $product = Database::fetchOne("SELECT * FROM products WHERE subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$subcategoryId]);
        }
        if (!$product && $categoryId) {
            $product = Database::fetchOne("SELECT * FROM products WHERE category_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$categoryId]);
        }

        $parsedSizes = [];
        $parsedColors = [];
        $sellerName = '';

        if ($rollId || $programId) {
            $ppr = null;
            if ($rollId) {
                $ppr = Database::fetchOne(
                    "SELECT ppr.*, 
                            COALESCE(NULLIF(sr.roll_number, ''), CONCAT('ROLL #', ppr.id)) as roll_number,
                            COALESCE(NULLIF(ppr.roll_color, ''), NULLIF(ppr.color, ''), sr.color) as sr_color,
                            COALESCE(NULLIF(ppr.roll_design, ''), NULLIF(ppr.design_name, ''), sr.design_name) as sr_design,
                            COALESCE(s.name, '') as seller_name, COALESCE(s.company, '') as seller_company
                     FROM production_program_rolls ppr
                     LEFT JOIN seller_rolls sr ON ppr.seller_roll_id = sr.id
                     LEFT JOIN sellers s ON (ppr.seller_id = s.id OR sr.seller_id = s.id)
                     WHERE (ppr.id = ? OR ppr.seller_roll_id = ?)" . ($programId ? " AND (ppr.production_program_id = " . (int)$programId . " OR ppr.program_id = " . (int)$programId . ")" : "") . "
                     LIMIT 1",
                    [$rollId, $rollId]
                );
            }
            if (!$ppr && $rollId) {
                $ppr = Database::fetchOne(
                    "SELECT sr.*, sr.roll_number, sr.color as sr_color, sr.design_name as sr_design,
                            s.name as seller_name, s.company as seller_company
                     FROM seller_rolls sr
                     LEFT JOIN sellers s ON sr.seller_id = s.id
                     WHERE sr.id = ? LIMIT 1",
                    [$rollId]
                );
            }
            if (!$ppr && $programId) {
                $ppr = Database::fetchOne(
                    "SELECT ppr.*, 
                            COALESCE(NULLIF(sr.roll_number, ''), CONCAT('ROLL #', ppr.id)) as roll_number,
                            COALESCE(NULLIF(ppr.roll_color, ''), NULLIF(ppr.color, ''), sr.color) as sr_color,
                            COALESCE(NULLIF(ppr.roll_design, ''), NULLIF(ppr.design_name, ''), sr.design_name) as sr_design,
                            COALESCE(s.name, '') as seller_name, COALESCE(s.company, '') as seller_company
                     FROM production_program_rolls ppr
                     LEFT JOIN seller_rolls sr ON ppr.seller_roll_id = sr.id
                     LEFT JOIN sellers s ON (ppr.seller_id = s.id OR sr.seller_id = s.id)
                     WHERE (ppr.production_program_id = ? OR ppr.program_id = ?)
                     LIMIT 1",
                    [$programId, $programId]
                );
            }
            if ($ppr) {
                if (!empty($ppr['attributes'])) {
                    $dec = is_string($ppr['attributes']) ? json_decode($ppr['attributes'], true) : $ppr['attributes'];
                    if (is_array($dec)) {
                        foreach ($dec as $k => $v) {
                            if (in_array(strtoupper($k), ['SIZE', 'SIZES'], true) && is_array($v) && !empty($v)) {
                                foreach ($v as $sz) {
                                    $sz = trim((string)$sz);
                                    if ($sz !== '' && !in_array($sz, $parsedSizes, true)) {
                                        $parsedSizes[] = $sz;
                                    }
                                }
                            }
                        }
                    }
                }
                $sellerName = trim($ppr['seller_company'] ?: ($ppr['seller_name'] ?? ''));
            }
        }

        if (empty($sellerName) && $programId) {
            $prgSeller = Database::fetchOne(
                "SELECT COALESCE(s.company, s.name, '') as seller_name
                 FROM production_programs pp
                 LEFT JOIN sellers s ON pp.seller_id = s.id
                 WHERE pp.id = ?",
                [$programId]
            );
            if ($prgSeller && !empty($prgSeller['seller_name'])) {
                $sellerName = $prgSeller['seller_name'];
            }
        }

        if ($product) {
            $pvRows = Database::fetchAll(
                "SELECT pv.*, s.name as size_name, s.code as size_code, c.name as color_name, c.hex_code as color_hex
                 FROM product_variants pv
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE pv.product_id = ? AND pv.is_active = 1",
                [$product['id']]
            );

            foreach ($pvRows as $pv) {
                $sName = $pv['size_name'] ?: ($pv['size_code'] ?: '');
                if (!empty($sName)) {
                    if (str_contains($sName, ',')) {
                        foreach (explode(',', $sName) as $sp) {
                            $sp = trim($sp);
                            if ($sp !== '' && !in_array($sp, $parsedSizes, true)) $parsedSizes[] = $sp;
                        }
                    } else {
                        if (!in_array($sName, $parsedSizes, true)) $parsedSizes[] = $sName;
                    }
                }

                $cName = $pv['color_name'] ?: '';
                if (!empty($cName)) {
                    if (str_contains($cName, ',')) {
                        foreach (explode(',', $cName) as $cp) {
                            $cp = trim($cp);
                            if ($cp !== '' && !in_array($cp, $parsedColors, true)) $parsedColors[] = $cp;
                        }
                    } else {
                        if (!in_array($cName, $parsedColors, true)) $parsedColors[] = $cName;
                    }
                }
            }
        }

        // Fallback to active sizes in DB if empty
        if (empty($parsedSizes)) {
            $rawSizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            foreach ($rawSizes as $s) {
                $rawStr = !empty($s['code']) ? $s['code'] : ($s['name'] ?? '');
                if (str_contains($rawStr, ',')) {
                    foreach (explode(',', $rawStr) as $sp) {
                        $sp = trim($sp);
                        if ($sp !== '' && !in_array($sp, $parsedSizes, true)) $parsedSizes[] = $sp;
                    }
                } else {
                    if ($rawStr !== '' && !in_array($rawStr, $parsedSizes, true)) $parsedSizes[] = $rawStr;
                }
            }
        }
        if (empty($parsedSizes)) {
            $parsedSizes = ['M', 'L', 'XL', 'XXL', '3XL'];
        }

        // Prioritize roll_color if provided from selected fabric roll
        $rollColor = trim($_GET['roll_color'] ?? '');
        if (!empty($rollColor) && $rollColor !== '-' && $rollColor !== 'Standard') {
            $parsedColors = [$rollColor];
        } elseif (empty($parsedColors)) {
            $rawColors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");
            foreach ($rawColors as $c) {
                if (str_contains($c['name'], ',')) {
                    foreach (explode(',', $c['name']) as $cp) {
                        $cp = trim($cp);
                        if ($cp !== '' && !in_array($cp, $parsedColors, true)) $parsedColors[] = $cp;
                    }
                } else {
                    if (!in_array($c['name'], $parsedColors, true)) $parsedColors[] = $c['name'];
                }
            }
        }
        if (empty($parsedColors)) {
            $parsedColors = ['STANDARD'];
        }

        $prodCode = $product ? ($product['code'] ?: 'CUT') : 'CUT';
        $variants = [];
        foreach ($parsedSizes as $sz) {
            foreach ($parsedColors as $col) {
                $variants[] = [
                    'id'              => null,
                    'sku'             => $prodCode . '-' . strtoupper(substr($sz, 0, 3)) . '-' . strtoupper(substr($col, 0, 3)),
                    'size_name'       => $sz,
                    'color_name'      => $col,
                    'planned_quantity'=> 0,
                ];
            }
        }

        Response::json([
            'success'     => true,
            'product'     => $product,
            'sizes'       => $parsedSizes,
            'colors'      => $parsedColors,
            'variants'    => $variants,
            'seller_name' => $sellerName,
        ]);
    }

    public function createCutting(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $programId = $_GET['program_id'] ?? null;
        
        $progUnitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        $programs = Database::fetchAll(
            "SELECT pp.*, 
                    COALESCE(pp.name, p.name) as product_name, 
                    pu.name as unit_name,
                    sr.roll_number, sr.color as roll_color, sr.design_name as roll_design,
                    c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name,
                    COALESCE(s.name, s_roll.name, (SELECT s2.name FROM production_program_rolls ppr2 JOIN seller_rolls sr2 ON ppr2.seller_roll_id = sr2.id JOIN sellers s2 ON (ppr2.seller_id = s2.id OR sr2.seller_id = s2.id) WHERE ppr2.production_program_id = pp.id LIMIT 1)) as seller_name,
                    COALESCE(s.company, s_roll.company, (SELECT s2.company FROM production_program_rolls ppr2 JOIN seller_rolls sr2 ON ppr2.seller_roll_id = sr2.id JOIN sellers s2 ON (ppr2.seller_id = s2.id OR sr2.seller_id = s2.id) WHERE ppr2.production_program_id = pp.id LIMIT 1)) as seller_company
             FROM production_programs pp
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
             LEFT JOIN seller_rolls sr ON pp.seller_roll_id = sr.id
             LEFT JOIN sellers s ON pp.seller_id = s.id
             LEFT JOIN sellers s_roll ON sr.seller_id = s_roll.id
             LEFT JOIN categories c ON pp.category_id = c.id
             LEFT JOIN subcategories sc ON pp.subcategory_id = sc.id
             LEFT JOIN sub_subcategories ssc ON pp.sub_subcategory_id = ssc.id
             WHERE (pp.is_active IS NULL OR pp.is_active = 1)$progUnitClause
               AND pp.id NOT IN (SELECT program_id FROM cutting_batches WHERE program_id IS NOT NULL)
             ORDER BY pp.created_at DESC"
        );

        $selectedProgram = null;
        $selectedProgramRolls = [];
        if ($programId) {
            $alreadyCut = Database::fetchOne("SELECT id, batch_number FROM cutting_batches WHERE program_id = ?", [(int)$programId]);
            if ($alreadyCut) {
                Session::flash('error', "Production Program has already been cut in Cutting Batch {$alreadyCut['batch_number']}. Duplicate cutting is not allowed.");
                Response::redirect(url('production/cutting'));
                return;
            }
            $selectedProgram = ProductionProgram::find((int)$programId);
            if ($selectedProgram) {
                $selectedProgramRolls = ProductionProgram::getRolls((int)$programId);
            }
        }

        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $units = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : Database::fetchAll("SELECT * FROM production_units WHERE is_active = 1 ORDER BY name ASC");
        $rolls = Database::fetchAll(
            "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
             FROM seller_rolls sr
             JOIN sellers s ON sr.seller_id = s.id
             WHERE s.is_active = 1
             ORDER BY sr.id DESC"
        );

        $count = Database::fetchOne("SELECT COUNT(*) as c FROM cutting_batches")['c'] ?? 0;
        $suggestedBatchNumber = 'CUT-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        Response::view('production.cutting.create', [
            'pageTitle'            => 'New Cutting Batch',
            'activeModule'         => 'production',
            'activeMenu'           => 'cutting',
            'programs'             => $programs,
            'selectedProgram'      => $selectedProgram,
            'selectedProgramRolls' => $selectedProgramRolls,
            'categories'           => $categories,
            'users'                => $users,
            'units'                => $units,
            'rolls'                => $rolls,
            'scopedUnitId'         => $scopedUnitId,
            'suggestedBatchNumber' => $suggestedBatchNumber,
            'breadcrumb'           => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Cutting', 'url' => url('production/cutting')],
                ['label' => 'New Cutting Batch'],
            ],
        ]);
    }

    public function storeCutting(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $programId = (int)($_POST['program_id'] ?? 0);
        $batchNumber = mb_strtoupper(trim($_POST['batch_number'] ?? ''));
        $sellerRollId = !empty($_POST['seller_roll_id']) ? (int)$_POST['seller_roll_id'] : null;
        $rollWeight = max(0.0, (float)($_POST['roll_weight'] ?? 0));
        $rollPrice = max(0.0, (float)($_POST['roll_price'] ?? 0));
        $wastageWeight = max(0.0, (float)($_POST['wastage_weight'] ?? 0));
        $operatorId = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : null;
        $unitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null;
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $status = $_POST['status'] ?? 'IN_PROGRESS';
        if (empty($name)) {
            Session::flash('error', 'Cutting Name is required. Please provide a valid batch name.');
            Response::back();
            return;
        }

        if (empty($programId)) {
            Session::flash('error', 'Please select a Production Program.');
            Response::back();
            return;
        }

        // Prevent duplicate cutting of the same program
        $existingCutting = Database::fetchOne(
            "SELECT id, batch_number, name FROM cutting_batches WHERE program_id = ?",
            [$programId]
        );
        if ($existingCutting) {
            $progInfo = ProductionProgram::find($programId);
            $progNum = $progInfo['program_number'] ?? ('#' . $programId);
            Session::flash('error', "Validation Error: Production Program ({$progNum}) has already been assigned to Cutting Batch {$existingCutting['batch_number']}. Duplicate cutting of the same program is not allowed.");
            Response::back();
            return;
        }

        // Process multiple rolls from $_POST['rolls']
        $submittedRolls = !empty($_POST['rolls']) && is_array($_POST['rolls']) ? $_POST['rolls'] : [];
        $validRolls = [];
        $calcTotalRollWeight = 0.0;
        $calcTotalRollPrice = 0.0;

        foreach ($submittedRolls as $r) {
            $rId = !empty($r['seller_roll_id']) ? (int)$r['seller_roll_id'] : 0;
            if ($rId <= 0) continue;

            $rWeight = max(0.0, (float)($r['roll_weight'] ?? 0));
            $rPrice = max(0.0, (float)($r['roll_price'] ?? 0));
            $rWastageWeight = max(0.0, (float)($r['wastage_weight'] ?? 0));
            $rRate = $rWeight > 0 ? ($rPrice / $rWeight) : 0;
            $rWastageCost = max(0.0, (float)($r['wastage_cost'] ?? round($rWastageWeight * $rRate, 2)));

            $rCategoryId = !empty($r['category_id']) ? (int)$r['category_id'] : (!empty($_POST['category_id']) ? (int)$_POST['category_id'] : null);
            $rSubcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : (!empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null);
            $rSubSubcatId = !empty($r['sub_subcategory_id']) ? (int)$r['sub_subcategory_id'] : (!empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null);

            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name FROM seller_rolls WHERE id = ?", [$rId]);

            if ($sellerData) {
                if ($rWeight <= 0) $rWeight = (float)$sellerData['weight'];
                if ($rPrice <= 0) $rPrice = (float)$sellerData['price'];
                $validRolls[] = [
                    'seller_roll_id'     => $rId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'wastage_weight'     => $rWastageWeight,
                    'wastage_cost'       => $rWastageCost,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'category_id'        => $rCategoryId,
                    'subcategory_id'     => $rSubcatId,
                    'sub_subcategory_id' => $rSubSubcatId,
                ];
                $calcTotalRollWeight += $rWeight;
                $calcTotalRollPrice += $rPrice;
            }
        }

        // Fallback to single roll if rolls array wasn't passed
        if (empty($validRolls) && !empty($_POST['seller_roll_id'])) {
            $singleRollId = (int)$_POST['seller_roll_id'];
            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name FROM seller_rolls WHERE id = ?", [$singleRollId]);
            if ($sellerData) {
                $rWeight = max(0.0, (float)($_POST['roll_weight'] ?? $sellerData['weight']));
                $rPrice = max(0.0, (float)($_POST['roll_price'] ?? $sellerData['price']));
                $rWastageWeight = max(0.0, (float)($_POST['wastage_weight'] ?? 0));
                $rRate = $rWeight > 0 ? ($rPrice / $rWeight) : 0;
                $rWastageCost = max(0.0, (float)($_POST['wastage_cost'] ?? round($rWastageWeight * $rRate, 2)));

                $validRolls[] = [
                    'seller_roll_id'     => $singleRollId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'wastage_weight'     => $rWastageWeight,
                    'wastage_cost'       => $rWastageCost,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'category_id'        => $categoryId,
                    'subcategory_id'     => $subcategoryId,
                    'sub_subcategory_id' => $subSubcategoryId,
                ];
                $calcTotalRollWeight = $rWeight;
                $calcTotalRollPrice = $rPrice;
            }
        }

        if ($calcTotalRollWeight > 0) $rollWeight = $calcTotalRollWeight;
        if ($calcTotalRollPrice > 0) $rollPrice = $calcTotalRollPrice;
        if (!empty($validRolls)) {
            $sellerRollId = $validRolls[0]['seller_roll_id'];
            if (!$categoryId) $categoryId = $validRolls[0]['category_id'] ?? null;
            if (!$subcategoryId) $subcategoryId = $validRolls[0]['subcategory_id'] ?? null;
            if (!$subSubcategoryId) $subSubcategoryId = $validRolls[0]['sub_subcategory_id'] ?? null;

            $sumRollWastageWeight = array_sum(array_column($validRolls, 'wastage_weight'));
            $sumRollWastageCost = array_sum(array_column($validRolls, 'wastage_cost'));
            if ($sumRollWastageWeight > 0) {
                $wastageWeight = $sumRollWastageWeight;
            }
            if ($sumRollWastageCost > 0) {
                $wastageCost = $sumRollWastageCost;
            }
        }

        $usedWeight = max(0.0, $rollWeight - $wastageWeight);

        if (empty($name)) {
            $program = ProductionProgram::find($programId);
            $name = 'CUTTING - ' . ($program['name'] ?: $program['program_number']);
        }

        if (empty($batchNumber)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM cutting_batches")['c'] ?? 0;
            $batchNumber = 'CUT-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        }

        $items = [];
        $totalCut = 0;
        $totalPlanned = 0;
        $totalRejected = 0;
        $totalWastage = 0;

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $cutQty = (int)($vals['cut_qty'] ?? 0);
                $plannedQty = (int)($vals['planned_qty'] ?? $cutQty);
                $rejQty = (int)($vals['rejected_qty'] ?? 0);
                $wQty = (int)($vals['wastage_qty'] ?? 0);
                $sizeName = trim($vals['size_name'] ?? '');
                $colorName = trim($vals['color_name'] ?? '');
                $variantId = !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null;

                $itemCatId = !empty($vals['category_id']) ? (int)$vals['category_id'] : $categoryId;
                $itemSubcatId = !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : $subcategoryId;
                $itemSubSubcatId = !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : $subSubcategoryId;
                $itemCatalogHierarchy = !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null;

                if (!empty($sizeName) || !empty($colorName) || $cutQty > 0 || $plannedQty > 0 || $rejQty > 0 || $wQty > 0) {
                    $items[] = [
                        'variant_id'         => $variantId,
                        'category_id'        => $itemCatId,
                        'subcategory_id'     => $itemSubcatId,
                        'sub_subcategory_id' => $itemSubSubcatId,
                        'catalog_hierarchy'  => $itemCatalogHierarchy,
                        'size_name'          => $sizeName,
                        'color_name'         => $colorName,
                        'planned_qty'        => $plannedQty,
                        'cut_qty'            => $cutQty,
                        'rejected_qty'       => $rejQty,
                        'wastage_qty'        => $wQty,
                    ];
                    $totalCut += $cutQty;
                    $totalPlanned += $plannedQty;
                    $totalRejected += $rejQty;
                    $totalWastage += $wQty;
                }
            }
        }

        $ratePerKg = $rollWeight > 0 ? ($rollPrice / $rollWeight) : 0;
        if (empty($wastageCost) || $wastageCost <= 0) {
            $wastageCost = max(0.0, (float)($_POST['wastage_cost'] ?? round($wastageWeight * $ratePerKg, 2)));
        }
        $costPerPiece = ($rollPrice > 0 && $totalCut > 0) ? round($rollPrice / $totalCut, 2) : 0.00;

        try {
            $userId = Session::userId();
            $batchId = ProductionService::createCuttingBatch([
                'name'               => $name,
                'program_id'         => $programId,
                'batch_number'       => $batchNumber,
                'seller_roll_id'     => $sellerRollId,
                'roll_weight'        => $rollWeight,
                'roll_price'         => $rollPrice,
                'wastage_weight'     => $wastageWeight,
                'wastage_cost'       => $wastageCost,
                'used_weight'        => $usedWeight,
                'cost_per_piece'     => $costPerPiece,
                'category_id'        => $categoryId,
                'subcategory_id'     => $subcategoryId,
                'sub_subcategory_id' => $subSubcategoryId,
                'operator_id'        => $operatorId,
                'production_unit_id' => $unitId,
                'material_issued'    => "Roll Weight: {$rollWeight} kg | Wastage: {$wastageWeight} kg (₹{$wastageCost}) | Used: {$usedWeight} kg",
                'planned_qty'        => $totalPlanned,
                'cut_qty'            => $totalCut,
                'rejected_qty'       => $totalRejected,
                'wastage_qty'        => $totalWastage,
                'status'             => $status,
                'notes'              => $_POST['notes'] ?? '',
            ], $items, $userId);

            // Sync rolls in cutting_batch_rolls
            CuttingBatch::syncRolls($batchId, $validRolls);

            Session::flash('success', "Cutting Batch '{$name}' created successfully with {$totalCut} pcs.");
            Response::redirect(url('production/cutting'));
        } catch (Exception $e) {
            Session::flash('error', 'Error creating cutting batch: ' . $e->getMessage());
            Response::back();
        }
    }

    public function editCutting(string $id): void {
        $batch = CuttingBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'Cutting batch not found.');
        }

        $batchRolls = CuttingBatch::getRolls((int)$id);
        $rolls = Database::fetchAll(
            "SELECT sr.*, s.name as seller_name, s.company as seller_company, s.material_type
             FROM seller_rolls sr
             JOIN sellers s ON sr.seller_id = s.id
             WHERE s.is_active = 1
             ORDER BY sr.id DESC"
        );

        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $items = CuttingBatch::getItems((int)$id);
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $units = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : Database::fetchAll("SELECT * FROM production_units WHERE is_active = 1 ORDER BY name ASC");

        Response::view('production.cutting.edit', [
            'pageTitle'    => 'Edit Cutting Batch — ' . ($batch['name'] ?: $batch['batch_number']),
            'activeModule' => 'production',
            'activeMenu'   => 'cutting',
            'batch'        => $batch,
            'batchRolls'   => $batchRolls,
            'rolls'        => $rolls,
            'categories'   => $categories,
            'items'        => $items,
            'users'        => $users,
            'units'        => $units,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Cutting', 'url' => url('production/cutting')],
                ['label' => $batch['batch_number']],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function updateCutting(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $rollWeight = max(0.0, (float)($_POST['roll_weight'] ?? 0));
        $rollPrice = max(0.0, (float)($_POST['roll_price'] ?? 0));
        $wastageWeight = max(0.0, (float)($_POST['wastage_weight'] ?? 0));
        $operatorId = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : null;
        $unitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null;
        $status = $_POST['status'] ?? 'IN_PROGRESS';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Cutting Name is required. Please provide a valid batch name.');
            Response::back();
            return;
        }

        // Process multiple rolls from $_POST['rolls']
        $submittedRolls = !empty($_POST['rolls']) && is_array($_POST['rolls']) ? $_POST['rolls'] : [];
        $validRolls = [];
        $calcTotalRollWeight = 0.0;
        $calcTotalRollPrice = 0.0;
        $sellerRollId = null;

        foreach ($submittedRolls as $r) {
            $rId = !empty($r['seller_roll_id']) ? (int)$r['seller_roll_id'] : 0;
            if ($rId <= 0) continue;

            $rWeight = max(0.0, (float)($r['roll_weight'] ?? 0));
            $rPrice = max(0.0, (float)($r['roll_price'] ?? 0));
            $rWastageWeight = max(0.0, (float)($r['wastage_weight'] ?? 0));
            $rRate = $rWeight > 0 ? ($rPrice / $rWeight) : 0;
            $rWastageCost = max(0.0, (float)($r['wastage_cost'] ?? round($rWastageWeight * $rRate, 2)));

            $rCategoryId = !empty($r['category_id']) ? (int)$r['category_id'] : (!empty($_POST['category_id']) ? (int)$_POST['category_id'] : null);
            $rSubcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : (!empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null);
            $rSubSubcatId = !empty($r['sub_subcategory_id']) ? (int)$r['sub_subcategory_id'] : (!empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null);

            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name FROM seller_rolls WHERE id = ?", [$rId]);

            if ($sellerData) {
                if ($rWeight <= 0) $rWeight = (float)$sellerData['weight'];
                if ($rPrice <= 0) $rPrice = (float)$sellerData['price'];
                $validRolls[] = [
                    'seller_roll_id'     => $rId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'wastage_weight'     => $rWastageWeight,
                    'wastage_cost'       => $rWastageCost,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'category_id'        => $rCategoryId,
                    'subcategory_id'     => $rSubcatId,
                    'sub_subcategory_id' => $rSubSubcatId,
                ];
                $calcTotalRollWeight += $rWeight;
                $calcTotalRollPrice += $rPrice;
            }
        }

        if (empty($validRolls) && !empty($_POST['seller_roll_id'])) {
            $singleRollId = (int)$_POST['seller_roll_id'];
            $sellerData = Database::fetchOne("SELECT seller_id, weight, price, color, design_name FROM seller_rolls WHERE id = ?", [$singleRollId]);
            if ($sellerData) {
                $rWeight = max(0.0, (float)($_POST['roll_weight'] ?? $sellerData['weight']));
                $rPrice = max(0.0, (float)($_POST['roll_price'] ?? $sellerData['price']));
                $rWastageWeight = max(0.0, (float)($_POST['wastage_weight'] ?? 0));
                $rRate = $rWeight > 0 ? ($rPrice / $rWeight) : 0;
                $rWastageCost = max(0.0, (float)($r['wastage_cost'] ?? round($rWastageWeight * $rRate, 2)));

                $validRolls[] = [
                    'seller_roll_id'     => $singleRollId,
                    'seller_id'          => (int)$sellerData['seller_id'],
                    'roll_weight'        => $rWeight,
                    'roll_price'         => $rPrice,
                    'wastage_weight'     => $rWastageWeight,
                    'wastage_cost'       => $rWastageCost,
                    'roll_color'         => $sellerData['color'] ?? '',
                    'roll_design'        => $sellerData['design_name'] ?? '',
                    'category_id'        => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                    'subcategory_id'     => !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null,
                ];
                $calcTotalRollWeight = $rWeight;
                $calcTotalRollPrice = $rPrice;
            }
        }

        if ($calcTotalRollWeight > 0) $rollWeight = $calcTotalRollWeight;
        if ($calcTotalRollPrice > 0) $rollPrice = $calcTotalRollPrice;
        if (!empty($validRolls)) {
            $sellerRollId = $validRolls[0]['seller_roll_id'];

            $sumRollWastageWeight = array_sum(array_column($validRolls, 'wastage_weight'));
            $sumRollWastageCost = array_sum(array_column($validRolls, 'wastage_cost'));
            if ($sumRollWastageWeight > 0) {
                $wastageWeight = $sumRollWastageWeight;
            }
            if ($sumRollWastageCost > 0) {
                $wastageCost = $sumRollWastageCost;
            }
        }

        $usedWeight = max(0.0, $rollWeight - $wastageWeight);

        $items = [];
        $totalCut = 0;
        $totalPlanned = 0;
        $totalRejected = 0;
        $totalWastage = 0;

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $cutQty = (int)($vals['cut_qty'] ?? 0);
                $plannedQty = (int)($vals['planned_qty'] ?? $cutQty);
                $rejQty = (int)($vals['rejected_qty'] ?? 0);
                $wQty = (int)($vals['wastage_qty'] ?? 0);
                $sizeName = trim($vals['size_name'] ?? '');
                $colorName = trim($vals['color_name'] ?? '');
                $variantId = !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null;

                $itemCatId = !empty($vals['category_id']) ? (int)$vals['category_id'] : null;
                $itemSubcatId = !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : null;
                $itemSubSubcatId = !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : null;
                $itemCatalogHierarchy = !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null;

                if (!empty($sizeName) || !empty($colorName) || $cutQty > 0 || $plannedQty > 0 || $rejQty > 0 || $wQty > 0) {
                    $items[] = [
                        'variant_id'         => $variantId,
                        'category_id'        => $itemCatId,
                        'subcategory_id'     => $itemSubcatId,
                        'sub_subcategory_id' => $itemSubSubcatId,
                        'catalog_hierarchy'  => $itemCatalogHierarchy,
                        'size_name'          => $sizeName,
                        'color_name'         => $colorName,
                        'planned_qty'        => $plannedQty,
                        'cut_qty'            => $cutQty,
                        'rejected_qty'       => $rejQty,
                        'wastage_qty'        => $wQty,
                    ];
                    $totalCut += $cutQty;
                    $totalPlanned += $plannedQty;
                    $totalRejected += $rejQty;
                    $totalWastage += $wQty;
                }
            }
        }

        $ratePerKg = $rollWeight > 0 ? ($rollPrice / $rollWeight) : 0;
        $wastageCost = max(0.0, (float)($_POST['wastage_cost'] ?? round($wastageWeight * $ratePerKg, 2)));
        $costPerPiece = ($rollPrice > 0 && $totalCut > 0) ? round($rollPrice / $totalCut, 2) : 0.00;

        try {
            $updateData = [
                'name'               => $name,
                'roll_weight'        => $rollWeight,
                'roll_price'         => $rollPrice,
                'wastage_weight'     => $wastageWeight,
                'wastage_cost'       => $wastageCost,
                'used_weight'        => $usedWeight,
                'cost_per_piece'     => $costPerPiece,
                'operator_id'        => $operatorId,
                'production_unit_id' => $unitId,
                'planned_qty'        => $totalPlanned,
                'cut_qty'            => $totalCut,
                'rejected_qty'       => $totalRejected,
                'wastage_qty'        => $totalWastage,
                'status'             => $status,
                'notes'              => $notes,
            ];
            if ($sellerRollId) {
                $updateData['seller_roll_id'] = $sellerRollId;
            }

            ProductionService::updateCuttingBatch((int)$id, $updateData, $items);

            // Sync rolls in cutting_batch_rolls
            CuttingBatch::syncRolls((int)$id, $validRolls);

            Session::flash('success', 'Cutting batch updated successfully.');
            Response::redirect(url('production/cutting'));
        } catch (Exception $e) {
            Session::flash('error', 'Error updating cutting batch: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteCutting(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $batch = CuttingBatch::find((int)$id);
        if (!$batch) {
            Session::flash('error', 'Cutting batch not found.');
            Response::redirect(url('production/cutting'));
            return;
        }

        CuttingBatch::delete((int)$id);
        AuditService::log('production', 'delete', 'cutting_batches', (int)$id, null, ['status' => 'CANCELLED', 'is_active' => 0]);

        $name = $batch['name'] ?: $batch['batch_number'];
        Session::flash('success', "Cutting Batch '{$name}' deleted successfully.");
        Response::redirect(url('production/cutting'));
    }

    // =========================================================================
    // STITCHING
    // =========================================================================

    public function stitchingList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $filters = [
            'status'             => $_GET['status'] ?? '',
            'program_id'         => $_GET['program_id'] ?? '',
            'production_unit_id' => $scopedUnitId ? (string)$scopedUnitId : ($_GET['production_unit_id'] ?? ''),
            'search'             => $_GET['search'] ?? '',
        ];

        $batches = StitchingBatch::getAll($filters);

        Response::view('production.stitching.index', [
            'pageTitle'    => 'Stitching Batches',
            'activeModule' => 'production',
            'activeMenu'   => 'stitching',
            'batches'      => $batches,
            'filters'      => $filters,
            'scopedUnitId' => $scopedUnitId,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Stitching'],
            ],
        ]);
    }

    public function createStitching(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $programId = $_GET['program_id'] ?? null;
        $cuttingBatchId = $_GET['cutting_batch_id'] ?? null;

        $progUnitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        $programs = Database::fetchAll(
            "SELECT pp.*, p.name as product_name, p.code as product_code, pu.name as unit_name
             FROM production_programs pp
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
             WHERE pp.is_active = 1$progUnitClause
               AND pp.id NOT IN (SELECT program_id FROM stitching_batches WHERE program_id IS NOT NULL AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED')
             ORDER BY pp.id DESC"
        );

        $cuttingBatches = Database::fetchAll(
            "SELECT cb.*, pp.program_number, pp.name as program_name, p.name as product_name
             FROM cutting_batches cb
             LEFT JOIN production_programs pp ON cb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE (cb.is_active = 1 OR cb.is_active IS NULL)$progUnitClause
               AND cb.id NOT IN (SELECT cutting_batch_id FROM stitching_batches WHERE cutting_batch_id IS NOT NULL AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED')
             ORDER BY cb.id DESC"
        );

        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");

        $selectedProgram = null;
        $selectedCutting = null;
        $items = [];

        if ($cuttingBatchId) {
            $alreadyStitched = Database::fetchOne(
                "SELECT id, batch_number FROM stitching_batches WHERE cutting_batch_id = ? AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                [(int)$cuttingBatchId]
            );
            if ($alreadyStitched) {
                Session::flash('error', "Cutting Batch has already been assigned to Stitching Batch {$alreadyStitched['batch_number']}. Duplicate stitching is not allowed.");
                Response::redirect(url('production/stitching'));
                return;
            }
            $selectedCutting = CuttingBatch::find((int)$cuttingBatchId);
            if ($selectedCutting) {
                $programId = $selectedCutting['program_id'];
                $items = CuttingBatch::getItems((int)$cuttingBatchId);
            }
        }

        if ($programId && !$cuttingBatchId) {
            $alreadyStitchedProg = Database::fetchOne(
                "SELECT id, batch_number FROM stitching_batches WHERE program_id = ? AND cutting_batch_id IS NULL AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                [(int)$programId]
            );
            if ($alreadyStitchedProg) {
                Session::flash('error', "Production Program has already been assigned to Stitching Batch {$alreadyStitchedProg['batch_number']}. Duplicate stitching is not allowed.");
                Response::redirect(url('production/stitching'));
                return;
            }
            $selectedProgram = ProductionProgram::find((int)$programId);
        } elseif ($programId) {
            $selectedProgram = ProductionProgram::find((int)$programId);
        }

        $count = Database::fetchOne("SELECT COUNT(*) as c FROM stitching_batches")['c'] ?? 0;
        $suggestedBatchNumber = 'STCH-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        Response::view('production.stitching.create', [
            'pageTitle'            => 'New Stitching Batch',
            'activeModule'         => 'production',
            'activeMenu'           => 'stitching',
            'programs'             => $programs,
            'cuttingBatches'       => $cuttingBatches,
            'selectedProgram'      => $selectedProgram,
            'selectedCutting'      => $selectedCutting,
            'categories'           => $categories,
            'suggestedBatchNumber' => $suggestedBatchNumber,
            'items'                => $items,
            'users'                => $users,
            'breadcrumb'           => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Stitching', 'url' => url('production/stitching')],
                ['label' => 'New Stitching Batch'],
            ],
        ]);
    }

    public function storeStitching(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $cuttingBatchId = !empty($_POST['cutting_batch_id']) ? (int)$_POST['cutting_batch_id'] : null;

        if (!$programId && $cuttingBatchId) {
            $cb = CuttingBatch::find($cuttingBatchId);
            if ($cb) {
                $programId = (int)$cb['program_id'];
            }
        }

        if (!$programId) {
            Session::flash('error', 'Please select a Production Program.');
            Response::back();
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $batchNumber = trim($_POST['batch_number'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $workerId = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] ?? 'IN_PROGRESS';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Stitching Name is required. Please enter a valid batch name.');
            Response::back();
            return;
        }

        if (empty($cuttingBatchId)) {
            Session::flash('error', 'Select Cutting Name is required. Please choose a source Cutting Batch.');
            Response::back();
            return;
        }

        // Prevent duplicate stitching of the same cutting batch
        if (!empty($cuttingBatchId)) {
            $existingStitching = Database::fetchOne(
                "SELECT id, batch_number, name FROM stitching_batches WHERE cutting_batch_id = ? AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                [$cuttingBatchId]
            );
            if ($existingStitching) {
                $cb = CuttingBatch::find($cuttingBatchId);
                $cbNum = $cb['batch_number'] ?? ('#' . $cuttingBatchId);
                Session::flash('error', "Validation Error: Cutting Batch ({$cbNum}) has already been assigned to Stitching Batch {$existingStitching['batch_number']}. Duplicate stitching of the same cutting batch is not allowed.");
                Response::back();
                return;
            }
        }

        if (empty($cuttingBatchId) && !empty($programId)) {
            $existingStitchingProg = Database::fetchOne(
                "SELECT id, batch_number, name FROM stitching_batches WHERE program_id = ? AND cutting_batch_id IS NULL AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                [$programId]
            );
            if ($existingStitchingProg) {
                $prog = ProductionProgram::find($programId);
                $progNum = $prog['program_number'] ?? ('#' . $programId);
                Session::flash('error', "Validation Error: Production Program ({$progNum}) has already been assigned to Stitching Batch {$existingStitchingProg['batch_number']}. Duplicate stitching is not allowed.");
                Response::back();
                return;
            }
        }

        if (empty($batchNumber)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM stitching_batches")['c'] ?? 0;
            $batchNumber = 'STCH-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        }

        $items = [];
        $totalStitched = 0;
        $totalWeight = 0.0;

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $stitchedQty = (int)($vals['stitched_qty'] ?? ($vals['qty'] ?? 0));
                $receivedQty = (int)($vals['received_qty'] ?? $stitchedQty);
                $weight = max(0.0, (float)($vals['weight'] ?? 0));
                $unitWeight = isset($vals['weight_per_piece']) ? max(0.0, (float)$vals['weight_per_piece']) : ($stitchedQty > 0 ? round($weight / $stitchedQty, 3) : 0.000);
                if ($weight <= 0 && $stitchedQty > 0 && $unitWeight > 0) {
                    $weight = round($stitchedQty * $unitWeight, 2);
                }
                $rejQty = (int)($vals['rejected_qty'] ?? 0);
                $rewQty = (int)($vals['rework_qty'] ?? 0);
                $basePrice = max(0.0, (float)($vals['base_price'] ?? 0));
                $discountPrice = max(0.0, (float)($vals['discount_price'] ?? 0));
                $sizeName = trim($vals['size_name'] ?? '');
                $colorName = trim($vals['color_name'] ?? '');
                $variantId = !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null;

                $itemCatId = !empty($vals['category_id']) ? (int)$vals['category_id'] : $categoryId;
                $itemSubcatId = !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : $subcategoryId;
                $itemSubSubcatId = !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : $subSubcategoryId;
                $itemCatalogHierarchy = !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null;

                if (!empty($sizeName) || !empty($colorName) || $stitchedQty > 0 || $weight > 0 || $receivedQty > 0) {
                    $items[] = [
                        'variant_id'         => $variantId,
                        'category_id'        => $itemCatId,
                        'subcategory_id'     => $itemSubcatId,
                        'sub_subcategory_id' => $itemSubSubcatId,
                        'catalog_hierarchy'  => $itemCatalogHierarchy,
                        'size_name'          => $sizeName,
                        'color_name'         => $colorName,
                        'received_qty'       => $receivedQty,
                        'stitched_qty'       => $stitchedQty,
                        'weight_per_piece'   => $unitWeight,
                        'weight'             => $weight,
                        'base_price'         => $basePrice,
                        'discount_price'     => $discountPrice,
                        'rejected_qty'       => $rejQty,
                        'rework_qty'         => $rewQty,
                    ];
                    $totalStitched += $stitchedQty;
                    $totalWeight += $weight;
                }
            }
        }

        if (empty($items)) {
            Session::flash('error', 'Please fill all required variant items.');
            Response::back();
            return;
        }

        foreach ($items as $idx => $item) {
            $rowNum = $idx + 1;
            $vName = trim(($item['size_name'] ?: '') . ' / ' . ($item['color_name'] ?: ''));
            if ($item['stitched_qty'] <= 0 || $item['weight_per_piece'] <= 0 || $item['weight'] <= 0 || $item['base_price'] <= 0 || $item['discount_price'] <= 0) {
                Session::flash('error', "Row #{$rowNum} ({$vName}) has missing or 0 values. Qty, Weight, Base Price, and Discount Price must all be greater than 0.");
                Response::back();
                return;
            }
            if ($item['discount_price'] >= $item['base_price']) {
                Session::flash('error', "Row #{$rowNum} ({$vName}): Discount Price must be less than Base Price.");
                Response::back();
                return;
            }
        }

        // Validate Stitching Weight against Source Cutting Batch Usable Weight
        if ($cuttingBatchId) {
            $cb = CuttingBatch::find($cuttingBatchId);
            if ($cb) {
                $cuttingUsedWeight = (float)($cb['used_weight'] > 0 ? $cb['used_weight'] : max(0.0, (float)($cb['roll_weight'] ?? 0) - (float)($cb['wastage_weight'] ?? 0)));
                if ($cuttingUsedWeight > 0 && $totalWeight > round($cuttingUsedWeight + 0.01, 2)) {
                    Session::flash('error', "Validation Error: Total Stitching Weight (" . number_format($totalWeight, 2) . " kg) exceeds Cutting Batch Usable Weight (" . number_format($cuttingUsedWeight, 2) . " kg). The stitching weight must match the cutting weight.");
                    Response::back();
                    return;
                }
            }
        }

        try {
            $userId = Session::userId();
            $batchId = ProductionService::createStitchingBatch([
                'name'               => $name,
                'batch_number'       => $batchNumber,
                'program_id'         => $programId,
                'cutting_batch_id'   => $cuttingBatchId,
                'category_id'        => $categoryId,
                'subcategory_id'     => $subcategoryId,
                'sub_subcategory_id' => $subSubcategoryId,
                'worker_id'          => $workerId,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'received_qty'       => $totalStitched,
                'stitched_qty'       => $totalStitched,
                'total_weight'       => $totalWeight,
                'status'             => $status,
                'notes'              => $notes,
            ], $items, $userId);

            Session::flash('success', "Stitching Batch '{$name}' created successfully with {$totalStitched} pcs (" . number_format($totalWeight, 2) . " kg).");
            Response::redirect(url('production/stitching'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function editStitching(string $id): void {
        $batch = StitchingBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'Stitching batch not found.');
        }

        $programs = Database::fetchAll(
            "SELECT pp.*, p.name as product_name, p.code as product_code, pu.name as unit_name
             FROM production_programs pp
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
             WHERE pp.is_active = 1
             ORDER BY pp.id DESC"
        );

        $cuttingBatches = Database::fetchAll(
            "SELECT cb.*, pp.program_number, pp.name as program_name, p.name as product_name
             FROM cutting_batches cb
             LEFT JOIN production_programs pp ON cb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE (cb.is_active = 1 OR cb.is_active IS NULL)
             ORDER BY cb.id DESC"
        );

        $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $items = StitchingBatch::getItems((int)$id);

        $sourceCutting = !empty($batch['cutting_batch_id']) ? CuttingBatch::find((int)$batch['cutting_batch_id']) : null;
        $cuttingUsableWeight = 0.0;
        if ($sourceCutting) {
            $cuttingUsableWeight = (float)($sourceCutting['used_weight'] > 0 ? $sourceCutting['used_weight'] : max(0.0, (float)($sourceCutting['roll_weight'] ?? 0) - (float)($sourceCutting['wastage_weight'] ?? 0)));
        }

        // Sync received_qty / cut_qty from linked source cutting batch items
        if (!empty($batch['cutting_batch_id'])) {
            $cuttingItems = CuttingBatch::getItems((int)$batch['cutting_batch_id']);
            $cutMap = [];
            foreach ($cuttingItems as $ci) {
                $sKey = strtoupper(trim($ci['size_name'] ?? ''));
                $cKey = strtoupper(trim($ci['color_name'] ?? ''));
                $cutMap[$sKey . '_' . $cKey] = (int)($ci['cut_qty'] ?? 0);
            }
            foreach ($items as &$it) {
                $sName = strtoupper(trim(!empty($it['size_name']) ? $it['size_name'] : ($it['display_size'] ?? '')));
                $cName = strtoupper(trim(!empty($it['color_name']) ? $it['color_name'] : ($it['display_color'] ?? '')));
                $key = $sName . '_' . $cName;
                if (isset($cutMap[$key])) {
                    $it['received_qty'] = $cutMap[$key];
                    $it['cut_qty'] = $cutMap[$key];
                }
            }
            unset($it);
        }

        // Load rolls for catalog hierarchy mapping
        $rolls = !empty($batch['cutting_batch_id']) ? CuttingBatch::getRolls((int)$batch['cutting_batch_id']) : [];
        if (empty($rolls) && !empty($batch['program_id'])) {
            $rolls = ProductionProgram::getRolls((int)$batch['program_id']);
        }

        $defaultHierarchyParts = array_filter([
            $batch['category_name'] ?? '',
            $batch['subcategory_name'] ?? '',
            $batch['sub_subcategory_name'] ?? ''
        ]);
        $defaultCatalogHierarchy = !empty($defaultHierarchyParts) ? implode(' › ', $defaultHierarchyParts) : 'General Apparel';

        $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
            'name'               => $defaultCatalogHierarchy,
            'category_id'        => $batch['category_id'] ?? null,
            'subcategory_id'     => $batch['subcategory_id'] ?? null,
            'sub_subcategory_id' => $batch['sub_subcategory_id'] ?? null,
        ], 'stitched_qty');

        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");

        Response::view('production.stitching.edit', [
            'pageTitle'           => 'Edit Stitching Batch — ' . ($batch['name'] ?: $batch['batch_number']),
            'activeModule'        => 'production',
            'activeMenu'          => 'stitching',
            'batch'               => $batch,
            'sourceCutting'       => $sourceCutting,
            'cuttingUsableWeight' => $cuttingUsableWeight,
            'programs'            => $programs,
            'cuttingBatches'      => $cuttingBatches,
            'categories'          => $categories,
            'distinctCatalogs'    => array_values($distinctCatalogs),
            'items'               => $items,
            'users'               => $users,
            'breadcrumb'          => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Stitching', 'url' => url('production/stitching')],
                ['label' => $batch['batch_number'] ?: ('Batch #' . $batch['id'])],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function updateStitching(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $cuttingBatchId = !empty($_POST['cutting_batch_id']) ? (int)$_POST['cutting_batch_id'] : null;
        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $workerId = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] ?? 'IN_PROGRESS';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Stitching Name is required. Please enter a valid batch name.');
            Response::back();
            return;
        }

        $items = [];
        $totalStitched = 0;
        $totalWeight = 0.0;

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $stitchedQty = (int)($vals['stitched_qty'] ?? ($vals['qty'] ?? 0));
                $receivedQty = (int)($vals['received_qty'] ?? $stitchedQty);
                $weight = max(0.0, (float)($vals['weight'] ?? 0));
                $unitWeight = isset($vals['weight_per_piece']) ? max(0.0, (float)$vals['weight_per_piece']) : ($stitchedQty > 0 ? round($weight / $stitchedQty, 3) : 0.000);
                if ($weight <= 0 && $stitchedQty > 0 && $unitWeight > 0) {
                    $weight = round($stitchedQty * $unitWeight, 2);
                }
                $rejQty = (int)($vals['rejected_qty'] ?? 0);
                $rewQty = (int)($vals['rework_qty'] ?? 0);
                $basePrice = max(0.0, (float)($vals['base_price'] ?? 0));
                $discountPrice = max(0.0, (float)($vals['discount_price'] ?? 0));
                $sizeName = trim($vals['size_name'] ?? '');
                $colorName = trim($vals['color_name'] ?? '');
                $variantId = !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null;

                $itemCatId = !empty($vals['category_id']) ? (int)$vals['category_id'] : $categoryId;
                $itemSubcatId = !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : $subcategoryId;
                $itemSubSubcatId = !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : $subSubcategoryId;
                $itemCatalogHierarchy = !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null;

                if (!empty($sizeName) || !empty($colorName) || $stitchedQty > 0 || $weight > 0 || $receivedQty > 0) {
                    $items[] = [
                        'variant_id'         => $variantId,
                        'category_id'        => $itemCatId,
                        'subcategory_id'     => $itemSubcatId,
                        'sub_subcategory_id' => $itemSubSubcatId,
                        'catalog_hierarchy'  => $itemCatalogHierarchy,
                        'size_name'          => $sizeName,
                        'color_name'         => $colorName,
                        'received_qty'       => $receivedQty,
                        'stitched_qty'       => $stitchedQty,
                        'weight_per_piece'   => $unitWeight,
                        'weight'             => $weight,
                        'base_price'         => $basePrice,
                        'discount_price'     => $discountPrice,
                        'rejected_qty'       => $rejQty,
                        'rework_qty'         => $rewQty,
                    ];
                    $totalStitched += $stitchedQty;
                    $totalWeight += $weight;
                }
            }
        }

        if (empty($items)) {
            Session::flash('error', 'Please fill all required variant items.');
            Response::back();
            return;
        }

        foreach ($items as $idx => $item) {
            $rowNum = $idx + 1;
            $vName = trim(($item['size_name'] ?: '') . ' / ' . ($item['color_name'] ?: ''));
            if ($item['stitched_qty'] <= 0 || $item['weight_per_piece'] <= 0 || $item['weight'] <= 0 || $item['base_price'] <= 0 || $item['discount_price'] <= 0) {
                Session::flash('error', "Row #{$rowNum} ({$vName}) has missing or 0 values. Qty, Weight, Base Price, and Discount Price must all be greater than 0.");
                Response::back();
                return;
            }
            if ($item['discount_price'] >= $item['base_price']) {
                Session::flash('error', "Row #{$rowNum} ({$vName}): Discount Price must be less than Base Price.");
                Response::back();
                return;
            }
        }

        // Validate Stitching Weight against Source Cutting Batch Usable Weight
        $existingBatch = StitchingBatch::find((int)$id);
        $targetCuttingId = $cuttingBatchId ?: ($existingBatch['cutting_batch_id'] ?? null);
        if ($targetCuttingId) {
            $cb = CuttingBatch::find((int)$targetCuttingId);
            if ($cb) {
                $cuttingUsedWeight = (float)($cb['used_weight'] > 0 ? $cb['used_weight'] : max(0.0, (float)($cb['roll_weight'] ?? 0) - (float)($cb['wastage_weight'] ?? 0)));
                if ($cuttingUsedWeight > 0 && $totalWeight > round($cuttingUsedWeight + 0.01, 2)) {
                    Session::flash('error', "Validation Error: Total Stitching Weight (" . number_format($totalWeight, 2) . " kg) exceeds Cutting Batch Usable Weight (" . number_format($cuttingUsedWeight, 2) . " kg). The stitching weight must match the cutting weight.");
                    Response::back();
                    return;
                }
            }
        }

        try {
            $batchData = [
                'worker_id'          => $workerId,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'status'             => $status,
                'notes'              => $notes,
                'received_qty'       => $totalStitched,
                'stitched_qty'       => $totalStitched,
                'total_weight'       => $totalWeight,
            ];
            if (!empty($name)) $batchData['name'] = $name;
            if ($programId) $batchData['program_id'] = $programId;
            if ($cuttingBatchId) $batchData['cutting_batch_id'] = $cuttingBatchId;
            if ($categoryId) $batchData['category_id'] = $categoryId;
            if ($subcategoryId) $batchData['subcategory_id'] = $subcategoryId;
            if ($subSubcategoryId) $batchData['sub_subcategory_id'] = $subSubcategoryId;

            ProductionService::updateStitchingBatch((int)$id, $batchData, $items);

            Session::flash('success', "Stitching Batch updated successfully with {$totalStitched} pcs (" . number_format($totalWeight, 2) . " kg).");
            Response::redirect(url('production/stitching'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function deleteStitching(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $batchId = (int)$id;
        $batch = StitchingBatch::find($batchId);
        if (!$batch) {
            Session::flash('error', 'Stitching batch not found.');
            Response::redirect(url('production/stitching'));
            return;
        }

        try {
            StitchingBatch::update($batchId, ['is_active' => 0]);
            AuditService::log('production', 'delete', 'stitching_batch', $batchId, $batch, ['is_active' => 0]);
            Session::flash('success', "Stitching Batch '{$batch['name']}' deleted successfully.");
        } catch (Exception $e) {
            Session::flash('error', 'Error deleting stitching batch: ' . $e->getMessage());
        }

        Response::redirect(url('production/stitching'));
    }

    // =========================================================================
    // LABELS
    // =========================================================================

    public function labelList(): void {
        $filters = [
            'status'     => $_GET['status'] ?? '',
            'program_id' => $_GET['program_id'] ?? '',
            'search'     => $_GET['search'] ?? '',
        ];

        $batches = LabelBatch::getAll($filters);

        Response::view('production.labels.index', [
            'pageTitle'    => 'Label Batches',
            'activeModule' => 'production',
            'activeMenu'   => 'labels',
            'batches'      => $batches,
            'filters'      => $filters,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Labels'],
            ],
        ]);
    }

    public function ajaxGetDeletedLabelCodes(): void {
        if (!hasPermission('labels', 'view') && !hasPermission('labels', 'create') && !hasPermission('labels', 'edit')) {
            Response::json(['success' => false, 'message' => 'You do not have permission to view Production QR labels.'], 403);
            return;
        }

        $rows = Database::fetchAll("SELECT code FROM production_deleted_label_codes");
        Response::json([
            'success' => true,
            'codes' => array_column($rows, 'code'),
        ]);
    }

    public function ajaxDeleteLabelCode(): void {
        if (!hasPermission('labels', 'create') && !hasPermission('labels', 'edit')) {
            Response::json(['success' => false, 'message' => 'You do not have permission to delete Production QR labels.'], 403);
            return;
        }

        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 419);
            return;
        }

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        if (!preg_match('/^[A-Z]{1,5}\d{6,}$/', $code)) {
            Response::json(['success' => false, 'message' => 'Enter a valid Production label code.'], 422);
            return;
        }

        Database::execute(
            "INSERT INTO production_deleted_label_codes (code, created_by)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code)",
            [$code, Session::userId()]
        );

        Response::json(['success' => true, 'code' => $code]);
    }

    public function deleteProductionInventoryPiece(): void {
        if (!hasPermission('inventory', 'delete')) {
            Response::json(['success' => false, 'message' => 'You do not have permission to delete Production inventory pieces.'], 403);
            return;
        }

        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 419);
            return;
        }

        $qcBatchId = (int)($_POST['qc_batch_id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        if ($qcBatchId <= 0 || !preg_match('/^[A-Z0-9][A-Z0-9_-]{2,49}$/', $code)) {
            Response::json(['success' => false, 'message' => 'A valid QC batch and QR code are required.'], 422);
            return;
        }

        $qcBatch = Database::fetchOne("SELECT id, program_id FROM qc_batches WHERE id = ?", [$qcBatchId]);
        if (!$qcBatch) {
            Response::json(['success' => false, 'message' => 'QC batch not found.'], 404);
            return;
        }

        $piecePositions = ProductionService::getQCPieceTagPositions($qcBatchId);
        $pieceNo = $piecePositions[$code] ?? null;
        if ($pieceNo === null) {
            Response::json(['success' => false, 'message' => 'This QR code could not be matched to a QC-passed inventory piece.'], 409);
            return;
        }

        $passedQty = array_sum(array_map(
            static fn(array $item): int => (int)($item['passed_qty'] ?? 0),
            QCBatch::getItems($qcBatchId)
        ));
        $outwardTotals = Database::fetchOne(
            "SELECT COALESCE(SUM(COALESCE(po.total_quantity, po.quantity, pb.total_qty, 0)), 0) as total_outward_qty
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             WHERE pb.qc_batch_id = ? OR (pb.program_id = ? AND pb.program_id > 0)",
            [$qcBatchId, (int)($qcBatch['program_id'] ?? 0)]
        );
        $outwardQty = (int)($outwardTotals['total_outward_qty'] ?? 0);
        if ($pieceNo <= min($passedQty, $outwardQty)) {
            Response::json(['success' => false, 'message' => 'Dispatched inventory pieces cannot be deleted.'], 409);
            return;
        }

        $availableTags = ProductionService::getAvailableQCPieceTags($qcBatchId);
        $isAvailable = false;
        foreach ($availableTags as $tags) {
            foreach ($tags as $tag) {
                if (strtoupper(trim((string)$tag)) === $code) {
                    $isAvailable = true;
                    break 2;
                }
            }
        }

        if (!$isAvailable) {
            Response::json(['success' => false, 'message' => 'This QR code is not available in this QC batch. It may already be packed or deleted.'], 409);
            return;
        }

        Database::execute(
            "INSERT INTO production_deleted_label_codes (code, created_by)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code)",
            [$code, Session::userId()]
        );

        Response::json(['success' => true, 'code' => $code]);
    }

    public function createLabel(): void {
        $programId = $_GET['program_id'] ?? null;
        $stitchingBatchId = $_GET['stitching_batch_id'] ?? null;

        $stitchingBatches = Database::fetchAll(
            "SELECT sb.*, pp.program_number, pp.name as program_name, p.name as product_name
             FROM stitching_batches sb
             LEFT JOIN production_programs pp ON sb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE (sb.is_active = 1 OR sb.is_active IS NULL)
               AND sb.id NOT IN (SELECT stitching_batch_id FROM label_batches WHERE stitching_batch_id IS NOT NULL)
               AND (sb.program_id IS NULL OR sb.program_id NOT IN (SELECT program_id FROM label_batches WHERE program_id IS NOT NULL))
             ORDER BY sb.id DESC"
        );

        $programs = Database::fetchAll(
            "SELECT pp.*, COALESCE(pp.name, p.name) as product_name 
             FROM production_programs pp
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE (pp.is_active = 1 OR pp.is_active IS NULL)
               AND pp.id NOT IN (SELECT program_id FROM label_batches WHERE program_id IS NOT NULL)
             ORDER BY pp.created_at DESC"
        );

        $selectedStitching = null;
        $selectedProgram = null;
        $items = [];
        $rolls = [];
        $distinctCatalogs = [];
        $catalogHierarchy = 'General Apparel';

        if ($stitchingBatchId) {
            $alreadyLabeled = Database::fetchOne(
                "SELECT id, batch_number FROM label_batches WHERE stitching_batch_id = ?",
                [(int)$stitchingBatchId]
            );
            if ($alreadyLabeled) {
                Session::flash('error', "Stitching Batch has already been assigned to Label Batch {$alreadyLabeled['batch_number']}. Duplicate label generation is not allowed.");
                Response::redirect(url('production/labels'));
                return;
            }
            $selectedStitching = StitchingBatch::find((int)$stitchingBatchId);
            if ($selectedStitching) {
                $programId = $selectedStitching['program_id'];
                $items = StitchingBatch::getItems((int)$stitchingBatchId);
                $rolls = StitchingBatch::getRolls((int)$stitchingBatchId);

                $defaultHierarchyParts = array_filter([
                    $selectedStitching['category_name'] ?? '',
                    $selectedStitching['subcategory_name'] ?? '',
                    $selectedStitching['sub_subcategory_name'] ?? ''
                ]);
                $catalogHierarchy = !empty($defaultHierarchyParts) ? implode(' › ', $defaultHierarchyParts) : ($selectedStitching['product_name'] ?? 'General Apparel');

                $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
                    'name'               => $catalogHierarchy,
                    'category_id'        => $selectedStitching['category_id'] ?? null,
                    'subcategory_id'     => $selectedStitching['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $selectedStitching['sub_subcategory_id'] ?? null,
                ], 'stitched_qty');
            }
        }

        if ($programId && !$selectedProgram) {
            $alreadyLabeledProg = Database::fetchOne(
                "SELECT id, batch_number FROM label_batches WHERE program_id = ?",
                [(int)$programId]
            );
            if ($alreadyLabeledProg) {
                Session::flash('error', "Production Program has already been assigned to Label Batch {$alreadyLabeledProg['batch_number']}. Duplicate label generation is not allowed.");
                Response::redirect(url('production/labels'));
                return;
            }
            $selectedProgram = ProductionProgram::find((int)$programId);
            if (empty($items)) {
                $items = ProductionProgram::getItems((int)$programId);
                $rolls = ProductionProgram::getRolls((int)$programId);
                $defaultHierarchyParts = array_filter([
                    $selectedProgram['category_name'] ?? '',
                    $selectedProgram['subcategory_name'] ?? '',
                    $selectedProgram['sub_subcategory_name'] ?? ''
                ]);
                $catalogHierarchy = !empty($defaultHierarchyParts) ? implode(' › ', $defaultHierarchyParts) : ($selectedProgram['product_name'] ?? 'General Apparel');
                $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
                    'name'               => $catalogHierarchy,
                    'category_id'        => $selectedProgram['category_id'] ?? null,
                    'subcategory_id'     => $selectedProgram['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $selectedProgram['sub_subcategory_id'] ?? null,
                ], 'planned_qty');
            }
        }

        $catalogSequences = [];
        foreach ($distinctCatalogs as $cName => &$catData) {
            $lastSeq = LabelItem::getLastSequenceForCatalog(
                $catData['category_id'] ?? null,
                $catData['subcategory_id'] ?? null,
                $catData['sub_subcategory_id'] ?? null,
                $catData['name'] ?? null
            );
            $catData['last_sequence'] = $lastSeq;
            $catData['next_sequence'] = $lastSeq + 1;

            $cKey = ($catData['category_id'] ?? 0) . '_' . ($catData['subcategory_id'] ?? 0) . '_' . ($catData['sub_subcategory_id'] ?? 0);
            $seqInfo = [
                'last_sequence'      => $lastSeq,
                'next_sequence'      => $lastSeq + 1,
                'category_id'        => $catData['category_id'] ?? null,
                'subcategory_id'     => $catData['subcategory_id'] ?? null,
                'sub_subcategory_id' => $catData['sub_subcategory_id'] ?? null,
                'name'               => $catData['name'] ?? null,
            ];
            $catalogSequences[$cKey] = $seqInfo;
            if (!empty($catData['name'])) {
                $catalogSequences[$catData['name']] = $seqInfo;
            }
        }
        unset($catData);

        foreach ($items as &$it) {
            $cKey = ($it['category_id'] ?? 0) . '_' . ($it['subcategory_id'] ?? 0) . '_' . ($it['sub_subcategory_id'] ?? 0);
            $seqInfo = $catalogSequences[$cKey] ?? ($catalogSequences[$it['catalog_hierarchy'] ?? ''] ?? null);
            $it['last_sequence'] = $seqInfo ? $seqInfo['last_sequence'] : 0;
            $it['next_sequence'] = $seqInfo ? $seqInfo['next_sequence'] : 1;
        }
        unset($it);

        $count = Database::fetchOne("SELECT COUNT(*) as c FROM label_batches")['c'] ?? 0;
        $suggestedBatchNumber = 'LBL-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        Response::view('production.labels.create', [
            'pageTitle'            => 'New Label Batch & QR Generator',
            'activeModule'         => 'production',
            'activeMenu'           => 'labels',
            'stitchingBatches'     => $stitchingBatches,
            'programs'             => $programs,
            'selectedStitching'    => $selectedStitching,
            'selectedProgram'      => $selectedProgram,
            'suggestedBatchNumber' => $suggestedBatchNumber,
            'items'                => $items,
            'distinctCatalogs'     => array_values($distinctCatalogs),
            'catalogSequences'     => $catalogSequences,
            'catalogHierarchy'     => $catalogHierarchy,
            'breadcrumb'           => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Labels', 'url' => url('production/labels')],
                ['label' => 'New Label Batch'],
            ],
        ]);
    }

    public function storeLabel(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $stitchingBatchId = !empty($_POST['stitching_batch_id']) ? (int)$_POST['stitching_batch_id'] : null;
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $batchNumber = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'IN_PROGRESS';

        if (empty($stitchingBatchId)) {
            Session::flash('error', 'Select Stitching Name is required. Please choose a Stitching Batch.');
            Response::back();
            return;
        }

        if (!$programId && $stitchingBatchId) {
            $sb = StitchingBatch::find($stitchingBatchId);
            if ($sb) {
                $programId = (int)$sb['program_id'];
            }
        }

        if (!$programId) {
            Session::flash('error', 'Please select a valid Stitching Batch linked to a Production Program.');
            Response::back();
            return;
        }

        // Prevent duplicate label batch generation
        if (!empty($stitchingBatchId)) {
            $existingLabel = Database::fetchOne(
                "SELECT id, batch_number FROM label_batches WHERE stitching_batch_id = ?",
                [$stitchingBatchId]
            );
            if ($existingLabel) {
                $sb = StitchingBatch::find($stitchingBatchId);
                $sbNum = $sb['batch_number'] ?? ('#' . $stitchingBatchId);
                Session::flash('error', "Validation Error: Stitching Batch ({$sbNum}) has already been assigned to Label Batch {$existingLabel['batch_number']}. Duplicate label generation is not allowed.");
                Response::back();
                return;
            }
        }

        if (!empty($programId)) {
            $existingLabelProg = Database::fetchOne(
                "SELECT id, batch_number FROM label_batches WHERE program_id = ?",
                [$programId]
            );
            if ($existingLabelProg) {
                $prog = ProductionProgram::find($programId);
                $progNum = $prog['program_number'] ?? ('#' . $programId);
                Session::flash('error', "Validation Error: Production Program ({$progNum}) has already been assigned to Label Batch {$existingLabelProg['batch_number']}. Duplicate label generation is not allowed.");
                Response::back();
                return;
            }
        }

        if (empty($batchNumber)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM label_batches")['c'] ?? 0;
            $batchNumber = 'LBL-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        }

        $items = [];
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $variantId = !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null;
                $categoryId = !empty($vals['category_id']) ? (int)$vals['category_id'] : null;
                $subcategoryId = !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : null;
                $subSubcategoryId = !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : null;
                $catHierarchy = !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null;
                $sizeName = !empty($vals['size_name']) ? trim($vals['size_name']) : null;
                $colorName = !empty($vals['color_name']) ? trim($vals['color_name']) : null;
                $basePrice = isset($vals['base_price']) ? (float)$vals['base_price'] : 0.00;
                $discountPrice = isset($vals['discount_price']) ? (float)$vals['discount_price'] : 0.00;
                $reqQty = (int)($vals['required_qty'] ?? 0);
                $prtQty = (int)($vals['printed_qty'] ?? $reqQty);
                $usdQty = (int)($vals['used_qty'] ?? $prtQty);
                $dmgQty = (int)($vals['damaged_qty'] ?? 0);
                $startSeq = !empty($vals['start_seq']) ? (int)$vals['start_seq'] : null;
                $endSeq = !empty($vals['end_seq']) ? (int)$vals['end_seq'] : null;
                $startCode = !empty($vals['start_code']) ? trim($vals['start_code']) : null;
                $endCode = !empty($vals['end_code']) ? trim($vals['end_code']) : null;

                if ($variantId || !empty($sizeName) || $reqQty > 0 || $prtQty > 0) {
                    $items[] = [
                        'variant_id'         => $variantId,
                        'category_id'        => $categoryId,
                        'subcategory_id'     => $subcategoryId,
                        'sub_subcategory_id' => $subSubcategoryId,
                        'catalog_hierarchy'  => $catHierarchy,
                        'size_name'          => $sizeName,
                        'color_name'         => $colorName,
                        'base_price'         => $basePrice,
                        'discount_price'     => $discountPrice,
                        'required_qty'       => $reqQty,
                        'printed_qty'        => $prtQty,
                        'used_qty'           => $usdQty,
                        'damaged_qty'        => $dmgQty,
                        'start_seq'          => $startSeq,
                        'end_seq'            => $endSeq,
                        'start_code'         => $startCode,
                        'end_code'           => $endCode,
                    ];
                }
            }
        }

        if (empty($items)) {
            Session::flash('error', 'Please select a Stitching Name / Batch with valid product variants before saving.');
            Response::back();
            return;
        }

        try {
            $userId = Session::userId();
            $batchId = ProductionService::createLabelBatch([
                'program_id'         => $programId,
                'stitching_batch_id' => $stitchingBatchId,
                'batch_number'       => $batchNumber,
                'status'             => $status,
            ], $items, $userId);

            Session::flash('success', "Label batch '{$batchNumber}' created successfully with QR code tag generator.");
            Response::redirect(url('production/labels'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function editLabel(string $id): void {
        $batch = LabelBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'Label batch not found.');
        }

        $items = LabelBatch::getItems((int)$id);
        $stitchingBatches = StitchingBatch::getAll();
        $rolls = !empty($batch['stitching_batch_id']) ? StitchingBatch::getRolls((int)$batch['stitching_batch_id']) : (!empty($batch['program_id']) ? ProductionProgram::getRolls((int)$batch['program_id']) : []);

        $hierarchyParts = array_filter([
            $batch['category_name'] ?? '',
            $batch['subcategory_name'] ?? '',
            $batch['sub_subcategory_name'] ?? ''
        ]);
        $catalogHierarchy = !empty($hierarchyParts) ? implode(' › ', $hierarchyParts) : ($batch['product_name'] ?? 'General Apparel');

        $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
            'name'               => $catalogHierarchy,
            'category_id'        => $batch['category_id'] ?? null,
            'subcategory_id'     => $batch['subcategory_id'] ?? null,
            'sub_subcategory_id' => $batch['sub_subcategory_id'] ?? null,
        ], 'required_qty');

        $catalogSequences = [];
        foreach ($distinctCatalogs as $cName => &$catData) {
            $lastSeq = LabelItem::getLastSequenceForCatalog(
                $catData['category_id'] ?? null,
                $catData['subcategory_id'] ?? null,
                $catData['sub_subcategory_id'] ?? null,
                $catData['name'] ?? null,
                (int)$id
            );
            $catData['last_sequence'] = $lastSeq;
            $catData['next_sequence'] = $lastSeq + 1;

            $cKey = ($catData['category_id'] ?? 0) . '_' . ($catData['subcategory_id'] ?? 0) . '_' . ($catData['sub_subcategory_id'] ?? 0);
            $seqInfo = [
                'last_sequence'      => $lastSeq,
                'next_sequence'      => $lastSeq + 1,
                'category_id'        => $catData['category_id'] ?? null,
                'subcategory_id'     => $catData['subcategory_id'] ?? null,
                'sub_subcategory_id' => $catData['sub_subcategory_id'] ?? null,
                'name'               => $catData['name'] ?? null,
            ];
            $catalogSequences[$cKey] = $seqInfo;
            if (!empty($catData['name'])) {
                $catalogSequences[$catData['name']] = $seqInfo;
            }
        }
        unset($catData);

        Response::view('production.labels.edit', [
            'pageTitle'        => 'Edit Label Batch — ' . $batch['batch_number'],
            'activeModule'     => 'production',
            'activeMenu'       => 'labels',
            'batch'            => $batch,
            'items'            => $items,
            'stitchingBatches' => $stitchingBatches,
            'catalogHierarchy' => $catalogHierarchy,
            'distinctCatalogs' => array_values($distinctCatalogs),
            'catalogSequences' => $catalogSequences,
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Labels', 'url' => url('production/labels')],
                ['label' => $batch['batch_number']],
            ],
        ]);
    }

    public function updateLabel(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        $items = [];
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $vals) {
                $items[] = [
                    'variant_id'         => !empty($vals['variant_id']) ? (int)$vals['variant_id'] : null,
                    'category_id'        => !empty($vals['category_id']) ? (int)$vals['category_id'] : null,
                    'subcategory_id'     => !empty($vals['subcategory_id']) ? (int)$vals['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($vals['sub_subcategory_id']) ? (int)$vals['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => !empty($vals['catalog_hierarchy']) ? trim($vals['catalog_hierarchy']) : null,
                    'size_name'          => !empty($vals['size_name']) ? trim($vals['size_name']) : null,
                    'color_name'         => !empty($vals['color_name']) ? trim($vals['color_name']) : null,
                    'base_price'         => (float)($vals['base_price'] ?? 0.00),
                    'discount_price'     => (float)($vals['discount_price'] ?? 0.00),
                    'required_qty'       => (int)($vals['required_qty'] ?? 0),
                    'printed_qty'        => (int)($vals['printed_qty'] ?? 0),
                    'used_qty'           => (int)($vals['used_qty'] ?? 0),
                    'damaged_qty'        => (int)($vals['damaged_qty'] ?? 0),
                    'start_seq'          => !empty($vals['start_seq']) ? (int)$vals['start_seq'] : null,
                    'end_seq'            => !empty($vals['end_seq']) ? (int)$vals['end_seq'] : null,
                    'start_code'         => !empty($vals['start_code']) ? trim($vals['start_code']) : null,
                    'end_code'           => !empty($vals['end_code']) ? trim($vals['end_code']) : null,
                ];
            }
        }

        try {
            ProductionService::updateLabelBatch((int)$id, [
                'status' => $_POST['status'] ?? 'IN_PROGRESS',
            ], $items);

            Session::flash('success', 'Label batch updated.');
            Response::redirect(url('production/labels'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function deleteLabel(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $batchId = (int)$id;
        $batch = LabelBatch::find($batchId);
        if (!$batch) {
            Session::flash('error', 'Label batch not found.');
            Response::back();
            return;
        }

        try {
            ProductionService::deleteLabelBatch($batchId);
            Session::flash('success', 'Label Batch ' . ($batch['batch_number'] ?? ('#' . $batchId)) . ' deleted successfully.');
            Response::redirect(url('production/labels'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }




    // =========================================================================
    // PRODUCTION BATCHES & PACKING
    // =========================================================================

    public function batchList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $warehouseId = trim($_GET['warehouse_id'] ?? $_GET['destination_warehouse_id'] ?? '');
        $filters = [
            'status'                   => trim($_GET['status'] ?? ''),
            'program_id'               => trim($_GET['program_id'] ?? ''),
            'production_unit_id'       => $scopedUnitId ? (string)$scopedUnitId : trim($_GET['production_unit_id'] ?? ''),
            'destination_warehouse_id' => $warehouseId,
            'warehouse_id'             => $warehouseId,
            'search'                   => trim($_GET['search'] ?? ''),
        ];

        $batches = ProductionBatch::getAll($filters);
        $warehouses = Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");
        if (empty($warehouses)) {
            $warehouses = Database::fetchAll("SELECT * FROM warehouses ORDER BY name ASC");
        }

        Response::view('production.batches.index', [
            'pageTitle'    => 'Finished Production Batches',
            'activeModule' => 'production',
            'activeMenu'   => 'batches',
            'batches'      => $batches,
            'warehouses'   => $warehouses,
            'filters'      => $filters,
            'scopedUnitId' => $scopedUnitId,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Finished Batches'],
            ],
        ]);
    }

    public function createBatch(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $qcBatchId = $_GET['qc_batch_id'] ?? null;
        $unitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        
        $rawQcBatches = Database::fetchAll(
            "SELECT qb.*, pp.program_number, pp.product_id as program_product_id, COALESCE(p.name, pp.name, 'General Apparel') as product_name,
                    COALESCE((SELECT SUM(passed_qty) FROM qc_items WHERE qc_batch_id = qb.id), qb.passed_qty, 0) as total_qc_passed,
                    COALESCE((SELECT SUM(rework_qty) FROM qc_items WHERE qc_batch_id = qb.id), qb.rework_qty, 0) as total_qc_rework,
                    COALESCE((
                        SELECT SUM(pbi.quantity)
                        FROM production_batch_items pbi
                        JOIN production_batches pb ON pbi.production_batch_id = pb.id
                        WHERE (pb.qc_batch_id = qb.id OR pb.additional_qc_batch_ids LIKE CONCAT('%', qb.id, '%')) AND pb.status != 'CANCELLED'
                    ), 0) as already_packed_qty
             FROM qc_batches qb
             JOIN production_programs pp ON qb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE (qb.passed_qty > 0 OR qb.status IN ('PASSED', 'PARTIAL', 'COMPLETED', 'REWORK'))$unitClause
             ORDER BY qb.created_at DESC"
        );

        $qcBatches = [];
        foreach ($rawQcBatches as $qb) {
            $passed = (int)($qb['total_qc_passed'] ?: $qb['passed_qty']);
            $packed = (int)$qb['already_packed_qty'];
            $rework = (int)($qb['total_qc_rework'] ?: ($qb['rework_qty'] ?? 0));
            $remaining = max(0, $passed - $packed);
            
            $qb['total_qc_passed'] = $passed;
            $qb['total_qc_rework'] = $rework;
            $qb['already_packed_qty'] = $packed;
            $qb['remaining_qty'] = $remaining;

            if ($remaining > 0 || $rework > 0 || ($qcBatchId && (int)$qcBatchId === (int)$qb['id'])) {
                $qcBatches[] = $qb;
            }
        }

        $selectedQC = null;
        $additionalQCBatches = [];
        $eligibleAdditionalBatches = [];
        $additionalQcIds = [];
        $isMultiQC = !empty($_GET['multi_qc']) || !empty($_GET['additional_qc_batch_ids']);

        if (!empty($_GET['additional_qc_batch_ids'])) {
            if (is_array($_GET['additional_qc_batch_ids'])) {
                $additionalQcIds = array_map('intval', $_GET['additional_qc_batch_ids']);
            } else {
                $additionalQcIds = array_map('intval', explode(',', (string)$_GET['additional_qc_batch_ids']));
            }
            $additionalQcIds = array_values(array_filter($additionalQcIds, fn($id) => $id > 0 && $id != (int)$qcBatchId));
        }

        $items = [];
        $includeRework = !empty($_GET['include_rework']);
        $totalQCRework = 0;
        $combinedTotals = [
            'total_passed' => 0,
            'packed'       => 0,
            'remaining'    => 0,
            'rework'       => 0,
        ];

        if ($qcBatchId) {
            $selectedQC = QCBatch::find((int)$qcBatchId);
            if ($selectedQC) {
                $selProg = Database::fetchOne("SELECT program_number, product_id FROM production_programs WHERE id = ?", [(int)$selectedQC['program_id']]);
                $selectedQC['program_number'] = $selProg['program_number'] ?? ('PRG #' . $selectedQC['program_id']);
                $selectedQCProd = Database::fetchOne("SELECT name FROM products WHERE id = ?", [(int)($selProg['product_id'] ?? 0)]);
                $selectedQC['product_name'] = $selectedQCProd['name'] ?? 'Finished Product';

                $totalQCPassed = (int)(Database::fetchOne(
                    "SELECT COALESCE(SUM(passed_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?",
                    [(int)$qcBatchId]
                )['total'] ?? 0);
                if ($totalQCPassed <= 0) {
                    $totalQCPassed = (int)($selectedQC['passed_qty'] ?? 0);
                }

                $alreadyPackedTotal = (int)(Database::fetchOne(
                    "SELECT COALESCE(SUM(pbi.quantity), 0) as total
                     FROM production_batch_items pbi
                     JOIN production_batches pb ON pbi.production_batch_id = pb.id
                     WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED'",
                    [(int)$qcBatchId, '%' . (int)$qcBatchId . '%']
                )['total'] ?? 0);

                $totalQCRework = (int)(Database::fetchOne(
                    "SELECT COALESCE(SUM(rework_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?",
                    [(int)$qcBatchId]
                )['total'] ?? 0);
                if ($totalQCRework <= 0) {
                    $totalQCRework = (int)($selectedQC['rework_qty'] ?? 0);
                }

                $selectedQC['total_qc_passed'] = $totalQCPassed;
                $selectedQC['already_packed_qty'] = $alreadyPackedTotal;
                $selectedQC['remaining_qty'] = max(0, $totalQCPassed - $alreadyPackedTotal);
                $selectedQC['total_qc_rework'] = $totalQCRework;

                $combinedTotals['total_passed'] += $totalQCPassed;
                $combinedTotals['packed'] += $alreadyPackedTotal;
                $combinedTotals['remaining'] += $selectedQC['remaining_qty'];
                $combinedTotals['rework'] += $totalQCRework;

                // Eligible other batches to combine: ONLY batches that have reworked / rejected-then-completed good products
                foreach ($qcBatches as $qb) {
                    if ((int)$qb['id'] === (int)$selectedQC['id']) {
                        continue;
                    }
                    $hasReworkGoods = false;
                    $reworkCount = 0;

                    if (!empty($qb['total_qc_rework']) || !empty($qb['rework_qty'])) {
                        $hasReworkGoods = true;
                        $reworkCount = (int)($qb['total_qc_rework'] ?? $qb['rework_qty']);
                    }

                    if (!$hasReworkGoods && !empty($qb['pieces_data'])) {
                        $pieces = json_decode($qb['pieces_data'], true);
                        if (is_array($pieces)) {
                            foreach ($pieces as $p) {
                                if (!empty($p['is_reinspected']) || (!empty($p['defect_reason']) && ($p['status'] ?? '') === 'APPROVED')) {
                                    $hasReworkGoods = true;
                                    $reworkCount++;
                                }
                            }
                        }
                    }

                    if (!$hasReworkGoods) {
                        $qcItemsDefects = Database::fetchOne(
                            "SELECT COUNT(*) as cnt FROM qc_items WHERE qc_batch_id = ? AND (defect_reason != '' OR rework_qty > 0)",
                            [(int)$qb['id']]
                        );
                        if (!empty($qcItemsDefects['cnt'])) {
                            $hasReworkGoods = true;
                            $reworkCount = (int)$qcItemsDefects['cnt'];
                        }
                    }

                    if ($hasReworkGoods && ($qb['remaining_qty'] ?? 1) > 0) {
                        $qb['rework_completed_qty'] = $reworkCount;
                        $eligibleAdditionalBatches[] = $qb;
                    }
                }

                // Fetch already packed quantities mapped per variant for primary batch
                $variantPackedMap = [];
                $packedRows = Database::fetchAll(
                    "SELECT pbi.variant_id, COALESCE(SUM(pbi.quantity), 0) as packed_qty
                     FROM production_batch_items pbi
                     JOIN production_batches pb ON pbi.production_batch_id = pb.id
                     WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED'
                     GROUP BY pbi.variant_id",
                    [(int)$qcBatchId, '%' . (int)$qcBatchId . '%']
                );
                foreach ($packedRows as $pr) {
                    $variantPackedMap[(int)$pr['variant_id']] = (int)$pr['packed_qty'];
                }

                $primaryItems = QCBatch::getItems((int)$qcBatchId);
                $productId = ProductionService::findOrCreateProductForProgram((int)$selectedQC['program_id']);
                $availablePieceTags = ProductionService::getAvailableQCPieceTags((int)$qcBatchId, $includeRework);

                foreach ($primaryItems as &$it) {
                    if (empty($it['variant_id']) && $productId > 0) {
                        $vId = ProductionService::findOrCreateVariant(
                            $productId,
                            $it['size_name'] ?? $it['display_size'] ?? '',
                            $it['color_name'] ?? $it['display_color'] ?? '',
                            $it['sku'] ?? ''
                        );
                        if ($vId > 0) {
                            $it['variant_id'] = $vId;
                            Database::execute("UPDATE qc_items SET variant_id = ? WHERE id = ?", [$vId, $it['id']]);
                            if (!empty($it['base_price']) || !empty($it['discount_price'])) {
                                Database::execute("UPDATE product_variants SET mrp = ?, selling_price = ? WHERE id = ?", [
                                    (float)($it['base_price'] ?? 0),
                                    (float)($it['discount_price'] ?? 0),
                                    $vId
                                ]);
                            }
                        }
                    }
                    $vId = (int)($it['variant_id'] ?? 0);
                    $vPacked = $variantPackedMap[$vId] ?? 0;
                    $it['already_packed'] = $vPacked;
                    $it['remaining_qty'] = max(0, (int)$it['passed_qty'] - $vPacked);
                    $it['available_piece_tags'] = $availablePieceTags[(int)$it['id']] ?? [];
                    $it['rework_qty'] = (int)($it['rework_qty'] ?? 0);
                    $it['qc_batch_id'] = (int)$selectedQC['id'];
                    $it['qc_batch_number'] = (int)$selectedQC['id'];
                    $it['program_number'] = $selectedQC['program_number'] ?? '';
                    $it['is_additional'] = false;
                    $items[] = $it;
                }
                unset($it);

                // Process additional QC batches if selected
                foreach ($additionalQcIds as $addId) {
                    $addQC = QCBatch::find((int)$addId);
                    if (!$addQC) continue;

                    $addProg = Database::fetchOne("SELECT program_number, product_id FROM production_programs WHERE id = ?", [(int)$addQC['program_id']]);
                    $addQC['program_number'] = $addProg['program_number'] ?? ('PRG #' . $addQC['program_id']);
                    $addProdRow = Database::fetchOne("SELECT name FROM products WHERE id = ?", [(int)($addProg['product_id'] ?? 0)]);
                    $addQC['product_name'] = $addProdRow['name'] ?? 'Finished Product';

                    $addPassed = (int)(Database::fetchOne("SELECT COALESCE(SUM(passed_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?", [$addId])['total'] ?? 0);
                    if ($addPassed <= 0) $addPassed = (int)($addQC['passed_qty'] ?? 0);

                    $addPacked = (int)(Database::fetchOne(
                        "SELECT COALESCE(SUM(pbi.quantity), 0) as total
                         FROM production_batch_items pbi
                         JOIN production_batches pb ON pbi.production_batch_id = pb.id
                         WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED'",
                        [$addId, '%' . $addId . '%']
                    )['total'] ?? 0);

                    $addRework = (int)(Database::fetchOne("SELECT COALESCE(SUM(rework_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?", [$addId])['total'] ?? 0);
                    if ($addRework <= 0) $addRework = (int)($addQC['rework_qty'] ?? 0);

                    $addRemaining = max(0, $addPassed - $addPacked);
                    $addQC['total_qc_passed'] = $addPassed;
                    $addQC['already_packed_qty'] = $addPacked;
                    $addQC['remaining_qty'] = $addRemaining;
                    $addQC['total_qc_rework'] = $addRework;

                    $combinedTotals['total_passed'] += $addPassed;
                    $combinedTotals['packed'] += $addPacked;
                    $combinedTotals['remaining'] += $addRemaining;
                    $combinedTotals['rework'] += $addRework;

                    $addTagsMap = ProductionService::getAvailableQCPieceTags($addId, $includeRework);
                    $addPackedRows = Database::fetchAll(
                        "SELECT pbi.variant_id, COALESCE(SUM(pbi.quantity), 0) as packed_qty
                         FROM production_batch_items pbi
                         JOIN production_batches pb ON pbi.production_batch_id = pb.id
                         WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED'
                         GROUP BY pbi.variant_id",
                        [$addId, '%' . $addId . '%']
                    );
                    $addVariantPackedMap = [];
                    foreach ($addPackedRows as $apr) {
                        $addVariantPackedMap[(int)$apr['variant_id']] = (int)$apr['packed_qty'];
                    }

                    $addItems = QCBatch::getItems($addId);
                    $addProdId = ProductionService::findOrCreateProductForProgram((int)$addQC['program_id']);
                    foreach ($addItems as &$ait) {
                        if (empty($ait['variant_id']) && $addProdId > 0) {
                            $vId = ProductionService::findOrCreateVariant(
                                $addProdId,
                                $ait['size_name'] ?? $ait['display_size'] ?? '',
                                $ait['color_name'] ?? $ait['display_color'] ?? '',
                                $ait['sku'] ?? ''
                            );
                            if ($vId > 0) {
                                $ait['variant_id'] = $vId;
                                Database::execute("UPDATE qc_items SET variant_id = ? WHERE id = ?", [$vId, $ait['id']]);
                                if (!empty($ait['base_price']) || !empty($ait['discount_price'])) {
                                    Database::execute("UPDATE product_variants SET mrp = ?, selling_price = ? WHERE id = ?", [
                                        (float)($ait['base_price'] ?? 0),
                                        (float)($ait['discount_price'] ?? 0),
                                        $vId
                                    ]);
                                }
                            }
                        }
                        $vId = (int)($ait['variant_id'] ?? 0);
                        $vPacked = $addVariantPackedMap[$vId] ?? 0;
                        $ait['already_packed'] = $vPacked;
                        $ait['remaining_qty'] = max(0, (int)$ait['passed_qty'] - $vPacked);
                        $ait['available_piece_tags'] = $addTagsMap[(int)$ait['id']] ?? [];
                        $ait['rework_qty'] = (int)($ait['rework_qty'] ?? 0);
                        $ait['qc_batch_id'] = $addId;
                        $ait['qc_batch_number'] = $addId;
                        $ait['program_number'] = $addQC['program_number'] ?? '';
                        $ait['is_additional'] = true;
                        $items[] = $ait;
                    }
                    unset($ait);

                    $additionalQCBatches[] = $addQC;
                }

                $existingBatches = Database::fetchAll(
                    "SELECT pb.*, w.name as warehouse_name, w.code as warehouse_code
                     FROM production_batches pb 
                     LEFT JOIN warehouses w ON pb.destination_warehouse_id = w.id 
                     WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED' 
                     ORDER BY pb.id DESC",
                    [(int)$qcBatchId, '%' . (int)$qcBatchId . '%']
                );

                // Ensure selectedQC is in $qcBatches list if not already present
                $inList = false;
                foreach ($qcBatches as $qb) {
                    if ((int)$qb['id'] === (int)$selectedQC['id']) {
                        $inList = true;
                        break;
                    }
                }
                if (!$inList) {
                    $qcBatches[] = array_merge($selectedQC, [
                        'total_qc_passed'   => $totalQCPassed,
                        'already_packed_qty' => $alreadyPackedTotal,
                        'remaining_qty'      => max(0, $totalQCPassed - $alreadyPackedTotal),
                    ]);
                }
            }
        }

        $warehouses = Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");

        Response::view('production.batches.create', [
            'pageTitle'                 => 'Pack Production Batch',
            'activeModule'              => 'production',
            'activeMenu'                => 'batches',
            'qcBatches'                 => $qcBatches,
            'selectedQC'                => $selectedQC,
            'additionalQCBatches'       => $additionalQCBatches,
            'eligibleAdditionalBatches' => $eligibleAdditionalBatches,
            'additionalQcIds'           => $additionalQcIds,
            'isMultiQC'                 => $isMultiQC,
            'combinedTotals'            => $combinedTotals,
            'existingBatches'           => $existingBatches ?? [],
            'items'                     => $items,
            'warehouses'                => $warehouses,
            'includeRework'             => $includeRework ?? false,
            'totalQCRework'             => $totalQCRework ?? 0,
            'breadcrumb'                => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Batches', 'url' => url('production/batches')],
                ['label' => 'Pack Batch'],
            ],
        ]);
    }

    public function storeBatch(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        $validator = Validator::make($_POST, [
            'destination_warehouse_id' => 'required|numeric',
            'qc_batch_id'              => 'required|numeric',
            'box_count'                => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            Response::back();
            return;
        }

        $qcBatchId = (int)$_POST['qc_batch_id'];
        $qcBatch = QCBatch::find($qcBatchId);
        if (!$qcBatch) {
            Session::flash('error', 'Selected QC Inspection Batch does not exist.');
            Response::back();
            return;
        }

        $additionalQcIds = [];
        if (!empty($_POST['additional_qc_batch_ids']) && is_array($_POST['additional_qc_batch_ids'])) {
            foreach ($_POST['additional_qc_batch_ids'] as $aqId) {
                $aqId = (int)$aqId;
                if ($aqId > 0 && $aqId !== $qcBatchId && !in_array($aqId, $additionalQcIds, true)) {
                    $additionalQcIds[] = $aqId;
                }
            }
        }
        $isMultiQC = !empty($additionalQcIds) || !empty($_POST['is_multi_qc']);

        $programId = (int)($qcBatch['program_id'] ?? ($_POST['program_id'] ?? 0));
        $productId = ProductionService::findOrCreateProductForProgram($programId);

        $items = [];
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $key => $vals) {
                $qty = (int)($vals['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $variantId = 0;
                if (!empty($vals['variant_id']) && is_numeric($vals['variant_id'])) {
                    $variantId = (int)$vals['variant_id'];
                } elseif (is_numeric($key) && (int)$key > 0 && empty($vals['size_name'])) {
                    $variantId = (int)$key;
                }

                // Verify variant exists in product_variants
                if ($variantId > 0) {
                    $exists = Database::fetchOne("SELECT id FROM product_variants WHERE id = ?", [$variantId]);
                    if (!$exists) {
                        $variantId = 0;
                    }
                }

                // Fallback find or create variant
                if ($variantId <= 0 && $productId > 0) {
                    $sizeName = $vals['size_name'] ?? '';
                    $colorName = $vals['color_name'] ?? '';
                    $sku = $vals['sku'] ?? '';
                    $variantId = ProductionService::findOrCreateVariant($productId, $sizeName, $colorName, $sku);

                    if (!empty($vals['qc_item_id']) && $variantId > 0) {
                        Database::execute("UPDATE qc_items SET variant_id = ? WHERE id = ?", [$variantId, (int)$vals['qc_item_id']]);
                    }
                }

                if ($variantId > 0) {
                    $items[] = [
                        'variant_id' => $variantId,
                        'quantity'   => $qty,
                        'qc_item_id' => (int)($vals['qc_item_id'] ?? 0),
                        'piece_tags' => isset($vals['piece_tags']) && is_array($vals['piece_tags']) ? $vals['piece_tags'] : [],
                    ];
                    
                    if (!empty($vals['qc_item_id'])) {
                        $qcItem = Database::fetchOne("SELECT base_price, discount_price FROM qc_items WHERE id = ?", [(int)$vals['qc_item_id']]);
                        if ($qcItem && ($qcItem['base_price'] > 0 || $qcItem['discount_price'] > 0)) {
                            Database::execute("UPDATE product_variants SET mrp = ?, selling_price = ? WHERE id = ?", [
                                (float)$qcItem['base_price'],
                                (float)$qcItem['discount_price'],
                                $variantId
                            ]);
                        }
                    }
                }
            }
        }

        if (empty($items)) {
            Session::flash('error', 'Please pack at least 1 variant item with quantity greater than 0.');
            Response::back();
            return;
        }

        try {
            $user = Session::get('user');
            $userId = !empty($user['id']) ? (int)$user['id'] : (int)(Database::fetchOne("SELECT id FROM users LIMIT 1")['id'] ?? 2);
            $includeRework = !empty($_POST['include_rework']);
            $batchNotes = [];
            if ($includeRework) {
                $batchNotes[] = '[Admin Rework Authorized: Includes repaired/reworked garments packed for outward dispatch]';
            }
            if ($isMultiQC && !empty($additionalQcIds)) {
                $allLabels = array_merge(['QC #' . $qcBatchId], array_map(fn($id) => 'QC #' . $id, $additionalQcIds));
                $batchNotes[] = '[Multi-Batch Combined: ' . implode(' + ', $allLabels) . ']';
            }
            $notesStr = !empty($batchNotes) ? implode(' ', $batchNotes) : null;

            $batchId = ProductionService::createProductionBatch([
                'program_id'               => $programId,
                'qc_batch_id'              => $qcBatchId,
                'additional_qc_batch_ids'  => $additionalQcIds,
                'is_multi_qc'              => $isMultiQC ? 1 : 0,
                'destination_warehouse_id' => (int)$_POST['destination_warehouse_id'],
                'box_count'                => (int)($_POST['box_count'] ?? 1),
                'status'                   => 'PREPARING',
                'include_rework'           => $includeRework,
                'notes'                    => $notesStr,
            ], $items, $userId);

            Session::flash('success', 'Production batch packed and created successfully.');
            Response::redirect(url('production/batches'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function approveBatch(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        try {
            $user = Session::get('user');
            $userId = !empty($user['id']) ? (int)$user['id'] : (int)(Database::fetchOne("SELECT id FROM users LIMIT 1")['id'] ?? 2);
            ProductionService::approveProductionBatch((int)$id, $userId);
            Session::flash('success', 'Production batch approved for outward dispatch.');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::back();
    }

    public function printBatchQRLabel(string $id): void {
        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Response::error(404, 'Production batch not found.');
            return;
        }
        $items = ProductionBatch::getItems($batchId);
        Response::viewOnly('warehouse.inward.label', [
            'batch' => $batch,
            'items' => $items,
        ]);
    }

    // =========================================================================
    // OUTWARD DISPATCH
    // =========================================================================

    public function outwardList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $search = $_GET['search'] ?? '';
        $outwards = ProductionOutward::getAll([
            'search'             => $search,
            'production_unit_id' => $scopedUnitId,
        ]);

        $unitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        $approvedBatches = Database::fetchAll(
            "SELECT pb.*, pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name, w.name as warehouse_name
             FROM production_batches pb
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             WHERE pb.status = 'APPROVED'
               AND pb.id NOT IN (SELECT production_batch_id FROM production_outwards WHERE production_batch_id IS NOT NULL)
               AND pb.id NOT IN (SELECT production_batch_id FROM production_outward_batches WHERE production_batch_id IS NOT NULL)$unitClause
             ORDER BY pb.created_at DESC"
        );

        $warehouses = Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");
        if (empty($warehouses)) {
            $warehouses = Database::fetchAll("SELECT * FROM warehouses ORDER BY name ASC");
        }

        Response::view('production.outward.index', [
            'pageTitle'       => 'Production Outward & Dispatch',
            'activeModule'    => 'production',
            'activeMenu'      => 'outward',
            'outwards'        => $outwards,
            'approvedBatches' => $approvedBatches,
            'warehouses'      => $warehouses,
            'search'          => $search,
            'scopedUnitId'    => $scopedUnitId,
            'breadcrumb'      => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Outward Dispatch'],
            ],
        ]);
    }

    public function storeOutward(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        // Support both single batch and multiple batches selection
        $batchIds = [];
        if (!empty($_POST['production_batch_ids']) && is_array($_POST['production_batch_ids'])) {
            foreach ($_POST['production_batch_ids'] as $bid) {
                $bid = (int)$bid;
                if ($bid > 0 && !in_array($bid, $batchIds, true)) {
                    $batchIds[] = $bid;
                }
            }
        }
        if (empty($batchIds) && !empty($_POST['production_batch_id'])) {
            $batchIds[] = (int)$_POST['production_batch_id'];
        }

        if (empty($batchIds)) {
            Session::flash('error', 'Please select at least one approved production batch to dispatch.');
            Response::back();
            return;
        }

        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
        if (empty($vehicleNumber)) {
            Session::flash('error', 'Please enter Vehicle / Transport Number or Courier reference before dispatching.');
            Response::back();
            return;
        }

        try {
            $userId = Session::userId();
            $outwardId = ProductionService::createOutward([
                'production_batch_ids' => $batchIds,
                'dispatch_date'        => !empty($_POST['dispatch_date']) ? $_POST['dispatch_date'] : date('Y-m-d H:i:s'),
                'vehicle_number'       => $vehicleNumber,
                'driver_name'          => trim($_POST['driver_name'] ?? ''),
                'driver_contact'       => trim($_POST['driver_contact'] ?? ''),
                'transporter_name'     => trim($_POST['transporter_name'] ?? ''),
                'lr_number'            => trim($_POST['lr_number'] ?? ''),
                'notes'                => trim($_POST['notes'] ?? ''),
            ], $userId);

            $batchCount = count($batchIds);
            $msg = ($batchCount > 1) 
                ? "Multiple batches ({$batchCount} batches) combined and dispatched to warehouse successfully under Outward #{$outwardId}."
                : "Production batch dispatched to warehouse successfully.";
            Session::flash('success', $msg);
            Response::redirect(url('production/outward'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function dispatchOutward(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }
        try {
            $userId = Session::userId();
            ProductionService::dispatchOutward((int)$id, $userId);
            Session::flash('success', 'Outward dispatched.');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::back();
    }

    public function batchDetailsAjax(string $id): void {
        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Response::json(['success' => false, 'message' => 'Production batch not found.'], 404);
            return;
        }
        $items = ProductionBatch::getItems($batchId);
        $outward = ProductionOutward::findByBatchId($batchId);

        $qcBatch = null;
        if (!empty($batch['qc_batch_id'])) {
            $qcBatch = QCBatch::find((int)$batch['qc_batch_id']);
            if ($qcBatch) {
                $qcProg = Database::fetchOne("SELECT program_number FROM production_programs WHERE id = ?", [(int)$qcBatch['program_id']]);
                $qcBatch['program_number'] = $qcProg['program_number'] ?? ('PRG #' . $qcBatch['program_id']);
            }
        }

        $multiQCBatches = [];
        $existingAddIds = [];
        if (!empty($batch['additional_qc_batch_ids'])) {
            $addIds = json_decode((string)$batch['additional_qc_batch_ids'], true);
            if (!is_array($addIds)) {
                $addIds = explode(',', (string)$batch['additional_qc_batch_ids']);
            }
            $existingAddIds = array_values(array_filter(array_map('intval', $addIds)));
            foreach ($existingAddIds as $aid) {
                $aq = QCBatch::find((int)$aid);
                if ($aq) {
                    $aqProg = Database::fetchOne("SELECT program_number FROM production_programs WHERE id = ?", [(int)$aq['program_id']]);
                    $aq['program_number'] = $aqProg['program_number'] ?? ('PRG #' . $aq['program_id']);
                    $multiQCBatches[] = $aq;
                }
            }
        }

        $eligibleQCBatches = [];
        if (!empty($batch['program_id'])) {
            $eligibleQCBatches = Database::fetchAll(
                "SELECT qb.id, qb.program_id, qb.passed_qty, qb.status,
                        pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name,
                        COALESCE((SELECT SUM(passed_qty) FROM qc_items WHERE qc_batch_id = qb.id), qb.passed_qty, 0) as total_qc_passed,
                        COALESCE((SELECT SUM(rework_qty) FROM qc_items WHERE qc_batch_id = qb.id), qb.rework_qty, 0) as rework_completed_qty
                 FROM qc_batches qb
                 JOIN production_programs pp ON qb.program_id = pp.id
                 LEFT JOIN products p ON pp.product_id = p.id
                 WHERE qb.program_id = ? AND qb.id != ?
                 ORDER BY qb.id DESC",
                [(int)$batch['program_id'], (int)($batch['qc_batch_id'] ?? 0)]
            );
        }

        Response::json([
            'success'             => true,
            'batch'               => $batch,
            'qc_batch'            => $qcBatch,
            'items'               => $items,
            'outward'             => $outward,
            'multi_qc_batches'    => $multiQCBatches,
            'eligible_qc_batches' => $eligibleQCBatches,
            'additional_qc_ids'   => $existingAddIds,
        ]);
    }

    public function updateBatch(string $id): void {
        if (!hasPermission('batches', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to edit packaging batches.');
            Response::redirect(url('production/batches'));
            return;
        }

        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Session::flash('error', 'Production batch not found.');
            Response::back();
            return;
        }

        $batchNumber = !empty($_POST['batch_number']) ? trim((string)$_POST['batch_number']) : ($batch['batch_number'] ?? '');
        $warehouseId = !empty($_POST['destination_warehouse_id']) ? (int)$_POST['destination_warehouse_id'] : (int)$batch['destination_warehouse_id'];
        $boxCount = isset($_POST['box_count']) ? (int)$_POST['box_count'] : (int)($batch['box_count'] ?? 0);
        $status = !empty($_POST['status']) ? trim($_POST['status']) : ($batch['status'] ?? 'PREPARING');
        $notes = trim($_POST['notes'] ?? '');

        $updateData = [
            'batch_number'             => $batchNumber ?: ($batch['batch_number'] ?? ''),
            'destination_warehouse_id' => $warehouseId,
            'box_count'                => max(1, $boxCount),
            'status'                   => $status,
            'notes'                    => $notes,
        ];

        if (isset($_POST['additional_qc_batch_ids'])) {
            $addIds = is_array($_POST['additional_qc_batch_ids']) 
                ? $_POST['additional_qc_batch_ids'] 
                : explode(',', (string)$_POST['additional_qc_batch_ids']);
            $addIds = array_values(array_filter(array_map('intval', $addIds), fn($v) => $v > 0 && $v !== (int)($batch['qc_batch_id'] ?? 0)));
            $updateData['additional_qc_batch_ids'] = !empty($addIds) ? json_encode($addIds) : null;
            $updateData['is_multi_qc'] = !empty($addIds) ? 1 : 0;
        }

        if (!empty($_POST['item_qty']) && is_array($_POST['item_qty'])) {
            $newTotal = 0;
            foreach ($_POST['item_qty'] as $itemId => $qty) {
                $qty = max(0, (int)$qty);
                Database::execute("UPDATE production_batch_items SET quantity = ? WHERE id = ? AND production_batch_id = ?", [$qty, (int)$itemId, $batchId]);
                $newTotal += $qty;
            }
            if ($newTotal > 0) {
                $updateData['total_qty'] = $newTotal;
                $updateData['quantity'] = $newTotal;
            }
        }

        ProductionBatch::update($batchId, $updateData);

        // Keep outward in sync if outward exists or destination warehouse / logistics changed
        $transporterName = trim((string)($_POST['transporter_name'] ?? ''));
        $vehicleNumber = trim((string)($_POST['vehicle_number'] ?? ''));
        $lrNumber = trim((string)($_POST['lr_number'] ?? ''));
        $driverName = trim((string)($_POST['driver_name'] ?? ''));
        $driverContact = trim((string)($_POST['driver_contact'] ?? ''));
        $totalQty = $updateData['total_qty'] ?? ($batch['total_qty'] ?? 0);

        Database::execute(
            "UPDATE production_outwards 
             SET warehouse_id = ?, total_boxes = ?, total_quantity = ?,
                 transporter_name = COALESCE(NULLIF(?, ''), transporter_name),
                 vehicle_number = COALESCE(NULLIF(?, ''), vehicle_number),
                 lr_number = COALESCE(NULLIF(?, ''), lr_number),
                 driver_name = COALESCE(NULLIF(?, ''), driver_name),
                 driver_contact = COALESCE(NULLIF(?, ''), driver_contact)
             WHERE production_batch_id = ?",
            [$warehouseId, max(1, $boxCount), $totalQty, $transporterName, $vehicleNumber, $lrNumber, $driverName, $driverContact, $batchId]
        );

        Session::flash('success', 'Production Batch ' . ($updateData['batch_number'] ?? ('#' . $batchId)) . ' updated successfully.');
        Response::redirect(url('production/batches'));
    }

    public function outwardDetailsAjax(string $id): void {
        $outwardId = (int)$id;
        $outward = ProductionOutward::find($outwardId);
        if (!$outward) {
            Response::json(['success' => false, 'message' => 'Outward record not found.'], 404);
            return;
        }
        $batch = !empty($outward['production_batch_id']) ? ProductionBatch::find((int)$outward['production_batch_id']) : null;
        $items = !empty($outward['production_batch_id']) ? ProductionBatch::getItems((int)$outward['production_batch_id']) : [];

        $batches = ProductionOutward::getBatchesForOutward($outwardId);

        // Aggregate items across all batches if multi-batch
        $allMixedItems = [];
        $variantMap = [];
        foreach ($batches as $b) {
            foreach (($b['items'] ?? []) as $it) {
                $vKey = (int)($it['variant_id'] ?? 0);
                if (!isset($variantMap[$vKey])) {
                    $variantMap[$vKey] = $it;
                    $variantMap[$vKey]['quantity'] = 0;
                    $variantMap[$vKey]['outward_piece_tags'] = [];
                    $variantMap[$vKey]['batch_numbers'] = [];
                }
                $variantMap[$vKey]['quantity'] += (int)($it['quantity'] ?? 0);
                $bNum = $b['batch_number'] ?? '';
                if ($bNum && !in_array($bNum, $variantMap[$vKey]['batch_numbers'], true)) {
                    $variantMap[$vKey]['batch_numbers'][] = $bNum;
                }
                $tags = is_array($it['piece_tags'] ?? null) ? $it['piece_tags'] : (json_decode($it['piece_tags'] ?? '', true) ?: []);
                foreach ($tags as $t) {
                    if (!in_array($t, $variantMap[$vKey]['outward_piece_tags'], true)) {
                        $variantMap[$vKey]['outward_piece_tags'][] = $t;
                    }
                }
            }
        }
        $allMixedItems = array_values($variantMap);

        Response::json([
            'success'        => true,
            'outward'        => $outward,
            'batch'          => $batch,
            'items'          => count($batches) > 1 ? $allMixedItems : $items,
            'batches'        => $batches,
            'is_multi_batch' => count($batches) > 1,
            'total_batches'  => count($batches),
        ]);
    }

    public function updateOutward(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $outwardId = (int)$id;
        $outward = ProductionOutward::find($outwardId);
        if (!$outward) {
            Session::flash('error', 'Outward record not found.');
            Response::back();
            return;
        }

        $warehouseId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : (int)($outward['warehouse_id'] ?? 0);
        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
        $driverName = trim($_POST['driver_name'] ?? '');
        $driverContact = trim($_POST['driver_contact'] ?? '');
        $transporterName = trim($_POST['transporter_name'] ?? '');
        $lrNumber = trim($_POST['lr_number'] ?? '');
        $totalBoxes = isset($_POST['total_boxes']) ? (int)$_POST['total_boxes'] : (int)($outward['total_boxes'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $dispatchDate = !empty($_POST['dispatch_date']) ? date('Y-m-d H:i:s', strtotime($_POST['dispatch_date'])) : ($outward['dispatch_date'] ?? date('Y-m-d H:i:s'));

        $updateData = [
            'vehicle_number'   => $vehicleNumber ?: null,
            'driver_name'      => $driverName ?: null,
            'driver_contact'   => $driverContact ?: null,
            'transporter_name' => $transporterName ?: null,
            'lr_number'        => $lrNumber ?: null,
            'total_boxes'      => max(1, $totalBoxes),
            'notes'            => $notes ?: null,
            'dispatch_date'    => $dispatchDate,
        ];

        if ($warehouseId > 0) {
            $updateData['warehouse_id'] = $warehouseId;
            if (!empty($outward['production_batch_id'])) {
                Database::execute("UPDATE production_batches SET destination_warehouse_id = ? WHERE id = ?", [$warehouseId, (int)$outward['production_batch_id']]);
            }
        }

        ProductionOutward::update($outwardId, $updateData);

        Session::flash('success', 'Outward Dispatch ' . ($outward['outward_number'] ?? ('#' . $outwardId)) . ' updated successfully.');
        Response::redirect(url('production/outward'));
    }

    // =========================================================================
    // REWORK FROM WAREHOUSE MODULE
    // =========================================================================

    public function warehouseReworkList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $warehouseId = !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;
        $defectType = trim($_GET['defect_type'] ?? '');

        $filters = array_filter([
            'search'             => $search,
            'status'             => $status,
            'warehouse_id'       => $warehouseId,
            'defect_type'        => $defectType,
            'production_unit_id' => $scopedUnitId,
        ]);

        $reworks = ProductionWarehouseRework::getAll($filters);
        $stats = ProductionWarehouseRework::getStats(['production_unit_id' => $scopedUnitId]);

        $warehouses = Database::fetchAll("SELECT id, name, code FROM warehouses ORDER BY name ASC");
        
        $unitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        $dispatchedBatches = Database::fetchAll(
            "SELECT pb.id, pb.batch_number, pb.total_qty, pb.destination_warehouse_id as warehouse_id,
                    w.name as warehouse_name, COALESCE(p.name, pp.name, 'General Garment') as product_name,
                    p.id as product_id, po.id as outward_id, po.outward_number, po.dispatch_date
             FROM production_batches pb
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
             WHERE pb.status IN ('DISPATCHED', 'RECEIVED', 'APPROVED') $unitClause
             ORDER BY pb.id DESC"
        );

        $productionUnits = Database::fetchAll("SELECT id, name, code FROM production_units WHERE is_active = 1 ORDER BY name ASC");

        $allVariants = Database::fetchAll(
            "SELECT pv.id, pv.sku, p.name as product_name, s.name as size_name, c.name as color_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC LIMIT 500"
        );

        Response::view('production.rework.index', [
            'pageTitle'         => 'Rework from Warehouse',
            'activeModule'      => 'production',
            'activeMenu'        => 'warehouse-rework',
            'reworks'           => $reworks,
            'stats'             => $stats,
            'warehouses'        => $warehouses,
            'dispatchedBatches' => $dispatchedBatches,
            'productionUnits'   => $productionUnits,
            'allVariants'       => $allVariants,
            'filters'           => [
                'search'       => $search,
                'status'       => $status,
                'warehouse_id' => $warehouseId,
                'defect_type'  => $defectType,
            ],
            'scopedUnitId'      => $scopedUnitId,
            'breadcrumb'        => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Rework from Warehouse'],
            ],
        ]);
    }

    public function lookupBatchVariantsAjax(string $batchId): void {
        $bId = (int)$batchId;
        $items = Database::fetchAll(
            "SELECT pbi.variant_id, pbi.quantity, pv.sku, p.name as product_name, p.id as product_id,
                    s.name as size_name, c.name as color_name
             FROM production_batch_items pbi
             JOIN product_variants pv ON pbi.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pbi.production_batch_id = ?",
            [$bId]
        );

        if (empty($items)) {
            // Fallback from program items
            $batch = Database::fetchOne("SELECT program_id FROM production_batches WHERE id = ?", [$bId]);
            if ($batch && !empty($batch['program_id'])) {
                $items = Database::fetchAll(
                    "SELECT ppi.variant_id, ppi.planned_quantity as quantity, pv.sku, p.name as product_name, p.id as product_id,
                            s.name as size_name, c.name as color_name
                     FROM production_program_items ppi
                     JOIN product_variants pv ON ppi.variant_id = pv.id
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors c ON pv.color_id = c.id
                     WHERE ppi.program_id = ?",
                    [(int)$batch['program_id']]
                );
            }
        }

        Response::json([
            'success'  => true,
            'variants' => $items,
        ]);
    }

    public function storeWarehouseRework(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $damageReason = trim($_POST['damage_reason'] ?? '');
        $defectType = trim($_POST['defect_type'] ?? 'STITCHING_ERROR');

        if ($warehouseId <= 0 || $variantId <= 0 || empty($damageReason)) {
            Session::flash('error', 'Please select a warehouse, product variant, and provide the damage reason.');
            Response::back();
        }

        $batchId = !empty($_POST['production_batch_id']) ? (int)$_POST['production_batch_id'] : null;
        $outwardId = !empty($_POST['production_outward_id']) ? (int)$_POST['production_outward_id'] : null;

        if ($batchId && !$outwardId) {
            $po = Database::fetchOne("SELECT id FROM production_outwards WHERE production_batch_id = ?", [$batchId]);
            if ($po) $outwardId = (int)$po['id'];
        }

        $photoUrl = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts, true)) {
                if (class_exists('CloudinaryService')) {
                    $photoUrl = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/production/rework', 'image');
                }
            }
        }

        try {
            $userId = Session::userId() ?? 1;
            $reworkId = ProductionWarehouseRework::create([
                'warehouse_id'          => $warehouseId,
                'production_batch_id'   => $batchId,
                'production_outward_id' => $outwardId,
                'variant_id'            => $variantId,
                'quantity'              => $quantity,
                'defect_type'           => $defectType,
                'damage_reason'         => $damageReason,
                'defect_notes'          => trim($_POST['defect_notes'] ?? ''),
                'challan_number'        => trim($_POST['challan_number'] ?? ''),
                'received_date'         => !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d'),
                'received_by'           => $userId,
                'assigned_to'           => trim($_POST['assigned_to'] ?? ''),
                'production_unit_id'    => !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null,
                'photo_url'             => $photoUrl,
                'status'                => !empty($_POST['start_immediately']) && hasPermission('warehouse_rework', 'edit') ? 'IN_REWORK' : 'RECEIVED',
                'created_by'            => $userId,
            ]);

            Session::flash('success', "Damaged goods return successfully logged for rework (Ref: #{$reworkId}).");
        } catch (Exception $e) {
            Session::flash('error', 'Error logging rework: ' . $e->getMessage());
        }

        Response::redirect(url('production/warehouse-rework'));
    }

    public function updateWarehouseReworkStatus(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::back();
        }

        $reworkId = (int)$id;
        $rework = ProductionWarehouseRework::find($reworkId);
        if (!$rework) {
            Session::flash('error', 'Rework record not found.');
            Response::back();
        }

        $newStatus = trim($_POST['status'] ?? '');
        $assignedTo = trim($_POST['assigned_to'] ?? ($rework['assigned_to'] ?? ''));
        $unitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : ($rework['production_unit_id'] ?? null);
        $notes = trim($_POST['repair_notes'] ?? ($rework['repair_notes'] ?? ''));

        $updateData = [
            'status'             => $newStatus,
            'assigned_to'        => $assignedTo,
            'production_unit_id' => $unitId,
            'repair_notes'       => $notes,
        ];

        ProductionWarehouseRework::update($reworkId, $updateData);
        Session::flash('success', "Rework #{$rework['rework_number']} updated to {$newStatus}.");
        Response::redirect(url('production/warehouse-rework'));
    }

    public function completeWarehouseRework(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::back();
        }

        $reworkId = (int)$id;
        $rework = ProductionWarehouseRework::find($reworkId);
        if (!$rework) {
            Session::flash('error', 'Rework record not found.');
            Response::back();
        }

        $totalQty = (int)$rework['quantity'];
        $repairedQty = max(0, (int)($_POST['repaired_qty'] ?? 0));
        $scrappedQty = max(0, (int)($_POST['scrapped_qty'] ?? 0));
        $repairNotes = trim($_POST['repair_notes'] ?? '');

        if (($repairedQty + $scrappedQty) <= 0) {
            Session::flash('error', 'Please enter a valid repaired or scrapped quantity.');
            Response::back();
        }

        if (($repairedQty + $scrappedQty) > $totalQty) {
            Session::flash('error', "Total repaired ({$repairedQty}) and scrapped ({$scrappedQty}) cannot exceed returned quantity ({$totalQty}).");
            Response::back();
        }

        $finalStatus = ($repairedQty > 0) ? 'REPAIRED' : 'SCRAPPED';

        ProductionWarehouseRework::update($reworkId, [
            'repaired_qty' => $repairedQty,
            'scrapped_qty' => $scrappedQty,
            'status'       => $finalStatus,
            'repair_notes' => $repairNotes,
            'repaired_at'  => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', "Rework #{$rework['rework_number']} completed: {$repairedQty} pcs repaired successfully, {$scrappedQty} pcs scrapped.");
        Response::redirect(url('production/warehouse-rework'));
    }

    public function redispatchWarehouseRework(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::back();
        }

        $reworkId = (int)$id;
        $rework = ProductionWarehouseRework::find($reworkId);
        if (!$rework) {
            Session::flash('error', 'Rework record not found.');
            Response::back();
        }

        $vehicle = trim($_POST['redispatched_vehicle'] ?? 'Direct Courier / Handover');
        $notes = trim($_POST['redispatched_notes'] ?? '');

        ProductionWarehouseRework::update($reworkId, [
            'status'               => 'RE_DISPATCHED',
            'redispatched_date'    => date('Y-m-d H:i:s'),
            'redispatched_vehicle' => $vehicle,
            'redispatched_notes'   => $notes,
        ]);

        Session::flash('success', "Repaired items for Rework #{$rework['rework_number']} re-dispatched to {$rework['warehouse_name']}.");
        Response::redirect(url('production/warehouse-rework'));
    }

    public function viewWarehouseReworkAjax(string $id): void {
        $rework = ProductionWarehouseRework::find((int)$id);
        if (!$rework) {
            Response::json(['success' => false, 'message' => 'Rework record not found.'], 404);
            return;
        }

        Response::json([
            'success' => true,
            'data'    => $rework,
        ]);
    }

    public function deleteWarehouseRework(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::back();
        }

        ProductionWarehouseRework::delete((int)$id);
        Session::flash('success', 'Rework record deleted successfully.');
        Response::redirect(url('production/warehouse-rework'));
    }

    // =========================================================================
    // QUALITY CONTROL (QC) INSPECTION MODULE
    // =========================================================================

    public function qcList(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $batches = QCBatch::getAll([
            'status'             => $status,
            'search'             => $search,
            'production_unit_id' => $scopedUnitId,
        ]);

        Response::view('production.qc.index', [
            'pageTitle'    => 'Quality Control (QC) Inspections',
            'activeModule' => 'production',
            'activeMenu'   => 'qc',
            'batches'      => $batches,
            'filters'      => ['status' => $status, 'search' => $search],
            'scopedUnitId' => $scopedUnitId,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'QC Inspections'],
            ],
        ]);
    }

    public function createQC(): void {
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        $stitchingBatchId = isset($_GET['stitching_batch_id']) ? (int)$_GET['stitching_batch_id'] : null;
        $labelBatchId = isset($_GET['label_batch_id']) ? (int)$_GET['label_batch_id'] : null;

        $unitClause = $scopedUnitId ? " AND pp.production_unit_id = $scopedUnitId" : "";
        $stitchingBatches = Database::fetchAll(
            "SELECT sb.*, pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name 
             FROM stitching_batches sb
             JOIN production_programs pp ON sb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE 1=1$unitClause
               AND sb.id NOT IN (SELECT stitching_batch_id FROM qc_batches WHERE stitching_batch_id IS NOT NULL)
               AND (sb.program_id IS NULL OR sb.program_id NOT IN (SELECT program_id FROM qc_batches WHERE program_id IS NOT NULL))
             ORDER BY sb.created_at DESC"
        );

        $labelBatches = Database::fetchAll(
            "SELECT lb.*, pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name
             FROM label_batches lb
             JOIN production_programs pp ON lb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             WHERE 1=1$unitClause
               AND lb.id NOT IN (SELECT label_batch_id FROM qc_batches WHERE label_batch_id IS NOT NULL)
               AND (lb.program_id IS NULL OR lb.program_id NOT IN (SELECT program_id FROM qc_batches WHERE program_id IS NOT NULL))
             ORDER BY lb.created_at DESC"
        );

        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");

        $selectedStitching = null;
        $selectedLabel = null;
        $items = [];
        $distinctCatalogs = [];
        $catalogHierarchy = 'General Apparel';
        $rolls = [];

        if ($stitchingBatchId) {
            $alreadyQC = Database::fetchOne(
                "SELECT id FROM qc_batches WHERE stitching_batch_id = ?",
                [$stitchingBatchId]
            );
            if ($alreadyQC) {
                Session::flash('error', "Stitching Batch has already undergone QC Inspection (QC #{$alreadyQC['id']}). Duplicate QC inspection is not allowed.");
                Response::redirect(url('production/qc'));
                return;
            }
            $selectedStitching = StitchingBatch::find($stitchingBatchId);
            if ($selectedStitching) {
                $stitchingItems = StitchingBatch::getItems($stitchingBatchId);
                $rolls = StitchingBatch::getRolls($stitchingBatchId);

                $linkedLabelBatch = Database::fetchOne(
                    "SELECT id FROM label_batches WHERE stitching_batch_id = ? ORDER BY id DESC LIMIT 1",
                    [$stitchingBatchId]
                );
                $labelItemsMap = [];
                if ($linkedLabelBatch) {
                    $lItems = LabelBatch::getItems((int)$linkedLabelBatch['id']);
                    foreach ($lItems as $li) {
                        $k = strtolower(trim($li['size_name'] ?? '')) . '_' . strtolower(trim($li['color_name'] ?? ''));
                        $labelItemsMap[$k] = $li;
                    }
                }

                foreach ($stitchingItems as $si) {
                    $k = strtolower(trim($si['display_size'] ?? $si['size_name'] ?? '')) . '_' . strtolower(trim($si['display_color'] ?? $si['color_name'] ?? ''));
                    $matchedLabel = $labelItemsMap[$k] ?? null;

                    $items[] = [
                        'variant_id'         => !empty($si['variant_id']) ? $si['variant_id'] : null,
                        'category_id'        => !empty($si['category_id']) ? (int)$si['category_id'] : null,
                        'subcategory_id'     => !empty($si['subcategory_id']) ? (int)$si['subcategory_id'] : null,
                        'sub_subcategory_id' => !empty($si['sub_subcategory_id']) ? (int)$si['sub_subcategory_id'] : null,
                        'catalog_hierarchy'  => $si['catalog_hierarchy'] ?? null,
                        'category_name'      => $si['category_name'] ?? null,
                        'subcategory_name'   => $si['subcategory_name'] ?? null,
                        'sub_subcategory_name'=> $si['sub_subcategory_name'] ?? null,
                        'size_name'          => $si['display_size'] ?? ($si['size_name'] ?? 'Standard'),
                        'color_name'         => $si['display_color'] ?? ($si['color_name'] ?? 'Assorted'),
                        'base_price'         => (float)($si['base_price'] ?? 1000.00),
                        'discount_price'     => (float)($si['discount_price'] ?? 500.00),
                        'stitched_qty'       => (int)($si['stitched_qty'] ?? 0),
                        'sku'                => $si['sku'] ?? (($si['size_name'] ?? '') . '-' . ($si['color_name'] ?? '')),
                        'start_seq'          => $matchedLabel['start_seq'] ?? null,
                        'end_seq'            => $matchedLabel['end_seq'] ?? null,
                        'start_code'         => $matchedLabel['start_code'] ?? null,
                        'end_code'           => $matchedLabel['end_code'] ?? null,
                    ];
                }
                $hierarchyParts = array_filter([
                    $selectedStitching['category_name'] ?? '',
                    $selectedStitching['subcategory_name'] ?? '',
                    $selectedStitching['sub_subcategory_name'] ?? ''
                ]);
                $catalogHierarchy = !empty($hierarchyParts) ? implode(' › ', $hierarchyParts) : ($selectedStitching['product_name'] ?? 'General Apparel');

                $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
                    'name'               => $catalogHierarchy,
                    'category_id'        => $selectedStitching['category_id'] ?? null,
                    'subcategory_id'     => $selectedStitching['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $selectedStitching['sub_subcategory_id'] ?? null,
                ], 'stitched_qty');
            }
        } elseif ($labelBatchId) {
            $alreadyQC = Database::fetchOne(
                "SELECT id FROM qc_batches WHERE label_batch_id = ?",
                [$labelBatchId]
            );
            if ($alreadyQC) {
                Session::flash('error', "Label Batch has already undergone QC Inspection (QC #{$alreadyQC['id']}). Duplicate QC inspection is not allowed.");
                Response::redirect(url('production/qc'));
                return;
            }
            $selectedLabel = LabelBatch::find($labelBatchId);
            if ($selectedLabel) {
                $labelItems = LabelBatch::getItems($labelBatchId);
                $rolls = !empty($selectedLabel['stitching_batch_id']) ? StitchingBatch::getRolls((int)$selectedLabel['stitching_batch_id']) : (!empty($selectedLabel['program_id']) ? ProductionProgram::getRolls((int)$selectedLabel['program_id']) : []);
                foreach ($labelItems as $li) {
                    $items[] = [
                        'variant_id'         => !empty($li['variant_id']) ? $li['variant_id'] : null,
                        'category_id'        => !empty($li['category_id']) ? (int)$li['category_id'] : null,
                        'subcategory_id'     => !empty($li['subcategory_id']) ? (int)$li['subcategory_id'] : null,
                        'sub_subcategory_id' => !empty($li['sub_subcategory_id']) ? (int)$li['sub_subcategory_id'] : null,
                        'catalog_hierarchy'  => $li['catalog_hierarchy'] ?? null,
                        'category_name'      => $li['category_name'] ?? null,
                        'subcategory_name'   => $li['subcategory_name'] ?? null,
                        'sub_subcategory_name'=> $li['sub_subcategory_name'] ?? null,
                        'size_name'          => $li['display_size'] ?? ($li['size_name'] ?? 'Standard'),
                        'color_name'         => $li['display_color'] ?? ($li['color_name'] ?? 'Assorted'),
                        'base_price'         => (float)($li['base_price'] ?? 1000.00),
                        'discount_price'     => (float)($li['discount_price'] ?? 500.00),
                        'stitched_qty'       => (int)($li['required_qty'] ?? 0),
                        'sku'                => $li['sku'] ?? (($li['size_name'] ?? '') . '-' . ($li['color_name'] ?? '')),
                        'start_seq'          => $li['start_seq'] ?? null,
                        'end_seq'            => $li['end_seq'] ?? null,
                        'start_code'         => $li['start_code'] ?? null,
                        'end_code'           => $li['end_code'] ?? null,
                    ];
                }
                $hierarchyParts = array_filter([
                    $selectedLabel['category_name'] ?? '',
                    $selectedLabel['subcategory_name'] ?? '',
                    $selectedLabel['sub_subcategory_name'] ?? ''
                ]);
                $catalogHierarchy = !empty($hierarchyParts) ? implode(' › ', $hierarchyParts) : ($selectedLabel['product_name'] ?? 'General Apparel');

                $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
                    'name'               => $catalogHierarchy,
                    'category_id'        => $selectedLabel['category_id'] ?? null,
                    'subcategory_id'     => $selectedLabel['subcategory_id'] ?? null,
                    'sub_subcategory_id' => $selectedLabel['sub_subcategory_id'] ?? null,
                ], 'stitched_qty');
            }
        }

        Response::view('production.qc.create', [
            'pageTitle'         => 'Initialize QC Inspection Batch',
            'activeModule'      => 'production',
            'activeMenu'        => 'qc',
            'stitchingBatches'  => $stitchingBatches,
            'labelBatches'      => $labelBatches,
            'selectedStitching' => $selectedStitching,
            'selectedLabel'     => $selectedLabel,
            'users'             => $users,
            'items'             => $items,
            'catalogHierarchy'  => $catalogHierarchy,
            'distinctCatalogs'  => array_values($distinctCatalogs),
            'breadcrumb'        => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'QC Inspections', 'url' => url('production/qc')],
                ['label' => 'New QC Inspection Batch'],
            ],
        ]);
    }

    public function storeQC(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $stitchingBatchId = !empty($_POST['stitching_batch_id']) ? (int)$_POST['stitching_batch_id'] : null;
        $labelBatchId = !empty($_POST['label_batch_id']) ? (int)$_POST['label_batch_id'] : null;
        $inspectorId = !empty($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : null;
        if (!$inspectorId) {
            $user = Session::get('user');
            $inspectorId = !empty($user['id']) ? (int)$user['id'] : 1;
        }
        $notes = trim($_POST['notes'] ?? '');

        $programId = null;

        if ($stitchingBatchId) {
            $sb = StitchingBatch::find($stitchingBatchId);
            if ($sb) $programId = $sb['program_id'];
        } elseif ($labelBatchId) {
            $lb = LabelBatch::find($labelBatchId);
            if ($lb) $programId = $lb['program_id'];
        }

        if (!$programId) {
            Session::flash('error', 'Please select a valid Stitching or Label Batch.');
            Response::back();
        }

        // Prevent duplicate QC inspection
        if (!empty($stitchingBatchId)) {
            $existingQC = Database::fetchOne(
                "SELECT id FROM qc_batches WHERE stitching_batch_id = ?",
                [$stitchingBatchId]
            );
            if ($existingQC) {
                $sb = StitchingBatch::find($stitchingBatchId);
                $sbNum = $sb['batch_number'] ?? ('#' . $stitchingBatchId);
                Session::flash('error', "Validation Error: Stitching Batch ({$sbNum}) has already undergone QC Inspection (QC #{$existingQC['id']}). Duplicate QC inspection is not allowed.");
                Response::back();
                return;
            }
        }

        if (!empty($labelBatchId)) {
            $existingQC = Database::fetchOne(
                "SELECT id FROM qc_batches WHERE label_batch_id = ?",
                [$labelBatchId]
            );
            if ($existingQC) {
                $lb = LabelBatch::find($labelBatchId);
                $lbNum = $lb['batch_number'] ?? ('#' . $labelBatchId);
                Session::flash('error', "Validation Error: Label Batch ({$lbNum}) has already undergone QC Inspection (QC #{$existingQC['id']}). Duplicate QC inspection is not allowed.");
                Response::back();
                return;
            }
        }

        if (!empty($programId)) {
            $existingQCProg = Database::fetchOne(
                "SELECT id FROM qc_batches WHERE program_id = ?",
                [$programId]
            );
            if ($existingQCProg) {
                $prog = ProductionProgram::find($programId);
                $progNum = $prog['program_number'] ?? ('#' . $programId);
                Session::flash('error', "Validation Error: Production Program ({$progNum}) has already undergone QC Inspection (QC #{$existingQCProg['id']}). Duplicate QC inspection is not allowed.");
                Response::back();
                return;
            }
        }

        try {
            $postedItems = $_POST['items'] ?? [];
            if (empty($postedItems)) {
                Session::flash('error', 'No items provided for QC inspection.');
                Response::back();
            }

            $totalQty = 0;
            foreach ($postedItems as $it) {
                $totalQty += (int)($it['inspected_qty'] ?? $it['stitched_qty'] ?? 0);
            }

            $qcBatchId = QCBatch::create([
                'program_id'         => $programId,
                'stitching_batch_id' => $stitchingBatchId,
                'label_batch_id'     => $labelBatchId,
                'inspector_id'       => $inspectorId,
                'total_qty'          => $totalQty,
                'passed_qty'         => 0,
                'rejected_qty'       => 0,
                'rework_qty'         => 0,
                'status'             => 'PENDING',
                'notes'              => $notes,
            ]);

            foreach ($postedItems as $idx => $it) {
                $inspected = (int)($it['inspected_qty'] ?? $it['stitched_qty'] ?? 0);
                $passed = isset($it['passed_qty']) ? (int)$it['passed_qty'] : 0;
                $rejected = (int)($it['rejected_qty'] ?? 0);
                $rework = (int)($it['rework_qty'] ?? 0);

                QCItem::create([
                    'qc_batch_id'        => $qcBatchId,
                    'variant_id'         => !empty($it['variant_id']) ? (int)$it['variant_id'] : null,
                    'category_id'        => !empty($it['category_id']) ? (int)$it['category_id'] : null,
                    'subcategory_id'     => !empty($it['subcategory_id']) ? (int)$it['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($it['sub_subcategory_id']) ? (int)$it['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => !empty($it['catalog_hierarchy']) ? trim($it['catalog_hierarchy']) : null,
                    'size_name'          => $it['size_name'] ?? 'Standard',
                    'color_name'         => $it['color_name'] ?? 'Assorted',
                    'tag_code'           => !empty($it['start_code']) ? $it['start_code'] : ($it['tag_code'] ?? null),
                    'base_price'         => (float)($it['base_price'] ?? 1000.00),
                    'discount_price'     => (float)($it['discount_price'] ?? 500.00),
                    'inspected_qty'      => $inspected,
                    'passed_qty'         => $passed,
                    'rejected_qty'       => $rejected,
                    'rework_qty'         => $rework,
                    'defect_reason'      => $it['defect_reason'] ?? null,
                    'defect_notes'       => $it['defect_notes'] ?? null,
                ]);
            }

            QCBatch::recalculateTotals($qcBatchId);

            Session::flash('success', 'QC Inspection batch created successfully.');
            Response::redirect(url('production/qc/inspect/' . $qcBatchId));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function deleteQC(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $batchId = (int)$id;
        $batch = QCBatch::find($batchId);
        if (!$batch) {
            Session::flash('error', 'QC Inspection batch not found.');
            Response::back();
            return;
        }

        try {
            QCBatch::delete($batchId);
            Session::flash('success', 'QC Inspection Batch #' . $batchId . ' deleted successfully.');
            Response::redirect(url('production/qc'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    public function inspectQC(string $id): void {
        $batchId = (int)$id;
        $batch = QCBatch::find($batchId);

        if (!$batch) {
            Response::error(404, 'QC Inspection Batch not found.');
            return;
        }

        $items = QCBatch::getItems($batchId);
        $lockedPieces = $this->getQCLockedPieceStates($batch, $items);
        $users = Database::fetchAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC");

        $rolls = !empty($batch['stitching_batch_id']) ? StitchingBatch::getRolls((int)$batch['stitching_batch_id']) : (!empty($batch['program_id']) ? ProductionProgram::getRolls((int)$batch['program_id']) : []);

        $hierarchyParts = array_filter([
            $batch['category_name'] ?? '',
            $batch['subcategory_name'] ?? '',
            $batch['sub_subcategory_name'] ?? ''
        ]);
        $catalogHierarchy = !empty($hierarchyParts) ? implode(' › ', $hierarchyParts) : ($batch['product_name'] ?? 'General Apparel');

        $distinctCatalogs = $this->resolveItemsCatalogHierarchy($items, $rolls, [
            'name'               => $catalogHierarchy,
            'category_id'        => $batch['program_category_id'] ?? null,
            'subcategory_id'     => $batch['program_subcategory_id'] ?? null,
            'sub_subcategory_id' => $batch['program_sub_subcategory_id'] ?? null,
        ], 'inspected_qty');

        Response::view('production.qc.inspect', [
            'pageTitle'        => 'QC Inspection Workstation — Batch #' . $batchId,
            'activeModule'     => 'production',
            'activeMenu'       => 'qc',
            'batch'            => $batch,
            'items'            => $items,
            'lockedPieces'     => $lockedPieces,
            'users'            => $users,
            'catalogHierarchy' => $catalogHierarchy,
            'distinctCatalogs' => array_values($distinctCatalogs),
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'QC Inspections', 'url' => url('production/qc')],
                ['label' => 'Inspect Batch #' . $batchId],
            ],
        ]);
    }

    private function getQCLockedPieceStates(array $batch, array $items): array {
        $outwardRow = Database::fetchOne(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(po.total_quantity, 0), NULLIF(po.quantity, 0), pb.total_qty, 0)), 0) as total_outward
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             WHERE pb.qc_batch_id = ? OR (pb.qc_batch_id IS NULL AND pb.program_id = ? AND pb.program_id > 0)",
            [(int)$batch['id'], (int)($batch['program_id'] ?? 0)]
        );
        $outwardQty = (int)($outwardRow['total_outward'] ?? 0);
        if ($outwardQty <= 0) {
            return [];
        }

        $savedPiecesMap = [];
        $decodedPieces = json_decode((string)($batch['pieces_data'] ?? ''), true);
        if (!is_array($decodedPieces)) {
            $decodedPieces = json_decode(html_entity_decode((string)($batch['pieces_data'] ?? ''), ENT_QUOTES, 'UTF-8'), true);
        }
        if (!is_array($decodedPieces)) {
            $decodedPieces = json_decode(stripslashes((string)($batch['pieces_data'] ?? '')), true);
        }
        if (is_array($decodedPieces)) {
            foreach ($decodedPieces as $piece) {
                if (is_array($piece) && isset($piece['index'])) {
                    $savedPiecesMap[(int)$piece['index']] = $piece;
                }
            }
        }

        $lockedPieces = [];
        $pieceIndex = 1;
        $approvedIndex = 0;
        foreach ($items as $item) {
            $inspectedQty = max(1, (int)$item['inspected_qty']);
            $passedQty = (int)($item['passed_qty'] ?? 0);
            $rejectedQty = (int)($item['rejected_qty'] ?? 0);
            $reworkQty = (int)($item['rework_qty'] ?? 0);

            for ($pieceNumber = 1; $pieceNumber <= $inspectedQty; $pieceNumber++, $pieceIndex++) {
                $savedPiece = $savedPiecesMap[$pieceIndex] ?? null;
                if ($savedPiece !== null) {
                    $status = strtoupper((string)($savedPiece['status'] ?? 'PENDING'));
                } elseif ($pieceNumber <= $passedQty) {
                    $status = 'APPROVED';
                } elseif ($pieceNumber <= $passedQty + $rejectedQty) {
                    $status = 'REJECTED';
                } elseif ($pieceNumber <= $passedQty + $rejectedQty + $reworkQty) {
                    $status = 'REWORK';
                } else {
                    $status = 'PENDING';
                }

                if ($status !== 'APPROVED') {
                    continue;
                }

                $approvedIndex++;
                if ($approvedIndex > $outwardQty) {
                    continue;
                }
                $tagCode = trim((string)($savedPiece['tag_code'] ?? ''));
                $lockKey = $tagCode !== '' ? $tagCode : $pieceIndex;
                $lockedPieces[$lockKey] = [
                    'status' => 'APPROVED',
                    'is_reinspected' => !empty($savedPiece['is_reinspected']),
                    'defect_reason' => $savedPiece['defect_reason'] ?? '',
                    'defect_notes' => $savedPiece['defect_notes'] ?? '',
                    'outwarded' => $approvedIndex <= $outwardQty,
                    'item_id' => (int)$item['id'],
                    'tag_code' => $tagCode,
                    'index' => $pieceIndex,
                ];
            }
        }

        return $lockedPieces;
    }

    private function protectLockedQCPieces(array $piecesData, array $lockedPieces): array {
        if (empty($lockedPieces)) {
            return $piecesData;
        }

        $piecesByIndex = [];
        foreach ($piecesData as $piece) {
            if (is_array($piece) && isset($piece['index'])) {
                $piecesByIndex[(int)$piece['index']] = $piece;
            }
        }

        foreach ($lockedPieces as $key => $lockedPiece) {
            if (empty($lockedPiece['outwarded'])) {
                continue;
            }
            $index = null;
            if ($lockedPiece['tag_code'] !== '') {
                foreach ($piecesByIndex as $pieceIndex => $candidate) {
                    if (trim((string)($candidate['tag_code'] ?? '')) === $lockedPiece['tag_code']) {
                        $index = $pieceIndex;
                        break;
                    }
                }
                if ($index === null) {
                    continue;
                }
            } else {
                $index = (int)($lockedPiece['index'] ?? $key);
            }

            $piece = $piecesByIndex[$index] ?? [
                'index' => $index,
                'item_id' => $lockedPiece['item_id'],
                'tag_code' => $lockedPiece['tag_code'],
            ];
            $piece['index'] = $index;
            $piece['item_id'] = $lockedPiece['item_id'];
            if ($lockedPiece['tag_code'] !== '') {
                $piece['tag_code'] = $lockedPiece['tag_code'];
            }
            $piece['status'] = $lockedPiece['status'];
            $piece['is_reinspected'] = $lockedPiece['is_reinspected'];
            $piece['defect_reason'] = $lockedPiece['defect_reason'];
            $piece['defect_notes'] = $lockedPiece['defect_notes'];
            $piecesByIndex[$index] = $piece;
        }

        ksort($piecesByIndex);
        return array_values($piecesByIndex);
    }

    private function saveQCItemTotalsFromPieces(array $items, array $piecesData): void {
        $totals = [];
        foreach ($items as $item) {
            $totals[(int)$item['id']] = [
                'inspected_qty' => 0,
                'passed_qty' => 0,
                'rejected_qty' => 0,
                'rework_qty' => 0,
                'defect_reason' => null,
                'defect_notes' => null,
            ];
        }

        foreach ($piecesData as $piece) {
            if (!is_array($piece)) {
                continue;
            }
            $itemId = (int)($piece['item_id'] ?? 0);
            if (!isset($totals[$itemId])) {
                continue;
            }
            $itemTotals = &$totals[$itemId];
            $itemTotals['inspected_qty']++;
            $status = strtoupper((string)($piece['status'] ?? 'PENDING'));
            if ($status === 'APPROVED' || $status === 'PASSED') {
                $itemTotals['passed_qty']++;
            } elseif ($status === 'REJECTED') {
                $itemTotals['rejected_qty']++;
            } elseif ($status === 'REWORK') {
                $itemTotals['rework_qty']++;
            }
            if (empty($itemTotals['defect_reason']) && !empty($piece['defect_reason'])) {
                $itemTotals['defect_reason'] = trim((string)$piece['defect_reason']);
            }
            if (empty($itemTotals['defect_notes']) && !empty($piece['defect_notes'])) {
                $itemTotals['defect_notes'] = trim((string)$piece['defect_notes']);
            }
            unset($itemTotals);
        }

        foreach ($totals as $itemId => $itemTotals) {
            QCItem::update($itemId, $itemTotals);
        }
    }

    public function updateQC(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $batchId = (int)$id;
        $batch = QCBatch::find($batchId);

        if (!$batch) {
            Response::error(404, 'QC Inspection Batch not found.');
            return;
        }

        try {
            $inspectorId = !empty($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : null;
            if (!$inspectorId) {
                $user = Session::get('user');
                $inspectorId = !empty($user['id']) ? (int)$user['id'] : (!empty($batch['inspector_id']) ? (int)$batch['inspector_id'] : 1);
            }
            $notes = trim($_POST['notes'] ?? '');
            
            $items = QCBatch::getItems($batchId);
            $lockedPieces = $this->getQCLockedPieceStates($batch, $items);
            $rawPieces = $_POST['pieces_data'] ?? null;
            $piecesData = null;
            $decodedPieces = null;
            if (!empty($rawPieces)) {
                $decoded = json_decode($rawPieces, true);
                if (!is_array($decoded)) {
                    $decoded = json_decode(html_entity_decode($rawPieces, ENT_QUOTES, 'UTF-8'), true);
                }
                if (!is_array($decoded)) {
                    $decoded = json_decode(stripslashes($rawPieces), true);
                }
                if (is_array($decoded)) {
                    $decodedPieces = $this->protectLockedQCPieces($decoded, $lockedPieces);
                    $piecesData = json_encode($decodedPieces, JSON_UNESCAPED_UNICODE);
                }
            }

            // Also check $_POST['items'] fallback if provided by aggregatePieceCounts
            $postedItems = $_POST['items'] ?? [];
            if (!empty($postedItems) && is_array($postedItems)) {
                $validItemIds = array_column($items, 'id');
                foreach ($postedItems as $pItemId => $pItemVals) {
                    if (in_array((int)$pItemId, $validItemIds)) {
                        QCItem::update((int)$pItemId, [
                            'inspected_qty' => (int)($pItemVals['inspected_qty'] ?? 0),
                            'passed_qty'    => (int)($pItemVals['passed_qty'] ?? 0),
                            'rejected_qty'  => (int)($pItemVals['rejected_qty'] ?? 0),
                            'rework_qty'    => (int)($pItemVals['rework_qty'] ?? 0),
                            'defect_reason' => !empty($pItemVals['defect_reason']) ? trim($pItemVals['defect_reason']) : null,
                            'defect_notes'  => !empty($pItemVals['defect_notes']) ? trim($pItemVals['defect_notes']) : null,
                        ]);
                    }
                }
            }

            $updateData = [
                'inspector_id' => $inspectorId,
                'notes'        => $notes,
            ];
            if ($piecesData !== null) {
                $updateData['pieces_data'] = $piecesData;
            }
            QCBatch::update($batchId, $updateData);

            if ($decodedPieces !== null) {
                $this->saveQCItemTotalsFromPieces($items, $decodedPieces);
            }

            $result = QCBatch::recalculateTotals($batchId);
            $this->syncQCToStitching($batchId);

            Session::flash('success', 'QC inspection results saved successfully. Status: ' . $result['status']);
            Response::redirect(url('production/qc'));
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            Response::back();
        }
    }

    /**
     * AJAX auto-save endpoint for QC inspection workstation.
     */
    public function saveQCAjax(string $id): void {
        $batchId = (int)$id;
        $batch = QCBatch::find($batchId);

        if (!$batch) {
            Response::json(['success' => false, 'message' => 'QC Inspection Batch not found.'], 404);
            return;
        }

        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if (!is_array($data)) {
                $data = $_POST;
            }

            $items = QCBatch::getItems($batchId);
            $lockedPieces = $this->getQCLockedPieceStates($batch, $items);
            $rawPieces = $data['pieces_data'] ?? null;
            $piecesData = null;
            $decodedPieces = null;
            if (!empty($rawPieces)) {
                if (is_array($rawPieces)) {
                    $decodedPieces = $rawPieces;
                } else {
                    $decoded = json_decode($rawPieces, true);
                    if (!is_array($decoded)) {
                        $decoded = json_decode(html_entity_decode($rawPieces, ENT_QUOTES, 'UTF-8'), true);
                    }
                    if (!is_array($decoded)) {
                        $decoded = json_decode(stripslashes($rawPieces), true);
                    }
                    if (is_array($decoded)) {
                        $decodedPieces = $decoded;
                    }
                }
            }

            if ($decodedPieces !== null) {
                $decodedPieces = $this->protectLockedQCPieces($decodedPieces, $lockedPieces);
                $piecesData = json_encode($decodedPieces, JSON_UNESCAPED_UNICODE);
            }

            if ($piecesData !== null) {
                QCBatch::update($batchId, [
                    'pieces_data' => $piecesData,
                ]);
                $this->saveQCItemTotalsFromPieces($items, $decodedPieces);
            }

            $result = QCBatch::recalculateTotals($batchId);
            $this->syncQCToStitching($batchId);

            Response::json([
                'success' => true,
                'message' => 'QC inspection auto-saved.',
                'totals'  => $result,
            ]);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Synchronize QC inspection defect counts (rejected_qty, rework_qty)
     * back to stitching_batches and stitching_items.
     */
    private function syncQCToStitching(int $qcBatchId): void {
        $qcBatch = Database::fetchOne(
            "SELECT qb.*, COALESCE(qb.stitching_batch_id, lb.stitching_batch_id) as actual_stitching_id 
             FROM qc_batches qb
             LEFT JOIN label_batches lb ON qb.label_batch_id = lb.id
             WHERE qb.id = ?",
            [$qcBatchId]
        );
        if (!$qcBatch || empty($qcBatch['actual_stitching_id'])) {
            return;
        }
        $stitchingBatchId = (int)$qcBatch['actual_stitching_id'];

        // Sum total rejected and rework for this stitching batch
        $totals = Database::fetchOne(
            "SELECT COALESCE(SUM(qb.rejected_qty), 0) as total_rejected,
                    COALESCE(SUM(qb.rework_qty), 0) as total_rework
             FROM qc_batches qb
             LEFT JOIN label_batches lb ON qb.label_batch_id = lb.id
             WHERE COALESCE(qb.stitching_batch_id, lb.stitching_batch_id) = ?",
            [$stitchingBatchId]
        );

        if ($totals) {
            Database::execute(
                "UPDATE stitching_batches 
                 SET rejected_qty = ?, rework_qty = ?
                 WHERE id = ?",
                [(int)$totals['total_rejected'], (int)$totals['total_rework'], $stitchingBatchId]
            );
        }

        // Sync item-level rejected and rework
        $qcItems = Database::fetchAll(
            "SELECT qi.variant_id, qi.size_name, qi.color_name,
                    SUM(qi.rejected_qty) as item_rejected,
                    SUM(qi.rework_qty) as item_rework
             FROM qc_items qi
             JOIN qc_batches qb ON qi.qc_batch_id = qb.id
             LEFT JOIN label_batches lb ON qb.label_batch_id = lb.id
             WHERE COALESCE(qb.stitching_batch_id, lb.stitching_batch_id) = ?
             GROUP BY qi.variant_id, qi.size_name, qi.color_name",
            [$stitchingBatchId]
        );

        foreach ($qcItems as $qci) {
            if (!empty($qci['variant_id'])) {
                Database::execute(
                    "UPDATE stitching_items 
                     SET rejected_qty = ?, rework_qty = ?
                     WHERE stitching_batch_id = ? AND variant_id = ?",
                    [(int)$qci['item_rejected'], (int)$qci['item_rework'], $stitchingBatchId, (int)$qci['variant_id']]
                );
            } else {
                Database::execute(
                    "UPDATE stitching_items 
                     SET rejected_qty = ?, rework_qty = ?
                     WHERE stitching_batch_id = ? AND LOWER(TRIM(COALESCE(size_name, ''))) = LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(color_name, ''))) = LOWER(TRIM(?))",
                    [(int)$qci['item_rejected'], (int)$qci['item_rework'], $stitchingBatchId, (string)$qci['size_name'], (string)$qci['color_name']]
                );
            }
        }
    }

    // =========================================================================
    // PRODUCTION INVENTORY (POST-QC PASSED PRODUCTS)
    // =========================================================================

    /**
     * Production Inventory — Dynamically lists all finished products passed through QC inspection.
     */
    /**
     * Production Inventory — Dynamically lists all finished products passed through QC inspection,
     * deducting outward dispatches and showing real-time remaining stock & outward history.
     */
    public function productionInventory(): void {
        $search = trim($_GET['search'] ?? '');
        $categoryFilter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

        $sql = "SELECT qi.*, 
                       qb.program_id, 
                       qb.inspector_id,
                       qb.created_at as batch_created_at,
                       pp.program_number, 
                       pp.name as program_name,
                       COALESCE(p.id, pp.product_id) as product_id,
                       COALESCE(p.name, pp.name) as product_name, 
                       p.code as product_sku,
                       p.image_url as product_image,
                       COALESCE(c_p.name, c_pp.name) as category_name,
                       COALESCE(p.category_id, pp.category_id) as category_id,
                       u.full_name as inspector_name
                FROM qc_items qi
                JOIN qc_batches qb ON qi.qc_batch_id = qb.id
                JOIN production_programs pp ON qb.program_id = pp.id
                LEFT JOIN products p ON pp.product_id = p.id
                LEFT JOIN categories c_p ON p.category_id = c_p.id
                LEFT JOIN categories c_pp ON pp.category_id = c_pp.id
                LEFT JOIN users u ON qb.inspector_id = u.id
                WHERE (qi.passed_qty > 0 OR qi.rework_qty > 0 OR qi.rejected_qty > 0)";
        
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (
                p.name LIKE ? 
                OR p.code LIKE ? 
                OR pp.name LIKE ? 
                OR pp.program_number LIKE ? 
                OR qi.size_name LIKE ? 
                OR qi.color_name LIKE ? 
                OR qi.tag_code LIKE ? 
                OR c_p.name LIKE ? 
                OR c_pp.name LIKE ? 
                OR CAST(qb.id AS CHAR) LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        if ($categoryFilter) {
            $sql .= " AND (p.category_id = ? OR pp.category_id = ?)";
            $params[] = $categoryFilter;
            $params[] = $categoryFilter;
        }

        $sql .= " ORDER BY qb.created_at DESC, qi.id DESC";

        $items = Database::fetchAll($sql, $params);

        $deletedCodeRows = Database::fetchAll("SELECT code FROM production_deleted_label_codes");
        $deletedCodes = array_column($deletedCodeRows, 'code');
        $deletedCountsByBatch = [];
        if (!empty($deletedCodes)) {
            foreach (array_unique(array_map(static fn(array $item): int => (int)$item['qc_batch_id'], $items)) as $qcBatchId) {
                $deletedCountsByBatch[$qcBatchId] = ProductionService::getDeletedQCPieceCounts($qcBatchId, $deletedCodes);
            }
        }

        // Fetch all outward dispatches to link with batches/programs
        $allOutwards = Database::fetchAll(
            "SELECT po.id as outward_id,
                    po.outward_number,
                    po.dispatch_date,
                    po.status as outward_status,
                    COALESCE(po.total_quantity, po.quantity, pb.total_qty, 0) as outward_qty,
                    COALESCE(po.total_boxes, pb.box_count, 0) as total_boxes,
                    pb.id as production_batch_id,
                    pb.batch_number,
                    pb.status as batch_status,
                    pb.qc_batch_id,
                    pb.program_id,
                    w.name as warehouse_name,
                    w.code as warehouse_code,
                    po.vehicle_number,
                    po.driver_name,
                    po.transporter_name,
                    po.lr_number
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON (po.warehouse_id = w.id OR pb.destination_warehouse_id = w.id)
             ORDER BY po.created_at DESC"
        );

        // Map outwards by qc_batch_id and program_id
        $outwardsByQC = [];
        $outwardsByProgram = [];
        foreach ($allOutwards as $ow) {
            if (!empty($ow['qc_batch_id'])) {
                $outwardsByQC[(int)$ow['qc_batch_id']][] = $ow;
            }
            if (!empty($ow['program_id'])) {
                $outwardsByProgram[(int)$ow['program_id']][] = $ow;
            }
        }

        // Group items by QC batch
        $batches = [];
        foreach ($items as $it) {
            $batchId = $it['qc_batch_id'];
            if (!isset($batches[$batchId])) {
                $batches[$batchId] = [
                    'qc_batch_id'         => $batchId,
                    'program_id'          => $it['program_id'],
                    'program_number'      => $it['program_number'],
                    'product_name'        => $it['product_name'] ?? $it['program_name'] ?? 'Finished Product',
                    'category_name'       => $it['category_name'] ?? '',
                    'category_id'         => $it['category_id'] ?? null,
                    'inspector_name'      => $it['inspector_name'],
                    'batch_created_at'    => $it['batch_created_at'],
                    'total_passed_pieces' => 0,
                    'total_rework_pieces' => 0,
                    'total_rejected_pieces' => 0,
                    'total_pieces'        => 0,
                    'total_value'         => 0.0,
                    'variants'            => [],
                    'variant_indexes'     => [],
                ];
            }
            $passedQty = (int)$it['passed_qty'];
            $reworkQty = (int)($it['rework_qty'] ?? 0);
            $rejectedQty = (int)($it['rejected_qty'] ?? 0);
            $price = (float)(($it['discount_price'] ?? 0) > 0 ? $it['discount_price'] : ($it['base_price'] ?? 0));
            $deletedQty = (int)($deletedCountsByBatch[(int)$batchId][(int)$it['id']] ?? 0);
            $inventoryPassedQty = max(0, $passedQty - $deletedQty);
            $batches[$batchId]['total_passed_pieces'] += $passedQty;
            $batches[$batchId]['total_rework_pieces'] += $reworkQty;
            $batches[$batchId]['total_rejected_pieces'] += $rejectedQty;
            $batches[$batchId]['total_pieces']        += $inventoryPassedQty;
            $batches[$batchId]['total_value']         += ($inventoryPassedQty * $price);
            if ($inventoryPassedQty > 0) {
                $variantKey = strtolower(trim((string)$it['size_name'])) . '|' . strtolower(trim((string)$it['color_name']));
                if (!isset($batches[$batchId]['variant_indexes'][$variantKey])) {
                    $batches[$batchId]['variant_indexes'][$variantKey] = count($batches[$batchId]['variants']);
                    $batches[$batchId]['variants'][] = [
                        'size_name'  => $it['size_name'],
                        'color_name' => $it['color_name'],
                        'passed_qty' => 0,
                        'outward_qty' => 0,
                        'qc_items'   => [],
                    ];
                }
                $variantIndex = $batches[$batchId]['variant_indexes'][$variantKey];
                $batches[$batchId]['variants'][$variantIndex]['passed_qty'] += $inventoryPassedQty;
                $batches[$batchId]['variants'][$variantIndex]['qc_items'][] = [
                    'qc_item_id' => (int)$it['id'],
                    'passed_qty' => $inventoryPassedQty,
                ];
            }
        }

        // Calculate Outward Deductions, Remaining In-Stock & Outward Status for each batch
        foreach ($batches as $bId => &$batch) {
            $progId = (int)($batch['program_id'] ?? 0);
            
            // Collect matching outward records
            $matchingOutwards = [];
            $seenOutwardIds = [];
            if (!empty($outwardsByQC[$bId])) {
                foreach ($outwardsByQC[$bId] as $ow) {
                    if (!isset($seenOutwardIds[$ow['outward_id']])) {
                        $matchingOutwards[] = $ow;
                        $seenOutwardIds[$ow['outward_id']] = true;
                    }
                }
            }
            if (!empty($outwardsByProgram[$progId])) {
                foreach ($outwardsByProgram[$progId] as $ow) {
                    if (!isset($seenOutwardIds[$ow['outward_id']])) {
                        $matchingOutwards[] = $ow;
                        $seenOutwardIds[$ow['outward_id']] = true;
                    }
                }
            }

            $totalOutwardQty = 0;
            foreach ($matchingOutwards as $ow) {
                $totalOutwardQty += (int)$ow['outward_qty'];
            }

            $passedPcs = (int)$batch['total_pieces'];
            $outwardPcs = min($passedPcs, $totalOutwardQty);
            $remainingPcs = max(0, $passedPcs - $outwardPcs);
            
            $unitAvgPrice = $passedPcs > 0 ? ($batch['total_value'] / $passedPcs) : 0;
            $remainingVal = max(0.0, $remainingPcs * $unitAvgPrice);

            $batch['total_outward_pieces'] = $outwardPcs;
            $batch['remaining_pieces']       = $remainingPcs;
            $batch['remaining_value']        = $remainingVal;
            $batch['total_passed_value']     = $batch['total_value'];
            $batch['outward_history']        = $passedPcs > 0 ? $matchingOutwards : [];
            $batch['outward_count']          = count($batch['outward_history']);

            $variants = array_values($batch['variants']);
            $variantPieces = [];
            foreach ($variants as $variantIndex => $variant) {
                foreach ($variant['qc_items'] as $qcItem) {
                    $variantPieces[] = [
                        'qc_item_id' => $qcItem['qc_item_id'],
                        'variant_index' => $variantIndex,
                        'passed_qty' => $qcItem['passed_qty'],
                    ];
                }
            }
            usort($variantPieces, static fn(array $left, array $right): int => $left['qc_item_id'] <=> $right['qc_item_id']);
            $remainingOutwardQty = $outwardPcs;
            foreach ($variantPieces as $variantPiece) {
                $outwardForItem = min((int)$variantPiece['passed_qty'], $remainingOutwardQty);
                $variants[$variantPiece['variant_index']]['outward_qty'] += $outwardForItem;
                $remainingOutwardQty -= $outwardForItem;
            }
            foreach ($variants as &$variant) {
                $variant['remaining_qty'] = (int)$variant['passed_qty'] - $variant['outward_qty'];
                unset($variant['qc_items']);
            }
            unset($variant);
            $batch['variants'] = $variants;
            unset($batch['variant_indexes']);

            if ($passedPcs === 0) {
                $batch['stock_status'] = 'NO_PASSED_STOCK';
            } elseif ($remainingPcs === 0) {
                $batch['stock_status'] = 'FULLY_OUTWARDED';
            } elseif ($outwardPcs > 0 && $remainingPcs > 0) {
                $batch['stock_status'] = 'PARTIALLY_OUTWARDED';
            } else {
                $batch['stock_status'] = 'IN_STOCK';
            }
        }
        unset($batch);

        // Overall stats reflecting remaining inventory & outward history
        $stats = [
            'total_remaining_pieces'    => array_sum(array_column($batches, 'remaining_pieces')),
            'total_remaining_valuation' => array_sum(array_column($batches, 'remaining_value')),
            'total_outwarded_pieces'    => array_sum(array_column($batches, 'total_outward_pieces')),
            'total_passed_pieces'       => array_sum(array_column($batches, 'total_passed_pieces')),
            'total_rework_pieces'       => array_sum(array_column($batches, 'total_rework_pieces')),
            'total_rejected_pieces'     => array_sum(array_column($batches, 'total_rejected_pieces')),
            'total_passed_valuation'    => array_sum(array_column($batches, 'total_passed_value')),
            'unique_batches_count'      => count($batches),
            'in_stock_batches_count'    => count(array_filter($batches, fn($b) => ($b['remaining_pieces'] ?? 0) > 0)),
            'unique_products_count'     => count(array_unique(array_filter(array_column($items, 'product_id')))),
        ];

        // Fetch categories for filter dropdown
        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
        if (empty($categories)) {
            $categories = Database::fetchAll("SELECT id, name FROM categories ORDER BY name ASC");
        }

        Response::view('production.inventory.index', [
            'pageTitle'      => 'Production Inventory',
            'activeModule'   => 'production',
            'activeMenu'     => 'prod-inventory',
            'batches'        => array_values($batches),
            'stats'          => $stats,
            'categories'     => $categories,
            'search'         => $search,
            'categoryFilter' => $categoryFilter,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'QC Inspections', 'url' => url('production/qc')],
                ['label' => 'Inventory'],
            ],
        ]);
    }

    /**
     * Show detailed piece-by-piece inventory for a single QC batch,
     * with real-time remaining stock calculation and full outward dispatch history.
     */
    public function productionInventoryBatch(int $id): void {
        $sql = "SELECT qi.*, 
                       qb.program_id, 
                       qb.inspector_id,
                       qb.created_at as batch_created_at,
                       qb.pieces_data,
                       pp.program_number, 
                       p.id as product_id,
                       p.name as product_name, 
                       p.code as product_sku,
                       p.image_url as product_image,
                       COALESCE(c_qi.name, c.name, c_pp.name) as category_name,
                       COALESCE(sc_qi.name, sc.name, sc_pp.name) as subcategory_name,
                       COALESCE(ssc_qi.name, ssc.name, ssc_pp.name) as sub_subcategory_name,
                       c_qi.code as qi_cat_code,
                       sc_qi.code as qi_sub_code,
                       ssc_qi.code as qi_subsub_code,
                       li.start_seq as label_start_seq,
                       li.end_seq as label_end_seq,
                       li.start_code as label_start_code,
                       li.end_code as label_end_code,
                       u.full_name as inspector_name
                FROM qc_items qi
                JOIN qc_batches qb ON qi.qc_batch_id = qb.id
                JOIN production_programs pp ON qb.program_id = pp.id
                LEFT JOIN products p ON pp.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN categories c_pp ON pp.category_id = c_pp.id
                LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
                LEFT JOIN subcategories sc_pp ON pp.subcategory_id = sc_pp.id
                LEFT JOIN sub_subcategories ssc ON p.sub_subcategory_id = ssc.id
                LEFT JOIN sub_subcategories ssc_pp ON pp.sub_subcategory_id = ssc_pp.id
                LEFT JOIN categories c_qi ON qi.category_id = c_qi.id
                LEFT JOIN subcategories sc_qi ON qi.subcategory_id = sc_qi.id
                LEFT JOIN sub_subcategories ssc_qi ON qi.sub_subcategory_id = ssc_qi.id
                LEFT JOIN users u ON qb.inspector_id = u.id
                LEFT JOIN label_items li ON (
                    (qb.label_batch_id IS NOT NULL AND li.label_batch_id = qb.label_batch_id AND (
                        (qi.variant_id IS NOT NULL AND li.variant_id = qi.variant_id)
                        OR (LOWER(TRIM(li.size_name)) = LOWER(TRIM(qi.size_name)) AND LOWER(TRIM(li.color_name)) = LOWER(TRIM(qi.color_name)))
                    ))
                    OR (qb.label_batch_id IS NULL AND qb.stitching_batch_id IS NOT NULL AND li.label_batch_id = (
                        SELECT lb_sub.id FROM label_batches lb_sub WHERE lb_sub.stitching_batch_id = qb.stitching_batch_id ORDER BY lb_sub.id DESC LIMIT 1
                    ) AND (
                        (qi.variant_id IS NOT NULL AND li.variant_id = qi.variant_id)
                        OR (LOWER(TRIM(li.size_name)) = LOWER(TRIM(qi.size_name)) AND LOWER(TRIM(li.color_name)) = LOWER(TRIM(qi.color_name)))
                    ))
                )
                WHERE qi.qc_batch_id = ?
                  AND (qi.passed_qty > 0 OR qi.rework_qty > 0 OR qi.rejected_qty > 0)
                ORDER BY qi.id ASC";

        $qcItems = Database::fetchAll($sql, [$id]);

        if (empty($qcItems)) {
            Response::redirect(url('production/inventory'), 'error', 'Batch not found or has no recorded QC outcome.');
            return;
        }

        $deletedCodeRows = Database::fetchAll("SELECT code FROM production_deleted_label_codes");
        $deletedCodes = array_fill_keys(array_map(
            static fn(array $row): string => strtoupper((string)$row['code']),
            $deletedCodeRows
        ), true);

        $first = $qcItems[0];
        $qcOutcomeItems = array_values(array_filter($qcItems, static function (array $item): bool {
            return (int)($item['rework_qty'] ?? 0) > 0 || (int)($item['rejected_qty'] ?? 0) > 0;
        }));
        $totalReworkPieces = array_sum(array_map(static fn(array $item): int => (int)($item['rework_qty'] ?? 0), $qcItems));
        $totalRejectedPieces = array_sum(array_map(static fn(array $item): int => (int)($item['rejected_qty'] ?? 0), $qcItems));
        $items = array_values(array_filter($qcItems, static function (array $item): bool {
            return (int)($item['passed_qty'] ?? 0) > 0;
        }));
        $programId = (int)($first['program_id'] ?? 0);

        // Fetch outward dispatch history for this QC batch / program
        $outwards = empty($items) ? [] : Database::fetchAll(
            "SELECT po.*,
                    pb.batch_number,
                    pb.box_count as batch_box_count,
                    pb.status as batch_status,
                    COALESCE(po.total_quantity, po.quantity, pb.total_qty, 0) as outward_qty,
                    COALESCE(po.total_boxes, pb.box_count, 0) as total_boxes,
                    w.name as warehouse_name,
                    w.code as warehouse_code,
                    u.full_name as dispatched_by_name,
                    pu.name as unit_name
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON (po.warehouse_id = w.id OR pb.destination_warehouse_id = w.id)
             LEFT JOIN production_units pu ON po.production_unit_id = pu.id
             LEFT JOIN users u ON po.created_by = u.id
             WHERE pb.qc_batch_id = ? OR (pb.program_id = ? AND pb.program_id > 0)
             ORDER BY po.created_at DESC",
            [$id, $programId]
        );

        $totalOutwardPieces = 0;
        foreach ($outwards as $ow) {
            $totalOutwardPieces += (int)$ow['outward_qty'];
        }

        $getPieceCatalogAbbr = function($it, $first) {
            if (!empty($it['label_start_code']) && preg_match('/^([A-Za-z]+)/', $it['label_start_code'], $m)) {
                return strtoupper($m[1]);
            }
            if (!empty($it['qr_code_prefix']) && strlen(trim($it['qr_code_prefix'])) <= 5) {
                return strtoupper(trim($it['qr_code_prefix']));
            }
            $c1 = trim($it['category_name'] ?? '');
            $c2 = trim($it['subcategory_name'] ?? '');
            $c3 = trim($it['sub_subcategory_name'] ?? '');
            $abbr = '';
            if (!empty($c1)) $abbr .= strtoupper(mb_substr($c1, 0, 1));
            if (!empty($c2)) $abbr .= strtoupper(mb_substr($c2, 0, 1));
            if (!empty($c3)) $abbr .= strtoupper(mb_substr($c3, 0, 1));
            if (strlen($abbr) === 3) return $abbr;

            $hierarchy = $it['catalog_hierarchy'] ?? '';
            if (!empty($hierarchy)) {
                $parts = array_filter(preg_split('/›|>|\||\-/', $hierarchy));
                if (!empty($parts)) {
                    $abbr = '';
                    foreach ($parts as $part) {
                        $words = preg_split('/\s+/', trim($part));
                        if (!empty($words[0])) $abbr .= strtoupper(mb_substr($words[0], 0, 1));
                    }
                    if (!empty($abbr)) return substr($abbr, 0, 3);
                }
            }
            return 'BSH';
        };

        // Decode saved pieces data if available
        $savedPiecesMap = [];
        if (!empty($first['pieces_data'])) {
            $raw = $first['pieces_data'];
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);
            }
            if (!is_array($decoded)) {
                $decoded = json_decode(stripslashes($raw), true);
            }
            if (is_array($decoded)) {
                foreach ($decoded as $dp) {
                    if (!empty($dp['item_id'])) {
                        $savedPiecesMap[(int)$dp['item_id']][] = $dp;
                    }
                }
            }
        }

        $availableTagsByItem = ProductionService::getAvailableQCPieceTags($id);
        $availableCodesByItem = [];
        foreach ($availableTagsByItem as $itemId => $tags) {
            $availableCodesByItem[(int)$itemId] = array_fill_keys(
                array_map(static fn($tag): string => strtoupper(trim((string)$tag)), $tags),
                true
            );
        }

        // Build piece-by-piece records
        $pieceItems = [];
        $globalPieceIndex = 1;
        $batchPieceSeq = 1;
        $totalPieces = 0;
        $totalValue  = 0.0;

        foreach ($items as $it) {
            $passedQty = (int)$it['passed_qty'];
            $price = (float)($it['discount_price'] > 0 ? $it['discount_price'] : $it['base_price']);

            $batchDate = new DateTime($it['batch_created_at'] ?? 'now');
            $monthStr  = $batchDate->format('m');
            $yearStr   = $batchDate->format('y');
            $catalogPrefix = $getPieceCatalogAbbr($it, $first);
            $startSeq = !empty($it['label_start_seq']) ? (int)$it['label_start_seq'] : null;

            $codePrefixAndDate = null;
            if (!empty($it['label_start_code']) && preg_match('/^([A-Za-z]+\d{4})/', $it['label_start_code'], $m)) {
                $codePrefixAndDate = strtoupper($m[1]);
            }

            $itemSavedPieces = $savedPiecesMap[(int)$it['id']] ?? [];
            $passedSavedPieces = array_values(array_filter($itemSavedPieces, function($p) {
                return ($p['status'] ?? '') === 'APPROVED';
            }));

            $hParts = array_filter([
                $it['category_name'] ?? '',
                $it['subcategory_name'] ?? '',
                $it['sub_subcategory_name'] ?? ''
            ]);
            $catHierarchy = !empty($hParts) ? implode(' > ', $hParts) : ($it['category_name'] ?? 'General');

            for ($p = 1; $p <= $passedQty; $p++) {
                if (isset($passedSavedPieces[$p - 1]['tag_code']) && !empty($passedSavedPieces[$p - 1]['tag_code'])) {
                    $pieceTagCode = $passedSavedPieces[$p - 1]['tag_code'];
                } elseif ($codePrefixAndDate && $startSeq !== null) {
                    $seqVal = $startSeq + ($p - 1);
                    $pieceTagCode = $codePrefixAndDate . sprintf("%02d", $seqVal);
                } elseif ($startSeq !== null) {
                    $seqVal = $startSeq + ($p - 1);
                    $pieceTagCode = $catalogPrefix . $monthStr . $yearStr . sprintf("%02d", $seqVal);
                } else {
                    $pieceTagCode = $catalogPrefix . $monthStr . $yearStr . sprintf("%02d", $batchPieceSeq);
                }
                $batchPieceSeq++;

                $pieceNo = $globalPieceIndex++;
                if (isset($deletedCodes[strtoupper($pieceTagCode)])) {
                    continue;
                }

                $totalPieces++;
                $totalValue += $price;
                $isDispatched = ($pieceNo <= $totalOutwardPieces);
                $pieceOutwardInfo = null;
                if ($isDispatched && !empty($outwards)) {
                    $pieceOutwardInfo = $outwards[0]['outward_number'] ?? 'OUTWARDED';
                }

                $pieceItems[] = [
                    'piece_no'        => $pieceNo,
                    'piece_seq'       => $p,
                    'passed_in_group' => $passedQty,
                    'qc_item_id'      => $it['id'],
                    'qc_batch_id'     => $it['qc_batch_id'],
                    'product_id'      => $it['product_id'],
                    'product_name'    => $it['product_name'],
                    'product_sku'     => $it['product_sku'] ?? '',
                    'sku'             => $it['product_sku'] ?? '',
                    'product_image'   => $it['product_image'] ?? null,
                    'category_name'   => !empty($it['catalog_hierarchy']) ? $it['catalog_hierarchy'] : $catHierarchy,
                    'size_name'       => $it['size_name'] ?? 'Std',
                    'color_name'      => $it['color_name'] ?? 'Std',
                    'tag_code'        => $pieceTagCode,
                    'base_price'      => (float)($it['base_price'] ?? 0),
                    'discount_price'  => (float)($it['discount_price'] ?? 0),
                    'program_id'      => $it['program_id'] ?? null,
                    'program_number'  => $it['program_number'] ?? 'N/A',
                    'inspector_name'  => $it['inspector_name'] ?? 'Inspector',
                    'batch_created_at'=> $it['batch_created_at'] ?? null,
                    'is_dispatched'   => $isDispatched,
                    'can_delete'      => !$isDispatched && isset($availableCodesByItem[(int)$it['id']][strtoupper($pieceTagCode)]),
                    'outward_info'    => $pieceOutwardInfo,
                ];
            }
        }

        $effectiveOutwardPieces = min($totalPieces, $totalOutwardPieces);
        $remainingPieces = max(0, $totalPieces - $effectiveOutwardPieces);
        $avgPiecePrice = $totalPieces > 0 ? ($totalValue / $totalPieces) : 0;
        $remainingValue = max(0.0, $remainingPieces * $avgPiecePrice);

        Response::view('production.inventory.batch', [
            'pageTitle'           => 'QC Batch #' . $id . ' — Inventory Detail',
            'activeModule'        => 'production',
            'activeMenu'          => 'prod-inventory',
            'qcBatchId'           => $id,
            'batchInfo'           => $first,
            'pieceItems'          => $pieceItems,
            'rawItems'            => $items,
            'qcOutcomeItems'      => $qcOutcomeItems,
            'totalReworkPieces'   => $totalReworkPieces,
            'totalRejectedPieces' => $totalRejectedPieces,
            'totalPieces'         => $totalPieces,
            'totalValue'          => $totalValue,
            'totalOutwardPieces'  => $effectiveOutwardPieces,
            'remainingPieces'     => $remainingPieces,
            'remainingValue'      => $remainingValue,
            'outwards'            => $outwards,
            'breadcrumb'          => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Inventory', 'url' => url('production/inventory')],
                ['label' => 'QC Batch #' . $id],
            ],
        ]);
    }

    // =========================================================================
    // PRODUCTION REPORTS & ANALYTICS
    // =========================================================================

    public function reports(): void {
        // Summary KPIs
        $totalPrograms = Database::fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(planned_quantity),0) as planned, COALESCE(SUM(completed_quantity),0) as completed FROM production_programs");
        $totalCutting = Database::fetchOne("SELECT COALESCE(SUM(cut_qty),0) as cut, COALESCE(SUM(rejected_qty),0) as rejected, COALESCE(SUM(wastage_qty),0) as wastage FROM cutting_batches");
        $totalQC = Database::fetchOne("SELECT COALESCE(SUM(inspected_qty),0) as inspected, COALESCE(SUM(passed_qty),0) as passed, COALESCE(SUM(rejected_qty),0) as rejected, COALESCE(SUM(rework_qty),0) as rework FROM qc_items");
        
        // Efficiency by production unit
        $unitStats = Database::fetchAll(
            "SELECT pu.name as unit_name,
                    COUNT(pp.id) as program_count,
                    COALESCE(SUM(pp.planned_quantity), 0) as total_planned,
                    COALESCE(SUM(pp.completed_quantity), 0) as total_completed
             FROM production_units pu
             LEFT JOIN production_programs pp ON pu.id = pp.production_unit_id
             WHERE pu.is_active = 1
             GROUP BY pu.id, pu.name"
        );

        // Stage breakdown
        $stageCounts = Database::fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM production_programs WHERE status = 'DRAFT') as draft_programs,
                (SELECT COUNT(*) FROM production_programs WHERE status IN ('APPROVED', 'SCHEDULED')) as scheduled_programs,
                (SELECT COUNT(*) FROM cutting_batches WHERE status IN ('PENDING', 'IN_PROGRESS')) as active_cutting,
                (SELECT COUNT(*) FROM stitching_batches WHERE status IN ('PENDING', 'IN_PROGRESS')) as active_stitching,
                (SELECT COUNT(*) FROM label_batches WHERE status IN ('PENDING', 'IN_PROGRESS')) as active_labels,
                (SELECT COUNT(*) FROM qc_batches WHERE status IN ('PENDING', 'PARTIAL', 'REWORK')) as active_qc,
                (SELECT COUNT(*) FROM production_batches WHERE status = 'APPROVED') as ready_batches"
        );

        Response::view('production.reports.index', [
            'pageTitle'     => 'Production Analytics & Reports',
            'activeModule'  => 'production',
            'activeMenu'    => 'reports',
            'totalPrograms' => $totalPrograms,
            'totalCutting'  => $totalCutting,
            'totalQC'       => $totalQC,
            'unitStats'     => $unitStats,
            'stageCounts'   => $stageCounts,
            'breadcrumb'    => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Analytics & Reports'],
            ],
        ]);
    }

    // =========================================================================
    // FABRIC & CUTTING WASTAGE SELLING MANAGEMENT
    // =========================================================================

    public function wastageList(): void {
        $search = trim($_GET['search'] ?? '');
        $type = trim($_GET['type'] ?? '');
        $batchFilter = trim($_GET['cutting_batch_id'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $sql = "SELECT ws.*, 
                       cb.batch_number as cutting_batch_number,
                       cb.name as cutting_batch_name,
                       cb.wastage_weight as batch_total_wastage,
                       cb.wastage_cost as batch_wastage_cost,
                       pp.program_number,
                       pp.name as program_name,
                       u.full_name as created_by_name
                FROM wastage_sales ws
                LEFT JOIN cutting_batches cb ON ws.cutting_batch_id = cb.id
                LEFT JOIN production_programs pp ON ws.program_id = pp.id
                LEFT JOIN users u ON ws.created_by = u.id
                WHERE 1=1";
        
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                ws.sale_number LIKE ?
                OR ws.buyer_name LIKE ?
                OR ws.buyer_company LIKE ?
                OR ws.buyer_phone LIKE ?
                OR ws.invoice_number LIKE ?
                OR ws.payment_reference LIKE ?
                OR cb.batch_number LIKE ?
                OR pp.program_number LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s]);
        }

        if ($type !== '') {
            $sql .= " AND ws.wastage_type = ?";
            $params[] = $type;
        }

        if ($batchFilter !== '') {
            $sql .= " AND ws.cutting_batch_id = ?";
            $params[] = (int)$batchFilter;
        }

        if ($dateFrom !== '') {
            $sql .= " AND ws.sale_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND ws.sale_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY ws.sale_date DESC, ws.id DESC";
        $sales = Database::fetchAll($sql, $params);

        // Overall summary stats
        $allSales = Database::fetchAll("SELECT weight, total_amount, paid_amount, payment_status, wastage_type FROM wastage_sales");
        $totalWeight = (float)array_sum(array_column($allSales, 'weight'));
        $totalAmount = (float)array_sum(array_column($allSales, 'total_amount'));
        $totalPaid = (float)array_sum(array_column($allSales, 'paid_amount'));
        $totalPending = max(0.0, $totalAmount - $totalPaid);
        $avgRate = $totalWeight > 0 ? ($totalAmount / $totalWeight) : 0.0;

        $stats = [
            'total_records'   => count($allSales),
            'total_weight'    => $totalWeight,
            'total_amount'    => $totalAmount,
            'total_paid'      => $totalPaid,
            'total_pending'   => $totalPending,
            'avg_rate_per_kg' => $avgRate,
            'count_paid'      => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PAID')),
            'count_partial'   => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PARTIAL')),
            'count_pending'   => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PENDING')),
        ];

        // Fetch cutting module batches with wastage details, program info, and remaining balance
        $cuttingBatches = Database::fetchAll("
            SELECT cb.id, cb.batch_number, cb.name, cb.program_id, cb.seller_roll_id,
                   cb.roll_weight, cb.roll_price, cb.wastage_weight, cb.wastage_cost, cb.used_weight,
                   pp.program_number, pp.name as program_name,
                   s.name as seller_name,
                   sr.color, sr.design_name,
                   COALESCE(sold.sold_weight, 0.0) as sold_weight,
                   GREATEST(0.0, cb.wastage_weight - COALESCE(sold.sold_weight, 0.0)) as available_wastage_weight
            FROM cutting_batches cb
            LEFT JOIN production_programs pp ON cb.program_id = pp.id
            LEFT JOIN seller_rolls sr ON cb.seller_roll_id = sr.id
            LEFT JOIN sellers s ON sr.seller_id = s.id
            LEFT JOIN (
                SELECT cutting_batch_id, SUM(weight) as sold_weight
                FROM wastage_sales
                WHERE cutting_batch_id IS NOT NULL
                GROUP BY cutting_batch_id
            ) sold ON cb.id = sold.cutting_batch_id
            ORDER BY cb.id DESC
        ");

        $programs = Database::fetchAll("SELECT id, program_number, name FROM production_programs ORDER BY id DESC LIMIT 50");

        Response::view('production.wastage.index', [
            'pageTitle'      => 'Fabric & Cutting Wastage Selling',
            'activeModule'   => 'production',
            'activeMenu'     => 'wastage-selling',
            'sales'          => $sales,
            'stats'          => $stats,
            'cuttingBatches' => $cuttingBatches,
            'programs'       => $programs,
            'search'         => $search,
            'type'           => $type,
            'cuttingBatchId' => $batchFilter,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Wastage Selling'],
            ],
        ]);
    }

    public function storeWastage(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $cuttingBatchId = !empty($_POST['cutting_batch_id']) ? (int)$_POST['cutting_batch_id'] : null;
        if (!$cuttingBatchId || $cuttingBatchId <= 0) {
            Session::flash('error', 'Select Cutting Module Wastage / Scrap Batch is required.');
            Response::back();
            return;
        }

        $buyerName = trim($_POST['buyer_name'] ?? '');
        if (empty($buyerName)) {
            Session::flash('error', 'Scrap Buyer / Vendor Name is required.');
            Response::back();
            return;
        }

        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        if (empty($buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile is required.');
            Response::back();
            return;
        }
        if (!preg_match('/^[0-9]{10}$/', $buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile must be exactly 10 digits.');
            Response::back();
            return;
        }

        $buyerCompany = trim($_POST['buyer_company'] ?? '');
        if (empty($buyerCompany)) {
            Session::flash('error', 'Buyer Company / Business Name is required.');
            Response::back();
            return;
        }

        $wastageType = trim($_POST['wastage_type'] ?? '');
        if (empty($wastageType)) {
            Session::flash('error', 'Wastage Category is required.');
            Response::back();
            return;
        }

        $weight = (float)($_POST['weight'] ?? 0.0);
        $ratePerKg = (float)($_POST['rate_per_kg'] ?? 0.0);
        $totalAmountInput = (float)($_POST['total_amount'] ?? 0.0);

        if ($weight <= 0) {
            Session::flash('error', 'Wastage Weight must be greater than 0.');
            Response::back();
            return;
        }

        // Validate cutting batch available wastage balance
        $cb = Database::fetchOne("SELECT id, batch_number, name, program_id, wastage_weight FROM cutting_batches WHERE id = ?", [$cuttingBatchId]);
        if (!$cb) {
            Session::flash('error', 'Selected cutting batch not found.');
            Response::back();
            return;
        }

        $soldRow = Database::fetchOne(
            "SELECT COALESCE(SUM(weight), 0.0) as sold_weight FROM wastage_sales WHERE cutting_batch_id = ?",
            [$cuttingBatchId]
        );
        $alreadySold = (float)($soldRow['sold_weight'] ?? 0.0);
        $totalWastage = (float)($cb['wastage_weight'] ?? 0.0);
        $availableWastage = max(0.0, $totalWastage - $alreadySold);

        if ($availableWastage <= 0.001) {
            Session::flash('error', "Cutting Batch [{$cb['batch_number']}] wastage is ALREADY FULLY SOLD ({$alreadySold} kg sold of {$totalWastage} kg total). Cannot record another sale.");
            Response::back();
            return;
        }

        if ($weight > $availableWastage + 0.001) {
            Session::flash('error', "Entered weight ({$weight} kg) exceeds available wastage balance ({$availableWastage} kg) for Batch [{$cb['batch_number']}].");
            Response::back();
            return;
        }

        if ($ratePerKg <= 0 && $totalAmountInput <= 0) {
            Session::flash('error', 'Selling Price & Quantity (Manual Entry) is required. Please enter a valid Selling Rate (₹/kg) or Total Sale Price.');
            Response::back();
            return;
        }

        if ($totalAmountInput <= 0 && $ratePerKg > 0) {
            $totalAmount = round($weight * $ratePerKg, 2);
        } elseif ($ratePerKg <= 0 && $totalAmountInput > 0) {
            $ratePerKg = round($totalAmountInput / $weight, 2);
            $totalAmount = round($totalAmountInput, 2);
        } else {
            $totalAmount = round($totalAmountInput, 2);
            $ratePerKg = round($ratePerKg, 2);
        }

        if (!isset($_POST['paid_amount']) || trim((string)$_POST['paid_amount']) === '') {
            Session::flash('error', 'Amount Received (₹) is required (enter 0 if unpaid).');
            Response::back();
            return;
        }
        $paidAmount = (float)$_POST['paid_amount'];
        if ($paidAmount < 0) {
            Session::flash('error', 'Amount Received cannot be negative.');
            Response::back();
            return;
        }
        if ($paidAmount > $totalAmount) {
            Session::flash('error', 'Amount Received cannot exceed Total Sale Price (₹' . number_format($totalAmount, 2) . ').');
            Response::back();
            return;
        }

        $paymentStatus = trim($_POST['payment_status'] ?? '');
        if (empty($paymentStatus) || !in_array($paymentStatus, ['PAID', 'PARTIAL', 'PENDING'], true)) {
            $paymentStatus = ($paidAmount >= $totalAmount && $totalAmount > 0) ? 'PAID' : (($paidAmount > 0) ? 'PARTIAL' : 'PENDING');
        }

        $paymentMethod = trim($_POST['payment_method'] ?? '');
        if (empty($paymentMethod) || !in_array($paymentMethod, ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE', 'CREDIT'], true)) {
            $paymentMethod = 'CASH';
        }

        $saleDate = trim($_POST['sale_date'] ?? '');
        if (empty($saleDate)) {
            Session::flash('error', 'Sale Date is required.');
            Response::back();
            return;
        }

        $paymentReference = !empty($_POST['payment_reference']) ? trim($_POST['payment_reference']) : null;
        $invoiceNumber = !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        if ($cuttingBatchId && !$programId) {
            $cb = Database::fetchOne("SELECT program_id FROM cutting_batches WHERE id = ?", [$cuttingBatchId]);
            if ($cb && !empty($cb['program_id'])) {
                $programId = (int)$cb['program_id'];
            }
        }

        // Handle photo / bill upload (Cloudinary Cloud Storage)
        $photoUrl = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoUrl = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/wastage', 'image');
        }

        // Generate sequential sale number e.g. WST-2026-0001
        $count = Database::fetchOne("SELECT COUNT(*) as cnt FROM wastage_sales");
        $nextSeq = (int)($count['cnt'] ?? 0) + 1;
        $saleNumber = 'WST-' . date('Y') . '-' . sprintf("%04d", $nextSeq);

        $userId = (int)(Session::get('user_id') ?? 1);

        try {
            Database::execute(
                "INSERT INTO wastage_sales 
                    (sale_number, buyer_name, buyer_phone, buyer_company, cutting_batch_id, program_id, wastage_type, weight, rate_per_kg, total_amount, paid_amount, payment_status, payment_method, payment_reference, invoice_number, sale_date, photo_url, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $saleNumber,
                    $buyerName,
                    $buyerPhone,
                    $buyerCompany,
                    $cuttingBatchId,
                    $programId,
                    $wastageType,
                    $weight,
                    $ratePerKg,
                    $totalAmount,
                    $paidAmount,
                    $paymentStatus,
                    $paymentMethod,
                    $paymentReference ?: null,
                    $invoiceNumber ?: null,
                    $saleDate,
                    $photoUrl,
                    $notes ?: null,
                    $userId
                ]
            );
            $saleId = (int)Database::lastInsertId();

            AuditService::log('production', 'create', 'wastage_sales', $saleId, null, [
                'sale_number'  => $saleNumber,
                'buyer_name'   => $buyerName,
                'weight'       => $weight,
                'total_amount' => $totalAmount
            ]);

            Session::flash('success', "Wastage sale {$saleNumber} recorded successfully for ₹" . number_format($totalAmount, 2));
            Response::redirect(url('production/wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error recording wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function updateWastage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $saleId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM wastage_sales WHERE id = ?", [$saleId]);
        if (!$existing) {
            Session::flash('error', 'Wastage sale record not found.');
            Response::back();
            return;
        }

        $cuttingBatchId = !empty($_POST['cutting_batch_id']) ? (int)$_POST['cutting_batch_id'] : null;
        if (!$cuttingBatchId || $cuttingBatchId <= 0) {
            Session::flash('error', 'Select Cutting Module Wastage / Scrap Batch is required.');
            Response::back();
            return;
        }

        $buyerName = trim($_POST['buyer_name'] ?? '');
        if (empty($buyerName)) {
            Session::flash('error', 'Scrap Buyer / Vendor Name is required.');
            Response::back();
            return;
        }

        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        if (empty($buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile is required.');
            Response::back();
            return;
        }
        if (!preg_match('/^[0-9]{10}$/', $buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile must be exactly 10 digits.');
            Response::back();
            return;
        }

        $buyerCompany = trim($_POST['buyer_company'] ?? '');
        if (empty($buyerCompany)) {
            Session::flash('error', 'Buyer Company / Business Name is required.');
            Response::back();
            return;
        }

        $wastageType = trim($_POST['wastage_type'] ?? '');
        if (empty($wastageType)) {
            Session::flash('error', 'Wastage Category is required.');
            Response::back();
            return;
        }

        $weight = (float)($_POST['weight'] ?? 0.0);
        $ratePerKg = (float)($_POST['rate_per_kg'] ?? 0.0);
        $totalAmountInput = (float)($_POST['total_amount'] ?? 0.0);

        if ($weight <= 0) {
            Session::flash('error', 'Wastage Weight must be greater than 0.');
            Response::back();
            return;
        }

        // Validate cutting batch available wastage balance (excluding current sale being updated)
        $cb = Database::fetchOne("SELECT id, batch_number, name, program_id, wastage_weight FROM cutting_batches WHERE id = ?", [$cuttingBatchId]);
        if (!$cb) {
            Session::flash('error', 'Selected cutting batch not found.');
            Response::back();
            return;
        }

        $soldRow = Database::fetchOne(
            "SELECT COALESCE(SUM(weight), 0.0) as sold_weight FROM wastage_sales WHERE cutting_batch_id = ? AND id != ?",
            [$cuttingBatchId, $saleId]
        );
        $alreadySold = (float)($soldRow['sold_weight'] ?? 0.0);
        $totalWastage = (float)($cb['wastage_weight'] ?? 0.0);
        $availableWastage = max(0.0, $totalWastage - $alreadySold);

        if ($availableWastage <= 0.001) {
            Session::flash('error', "Cutting Batch [{$cb['batch_number']}] wastage is ALREADY FULLY SOLD by other transactions ({$alreadySold} kg sold of {$totalWastage} kg total). Cannot update sale.");
            Response::back();
            return;
        }

        if ($weight > $availableWastage + 0.001) {
            Session::flash('error', "Entered weight ({$weight} kg) exceeds available wastage balance ({$availableWastage} kg) for Batch [{$cb['batch_number']}].");
            Response::back();
            return;
        }

        if ($ratePerKg <= 0 && $totalAmountInput <= 0) {
            Session::flash('error', 'Selling Price & Quantity (Manual Entry) is required. Please enter a valid Selling Rate (₹/kg) or Total Sale Price.');
            Response::back();
            return;
        }

        if ($totalAmountInput <= 0 && $ratePerKg > 0) {
            $totalAmount = round($weight * $ratePerKg, 2);
        } elseif ($ratePerKg <= 0 && $totalAmountInput > 0) {
            $ratePerKg = round($totalAmountInput / $weight, 2);
            $totalAmount = round($totalAmountInput, 2);
        } else {
            $totalAmount = round($totalAmountInput, 2);
            $ratePerKg = round($ratePerKg, 2);
        }

        if (!isset($_POST['paid_amount']) || trim((string)$_POST['paid_amount']) === '') {
            Session::flash('error', 'Amount Received (₹) is required (enter 0 if unpaid).');
            Response::back();
            return;
        }
        $paidAmount = (float)$_POST['paid_amount'];
        if ($paidAmount < 0) {
            Session::flash('error', 'Amount Received cannot be negative.');
            Response::back();
            return;
        }
        if ($paidAmount > $totalAmount) {
            Session::flash('error', 'Amount Received cannot exceed Total Sale Price (₹' . number_format($totalAmount, 2) . ').');
            Response::back();
            return;
        }

        $paymentStatus = trim($_POST['payment_status'] ?? '');
        if (empty($paymentStatus) || !in_array($paymentStatus, ['PAID', 'PARTIAL', 'PENDING'], true)) {
            $paymentStatus = ($paidAmount >= $totalAmount && $totalAmount > 0) ? 'PAID' : (($paidAmount > 0) ? 'PARTIAL' : 'PENDING');
        }

        $paymentMethod = trim($_POST['payment_method'] ?? '');
        if (empty($paymentMethod) || !in_array($paymentMethod, ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE', 'CREDIT'], true)) {
            $paymentMethod = $existing['payment_method'] ?? 'CASH';
        }

        $saleDate = trim($_POST['sale_date'] ?? '');
        if (empty($saleDate)) {
            Session::flash('error', 'Sale Date is required.');
            Response::back();
            return;
        }

        $paymentReference = !empty($_POST['payment_reference']) ? trim($_POST['payment_reference']) : ($existing['payment_reference'] ?? null);
        $invoiceNumber = !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : ($existing['invoice_number'] ?? null);
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : ($existing['notes'] ?? null);

        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        if ($cuttingBatchId && !$programId) {
            $cb = Database::fetchOne("SELECT program_id FROM cutting_batches WHERE id = ?", [$cuttingBatchId]);
            if ($cb && !empty($cb['program_id'])) {
                $programId = (int)$cb['program_id'];
            }
        }

        $photoUrl = $existing['photo_url'];
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploaded = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/wastage', 'image');
            if ($uploaded) {
                $photoUrl = $uploaded;
            }
        }

        try {
            Database::execute(
                "UPDATE wastage_sales SET
                    buyer_name = ?,
                    buyer_phone = ?,
                    buyer_company = ?,
                    cutting_batch_id = ?,
                    program_id = ?,
                    wastage_type = ?,
                    weight = ?,
                    rate_per_kg = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    payment_status = ?,
                    payment_method = ?,
                    payment_reference = ?,
                    invoice_number = ?,
                    sale_date = ?,
                    photo_url = ?,
                    notes = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $buyerName,
                    $buyerPhone,
                    $buyerCompany,
                    $cuttingBatchId,
                    $programId,
                    $wastageType,
                    $weight,
                    $ratePerKg,
                    $totalAmount,
                    $paidAmount,
                    $paymentStatus,
                    $paymentMethod,
                    $paymentReference ?: null,
                    $invoiceNumber ?: null,
                    $saleDate,
                    $photoUrl,
                    $notes ?: null,
                    $saleId
                ]
            );

            AuditService::log('production', 'update', 'wastage_sales', $saleId, $existing, [
                'buyer_name'   => $buyerName,
                'weight'       => $weight,
                'total_amount' => $totalAmount,
                'paid_amount'  => $paidAmount
            ]);

            Session::flash('success', "Wastage sale #{$existing['sale_number']} updated successfully.");
            Response::redirect(url('production/wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error updating wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteWastage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $saleId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM wastage_sales WHERE id = ?", [$saleId]);
        if (!$existing) {
            Session::flash('error', 'Wastage sale record not found.');
            Response::back();
            return;
        }

        try {
            Database::execute("DELETE FROM wastage_sales WHERE id = ?", [$saleId]);
            AuditService::log('production', 'delete', 'wastage_sales', $saleId, $existing, null);
            Session::flash('success', "Wastage sale #{$existing['sale_number']} deleted successfully.");
            Response::redirect(url('production/wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error deleting wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function wastageReceipt(string $id): void {
        $saleId = (int)$id;
        $sale = Database::fetchOne(
            "SELECT ws.*, 
                    cb.batch_number as cutting_batch_number,
                    cb.name as cutting_batch_name,
                    pp.program_number,
                    pp.name as program_name,
                    u.full_name as created_by_name
             FROM wastage_sales ws
             LEFT JOIN cutting_batches cb ON ws.cutting_batch_id = cb.id
             LEFT JOIN production_programs pp ON ws.program_id = pp.id
             LEFT JOIN users u ON ws.created_by = u.id
             WHERE ws.id = ?",
            [$saleId]
        );

        if (!$sale) {
            Response::error(404, 'Wastage sale record not found.');
            return;
        }

        Response::json([
            'success' => true,
            'sale'    => $sale
        ]);
    }

    // =========================================================================
    // STITCHING WASTAGE SELLING MANAGEMENT
    // =========================================================================

    public function stitchingWastageList(): void {
        $search = trim($_GET['search'] ?? '');
        $type = trim($_GET['type'] ?? '');
        $status = trim($_GET['payment_status'] ?? '');
        $batchFilter = trim($_GET['stitching_batch_id'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $sql = "SELECT sws.*, 
                       sb.batch_number as stitching_batch_number,
                       sb.name as stitching_batch_name,
                       pp.program_number,
                       pp.name as program_name,
                       u.full_name as created_by_name
                FROM stitching_wastage_sales sws
                LEFT JOIN stitching_batches sb ON sws.stitching_batch_id = sb.id
                LEFT JOIN production_programs pp ON sws.program_id = pp.id
                LEFT JOIN users u ON sws.created_by = u.id
                WHERE 1=1";
        
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                sws.sale_number LIKE ?
                OR sws.buyer_name LIKE ?
                OR sws.buyer_company LIKE ?
                OR sws.buyer_phone LIKE ?
                OR sws.invoice_number LIKE ?
                OR sws.payment_reference LIKE ?
                OR sb.batch_number LIKE ?
                OR pp.program_number LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s]);
        }

        if ($type !== '') {
            $sql .= " AND sws.waste_type = ?";
            $params[] = $type;
        }

        if ($status !== '') {
            $sql .= " AND sws.payment_status = ?";
            $params[] = $status;
        }

        if ($batchFilter !== '') {
            $sql .= " AND sws.stitching_batch_id = ?";
            $params[] = (int)$batchFilter;
        }

        if ($dateFrom !== '') {
            $sql .= " AND sws.sale_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND sws.sale_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY sws.sale_date DESC, sws.id DESC";
        $sales = Database::fetchAll($sql, $params);

        // Overall summary stats
        $allSales = Database::fetchAll("SELECT quantity, unit_type, total_amount, paid_amount, payment_status, waste_type FROM stitching_wastage_sales");
        $totalQty = (float)array_sum(array_column($allSales, 'quantity'));
        $totalAmount = (float)array_sum(array_column($allSales, 'total_amount'));
        $totalPaid = (float)array_sum(array_column($allSales, 'paid_amount'));
        $totalPending = max(0.0, $totalAmount - $totalPaid);
        $avgRate = $totalQty > 0 ? ($totalAmount / $totalQty) : 0.0;

        $stats = [
            'total_records'    => count($allSales),
            'total_quantity'   => $totalQty,
            'total_amount'     => $totalAmount,
            'total_paid'       => $totalPaid,
            'total_pending'    => $totalPending,
            'avg_rate_per_unit'=> $avgRate,
            'count_paid'       => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PAID')),
            'count_partial'    => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PARTIAL')),
            'count_pending'    => count(array_filter($allSales, fn($s) => $s['payment_status'] === 'PENDING')),
        ];

        // Fetch stitching module batches with program and production quantities
        $stitchingBatches = Database::fetchAll("
            SELECT sb.id, sb.batch_number, sb.name, sb.program_id, sb.cutting_batch_id,
                   sb.received_qty, sb.stitched_qty, sb.rejected_qty, sb.rework_qty, sb.total_weight, sb.status,
                   pp.program_number, pp.name as program_name,
                   cb.batch_number as cutting_batch_number,
                   COALESCE(sold.sold_quantity, 0.0) as sold_quantity,
                   COALESCE(sold.sales_count, 0) as sales_count,
                   sold.latest_sale_number,
                   sold.latest_unit_type
            FROM stitching_batches sb
            LEFT JOIN production_programs pp ON sb.program_id = pp.id
            LEFT JOIN cutting_batches cb ON sb.cutting_batch_id = cb.id
            LEFT JOIN (
                SELECT stitching_batch_id, 
                       SUM(quantity) as sold_quantity, 
                       COUNT(*) as sales_count,
                       MAX(sale_number) as latest_sale_number,
                       MAX(unit_type) as latest_unit_type
                FROM stitching_wastage_sales
                WHERE stitching_batch_id IS NOT NULL
                GROUP BY stitching_batch_id
            ) sold ON sb.id = sold.stitching_batch_id
            ORDER BY sb.id DESC
        ");

        $programs = Database::fetchAll("SELECT id, program_number, name FROM production_programs ORDER BY id DESC LIMIT 50");

        Response::view('production.stitching_wastage.index', [
            'pageTitle'        => 'Stitching Scrap & Wastage Selling',
            'activeModule'     => 'production',
            'activeMenu'       => 'stitching-wastage',
            'sales'            => $sales,
            'stats'            => $stats,
            'stitchingBatches' => $stitchingBatches,
            'programs'         => $programs,
            'search'           => $search,
            'type'             => $type,
            'paymentStatus'    => $status,
            'stitchingBatchId' => $batchFilter,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'breadcrumb'       => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Stitching Wastage'],
            ],
        ]);
    }

    public function storeStitchingWastage(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $stitchingBatchId = !empty($_POST['stitching_batch_id']) ? (int)$_POST['stitching_batch_id'] : null;
        $buyerName = trim($_POST['buyer_name'] ?? '');
        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany = trim($_POST['buyer_company'] ?? '');
        $wasteType = trim($_POST['waste_type'] ?? '');
        $unitType = trim($_POST['unit_type'] ?? 'KG');
        $quantity = (float)($_POST['quantity'] ?? 0.0);
        $ratePerUnit = (float)($_POST['rate_per_unit'] ?? 0.0);
        $saleDate = trim($_POST['sale_date'] ?? '');
        $paymentStatus = trim($_POST['payment_status'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $paymentReference = trim($_POST['payment_reference'] ?? '');
        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

        // 1. Select Stitching Module Batch / Program is required
        if (empty($stitchingBatchId)) {
            Session::flash('error', 'Select Stitching Module Batch / Program is required. Please choose a Stitching Batch.');
            Response::back();
            return;
        }

        // Validation: Prevent duplicate selling of already sold stitching batch scrap
        $alreadySold = Database::fetchOne(
            "SELECT id, sale_number, quantity, unit_type, sale_date 
             FROM stitching_wastage_sales 
             WHERE stitching_batch_id = ?",
            [$stitchingBatchId]
        );
        if ($alreadySold) {
            $sb = Database::fetchOne("SELECT batch_number, name FROM stitching_batches WHERE id = ?", [$stitchingBatchId]);
            $sbNum = $sb['batch_number'] ?? ('#' . $stitchingBatchId);
            Session::flash('error', "Validation Error: Stitching Batch [{$sbNum}] wastage has already been sold under Sale #{$alreadySold['sale_number']} ({$alreadySold['quantity']} {$alreadySold['unit_type']} on {$alreadySold['sale_date']}). Duplicate sale of the same stitching batch wastage is not allowed.");
            Response::back();
            return;
        }

        // 2. Scrap Buyer / Vendor Name is required
        if (empty($buyerName)) {
            Session::flash('error', 'Scrap Buyer / Vendor Name is required.');
            Response::back();
            return;
        }

        // 3. Buyer Phone / Mobile is required and 10 digits only
        if (empty($buyerPhone) || !preg_match('/^[0-9]{10}$/', $buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile is required and must be a valid 10-digit number.');
            Response::back();
            return;
        }

        // 4. Buyer Company / Business Name is required
        if (empty($buyerCompany)) {
            Session::flash('error', 'Buyer Company / Business Name is required.');
            Response::back();
            return;
        }

        // 5. Stitching Waste Category is required
        if (empty($wasteType)) {
            Session::flash('error', 'Stitching Waste Category is required.');
            Response::back();
            return;
        }

        // 6. Selling Price & Quantity (Manual Entry) is required
        if ($quantity <= 0 || $ratePerUnit <= 0) {
            Session::flash('error', 'Selling Price & Quantity (Manual Entry) is required. Quantity and Rate must be greater than 0.');
            Response::back();
            return;
        }

        // 7. Amount Received (₹) is required
        if (!isset($_POST['paid_amount']) || $_POST['paid_amount'] === '' || (float)$_POST['paid_amount'] < 0) {
            Session::flash('error', 'Amount Received (₹) is required.');
            Response::back();
            return;
        }

        // 8. Sale Date is required
        if (empty($saleDate)) {
            Session::flash('error', 'Sale Date is required.');
            Response::back();
            return;
        }

        if ($stitchingBatchId && !$programId) {
            $sb = Database::fetchOne("SELECT program_id FROM stitching_batches WHERE id = ?", [$stitchingBatchId]);
            if ($sb && !empty($sb['program_id'])) {
                $programId = (int)$sb['program_id'];
            }
        }

        $totalAmount = round($quantity * $ratePerUnit, 2);
        if (!empty($_POST['total_amount']) && (float)$_POST['total_amount'] > 0) {
            $totalAmount = round((float)$_POST['total_amount'], 2);
        }
        $paidAmount = max(0.0, (float)($_POST['paid_amount'] ?? 0.0));
        if ($paidAmount > $totalAmount) {
            $paidAmount = $totalAmount;
        }

        if (empty($paymentStatus) || !in_array($paymentStatus, ['PAID', 'PARTIAL', 'PENDING'], true)) {
            $paymentStatus = ($paidAmount >= $totalAmount && $totalAmount > 0) ? 'PAID' : (($paidAmount > 0) ? 'PARTIAL' : 'PENDING');
        }
        if (empty($paymentMethod) || !in_array($paymentMethod, ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE', 'CREDIT'], true)) {
            $paymentMethod = 'CASH';
        }
        $paymentReference = !empty($_POST['payment_reference']) ? trim($_POST['payment_reference']) : null;
        $invoiceNumber = !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

        // Handle photo upload (Cloudinary Cloud Storage)
        $photoUrl = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoUrl = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/stitching_wastage', 'image');
        }

        // Generate sequential sale number e.g. STW-2026-0001
        $count = Database::fetchOne("SELECT COUNT(*) as cnt FROM stitching_wastage_sales");
        $nextSeq = (int)($count['cnt'] ?? 0) + 1;
        $saleNumber = 'STW-' . date('Y') . '-' . sprintf("%04d", $nextSeq);

        $userId = (int)(Session::get('user_id') ?? 1);

        try {
            Database::execute(
                "INSERT INTO stitching_wastage_sales 
                    (sale_number, buyer_name, buyer_phone, buyer_company, stitching_batch_id, program_id, waste_type, unit_type, quantity, rate_per_unit, total_amount, paid_amount, payment_status, payment_method, payment_reference, invoice_number, sale_date, photo_url, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $saleNumber,
                    $buyerName,
                    $buyerPhone ?: null,
                    $buyerCompany ?: null,
                    $stitchingBatchId,
                    $programId,
                    $wasteType,
                    $unitType,
                    $quantity,
                    $ratePerUnit,
                    $totalAmount,
                    $paidAmount,
                    $paymentStatus,
                    $paymentMethod,
                    $paymentReference ?: null,
                    $invoiceNumber ?: null,
                    $saleDate,
                    $photoUrl,
                    $notes ?: null,
                    $userId
                ]
            );
            $saleId = (int)Database::lastInsertId();

            AuditService::log('production', 'create', 'stitching_wastage_sales', $saleId, null, [
                'sale_number'  => $saleNumber,
                'buyer_name'   => $buyerName,
                'quantity'     => $quantity,
                'total_amount' => $totalAmount
            ]);

            Session::flash('success', "Stitching wastage sale {$saleNumber} recorded successfully for ₹" . number_format($totalAmount, 2));
            Response::redirect(url('production/stitching-wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error recording stitching wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function updateStitchingWastage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $saleId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM stitching_wastage_sales WHERE id = ?", [$saleId]);
        if (!$existing) {
            Session::flash('error', 'Stitching wastage sale record not found.');
            Response::back();
            return;
        }

        $stitchingBatchId = !empty($_POST['stitching_batch_id']) ? (int)$_POST['stitching_batch_id'] : (int)($existing['stitching_batch_id'] ?? 0);
        $buyerName = trim($_POST['buyer_name'] ?? $existing['buyer_name']);
        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany = trim($_POST['buyer_company'] ?? '');
        $wasteType = trim($_POST['waste_type'] ?? $existing['waste_type']);
        $unitType = trim($_POST['unit_type'] ?? $existing['unit_type']);
        $quantity = (float)($_POST['quantity'] ?? $existing['quantity']);
        $ratePerUnit = (float)($_POST['rate_per_unit'] ?? $existing['rate_per_unit']);
        $saleDate = trim($_POST['sale_date'] ?? $existing['sale_date']);
        $paymentStatus = trim($_POST['payment_status'] ?? $existing['payment_status']);
        $paymentMethod = trim($_POST['payment_method'] ?? $existing['payment_method']);
        $paymentReference = trim($_POST['payment_reference'] ?? '');
        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

        // 1. Select Stitching Module Batch / Program is required
        if (empty($stitchingBatchId)) {
            Session::flash('error', 'Select Stitching Module Batch / Program is required. Please choose a Stitching Batch.');
            Response::back();
            return;
        }

        // Validation: Prevent updating to another stitching batch whose scrap has already been sold
        $alreadySold = Database::fetchOne(
            "SELECT id, sale_number, quantity, unit_type, sale_date 
             FROM stitching_wastage_sales 
             WHERE stitching_batch_id = ? AND id != ?",
            [$stitchingBatchId, $saleId]
        );
        if ($alreadySold) {
            $sb = Database::fetchOne("SELECT batch_number, name FROM stitching_batches WHERE id = ?", [$stitchingBatchId]);
            $sbNum = $sb['batch_number'] ?? ('#' . $stitchingBatchId);
            Session::flash('error', "Validation Error: Stitching Batch [{$sbNum}] wastage has already been sold under Sale #{$alreadySold['sale_number']} ({$alreadySold['quantity']} {$alreadySold['unit_type']} on {$alreadySold['sale_date']}). Duplicate sale of the same stitching batch wastage is not allowed.");
            Response::back();
            return;
        }

        // 2. Scrap Buyer / Vendor Name is required
        if (empty($buyerName)) {
            Session::flash('error', 'Scrap Buyer / Vendor Name is required.');
            Response::back();
            return;
        }

        // 3. Buyer Phone / Mobile is required and 10 digits only
        if (empty($buyerPhone) || !preg_match('/^[0-9]{10}$/', $buyerPhone)) {
            Session::flash('error', 'Buyer Phone / Mobile is required and must be a valid 10-digit number.');
            Response::back();
            return;
        }

        // 4. Buyer Company / Business Name is required
        if (empty($buyerCompany)) {
            Session::flash('error', 'Buyer Company / Business Name is required.');
            Response::back();
            return;
        }

        // 5. Stitching Waste Category is required
        if (empty($wasteType)) {
            Session::flash('error', 'Stitching Waste Category is required.');
            Response::back();
            return;
        }

        // 6. Selling Price & Quantity (Manual Entry) is required
        if ($quantity <= 0 || $ratePerUnit <= 0) {
            Session::flash('error', 'Selling Price & Quantity (Manual Entry) is required. Quantity and Rate must be greater than 0.');
            Response::back();
            return;
        }

        // 7. Amount Received (₹) is required
        if (!isset($_POST['paid_amount']) || $_POST['paid_amount'] === '' || (float)$_POST['paid_amount'] < 0) {
            Session::flash('error', 'Amount Received (₹) is required.');
            Response::back();
            return;
        }

        // 8. Sale Date is required
        if (empty($saleDate)) {
            Session::flash('error', 'Sale Date is required.');
            Response::back();
            return;
        }

        if ($stitchingBatchId && !$programId) {
            $sb = Database::fetchOne("SELECT program_id FROM stitching_batches WHERE id = ?", [$stitchingBatchId]);
            if ($sb && !empty($sb['program_id'])) {
                $programId = (int)$sb['program_id'];
            }
        }

        $totalAmount = round($quantity * $ratePerUnit, 2);
        if (!empty($_POST['total_amount']) && (float)$_POST['total_amount'] > 0) {
            $totalAmount = round((float)$_POST['total_amount'], 2);
        }
        $paidAmount = max(0.0, (float)($_POST['paid_amount'] ?? 0.0));
        if ($paidAmount > $totalAmount) {
            $paidAmount = $totalAmount;
        }

        if (empty($paymentStatus) || !in_array($paymentStatus, ['PAID', 'PARTIAL', 'PENDING'], true)) {
            $paymentStatus = ($paidAmount >= $totalAmount && $totalAmount > 0) ? 'PAID' : (($paidAmount > 0) ? 'PARTIAL' : 'PENDING');
        }
        if (empty($paymentMethod) || !in_array($paymentMethod, ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE', 'CREDIT'], true)) {
            $paymentMethod = $existing['payment_method'] ?? 'CASH';
        }
        $paymentReference = !empty($_POST['payment_reference']) ? trim($_POST['payment_reference']) : ($existing['payment_reference'] ?? null);
        $invoiceNumber = !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : ($existing['invoice_number'] ?? null);
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : ($existing['notes'] ?? null);

        $photoUrl = $existing['photo_url'];
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploaded = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/stitching_wastage', 'image');
            if ($uploaded) {
                $photoUrl = $uploaded;
            }
        }

        try {
            Database::execute(
                "UPDATE stitching_wastage_sales SET
                    buyer_name = ?,
                    buyer_phone = ?,
                    buyer_company = ?,
                    stitching_batch_id = ?,
                    program_id = ?,
                    waste_type = ?,
                    unit_type = ?,
                    quantity = ?,
                    rate_per_unit = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    payment_status = ?,
                    payment_method = ?,
                    payment_reference = ?,
                    invoice_number = ?,
                    sale_date = ?,
                    photo_url = ?,
                    notes = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $buyerName,
                    $buyerPhone ?: null,
                    $buyerCompany ?: null,
                    $stitchingBatchId,
                    $programId,
                    $wasteType,
                    $unitType,
                    $quantity,
                    $ratePerUnit,
                    $totalAmount,
                    $paidAmount,
                    $paymentStatus,
                    $paymentMethod,
                    $paymentReference ?: null,
                    $invoiceNumber ?: null,
                    $saleDate,
                    $photoUrl,
                    $notes ?: null,
                    $saleId
                ]
            );

            AuditService::log('production', 'update', 'stitching_wastage_sales', $saleId, $existing, [
                'buyer_name'   => $buyerName,
                'quantity'     => $quantity,
                'total_amount' => $totalAmount,
                'paid_amount'  => $paidAmount
            ]);

            Session::flash('success', "Stitching wastage sale #{$existing['sale_number']} updated successfully.");
            Response::redirect(url('production/stitching-wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error updating stitching wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteStitchingWastage(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $saleId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM stitching_wastage_sales WHERE id = ?", [$saleId]);
        if (!$existing) {
            Session::flash('error', 'Stitching wastage sale record not found.');
            Response::back();
            return;
        }

        try {
            Database::execute("DELETE FROM stitching_wastage_sales WHERE id = ?", [$saleId]);
            AuditService::log('production', 'delete', 'stitching_wastage_sales', $saleId, $existing, null);
            Session::flash('success', "Stitching wastage sale #{$existing['sale_number']} deleted successfully.");
            Response::redirect(url('production/stitching-wastage'));
        } catch (Exception $e) {
            Session::flash('error', 'Error deleting stitching wastage sale: ' . $e->getMessage());
            Response::back();
        }
    }

    public function stitchingWastageReceipt(string $id): void {
        $saleId = (int)$id;
        $sale = Database::fetchOne(
            "SELECT sws.*, 
                    sb.batch_number as stitching_batch_number,
                    sb.name as stitching_batch_name,
                    pp.program_number,
                    pp.name as program_name,
                    u.full_name as created_by_name
             FROM stitching_wastage_sales sws
             LEFT JOIN stitching_batches sb ON sws.stitching_batch_id = sb.id
             LEFT JOIN production_programs pp ON sws.program_id = pp.id
             LEFT JOIN users u ON sws.created_by = u.id
             WHERE sws.id = ?",
            [$saleId]
        );

        if (!$sale) {
            Response::error(404, 'Stitching wastage sale record not found.');
            return;
        }

        Response::json([
            'success' => true,
            'sale'    => $sale
        ]);
    }

    // =========================================================================
    // PRODUCTION EXPENSES MANAGEMENT (Unit 1 & Unit 2)
    // =========================================================================

    public function expensesList(): void {
        $search = trim($_GET['search'] ?? '');
        $unitFilter = trim($_GET['production_unit'] ?? '');
        $categoryFilter = trim($_GET['category'] ?? '');
        $statusFilter = trim($_GET['payment_status'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $sql = "SELECT pe.*,
                       pp.program_number,
                       pp.name as program_name,
                       u.full_name as created_by_name
                FROM production_expenses pe
                LEFT JOIN production_programs pp ON pe.program_id = pp.id
                LEFT JOIN users u ON pe.created_by = u.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                pe.expense_number LIKE ?
                OR pe.item_name LIKE ?
                OR pe.vendor_name LIKE ?
                OR pe.vendor_company LIKE ?
                OR pe.invoice_number LIKE ?
                OR pe.notes LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
        }

        if ($unitFilter !== '' && $unitFilter !== 'ALL') {
            $sql .= " AND pe.production_unit = ?";
            $params[] = $unitFilter;
        }

        if ($categoryFilter !== '' && $categoryFilter !== 'ALL') {
            $sql .= " AND pe.category = ?";
            $params[] = $categoryFilter;
        }

        if ($statusFilter !== '' && $statusFilter !== 'ALL') {
            $sql .= " AND pe.payment_status = ?";
            $params[] = $statusFilter;
        }

        if ($dateFrom !== '') {
            $sql .= " AND pe.expense_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND pe.expense_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY pe.expense_date DESC, pe.id DESC";
        $expenses = Database::fetchAll($sql, $params);

        $productionUnitOptions = [];
        foreach (ProductionUnit::getAll() as $unit) {
            $code = trim((string)($unit['code'] ?? ''));
            if ($code !== '') {
                $productionUnitOptions[$code] = trim((string)$unit['name']);
            }
        }

        $savedProductionUnits = Database::fetchAll(
            "SELECT DISTINCT production_unit
             FROM production_expenses
             WHERE production_unit IS NOT NULL AND TRIM(production_unit) != ''
             ORDER BY production_unit ASC"
        );
        $legacyUnitLabels = [
            'PRODUCTION_1' => 'Production Unit 1',
            'PRODUCTION_2' => 'Production Unit 2',
            'BOTH_UNITS'   => 'Both Units / Common',
            'GENERAL'      => 'General Facility',
        ];
        foreach ($savedProductionUnits as $savedUnit) {
            $value = trim((string)$savedUnit['production_unit']);
            if ($value !== '' && !isset($productionUnitOptions[$value])) {
                $productionUnitOptions[$value] = $legacyUnitLabels[$value] ?? ucwords(strtolower(str_replace(['_', '-'], ' ', $value)));
            }
        }

        $savedExpenseCategories = Database::fetchAll(
            "SELECT category AS value
             FROM production_expenses
             WHERE category IS NOT NULL AND TRIM(category) != ''
             UNION
             SELECT category AS value
             FROM production_expense_items
             WHERE category IS NOT NULL AND TRIM(category) != ''
             ORDER BY value ASC"
        );
        $expenseCategoryOptions = [];
        $knownCategoryLabels = [
            'SEWING_MATERIALS'    => 'Sewing Materials & Trims (Threads, Needles, Zips)',
            'MAINTENANCE_REPAIR'  => 'Machine Maintenance & Repairs (Oil, Spares, Belts)',
            'OPERATIONAL_UTILITY' => 'Operational & Utilities (Electricity, Fuel, Polybags)',
            'MACHINERY_ASSET'     => 'Machine Maintenance & Parts',
            'OTHER'               => 'Other Floor Expenses',
            'GENERAL'             => 'General',
        ];

        // Populate from active expense_categories database records
        $dbCats = Database::fetchAll("SELECT code, name FROM expense_categories WHERE is_active = 1 AND department_scope IN ('PRODUCTION', 'ALL') ORDER BY name ASC");
        foreach ($dbCats as $dbc) {
            $cCode = trim((string)$dbc['code']);
            if ($cCode !== '') {
                $expenseCategoryOptions[$cCode] = trim((string)$dbc['name']);
            }
        }

        foreach ($savedExpenseCategories as $savedCategory) {
            $value = trim((string)$savedCategory['value']);
            if ($value !== '' && !isset($expenseCategoryOptions[$value])) {
                $expenseCategoryOptions[$value] = $knownCategoryLabels[$value] ?? ucwords(strtolower(str_replace(['_', '-'], ' ', $value)));
            }
        }

        // Overall stats
        $allExpenses = Database::fetchAll("SELECT production_unit, category, total_cost, paid_amount, payment_status FROM production_expenses");
        $totalCost = (float)array_sum(array_column($allExpenses, 'total_cost'));
        $totalPaid = (float)array_sum(array_column($allExpenses, 'paid_amount'));
        $totalPending = max(0.0, $totalCost - $totalPaid);

        $unit1Cost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => $e['production_unit'] === 'PRODUCTION_1'), 'total_cost'));
        $unit2Cost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => $e['production_unit'] === 'PRODUCTION_2'), 'total_cost'));
        $bothUnitsCost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => in_array($e['production_unit'], ['BOTH_UNITS', 'GENERAL'])), 'total_cost'));

        $machineryCost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => in_array($e['category'], ['MACHINERY_ASSET', 'MAINTENANCE_REPAIR'])), 'total_cost'));
        $sewingMaterialsCost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => $e['category'] === 'SEWING_MATERIALS'), 'total_cost'));
        $maintenanceCost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => $e['category'] === 'MAINTENANCE_REPAIR'), 'total_cost'));
        $operationsCost = (float)array_sum(array_column(array_filter($allExpenses, fn($e) => $e['category'] === 'OPERATIONAL_UTILITY'), 'total_cost'));

        $stats = [
            'total_records'          => count($allExpenses),
            'total_cost'             => $totalCost,
            'total_paid'             => $totalPaid,
            'total_pending'          => $totalPending,
            'unit1_cost'             => $unit1Cost,
            'unit2_cost'             => $unit2Cost,
            'both_units_cost'        => $bothUnitsCost,
            'machinery_cost'         => $machineryCost,
            'sewing_materials_cost'  => $sewingMaterialsCost,
            'maintenance_cost'       => $maintenanceCost,
            'operations_cost'        => $operationsCost,
            'count_paid'             => count(array_filter($allExpenses, fn($e) => $e['payment_status'] === 'PAID')),
            'count_pending'          => count(array_filter($allExpenses, fn($e) => $e['payment_status'] === 'PENDING')),
            'count_partial'          => count(array_filter($allExpenses, fn($e) => $e['payment_status'] === 'PARTIAL')),
        ];

        $programs = Database::fetchAll("SELECT id, program_number, name FROM production_programs ORDER BY id DESC LIMIT 50");
        $expenseItems = Database::fetchAll("SELECT id, name, category, unit_type, default_rate FROM production_expense_items ORDER BY name ASC");
        $unitsList = Database::fetchAll("SELECT id, name, abbreviation FROM units WHERE is_active = 1 ORDER BY abbreviation ASC");
        if (empty($unitsList)) {
            $unitsList = [
                ['name' => 'Pieces', 'abbreviation' => 'PCS'],
                ['name' => 'Cones', 'abbreviation' => 'CONE'],
                ['name' => 'Packets', 'abbreviation' => 'PKT'],
                ['name' => 'Boxes', 'abbreviation' => 'BOX'],
                ['name' => 'Litres', 'abbreviation' => 'LTR'],
                ['name' => 'Metres', 'abbreviation' => 'MTR'],
                ['name' => 'Sets', 'abbreviation' => 'SET'],
                ['name' => 'Rolls', 'abbreviation' => 'ROLL'],
                ['name' => 'Kilograms', 'abbreviation' => 'KG'],
                ['name' => 'Pairs', 'abbreviation' => 'PAIR'],
                ['name' => 'Dozens', 'abbreviation' => 'DOZEN'],
            ];
        }

        Response::view('production.expenses.index', [
            'pageTitle'      => 'Production Expenses',
            'activeModule'   => 'production',
            'activeMenu'     => 'production-expenses',
            'expenses'       => $expenses,
            'stats'          => $stats,
            'programs'       => $programs,
            'expenseItems'   => $expenseItems,
            'unitsList'      => $unitsList,
            'productionUnitOptions' => $productionUnitOptions,
            'expenseCategoryOptions' => $expenseCategoryOptions,
            'search'         => $search,
            'productionUnit' => $unitFilter,
            'category'       => $categoryFilter,
            'paymentStatus'  => $statusFilter,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'breadcrumb'     => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => 'Expenses'],
            ],
        ]);
    }

    public function storeExpense(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $expenseLines = [];

        // Support direct single-voucher form submission (matching Warehouse flow)
        if (isset($_POST['amount']) && !is_array($_POST['amount'])) {
            $amount = (float)$_POST['amount'];
            if ($amount <= 0) {
                Session::flash('error', 'Expense amount must be greater than zero.');
                Response::back();
                return;
            }
            $singleItem = trim($_POST['item_name'] ?? '');
            if ($singleItem === '') {
                Session::flash('error', 'Particulars / Item Description is required.');
                Response::back();
                return;
            }
            $notes = trim($_POST['notes'] ?? '');
            $logisticsInfo = [];
            if (!empty($_POST['transporter_name'])) $logisticsInfo[] = 'Transporter: ' . trim($_POST['transporter_name']);
            if (!empty($_POST['vehicle_number'])) $logisticsInfo[] = 'Vehicle: ' . trim($_POST['vehicle_number']);
            if ($logisticsInfo) {
                $notes = ($notes ? $notes . ' | ' : '') . implode(' | ', $logisticsInfo);
            }

            $expenseLines = [[
                'item_name' => $singleItem,
                'quantity' => 1,
                'unit_type' => 'PCS',
                'unit_cost' => $amount,
                'total_cost' => $amount,
                'notes' => $notes,
            ]];
        } else {
            $itemNames = $_POST['item_name'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $unitTypes = $_POST['unit_type'] ?? [];
            $unitCosts = $_POST['unit_cost'] ?? [];
            $notesList = $_POST['notes'] ?? [];

            if (!is_array($itemNames)) {
                $itemNames = (string)$itemNames !== '' ? [$itemNames] : [];
                $quantities = is_array($quantities) ? $quantities : [$quantities];
                $unitTypes = is_array($unitTypes) ? $unitTypes : [$unitTypes];
                $unitCosts = is_array($unitCosts) ? $unitCosts : [$unitCosts];
                $notesList = is_array($notesList) ? $notesList : [$notesList];
            }

            if (empty($itemNames) || !is_array($quantities) || !is_array($unitCosts)) {
                Session::flash('error', 'Please enter at least one valid expense item.');
                Response::back();
                return;
            }

            $defaultUnitType = strtoupper(trim($_POST['default_unit_type'] ?? 'PCS')) ?: 'PCS';

            foreach ($itemNames as $index => $submittedName) {
                $itemName = trim((string)$submittedName);
                $quantityValue = $quantities[$index] ?? null;
                $unitCostValue = $unitCosts[$index] ?? null;
                $lineUnitType = !empty($unitTypes[$index]) ? strtoupper(trim((string)$unitTypes[$index])) : $defaultUnitType;
                if ($lineUnitType === '') $lineUnitType = 'PCS';
                $noteText = trim((string)($notesList[$index] ?? ''));
                if ($itemName === '' || !is_numeric($quantityValue) || !is_finite((float)$quantityValue)
                    || !is_numeric($unitCostValue) || !is_finite((float)$unitCostValue)) {
                    Session::flash('error', 'Each expense item must have a type, quantity, and valid price.');
                    Response::back();
                    return;
                }

                $quantity = (float)$quantityValue;
                $unitCost = (float)$unitCostValue;
                if ($quantity <= 0 || $unitCost < 0) {
                    Session::flash('error', 'Expense quantities must be greater than zero and prices cannot be negative.');
                    Response::back();
                    return;
                }

                $expenseLines[] = [
                    'item_name' => $itemName,
                    'quantity' => $quantity,
                    'unit_type' => $lineUnitType,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantity * $unitCost, 2),
                    'notes' => $noteText,
                ];
            }
        }

        if (!$expenseLines) {
            Session::flash('error', 'Add at least one expense item.');
            Response::back();
            return;
        }

        $productionUnit = trim($_POST['production_unit'] ?? 'PRODUCTION_1');
        if ($productionUnit === '') $productionUnit = 'PRODUCTION_1';

        $category = trim($_POST['category'] ?? 'SEWING_MATERIALS');
        if ($category === '') $category = 'SEWING_MATERIALS';

        $vendorName = trim($_POST['paid_to'] ?? $_POST['vendor_name'] ?? '');
        if ($vendorName === '') $vendorName = 'Direct / Floor Expense';

        $paymentStatus = trim($_POST['payment_status'] ?? 'PAID');
        if (!in_array($paymentStatus, ['PAID', 'PENDING', 'PARTIAL', 'CANCELLED'], true)) {
            $paymentStatus = 'PAID';
        }

        $paymentMethod = trim($_POST['payment_method'] ?? 'CASH');
        if ($paymentMethod === '') $paymentMethod = 'CASH';

        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            $expenseDate = date('Y-m-d');
        }

        // Generate sequential expense number e.g. EXP-2026-0001
        $user = Session::get('user');
        $userId = (int)($user['id'] ?? Session::get('user_id') ?? 1);

        // Handle photo / receipt / bill image upload
        $photoUrl = null;
        $fileKey = !empty($_FILES['receipt_file']['name']) ? 'receipt_file' : (!empty($_FILES['photo']['name']) ? 'photo' : null);
        if ($fileKey && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts, true)) {
                if (class_exists('CloudinaryService')) {
                    $photoUrl = CloudinaryService::upload($_FILES[$fileKey], 'grm_inventory/production/expenses', 'auto');
                }
                if (!$photoUrl) {
                    $uploadDir = BASE_PATH . '/public/uploads/production_expenses/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }
                    $filename = 'exp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $filename)) {
                        $photoUrl = 'uploads/production_expenses/' . $filename;
                    }
                }
            }
        }

        try {
            if (!Database::beginTransaction()) {
                throw new \RuntimeException('Could not start expense transaction.');
            }
            $count = Database::fetchOne("SELECT MAX(id) as max_id, COUNT(*) as cnt FROM production_expenses");
            $nextSeq = max((int)($count['max_id'] ?? 0), (int)($count['cnt'] ?? 0)) + 1;
            $expenseNumbers = [];

            foreach ($expenseLines as $line) {
                $expenseNumber = 'EXP-' . date('Y') . '-' . sprintf("%04d", $nextSeq++);
                while (Database::fetchOne("SELECT id FROM production_expenses WHERE expense_number = ?", [$expenseNumber])) {
                    $nextSeq++;
                    $expenseNumber = 'EXP-' . date('Y') . '-' . sprintf("%04d", $nextSeq);
                }
                $lineTotal = (float)$line['total_cost'];
                $paidAmt = ($paymentStatus === 'PAID') ? $lineTotal : 0.00;

                Database::execute(
                    "INSERT INTO production_expenses
                        (expense_number, production_unit, category, item_name,
                         quantity, unit_type, unit_cost, total_cost, paid_amount, payment_status,
                         payment_method, vendor_name, vendor_phone, invoice_number, expense_date, photo_url, notes, created_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $expenseNumber,
                        $productionUnit,
                        $category,
                        $line['item_name'],
                        $line['quantity'],
                        $line['unit_type'],
                        $line['unit_cost'],
                        $lineTotal,
                        $paidAmt,
                        $paymentStatus,
                        $paymentMethod,
                        $vendorName,
                        !empty($_POST['vendor_phone']) ? trim($_POST['vendor_phone']) : null,
                        $invoiceNumber ?: null,
                        $expenseDate,
                        $photoUrl,
                        $line['notes'] ?: null,
                        $userId
                    ]
                );
                $expenseNumbers[] = $expenseNumber;

                Database::execute(
                    "INSERT INTO production_expense_items (name, category, unit_type, default_rate)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), default_rate = VALUES(default_rate)",
                    [$line['item_name'], $category, $line['unit_type'], $line['unit_cost']]
                );
            }

            if (!Database::commit()) {
                throw new \RuntimeException('Could not commit expense items.');
            }
            Session::flash('success', count($expenseLines) . ' expense item(s) recorded successfully (' . implode(', ', $expenseNumbers) . ').');
            Response::redirect(url('production/expenses'));
        } catch (\Exception $e) {
            Database::rollBack();
            Session::flash('error', 'Error recording production expense: ' . $e->getMessage());
            Response::back();
        }
    }

    public function updateExpense(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $expenseId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM production_expenses WHERE id = ?", [$expenseId]);
        if (!$existing) {
            Session::flash('error', 'Expense record not found.');
            Response::back();
            return;
        }

        $itemName = trim($_POST['item_name'] ?? $existing['item_name']);
        $quantity = max(0.01, (float)($_POST['quantity'] ?? $existing['quantity']));
        $unitType = strtoupper(trim($_POST['unit_type'] ?? $existing['unit_type'])) ?: 'PCS';
        $unitCost = max(0.0, (float)($_POST['unit_cost'] ?? $existing['unit_cost']));
        $totalCost = max(0.0, (float)($_POST['total_cost'] ?? round($quantity * $unitCost, 2)));
        if ($totalCost <= 0.0 && $quantity > 0 && $unitCost > 0) {
            $totalCost = round($quantity * $unitCost, 2);
        }

        $productionUnit = trim($_POST['production_unit'] ?? $existing['production_unit']);
        if ($productionUnit === '') $productionUnit = 'PRODUCTION_1';

        $category = trim($_POST['category'] ?? $existing['category']);
        if ($category === '') $category = 'SEWING_MATERIALS';

        $vendorName = trim($_POST['vendor_name'] ?? $existing['vendor_name']);
        $paymentStatus = trim($_POST['payment_status'] ?? $existing['payment_status']);
        if (!in_array($paymentStatus, ['PAID', 'PENDING', 'PARTIAL', 'CANCELLED'], true)) {
            $paymentStatus = $existing['payment_status'];
        }

        $paymentMethod = trim($_POST['payment_method'] ?? $existing['payment_method']);
        $expenseDate = trim($_POST['expense_date'] ?? $existing['expense_date']);
        $invoiceNumber = trim($_POST['invoice_number'] ?? $existing['invoice_number']);
        $notes = trim($_POST['notes'] ?? $existing['notes']);

        $paidAmount = ($paymentStatus === 'PAID')
            ? $totalCost
            : (($paymentStatus === 'PENDING') ? 0.00 : (float)($existing['paid_amount'] ?? 0.00));

        if (empty($itemName)) {
            Session::flash('error', 'Expense Type is required.');
            Response::back();
            return;
        }

        // Handle photo / receipt / bill image upload
        $photoUrl = $existing['photo_url'] ?? null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts, true)) {
                $newPhoto = null;
                if (class_exists('CloudinaryService')) {
                    $newPhoto = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/production/expenses', 'auto');
                }
                if (!$newPhoto) {
                    $uploadDir = BASE_PATH . '/public/uploads/production_expenses/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }
                    $filename = 'exp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                        $newPhoto = 'uploads/production_expenses/' . $filename;
                    }
                }
                if ($newPhoto) {
                    $photoUrl = $newPhoto;
                }
            }
        }

        try {
            Database::execute(
                "UPDATE production_expenses SET
                    production_unit = ?,
                    category = ?,
                    item_name = ?,
                    quantity = ?,
                    unit_type = ?,
                    unit_cost = ?,
                    total_cost = ?,
                    paid_amount = ?,
                    payment_status = ?,
                    payment_method = ?,
                    vendor_name = ?,
                    invoice_number = ?,
                    expense_date = ?,
                    notes = ?,
                    photo_url = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $productionUnit,
                    $category,
                    $itemName,
                    $quantity,
                    $unitType,
                    $unitCost,
                    $totalCost,
                    $paidAmount,
                    $paymentStatus,
                    $paymentMethod,
                    $vendorName,
                    $invoiceNumber ?: null,
                    $expenseDate,
                    $notes ?: null,
                    $photoUrl,
                    $expenseId
                ]
            );

            Session::flash('success', "Production expense #{$existing['expense_number']} updated successfully.");
            Response::redirect(url('production/expenses'));
        } catch (\Exception $e) {
            Session::flash('error', 'Error updating expense record: ' . $e->getMessage());
            Response::back();
        }
    }

    public function deleteExpense(string $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
            return;
        }

        $expenseId = (int)$id;
        $existing = Database::fetchOne("SELECT * FROM production_expenses WHERE id = ?", [$expenseId]);
        if (!$existing) {
            Session::flash('error', 'Expense record not found.');
            Response::back();
            return;
        }

        try {
            Database::execute("DELETE FROM production_expenses WHERE id = ?", [$expenseId]);
            AuditService::log('production', 'delete', 'production_expenses', $expenseId, $existing, null);
            Session::flash('success', "Production expense #{$existing['expense_number']} deleted successfully.");
            Response::redirect(url('production/expenses'));
        } catch (Exception $e) {
            Session::flash('error', 'Error deleting expense record: ' . $e->getMessage());
            Response::back();
        }
    }

    public function viewExpense(string $id): void {
        $expenseId = (int)$id;
        $expense = Database::fetchOne(
            "SELECT pe.*,
                    pp.program_number,
                    pp.name as program_name,
                    u.full_name as created_by_name
             FROM production_expenses pe
             LEFT JOIN production_programs pp ON pe.program_id = pp.id
             LEFT JOIN users u ON pe.created_by = u.id
             WHERE pe.id = ?",
            [$expenseId]
        );

        if (!$expense) {
            Session::flash('error', 'Expense record not found.');
            Response::redirect(url('production/expenses'));
            return;
        }

        $unitLabels = [
            'PRODUCTION_1' => 'Production Unit 1 (PU-01)',
            'PRODUCTION_2' => 'Production Unit 2 (PU-02)',
            'BOTH_UNITS'   => 'Both Units / Common',
            'GENERAL'      => 'General Facility',
        ];

        $items = [
            [
                'item_name'   => $expense['item_name'],
                'category'    => ucwords(str_replace('_', ' ', strtolower((string)$expense['category']))),
                'quantity'    => number_format((float)$expense['quantity'], 2) . ' ' . ($expense['unit_type'] ?: 'PCS'),
                'unit_cost'   => '₹' . number_format((float)$expense['unit_cost'], 2),
                'total_cost'  => '₹' . number_format((float)$expense['total_cost'], 2),
            ]
        ];

        $fields = [
            'Expense Number'   => $expense['expense_number'],
            'Expense Date'     => date('d M Y', strtotime($expense['expense_date'])),
            'Production Unit'  => $unitLabels[$expense['production_unit']] ?? ($expense['production_unit'] ?: '—'),
            'Category'         => ucwords(str_replace('_', ' ', strtolower((string)$expense['category']))),
            'Vendor / Payee'   => $expense['vendor_name'] ?: 'Direct',
            'Vendor Phone'     => $expense['vendor_phone'] ?: '—',
            'Invoice / Ref #'  => $expense['invoice_number'] ?: ($expense['payment_reference'] ?: '—'),
            'Payment Status'   => $expense['payment_status'],
            'Payment Method'   => $expense['payment_method'],
            'Total Cost'       => '₹' . number_format((float)$expense['total_cost'], 2),
            'Amount Paid'      => '₹' . number_format((float)$expense['paid_amount'], 2),
            'Balance Due'      => '₹' . number_format(max(0, (float)$expense['total_cost'] - (float)$expense['paid_amount']), 2),
            'Program'          => !empty($expense['program_number']) ? ($expense['program_number'] . (!empty($expense['program_name']) ? ' (' . $expense['program_name'] . ')' : '')) : '—',
            'Created By'       => $expense['created_by_name'] ?: '—',
            'Created At'       => $expense['created_at'] ?: '—',
            'Notes / Remarks'  => $expense['notes'] ?: 'No remarks',
        ];

        $this->renderProductionRecord(
            'Expense ' . $expense['expense_number'],
            'prod-expenses',
            'Expenses',
            url('production/expenses'),
            $fields,
            $items,
            [
                'Item Description' => 'item_name',
                'Category'         => 'category',
                'Quantity & Unit'  => 'quantity',
                'Unit Cost'        => 'unit_cost',
                'Total Cost'       => 'total_cost',
            ],
            'Expense Items & Cost Breakdown'
        );
    }

    public function expenseReceipt(string $id): void {
        $expenseId = (int)$id;
        $expense = Database::fetchOne(
            "SELECT pe.*,
                    pp.program_number,
                    pp.name as program_name,
                    u.full_name as created_by_name
             FROM production_expenses pe
             LEFT JOIN production_programs pp ON pe.program_id = pp.id
             LEFT JOIN users u ON pe.created_by = u.id
             WHERE pe.id = ?",
            [$expenseId]
        );

        if (!$expense) {
            Response::error(404, 'Expense record not found.');
            return;
        }

        Response::json([
            'success' => true,
            'expense' => $expense
        ]);
    }

    public function ajaxQuickAddExpenseItem(): void {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'GENERAL');
        $unitType = trim($_POST['unit_type'] ?? 'PCS');
        $defaultRate = max(0.0, (float)($_POST['default_rate'] ?? 0.0));

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Expense type name is required.'], 400);
            return;
        }

        $existing = Database::fetchOne("SELECT id, name, category, unit_type, default_rate FROM production_expense_items WHERE name = ?", [$name]);
        if ($existing) {
            Response::json([
                'success' => true,
                'message' => 'Expense type already exists.',
                'item' => $existing,
                'is_existing' => true
            ]);
            return;
        }

        Database::execute(
            "INSERT INTO production_expense_items (name, category, unit_type, default_rate) VALUES (?, ?, ?, ?)",
            [$name, $category, $unitType, $defaultRate]
        );
        $id = (int)Database::lastInsertId();

        Response::json([
            'success' => true,
            'message' => 'Expense type created successfully.',
            'item' => [
                'id' => $id,
                'name' => $name,
                'category' => $category,
                'unit_type' => $unitType,
                'default_rate' => $defaultRate
            ],
            'is_existing' => false
        ]);
    }

    public function ajaxGetExpenseItems(): void {
        $items = Database::fetchAll("SELECT id, name, category, unit_type, default_rate FROM production_expense_items ORDER BY name ASC");
        Response::json(['success' => true, 'items' => $items]);
    }

    public function ajaxQuickAddExpenseCategory(): void {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true) ?: [];
        $name = trim($json['name'] ?? $_POST['name'] ?? '');
        $desc = trim($json['description'] ?? $_POST['description'] ?? '');

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Please enter a category name.'], 400);
            return;
        }

        $code = trim($json['code'] ?? $_POST['code'] ?? '');
        if (empty($code)) {
            $clean = preg_replace('/[^a-zA-Z0-9]+/', '_', strtoupper($name));
            $code = trim($clean, '_');
            if (empty($code)) {
                $code = 'CAT_' . time();
            }
        } else {
            $code = strtoupper(preg_replace('/[^a-zA-Z0-9_]+/', '_', $code));
        }

        $existing = Database::fetchOne(
            "SELECT id, name, code FROM expense_categories WHERE code = ? OR name = ?",
            [$code, $name]
        );
        if ($existing) {
            Response::json([
                'success' => true,
                'message' => 'Expense category already exists.',
                'category' => $existing,
                'is_existing' => true
            ]);
            return;
        }

        Database::execute(
            "INSERT INTO expense_categories (name, code, department_scope, description, is_active) VALUES (?, ?, 'PRODUCTION', ?, 1)",
            [$name, $code, $desc ?: $name]
        );
        $id = (int)Database::lastInsertId();

        Response::json([
            'success' => true,
            'message' => 'Expense category created successfully.',
            'category' => [
                'id' => $id,
                'name' => $name,
                'code' => $code,
            ],
            'is_existing' => false
        ]);
    }

    public function ajaxQuickAddUnit(): void {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true) ?: [];
        $abbr = strtoupper(trim($json['abbreviation'] ?? $_POST['abbreviation'] ?? ''));
        $name = trim($json['name'] ?? $_POST['name'] ?? '');

        if (empty($abbr)) {
            Response::json(['success' => false, 'message' => 'Please enter a unit symbol / code (e.g. BDL, DRUM).'], 400);
            return;
        }

        if (empty($name)) {
            $name = $abbr;
        }

        $existing = Database::fetchOne("SELECT id, name, abbreviation FROM units WHERE abbreviation = ?", [$abbr]);
        if ($existing) {
            Response::json([
                'success' => true,
                'message' => 'Unit already exists.',
                'unit' => $existing,
                'is_existing' => true
            ]);
            return;
        }

        Database::execute(
            "INSERT INTO units (name, abbreviation, is_active) VALUES (?, ?, 1)",
            [$name, $abbr]
        );
        $id = (int)Database::lastInsertId();

        Response::json([
            'success' => true,
            'message' => 'Unit created successfully.',
            'unit' => [
                'id' => $id,
                'name' => $name,
                'abbreviation' => $abbr,
            ],
            'is_existing' => false
        ]);
    }

    public function viewDamage(string $id): void {
        $damage = Database::fetchOne(
            "SELECT rd.*, s.name AS seller_name, s.company AS seller_company,
                    sr.roll_number, sr.color AS roll_color, sr.design_name AS roll_design,
                    p.name AS catalog_product_name, pv.sku AS variant_sku,
                    sz.name AS variant_size_name, cl.name AS variant_color_name,
                    u_rep.full_name AS reported_by_name, u_res.full_name AS resolved_by_name,
                    repl_sr.roll_number AS replacement_roll_code
             FROM roll_damages rd
             LEFT JOIN sellers s ON rd.seller_id = s.id
             LEFT JOIN seller_rolls sr ON rd.seller_roll_id = sr.id
             LEFT JOIN product_variants pv ON rd.variant_id = pv.id
             LEFT JOIN products p ON (rd.product_id = p.id OR pv.product_id = p.id)
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             LEFT JOIN users u_rep ON rd.reported_by = u_rep.id
             LEFT JOIN users u_res ON rd.resolved_by = u_res.id
             LEFT JOIN seller_rolls repl_sr ON rd.replacement_roll_id = repl_sr.id
             WHERE rd.id = ?",
            [(int)$id]
        );
        if (!$damage) {
            Response::error(404, 'Damage record not found.');
            return;
        }

        $isProd = (($damage['item_type'] ?? '') === 'PRODUCT' || !empty($damage['product_id']) || !empty($damage['variant_id']) || empty($damage['seller_roll_id']));
        $qrCodeVal = !empty($damage['qr_code']) ? $damage['qr_code'] : (!empty($damage['roll_number']) ? $damage['roll_number'] : ('DMG-' . ($damage['damage_number'] ?? $damage['id'])));
        $prodDisplayName = !empty($damage['product_name']) ? $damage['product_name'] : ($damage['catalog_product_name'] ?: 'Garment Product');

        $this->renderProductionRecord(
            'Damage Record ' . ($damage['damage_number'] ?? ('#' . $damage['id'])),
            'damages',
            'Damages',
            url('production/damages'),
            [
                'Item Type'         => $isProd ? 'Finished Garment Product' : 'Fabric Roll Piece',
            'Origin / Supplier' => $isProd ? 'Own Production Unit' : ($damage['seller_company'] ?: ($damage['seller_name'] ?? '—')),
            'Item / Roll'       => $isProd ? $prodDisplayName : ($damage['roll_number'] ?? '—'),
            'Scanned QR Label'  => $qrCodeVal,
            'Variant / Color'   => $isProd ? trim(($damage['variant_size_name'] ? 'Size: ' . $damage['variant_size_name'] . ' ' : '') . ($damage['variant_color_name'] ? 'Color: ' . $damage['variant_color_name'] : '')) : ($damage['roll_color'] ?? '—'),
            'SKU / Design'      => $isProd ? ($damage['variant_sku'] ?? '—') : ($damage['roll_design'] ?? '—'),
            'Damaged Quantity'  => number_format((int)($damage['damaged_quantity'] ?? 0)) . ($isProd ? ' piece(s)' : ' roll(s)'),
            'Damaged Weight'    => number_format((float)($damage['damaged_weight'] ?? 0), 2) . ' kg',
            'Loss Amount'       => '₹ ' . number_format((float)($damage['loss_amount'] ?? 0), 2),
            'Reason'            => $damage['damage_reason'] ?? '—',
            'Notes'             => $damage['defect_notes'] ?? '—',
            'Status'            => $damage['status'] ?? '—',
                'Resolution'        => $damage['resolution_type'] ?? '—',
                'Debit Note'        => $damage['debit_note_number'] ?? '—',
                'Replacement Roll'  => $damage['replacement_roll_code'] ?? ($damage['replacement_roll_number'] ?? '—'),
                'Reported By'       => $damage['reported_by_name'] ?? '—',
                'Resolved By'       => $damage['resolved_by_name'] ?? '—',
                'Created At'        => $damage['created_at'] ?? '—',
            ],
            [],
            [],
            'Damage Details',
            [
                'code'        => $qrCodeVal,
                'roll_number' => $damage['roll_number'] ?? '',
                'color'       => $damage['roll_color'] ?? '',
                'design'      => $damage['roll_design'] ?? '',
                'weight'      => (float)($damage['damaged_weight'] ?? 0),
            ]
        );
    }

    public function viewCuttingBatch(string $id): void {
        $batch = CuttingBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'Cutting batch not found.');
            return;
        }

        $items = CuttingBatch::getItems((int)$id);
        $cuttingWastageVal = (!empty($batch['wastage_weight']) && (float)$batch['wastage_weight'] > 0)
            ? (number_format((float)$batch['wastage_weight'], 2) . ' kg')
            : ((!empty($batch['wastage_qty']) && (float)$batch['wastage_qty'] > 0)
                ? (number_format((float)$batch['wastage_qty'], 2) . ' pcs')
                : '0.00 kg');

        $totalCut = array_sum(array_column($items, 'cut_qty')) ?: (int)($batch['cut_qty'] ?? 0);
        $batchWastageWeight = (float)($batch['wastage_weight'] ?? 0);
        $batchWastageQty = (int)($batch['wastage_qty'] ?? 0);

        foreach ($items as &$it) {
            $itemCut = (int)($it['cut_qty'] ?? 0);
            $itemWastageQty = (int)($it['wastage_qty'] ?? 0);

            if ($batchWastageWeight > 0) {
                if (count($items) === 1) {
                    $itemWWeight = $batchWastageWeight;
                } elseif ($totalCut > 0) {
                    $itemWWeight = round(($itemCut / $totalCut) * $batchWastageWeight, 2);
                } else {
                    $itemWWeight = round($batchWastageWeight / count($items), 2);
                }
                $it['display_wastage'] = number_format($itemWWeight, 2) . ' kg';
            } elseif ($itemWastageQty > 0 || $batchWastageQty > 0) {
                $qty = $itemWastageQty > 0 ? $itemWastageQty : (count($items) === 1 ? $batchWastageQty : round(($itemCut / max(1, $totalCut)) * $batchWastageQty));
                $it['display_wastage'] = number_format($qty) . ' pcs';
            } else {
                $it['display_wastage'] = '0.00 kg';
            }
        }
        unset($it);

        $this->renderProductionRecord(
            'Cutting Batch ' . ($batch['batch_number'] ?? ('#' . $batch['id'])),
            'cutting',
            'Cutting',
            url('production/cutting'),
            [
                'Name'            => $batch['name'] ?? '—',
                'Batch Number'    => $batch['batch_number'] ?? '—',
                'Program'         => $batch['program_number'] ?? ($batch['program_name'] ?? '—'),
                'Product'         => $batch['product_name'] ?? '—',
                'Production Unit' => $batch['unit_name'] ?? '—',
                'Operator'        => $batch['operator_name'] ?? '—',
                'Status'          => $batch['status'] ?? '—',
                'Planned Quantity'=> number_format((int)($batch['planned_qty'] ?? 0)),
                'Cut Quantity'    => number_format((int)($batch['cut_qty'] ?? 0)),
                'Cutting Wastage' => $cuttingWastageVal,
                'Created At'      => $batch['created_at'] ?? '—',
            ],
            $items,
            [
                'Catalog'         => 'catalog_hierarchy',
                'Size'            => 'size_name',
                'Color'           => 'color_name',
                'Planned'         => 'planned_qty',
                'Cut'             => 'cut_qty',
                'Cutting Wastage' => 'display_wastage',
            ],
            'Cut Items'
        );
    }

    public function viewStitchingBatch(string $id): void {
        $stitchingId = (int)$id;

        // Auto-sync QC inspection defect counts to this stitching batch if QC was saved/updated
        $linkedQc = Database::fetchOne(
            "SELECT qb.id 
             FROM qc_batches qb 
             LEFT JOIN label_batches lb ON qb.label_batch_id = lb.id 
             WHERE COALESCE(qb.stitching_batch_id, lb.stitching_batch_id) = ? 
             ORDER BY qb.id DESC LIMIT 1",
            [$stitchingId]
        );
        if ($linkedQc) {
            $this->syncQCToStitching((int)$linkedQc['id']);
        }

        $batch = StitchingBatch::find($stitchingId);
        if (!$batch) {
            Response::error(404, 'Stitching batch not found.');
            return;
        }

        $items = StitchingBatch::getItems($stitchingId);

        // Fetch any logged stitching wastage
        $stitchingWastage = Database::fetchOne(
            "SELECT COALESCE(SUM(quantity), 0) as total_wastage_qty, MAX(unit_type) as unit_type 
             FROM stitching_wastage_sales 
             WHERE stitching_batch_id = ?",
            [$stitchingId]
        );
        $stitchingWastageText = ($stitchingWastage && (float)$stitchingWastage['total_wastage_qty'] > 0)
            ? (number_format((float)$stitchingWastage['total_wastage_qty'], 2) . ' ' . ($stitchingWastage['unit_type'] ?: 'kg'))
            : '0.00 kg';

        $this->renderProductionRecord(
            'Stitching Batch ' . ($batch['batch_number'] ?? ('#' . $batch['id'])),
            'stitching',
            'Stitching',
            url('production/stitching'),
            [
                'Name'             => $batch['name'] ?? '—',
                'Batch Number'     => $batch['batch_number'] ?? '—',
                'Program'          => $batch['program_number'] ?? ($batch['program_name'] ?? '—'),
                'Cutting Batch'    => $batch['cutting_batch_number'] ?? ($batch['cutting_name'] ?? '—'),
                'Product'          => $batch['product_name'] ?? '—',
                'Worker'           => $batch['worker_name'] ?? '—',
                'Status'           => $batch['status'] ?? '—',
                'Received Quantity'=> number_format((int)($batch['received_qty'] ?? 0)),
                'Stitched Quantity'=> number_format((int)($batch['stitched_qty'] ?? 0)),
                'Rejected Quantity'=> number_format((int)($batch['rejected_qty'] ?? 0)),
                'Rework Quantity'  => number_format((int)($batch['rework_qty'] ?? 0)),
                'Stitching Wastage'=> $stitchingWastageText,
                'Total Weight'     => number_format((float)($batch['total_weight'] ?? 0), 2) . ' kg',
                'Created At'       => $batch['created_at'] ?? '—',
            ],
            $items,
            [
                'Catalog' => 'catalog_hierarchy',
                'Size'    => 'display_size',
                'Color'   => 'display_color',
                'Received'=> 'received_qty',
                'Stitched'=> 'stitched_qty',
                'Rejected'=> 'rejected_qty',
                'Rework'  => 'rework_qty',
                'Weight'  => 'weight',
            ],
            'Stitching Items'
        );
    }

    public function viewLabelBatch(string $id): void {
        $batch = LabelBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'Label batch not found.');
            return;
        }

        $items = LabelBatch::getItems((int)$id);
        $this->renderProductionRecord(
            'Label Batch ' . ($batch['batch_number'] ?? ('#' . $batch['id'])),
            'labels',
            'Labels',
            url('production/labels'),
            [
                'Batch Number'      => $batch['batch_number'] ?? '—',
                'Program'           => $batch['program_number'] ?? ($batch['program_name'] ?? '—'),
                'Stitching Batch'   => $batch['stitching_batch_number'] ?? ($batch['stitching_name'] ?? '—'),
                'Product'           => $batch['product_name'] ?? '—',
                'Required Labels'   => number_format((int)($batch['required_qty'] ?? 0)),
                'Printed'           => number_format((int)($batch['printed_qty'] ?? 0)),
                'Used / Applied'    => number_format((int)($batch['used_qty'] ?? 0)),
                'Damaged'           => number_format((int)($batch['damaged_qty'] ?? 0)),
                'Status'            => $batch['status'] ?? '—',
                'Created At'        => $batch['created_at'] ?? '—',
            ],
            $items,
            [
                'Catalog' => 'category_name',
                'Size'    => 'display_size',
                'Color'   => 'display_color',
                'SKU'     => 'sku',
                'Required'=> 'required_qty',
                'Printed' => 'printed_qty',
                'Used'    => 'used_qty',
                'Damaged' => 'damaged_qty',
            ],
            'Label Items'
        );
    }

    public function viewQCBatch(string $id): void {
        $batch = QCBatch::find((int)$id);
        if (!$batch) {
            Response::error(404, 'QC batch not found.');
            return;
        }

        $items = QCBatch::getItems((int)$id);
        $this->renderProductionRecord(
            'QC Batch #' . $batch['id'],
            'qc',
            'Quality Control',
            url('production/qc'),
            [
                'Program'         => $batch['program_number'] ?? ($batch['program_name'] ?? '—'),
                'Product'         => $batch['product_name'] ?? '—',
                'Inspector'       => $batch['inspector_name'] ?? '—',
                'Status'          => $batch['status'] ?? '—',
                'Total Quantity'  => number_format((int)($batch['total_qty'] ?? 0)),
                'Passed'          => number_format((int)($batch['passed_qty'] ?? 0)),
                'Rejected'        => number_format((int)($batch['rejected_qty'] ?? 0)),
                'Rework'          => number_format((int)($batch['rework_qty'] ?? 0)),
                'Created At'      => $batch['created_at'] ?? '—',
            ],
            $items,
            [
                'Catalog'   => 'catalog_hierarchy',
                'Size'      => 'display_size',
                'Color'     => 'display_color',
                'Inspected' => 'inspected_qty',
                'Passed'    => 'passed_qty',
                'Rejected'  => 'rejected_qty',
                'Rework'    => 'rework_qty',
            ],
            'Inspection Items'
        );
    }

    private function renderProductionRecord(
        string $recordTitle,
        string $activeMenu,
        string $sectionTitle,
        string $backUrl,
        array $fields,
        array $items,
        array $itemColumns,
        string $itemsTitle,
        array $qrLabel = []
    ): void {
        Response::view('production.record_view', [
            'pageTitle'    => $recordTitle,
            'activeModule' => 'production',
            'activeMenu'   => $activeMenu,
            'breadcrumb'   => [
                ['label' => 'Production', 'url' => url('production/dashboard')],
                ['label' => $sectionTitle, 'url' => $backUrl],
                ['label' => 'Details'],
            ],
            'recordTitle'  => $recordTitle,
            'backUrl'      => $backUrl,
            'fields'       => $fields,
            'items'        => $items,
            'itemColumns'  => $itemColumns,
            'itemsTitle'   => $itemsTitle,
            'qrLabel'      => $qrLabel,
        ]);
    }

    /**
     * AJAX endpoint to list all HSN codes & GST rates from database.
     */
    public function ajaxHsnCodesList(): void {
        $codes = Database::fetchAll("SELECT * FROM hsn_codes ORDER BY hsn_code ASC");
        Response::json(['success' => true, 'data' => $codes]);
    }

    /**
     * AJAX endpoint to save or update an HSN code.
     */
    public function ajaxStoreHsnCode(): void {
        $rawInput = file_get_contents('php://input');
        $jsonData = !empty($rawInput) ? json_decode($rawInput, true) : [];
        if (!is_array($jsonData)) $jsonData = [];

        $hsnCode = mb_strtoupper(trim($_POST['hsn_code'] ?? $jsonData['hsn_code'] ?? ''));
        $description = trim($_POST['description'] ?? $jsonData['description'] ?? '');
        $gstRate = max(0.0, (float)($_POST['gst_rate'] ?? $jsonData['gst_rate'] ?? 5.0));

        if (empty($hsnCode) || strlen($hsnCode) < 2 || strlen($hsnCode) > 15) {
            Response::json(['success' => false, 'message' => 'Please enter a valid HSN code (e.g. 6109 or 6205).'], 422);
            return;
        }

        if (empty($description)) {
            $description = 'HSN ' . $hsnCode . ' Garment Commodity';
        }

        $existing = Database::fetchOne("SELECT * FROM hsn_codes WHERE hsn_code = ?", [$hsnCode]);
        if ($existing) {
            Database::execute("UPDATE hsn_codes SET description = ?, gst_rate = ? WHERE id = ?", [$description, $gstRate, $existing['id']]);
            Response::json([
                'success' => true,
                'message' => 'HSN Code updated successfully.',
                'data'    => [
                    'id'          => $existing['id'],
                    'hsn_code'    => $hsnCode,
                    'description' => $description,
                    'gst_rate'    => number_format($gstRate, 2, '.', ''),
                ]
            ]);
            return;
        }

        Database::execute("INSERT INTO hsn_codes (hsn_code, description, gst_rate) VALUES (?, ?, ?)", [$hsnCode, $description, $gstRate]);
        $newId = Database::lastInsertId();

        Response::json([
            'success' => true,
            'message' => 'New HSN Code created successfully.',
            'data'    => [
                'id'          => $newId,
                'hsn_code'    => $hsnCode,
                'description' => $description,
                'gst_rate'    => number_format($gstRate, 2, '.', ''),
            ]
        ]);
    }

    /**
     * AJAX quick add Cutting Master / Operator.
     */
    public function ajaxQuickAddOperator(): void {
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $jsonData = !empty($rawInput) ? json_decode($rawInput, true) : [];
        if (!is_array($jsonData)) $jsonData = [];

        $fullName = mb_strtoupper(trim($_POST['full_name'] ?? $jsonData['full_name'] ?? ''));
        $phone = trim($_POST['phone'] ?? $jsonData['phone'] ?? '');

        if (empty($fullName)) {
            Response::json(['success' => false, 'message' => 'Cutting Master / Operator name is required.'], 422);
            return;
        }

        // Determine operator role
        $role = Database::fetchOne("SELECT id FROM roles WHERE slug IN ('production-staff', 'operator', 'staff', 'production-manager') ORDER BY id ASC LIMIT 1");
        $roleId = $role ? (int)$role['id'] : 4;

        // Generate clean unique username
        $baseUsername = strtolower(preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', $fullName)));
        if (empty($baseUsername)) {
            $baseUsername = 'operator_' . time();
        }
        $username = $baseUsername;
        $counter = 1;
        while (Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username])) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        $passwordHash = password_hash('Operator@123', PASSWORD_BCRYPT);

        Database::execute(
            "INSERT INTO users (username, full_name, phone, role_id, department, password_hash, is_active, created_at, updated_at) 
             VALUES (?, ?, ?, ?, 'PRODUCTION', ?, 1, NOW(), NOW())",
            [$username, $fullName, $phone ?: null, $roleId, $passwordHash]
        );

        $newId = (int)Database::lastInsertId();

        Response::json([
            'success' => true,
            'id'      => $newId,
            'name'    => $fullName,
            'message' => 'Cutting Master added successfully!'
        ]);
    }

    /**
     * Dedicated QR Barcode Generation & Thermal Printing for a Purchase.
     */
    public function productionPurchaseRollQr(string $id): void {
        $purchaseId = (int)$id;
        $purchase = Database::fetchOne(
            "SELECT pp.*, s.name AS supplier_name, s.company AS supplier_company, s.phone AS supplier_phone,
                    s.material_type AS seller_material_type, s.roll_photo AS seller_photo
             FROM production_purchases pp
             LEFT JOIN sellers s ON s.id = pp.seller_id
             WHERE pp.id = ?",
            [$purchaseId]
        );

        if (!$purchase) {
            Session::flash('error', 'Purchase record not found.');
            Response::redirect(url('production/purchases'));
            return;
        }

        // Fetch purchase items with linked seller_rolls
        $items = Database::fetchAll(
            "SELECT ppi.*, sr.roll_number AS sr_roll_number, sr.start_code, sr.end_code, sr.start_seq, sr.end_seq,
                    sr.qr_code, sr.weight AS sr_weight, sr.material_type AS sr_material_type, sr.color AS sr_color,
                    sr.design_name AS sr_design_name, sr.price AS sr_price, sr.photo_url AS sr_photo_url,
                    sr.quantity AS sr_quantity
             FROM production_purchase_items ppi
             LEFT JOIN seller_rolls sr ON sr.id = ppi.seller_roll_id
             WHERE ppi.purchase_id = ?
             ORDER BY ppi.id ASC",
            [$purchaseId]
        );

        $seller = [
            'id'            => $purchase['seller_id'],
            'name'          => $purchase['supplier_name'],
            'company'       => $purchase['supplier_company'],
            'phone'         => $purchase['supplier_phone'],
            'material_type' => $purchase['seller_material_type'],
        ];

        // Collect fabric rolls and generate QR stickers per roll quantity
        $rolls = [];
        $stickers = [];
        $supplierTitle = mb_strtoupper(trim($purchase['supplier_company'] ?: ($purchase['supplier_name'] ?? 'SUPPLIER')));
        $globalStickerIndex = 1;

        foreach ($items as $idx => $it) {
            if ($it['item_type'] === 'FABRIC_ROLL') {
                $rollNum = $it['sr_roll_number'] ?: ($it['roll_number'] ?: ('ROLL-' . ($idx + 1)));
                $totalWeight = (float)($it['sr_weight'] ?: ($it['weight_kg'] ?: 0));
                $totalPrice = (float)($it['sr_price'] ?: ($it['unit_price'] ?: ($it['total_price'] ?: 0)));
                $color = mb_strtoupper(trim($it['sr_color'] ?: ($it['color'] ?? '')));
                $design = mb_strtoupper(trim($it['sr_design_name'] ?: ($it['design_name'] ?? '')));
                $materialType = mb_strtoupper(trim($it['sr_material_type'] ?: ($it['item_name'] ?? 'FABRIC')));

                // Roll quantity
                $itemQty = max(1, (int)($it['quantity'] ?: ($it['sr_quantity'] ?? 1)));

                // Prefix determination (default 'FB' + month + 2-digit year)
                $prefix = '';
                $padLen = 2;
                if (!empty($it['start_code']) && preg_match('/^([A-Za-z]{2}\d{4})(\d+)$/', $it['start_code'], $m)) {
                    $prefix = $m[1];
                    $padLen = max(2, strlen($m[2]));
                } elseif (!empty($it['start_code']) && preg_match('/^([A-Za-z0-9_-]+?)(\d+)$/', $it['start_code'], $m)) {
                    $prefix = $m[1];
                    $padLen = max(2, strlen($m[2]));
                } else {
                    $prefix = 'FB' . date('my', strtotime($purchase['purchase_date']));
                    $padLen = 2;
                }

                // Sequence determination
                $startSeq = !empty($it['start_seq']) ? (int)$it['start_seq'] : null;
                $endSeq = !empty($it['end_seq']) ? (int)$it['end_seq'] : null;

                if ($startSeq === null && !empty($it['start_code']) && preg_match('/(\d+)$/', $it['start_code'], $m)) {
                    $startSeq = (int)$m[1];
                }
                if ($startSeq === null) {
                    $startSeq = 1;
                }
                if ($endSeq === null || $endSeq < $startSeq) {
                    $endSeq = $startSeq + $itemQty - 1;
                }

                // Check deleted labels if any
                $deletedSeqs = [];
                if (!empty($it['seller_roll_id'])) {
                    $deletedRows = Database::fetchAll(
                        "SELECT `sequence` FROM seller_roll_deleted_labels WHERE seller_roll_id = ?",
                        [(int)$it['seller_roll_id']]
                    );
                    $deletedSeqs = array_fill_keys(array_map('intval', array_column($deletedRows, 'sequence')), true);
                }

                // Check damaged codes if any
                $damagedCodes = [];
                if (!empty($it['seller_roll_id'])) {
                    $damagedRows = Database::fetchAll(
                        "SELECT qr_code FROM roll_damages WHERE seller_roll_id = ? AND qr_code IS NOT NULL AND TRIM(qr_code) <> ''",
                        [(int)$it['seller_roll_id']]
                    );
                    $damagedCodes = array_fill_keys(array_map(static fn($r) => strtoupper(trim((string)$r['qr_code'])), $damagedRows), true);
                }

                $activeSequences = [];
                for ($s = $startSeq; $s <= $endSeq; $s++) {
                    if (!isset($deletedSeqs[$s])) {
                        $activeSequences[] = $s;
                    }
                }
                if (empty($activeSequences)) {
                    for ($s = $startSeq; $s < $startSeq + $itemQty; $s++) {
                        $activeSequences[] = $s;
                    }
                }

                $totalPieces = count($activeSequences);
                $pieceWeight = $totalPieces > 0 ? round($totalWeight / $totalPieces, 2) : $totalWeight;
                $piecePrice = $totalPieces > 0 ? round($totalPrice / $totalPieces, 2) : $totalPrice;

                foreach ($activeSequences as $pieceIdx => $seq) {
                    $pieceCode = sprintf('%s%0' . $padLen . 'd', $prefix, $seq);
                    $qrPayload = $pieceCode;
                    $pieceLabel = $totalPieces > 1 ? ($rollNum . ' (' . ($pieceIdx + 1) . '/' . $totalPieces . ')') : $rollNum;

                    $sticker = [
                        'index'            => $globalStickerIndex++,
                        'item_index'       => $idx + 1,
                        'piece_index'      => $pieceIdx + 1,
                        'total_qty'        => $totalPieces,
                        'roll_id'          => $it['seller_roll_id'] ?? 0,
                        'roll_number'      => $pieceLabel,
                        'base_roll_number' => $rollNum,
                        'po_number'        => $purchase['purchase_number'],
                        'invoice_number'   => $purchase['invoice_number'] ?? '-',
                        'supplier_name'    => $supplierTitle,
                        'fabric_type'      => $materialType,
                        'color'            => $color,
                        'design_name'      => $design,
                        'weight'           => $pieceWeight,
                        'total_weight'     => $totalWeight,
                        'price'            => $piecePrice,
                        'code'             => $pieceCode,
                        'qr_payload'       => $qrPayload,
                        'is_damaged'       => isset($damagedCodes[strtoupper($pieceCode)]),
                        'purchase_date'    => date('d M Y', strtotime($purchase['purchase_date'])),
                    ];

                    $stickers[] = $sticker;
                }

                $rolls[] = $it;
            }
        }

        Response::viewOnly('production.purchases.roll_qr', [
            'pageTitle'  => 'Roll QR Labels — ' . $purchase['purchase_number'],
            'purchase'   => $purchase,
            'seller'     => $seller,
            'rolls'      => $rolls,
            'stickers'   => $stickers,
        ]);
    }

    /**
     * Dedicated QR Barcode Generation & Thermal Printing for Other Garment Supplies in a Purchase.
     */
    public function productionPurchaseSupplyQr(string $id): void {
        self::ensureDatabaseSchema();
        $purchaseId = (int)$id;
        $purchase = Database::fetchOne(
            "SELECT pp.*, s.name AS supplier_name, s.company AS supplier_company, s.phone AS supplier_phone,
                    s.material_type AS seller_material_type
             FROM production_purchases pp
             LEFT JOIN sellers s ON s.id = pp.seller_id
             WHERE pp.id = ?",
            [$purchaseId]
        );

        if (!$purchase) {
            Session::flash('error', 'Purchase record not found.');
            Response::redirect(url('production/purchases'));
            return;
        }

        $seller = [
            'id'            => $purchase['seller_id'],
            'name'          => $purchase['supplier_name'],
            'company'       => $purchase['supplier_company'],
            'phone'         => $purchase['supplier_phone'],
            'material_type' => $purchase['seller_material_type'],
        ];

        $selectedItemId = !empty($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
        $sql = "SELECT ppi.*
                FROM production_purchase_items ppi
                WHERE ppi.purchase_id = ? AND ppi.item_type = 'GENERAL_SUPPLY'";
        $params = [$purchaseId];
        if ($selectedItemId > 0) {
            $sql .= " AND ppi.id = ?";
            $params[] = $selectedItemId;
        }
        $sql .= " ORDER BY ppi.id ASC";
        $supplies = Database::fetchAll($sql, $params);

        $mode = strtolower(trim((string)($_GET['mode'] ?? 'piece'))); // 'piece' or 'package'
        $supplierTitle = mb_strtoupper(trim($purchase['supplier_company'] ?: ($purchase['supplier_name'] ?? 'SUPPLIER')));
        $prefix = 'SP' . date('my', strtotime($purchase['purchase_date']));
        $globalStickerIndex = 1;
        $stickers = [];
        $maxPerItem = 100;

        foreach ($supplies as $idx => $it) {
            $itemName = mb_strtoupper(trim($it['item_name'] ?? 'SUPPLY ITEM'));
            $unit = mb_strtoupper(trim($it['unit'] ?? 'PCS'));
            $rawQty = (float)($it['quantity'] ?? 1);
            $isIntQty = (floor($rawQty) === $rawQty);
            $qty = (int)$rawQty;
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $totalPrice = (float)($it['total_price'] ?? ($rawQty * $unitPrice));
            $hsnCode = !empty($it['hsn_code']) ? trim((string)$it['hsn_code']) : '—';

            // In package mode, or for non-integer / bulk quantities, generate 1 master package label
            if ($mode === 'package' || !$isIntQty || $qty <= 1 || $qty > $maxPerItem) {
                $pieceCode = sprintf('%s%02d', $prefix, $globalStickerIndex++);
                $stickers[] = [
                    'index'          => count($stickers) + 1,
                    'item_id'        => (int)$it['id'],
                    'item_index'     => $idx + 1,
                    'piece_index'    => 1,
                    'total_qty'      => 1,
                    'is_package'     => true,
                    'item_name'      => $itemName,
                    'display_title'  => $itemName,
                    'unit'           => $unit,
                    'quantity_label' => number_format($rawQty, $isIntQty ? 0 : 3) . ' ' . $unit,
                    'quantity_raw'   => $rawQty,
                    'unit_price'     => $unitPrice,
                    'total_price'    => $totalPrice,
                    'hsn_code'       => $hsnCode,
                    'po_number'      => $purchase['purchase_number'],
                    'invoice_number' => $purchase['invoice_number'] ?? '-',
                    'supplier_name'  => $supplierTitle,
                    'code'           => $pieceCode,
                    'qr_payload'     => $pieceCode,
                    'purchase_date'  => date('d M Y', strtotime($purchase['purchase_date'])),
                ];
            } else {
                // In piece mode, generate individual unit labels (e.g. 1 of 10)
                for ($p = 0; $p < $qty; $p++) {
                    $pieceCode = sprintf('%s%02d', $prefix, $globalStickerIndex++);
                    $pieceLabel = $itemName . ' (' . ($p + 1) . '/' . $qty . ')';
                    $stickers[] = [
                        'index'          => count($stickers) + 1,
                        'item_id'        => (int)$it['id'],
                        'item_index'     => $idx + 1,
                        'piece_index'    => $p + 1,
                        'total_qty'      => $qty,
                        'is_package'     => false,
                        'item_name'      => $itemName,
                        'display_title'  => $pieceLabel,
                        'unit'           => $unit,
                        'quantity_label' => '1 ' . $unit . ' (of ' . $qty . ')',
                        'quantity_raw'   => 1,
                        'unit_price'     => $unitPrice,
                        'total_price'    => $unitPrice,
                        'hsn_code'       => $hsnCode,
                        'po_number'      => $purchase['purchase_number'],
                        'invoice_number' => $purchase['invoice_number'] ?? '-',
                        'supplier_name'  => $supplierTitle,
                        'code'           => $pieceCode,
                        'qr_payload'     => $pieceCode,
                        'purchase_date'  => date('d M Y', strtotime($purchase['purchase_date'])),
                    ];
                }
            }
        }

        // Count rolls in this purchase for quick navigation
        $rollCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM production_purchase_items WHERE purchase_id = ? AND item_type = 'FABRIC_ROLL'",
            [$purchaseId]
        )['cnt'] ?? 0);

        Response::viewOnly('production.purchases.supply_qr', [
            'pageTitle'      => 'Other Garment Supplies QR Labels — ' . $purchase['purchase_number'],
            'purchase'       => $purchase,
            'seller'         => $seller,
            'supplies'       => $supplies,
            'stickers'       => $stickers,
            'mode'           => $mode,
            'selectedItemId' => $selectedItemId,
            'rollCount'      => $rollCount,
        ]);
    }

    /**
     * Move garment supplies from a Production Purchase to Inward / Inventory.
     * Automatically registers products, variants, increases warehouse stock balances,
     * and maps QR codes in warehouse_qr_codes.
     */
    public function productionPurchaseSupplyInward(string $id): void {
        self::ensureDatabaseSchema();

        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid or expired security token. Please try again.');
            Response::back();
            return;
        }

        $canManageSupplies = hasPermission('production_purchases', 'create') 
            || hasPermission('production_purchases', 'edit') 
            || hasPermission('sellers', 'create') 
            || hasPermission('sellers', 'edit')
            || (class_exists('Auth') && method_exists('Auth', 'isAdmin') && Auth::isAdmin())
            || (class_exists('Session') && Session::get('role') === 'admin');

        if (!$canManageSupplies) {
            Session::flash('error', 'Access denied: You do not have permission to inward purchase supplies.');
            Response::back();
            return;
        }

        $purchaseId = (int)$id;
        $purchase = Database::fetchOne(
            "SELECT pp.*, s.name AS supplier_name, s.company AS supplier_company
             FROM production_purchases pp
             LEFT JOIN sellers s ON s.id = pp.seller_id
             WHERE pp.id = ?",
            [$purchaseId]
        );

        if (!$purchase) {
            Session::flash('error', 'Purchase record not found.');
            Response::redirect(url('production/purchases'));
            return;
        }

        $selectedItemId = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $sql = "SELECT ppi.*
                FROM production_purchase_items ppi
                WHERE ppi.purchase_id = ? AND ppi.item_type = 'GENERAL_SUPPLY'";
        $params = [$purchaseId];
        if ($selectedItemId > 0) {
            $sql .= " AND ppi.id = ?";
            $params[] = $selectedItemId;
        }
        $sql .= " ORDER BY ppi.id ASC";
        $supplies = Database::fetchAll($sql, $params);

        if (empty($supplies)) {
            Session::flash('error', 'No garment supply items found to inward in this purchase.');
            Response::redirect(url('production/purchases/supply-qr/' . $purchaseId));
            return;
        }

        $userId = Session::userId() ?: (int)(Session::get('user_id') ?? 1);

        // Find primary active warehouse (Central Warehouse)
        $warehouse = Database::fetchOne("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $warehouseId = $warehouse ? (int)$warehouse['id'] : 1;
        $whName = $warehouse['name'] ?? ('Warehouse #' . $warehouseId);

        // Find categories for fallback product categorization
        $defaultCatId = Database::fetchOne("SELECT id FROM categories WHERE is_active = 1 AND (name LIKE '%Supply%' OR name LIKE '%Fabric%' OR name LIKE '%Accessory%' OR name LIKE '%General%') LIMIT 1")['id'] ?? null;
        if (!$defaultCatId) {
            $defaultCatId = Database::fetchOne("SELECT id FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 1")['id'] ?? null;
        }
        $defaultWhCatId = Database::fetchOne("SELECT id FROM warehouse_categories WHERE is_active = 1 AND (name LIKE '%Supply%' OR name LIKE '%Fabric%' OR name LIKE '%Accessory%' OR name LIKE '%General%') LIMIT 1")['id'] ?? null;
        if (!$defaultWhCatId) {
            $defaultWhCatId = Database::fetchOne("SELECT id FROM warehouse_categories WHERE is_active = 1 ORDER BY id ASC LIMIT 1")['id'] ?? null;
        }

        $prefix = 'SP' . date('my', strtotime($purchase['purchase_date']));
        $globalStickerIndex = 1;

        Database::beginTransaction();
        try {
            $totalInwardQty = 0;
            $inwardedItemNames = [];

            foreach ($supplies as $item) {
                $rawQty = (float)($item['quantity'] ?? 1);
                if ($rawQty <= 0) continue;

                $itemName = mb_strtoupper(trim($item['item_name'] ?? 'GARMENT SUPPLY'));
                $unit = mb_strtoupper(trim($item['unit'] ?? 'PCS'));
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $totalPrice = (float)($item['total_price'] ?? ($rawQty * $unitPrice));
                $hsnCode = !empty($item['hsn_code']) ? trim((string)$item['hsn_code']) : null;
                $inwardedItemNames[] = $itemName;
                $totalInwardQty += $rawQty;

                // 1. Check or Create Product in products table
                $existingProduct = Database::fetchOne(
                    "SELECT id, code, category_id, warehouse_category_id FROM products WHERE UPPER(TRIM(name)) = ? LIMIT 1",
                    [$itemName]
                );

                if ($existingProduct) {
                    $productId = (int)$existingProduct['id'];
                    $prodCode = $existingProduct['code'];
                } else {
                    $cleanSlug = preg_replace('/[^A-Z0-9]/', '', $itemName);
                    $baseCode = 'SUP-' . substr($cleanSlug ?: 'ITEM', 0, 8);
                    $codeMatches = Database::fetchOne("SELECT COUNT(*) AS cnt FROM products WHERE code LIKE ?", [$baseCode . '%'])['cnt'] ?? 0;
                    $prodCode = $baseCode . '-' . sprintf('%03d', (int)$codeMatches + 1);

                    Database::execute(
                        "INSERT INTO products
                            (name, code, category_id, warehouse_category_id, hsn_code, source_type, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 'EXTERNAL_PURCHASE', 1, NOW(), NOW())",
                        [$itemName, $prodCode, $defaultCatId, $defaultWhCatId, $hsnCode]
                    );
                    $productId = (int)Database::lastInsertId();
                }

                // 2. Check or Create Product Variant
                $existingVariant = Database::fetchOne(
                    "SELECT id, sku FROM product_variants WHERE product_id = ? LIMIT 1",
                    [$productId]
                );

                if ($existingVariant) {
                    $variantId = (int)$existingVariant['id'];
                } else {
                    $sku = $prodCode . '-STD';
                    Database::execute(
                        "INSERT INTO product_variants
                            (product_id, sku, hsn_code, purchase_price, production_cost, selling_price, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())",
                        [$productId, $sku, $hsnCode, $unitPrice, $unitPrice, $unitPrice]
                    );
                    $variantId = (int)Database::lastInsertId();
                }

                // 3. Increase Stock in Central Warehouse using StockService
                if (empty($item['is_inwarded'])) {
                    StockService::increase(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'AVAILABLE',
                        quantity: $rawQty,
                        transactionType: 'PURCHASE_GRN',
                        referenceType: 'production_purchases',
                        referenceId: $purchaseId,
                        userId: $userId,
                        notes: "Supply Inward for PO #{$purchase['purchase_number']} ({$itemName}: {$rawQty} {$unit} @ ₹" . number_format($unitPrice, 2) . ")"
                    );
                }

                // 4. Register supply QR codes in warehouse_qr_codes for system-wide scanability
                $isIntQty = (floor($rawQty) === $rawQty);
                $qtyInt = (int)$rawQty;
                $pieceCount = ($isIntQty && $qtyInt >= 1 && $qtyInt <= 100) ? $qtyInt : 1;
                $packQty = ($pieceCount === 1 && $rawQty > 1) ? (int)$rawQty : 1;

                for ($p = 0; $p < $pieceCount; $p++) {
                    $qrCode = sprintf('%s%02d', $prefix, $globalStickerIndex++);
                    $existingQr = Database::fetchOne("SELECT id FROM warehouse_qr_codes WHERE qr_code = ?", [$qrCode]);
                    if (!$existingQr) {
                        Database::execute(
                            "INSERT INTO warehouse_qr_codes
                                (qr_code, variant_id, warehouse_id, created_by, inwarded_at, pack_quantity)
                             VALUES (?, ?, ?, ?, NOW(), ?)",
                            [$qrCode, $variantId, $warehouseId, $userId, $packQty]
                        );
                    } else {
                        Database::execute(
                            "UPDATE warehouse_qr_codes
                             SET inwarded_at = NOW(), variant_id = ?, warehouse_id = ?
                             WHERE id = ?",
                            [$variantId, $warehouseId, (int)$existingQr['id']]
                        );
                    }
                }

                // 5. Update supply purchase item
                Database::execute(
                    "UPDATE production_purchase_items
                     SET is_inwarded = 1, inwarded_at = NOW(), inwarded_by = ?, product_id = ?, variant_id = ?
                     WHERE id = ?",
                    [$userId, $productId, $variantId, (int)$item['id']]
                );
            }

            // Check if all supplies in this purchase are now inwarded
            $pendingCount = (int)(Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM production_purchase_items
                 WHERE purchase_id = ? AND item_type = 'GENERAL_SUPPLY' AND (is_inwarded = 0 OR is_inwarded IS NULL)",
                [$purchaseId]
            )['cnt'] ?? 0);

            if ($pendingCount === 0) {
                Database::execute(
                    "UPDATE production_purchases SET is_inwarded = 1, inwarded_at = NOW() WHERE id = ?",
                    [$purchaseId]
                );
            }

            AuditService::log('production', 'INWARD', 'production_purchases', $purchaseId, null, [
                'purchase_number' => $purchase['purchase_number'],
                'items_inwarded'  => implode(', ', array_unique($inwardedItemNames)),
                'total_qty'       => $totalInwardQty,
                'warehouse_id'    => $warehouseId,
                'warehouse_name'  => $whName,
            ]);

            Database::commit();

            Session::flash('success', "Success! Supply product(s) (" . implode(', ', array_unique($inwardedItemNames)) . ") successfully inwarded and added to inventory (" . number_format($totalInwardQty) . " pcs added to {$whName}).");
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Failed to inward supply purchase #{$purchaseId}: " . $e->getMessage());
            Session::flash('error', 'Failed to inward supply products to inventory: ' . $e->getMessage());
        }

        Response::redirect(url('production/purchases/supply-qr/' . $purchaseId . ($selectedItemId > 0 ? '?item_id=' . $selectedItemId : '')));
    }

    /**
     * Real-time duplicate & validation check for Material Supplier creation/editing.
     */
    public function ajaxCheckSupplierDuplicate(): void {
        self::ensureDatabaseSchema();
        $field = trim((string)($_GET['field'] ?? ''));
        $value = trim((string)($_GET['value'] ?? ''));
        $exceptId = !empty($_GET['except_id']) ? (int)$_GET['except_id'] : null;

        if ($field === '' || $value === '') {
            Response::json(['valid' => true, 'available' => true, 'message' => '']);
            return;
        }

        if ($field === 'phone') {
            $clean = preg_replace('/\D+/', '', $value);
            if (strlen($clean) === 12 && substr($clean, 0, 2) === '91') $clean = substr($clean, 2);
            if (strlen($clean) !== 10) {
                Response::json(['valid' => false, 'available' => false, 'message' => 'Phone number must be exactly 10 digits.']);
                return;
            }
            if (!in_array($clean[0], ['6','7','8','9'])) {
                Response::json(['valid' => false, 'available' => false, 'message' => 'Indian mobile numbers must start with 6, 7, 8, or 9.']);
                return;
            }

            $sql = "SELECT id, name, company FROM sellers WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL";
            $params = ['%' . $clean];
            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            $existing = Database::fetchOne($sql, $params);
            if ($existing) {
                $name = !empty($existing['company']) ? $existing['company'] . ' (' . $existing['name'] . ')' : $existing['name'];
                Response::json([
                    'valid' => true,
                    'available' => false,
                    'message' => "Phone number is already registered to material supplier '{$name}'."
                ]);
                return;
            }
            Response::json(['valid' => true, 'available' => true, 'message' => 'Phone number is available.']);
            return;
        }

        if ($field === 'gst_number') {
            $gst = strtoupper($value);
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst)) {
                Response::json(['valid' => false, 'available' => false, 'message' => 'Invalid GSTIN format (15 characters alphanumeric).']);
                return;
            }
            $sql = "SELECT id, name, company FROM sellers WHERE UPPER(gst_number) = ? AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL";
            $params = [$gst];
            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            $existing = Database::fetchOne($sql, $params);
            if ($existing) {
                $name = !empty($existing['company']) ? $existing['company'] . ' (' . $existing['name'] . ')' : $existing['name'];
                Response::json([
                    'valid' => true,
                    'available' => false,
                    'message' => "GSTIN is already registered to material supplier '{$name}'."
                ]);
                return;
            }
            Response::json(['valid' => true, 'available' => true, 'message' => 'GSTIN is valid and available.']);
            return;
        }

        if ($field === 'name' || $field === 'company') {
            if (mb_strlen($value) < 2) {
                Response::json(['valid' => false, 'available' => false, 'message' => ucfirst($field) . ' must be at least 2 characters.']);
                return;
            }
            $col = ($field === 'company') ? 'company' : 'name';
            $sql = "SELECT id, name, company FROM sellers WHERE LOWER({$col}) = LOWER(?) AND (is_active = 1 OR is_active IS NULL) AND production_deleted_at IS NULL";
            $params = [$value];
            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            $existing = Database::fetchOne($sql, $params);
            if ($existing) {
                Response::json([
                    'valid' => true,
                    'available' => false,
                    'message' => "A material supplier with this " . ($field === 'company' ? 'company' : 'contact') . " name already exists ('{$existing['name']}')."
                ]);
                return;
            }
            Response::json(['valid' => true, 'available' => true, 'message' => ucfirst($field) . ' is available.']);
            return;
        }

        Response::json(['valid' => true, 'available' => true, 'message' => '']);
    }
}
