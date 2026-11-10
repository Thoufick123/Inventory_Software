<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div><h2><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Supplier Details</h2></div>
    <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Suppliers</a>
</div>
<form method="POST" action="<?= url('production/sellers/update/' . $seller['id']) ?>" class="card shadow-sm border" id="supplierEditForm" novalidate>
    <?= CSRF::field() ?>
    <input type="hidden" name="supplier_profile_only" value="1">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold"><?= e($seller['company'] ?: $seller['name']) ?></h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Contact Person / Supplier Name *</label>
                <input name="name" class="form-control text-uppercase" required minlength="2" maxlength="200" value="<?= e($seller['name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label required">Company / Mill / Business Name *</label>
                <input name="company" class="form-control text-uppercase" required minlength="2" maxlength="300" value="<?= e($seller['company']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label required">Phone Number *</label>
                <input type="tel" name="phone" id="supplierPhone" class="form-control" required pattern="[0-9]{10}" maxlength="10" inputmode="numeric" value="<?= e($seller['phone']) ?>" placeholder="10-digit mobile number" autocomplete="tel">
                <div class="invalid-feedback" id="phoneFeedback">Phone number must be exactly 10 digits.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email Address (Optional)</label>
                <input type="email" name="email" class="form-control" maxlength="255" value="<?= e($seller['email']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">GSTIN / Tax ID</label>
                <input name="gst_number" class="form-control text-uppercase" maxlength="15" value="<?= e($seller['gst_number']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label required">City / District *</label>
                <input name="city" id="supplierCity" list="tnDistrictsList" class="form-control text-uppercase <?= Session::has('city_state_error') ? 'is-invalid' : '' ?>" required maxlength="100" value="<?= e($seller['city']) ?>" placeholder="e.g. TIRUPPUR, COIMBATORE" autocomplete="off">
                <datalist id="tnDistrictsList">
                    <option value="ARIYALUR">
                    <option value="CHENGALPATTU">
                    <option value="CHENNAI">
                    <option value="COIMBATORE">
                    <option value="CUDDALORE">
                    <option value="DHARMAPURI">
                    <option value="DINDIGUL">
                    <option value="ERODE">
                    <option value="KALLAKURICHI">
                    <option value="KANCHIPURAM">
                    <option value="KANYAKUMARI">
                    <option value="KARUR">
                    <option value="KRISHNAGIRI">
                    <option value="MADURAI">
                    <option value="MAYILADUTHURAI">
                    <option value="NAGAPATTINAM">
                    <option value="NAMAKKAL">
                    <option value="NILGIRIS">
                    <option value="PERAMBALUR">
                    <option value="PUDUKKOTTAI">
                    <option value="RAMANATHAPURAM">
                    <option value="RANIPET">
                    <option value="SALEM">
                    <option value="SIVAGANGA">
                    <option value="TENKASI">
                    <option value="THANJAVUR">
                    <option value="THENI">
                    <option value="THOOTHUKUDI">
                    <option value="TIRUCHIRAPPALLI">
                    <option value="TIRUNELVELI">
                    <option value="TIRUPATHUR">
                    <option value="TIRUPPUR">
                    <option value="TIRUVALLUR">
                    <option value="TIRUVANNAMALAI">
                    <option value="TIRUVARUR">
                    <option value="VELLORE">
                    <option value="VILUPPURAM">
                    <option value="VIRUDHUNAGAR">
                    <option value="POLLACHI">
                    <option value="AVINASHI">
                    <option value="PALLADAM">
                    <option value="HOSUR">
                    <option value="SIVAKASI">
                    <option value="RAJAPALAYAM">
                </datalist>
                <div class="invalid-feedback" id="cityFeedback">Please check the entered city/district.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label required">State *</label>
                <input name="state" id="supplierState" list="statesList" class="form-control text-uppercase <?= Session::has('city_state_error') ? 'is-invalid' : '' ?>" required maxlength="100" value="<?= e($seller['state'] ?: 'TAMIL NADU') ?>" placeholder="e.g. TAMIL NADU" autocomplete="off">
                <datalist id="statesList">
                    <option value="TAMIL NADU">
                    <option value="ANDHRA PRADESH">
                    <option value="KARNATAKA">
                    <option value="KERALA">
                    <option value="MAHARASHTRA">
                    <option value="GUJARAT">
                    <option value="TELANGANA">
                    <option value="DELHI">
                    <option value="HARYANA">
                    <option value="MADHYA PRADESH">
                    <option value="PUNJAB">
                    <option value="RAJASTHAN">
                    <option value="UTTAR PRADESH">
                    <option value="WEST BENGAL">
                </datalist>
                <div class="invalid-feedback" id="stateFeedback">State must match the entered city.</div>
            </div>
            <div class="col-12" id="cityStateAlertContainer" style="<?= Session::has('city_state_error') ? '' : 'display:none;' ?>">
                <div class="alert alert-danger d-flex align-items-center mb-0 py-2 px-3 shadow-sm border border-danger-subtle" role="alert" id="cityStateAlert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-2 flex-shrink-0"></i>
                    <div id="cityStateAlertText"><?= e(Session::get('city_state_error') ?? '') ?></div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label required">Full Business Address *</label>
                <textarea name="address" class="form-control text-uppercase" required maxlength="500" rows="2"><?= e($seller['address']) ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between">
        <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btnUpdateSupplier"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
    </div>
</form>

<script>
(function() {
    const cityInput = document.getElementById('supplierCity');
    const stateInput = document.getElementById('supplierState');
    const phoneInput = document.getElementById('supplierPhone');
    const phoneFeedback = document.getElementById('phoneFeedback');
    const alertContainer = document.getElementById('cityStateAlertContainer');
    const alertText = document.getElementById('cityStateAlertText');
    const form = document.getElementById('supplierEditForm');

    function checkPhone() {
        if (!phoneInput) return true;
        phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
        const val = phoneInput.value.trim();

        if (val.length === 0) {
            phoneInput.classList.remove('is-valid');
            phoneInput.classList.add('is-invalid');
            if (phoneFeedback) phoneFeedback.textContent = 'Phone number is required (10 digits).';
            return false;
        }

        if (val.length !== 10) {
            phoneInput.classList.remove('is-valid');
            phoneInput.classList.add('is-invalid');
            if (phoneFeedback) phoneFeedback.textContent = `Phone number must be exactly 10 digits (currently ${val.length} digits).`;
            return false;
        }

        phoneInput.classList.remove('is-invalid');
        phoneInput.classList.add('is-valid');
        return true;
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', checkPhone);
        phoneInput.addEventListener('blur', checkPhone);
    }

    const tnCities = [
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

    const otherStateCities = {
        'BENGALURU': 'KARNATAKA', 'BANGALORE': 'KARNATAKA', 'MYSORE': 'KARNATAKA', 'MYSURU': 'KARNATAKA',
        'MANGALORE': 'KARNATAKA', 'MANGALURU': 'KARNATAKA', 'HUBLI': 'KARNATAKA', 'HUBBALLI': 'KARNATAKA',
        'BELGAUM': 'KARNATAKA', 'BELAGAVI': 'KARNATAKA', 'DAVANAGERE': 'KARNATAKA', 'BELLARY': 'KARNATAKA',
        'SHIMOGA': 'KARNATAKA', 'SHIVAMOGGA': 'KARNATAKA', 'TUMKUR': 'KARNATAKA', 'UDUPI': 'KARNATAKA',
        'HASSAN': 'KARNATAKA', 'GULBARGA': 'KARNATAKA',
        'KOCHI': 'KERALA', 'COCHIN': 'KERALA', 'THIRUVANANTHAPURAM': 'KERALA', 'TRIVANDRUM': 'KERALA',
        'KOZHIKODE': 'KERALA', 'CALICUT': 'KERALA', 'THRISSUR': 'KERALA', 'TRICHUR': 'KERALA',
        'KOLLAM': 'KERALA', 'PALAKKAD': 'KERALA', 'ALAPPUZHA': 'KERALA', 'KANNUR': 'KERALA',
        'KOTTAYAM': 'KERALA', 'MALAPPURAM': 'KERALA', 'KASARAGOD': 'KERALA',
        'HYDERABAD': 'TELANGANA', 'SECUNDERABAD': 'TELANGANA', 'WARANGAL': 'TELANGANA', 'NIZAMABAD': 'TELANGANA',
        'VISAKHAPATNAM': 'ANDHRA PRADESH', 'VIZAG': 'ANDHRA PRADESH', 'VIJAYAWADA': 'ANDHRA PRADESH',
        'GUNTUR': 'ANDHRA PRADESH', 'NELLORE': 'ANDHRA PRADESH', 'KURNOOL': 'ANDHRA PRADESH',
        'RAJAHMUNDRY': 'ANDHRA PRADESH', 'TIRUPATI': 'ANDHRA PRADESH', 'KAKINADA': 'ANDHRA PRADESH',
        'MUMBAI': 'MAHARASHTRA', 'BOMBAY': 'MAHARASHTRA', 'PUNE': 'MAHARASHTRA', 'NAGPUR': 'MAHARASHTRA',
        'THANE': 'MAHARASHTRA', 'NASHIK': 'MAHARASHTRA', 'AURANGABAD': 'MAHARASHTRA', 'KOLHAPUR': 'MAHARASHTRA',
        'AHMEDABAD': 'GUJARAT', 'SURAT': 'GUJARAT', 'VADODARA': 'GUJARAT', 'BARODA': 'GUJARAT',
        'RAJKOT': 'GUJARAT', 'BHAVNAGAR': 'GUJARAT', 'JAMNAGAR': 'GUJARAT', 'GANDHINAGAR': 'GUJARAT',
        'DELHI': 'DELHI', 'NEW DELHI': 'DELHI', 'NOIDA': 'UTTAR PRADESH', 'GURGAON': 'HARYANA', 'GURUGRAM': 'HARYANA',
        'LUCKNOW': 'UTTAR PRADESH', 'KANPUR': 'UTTAR PRADESH', 'AGRA': 'UTTAR PRADESH', 'VARANASI': 'UTTAR PRADESH',
        'KOLKATA': 'WEST BENGAL', 'CALCUTTA': 'WEST BENGAL', 'HOWRAH': 'WEST BENGAL',
        'JAIPUR': 'RAJASTHAN', 'JODHPUR': 'RAJASTHAN', 'UDAIPUR': 'RAJASTHAN', 'KOTA': 'RAJASTHAN',
        'INDORE': 'MADHYA PRADESH', 'BHOPAL': 'MADHYA PRADESH', 'GWALIOR': 'MADHYA PRADESH',
        'PATNA': 'BIHAR', 'LUDHIANA': 'PUNJAB', 'AMRITSAR': 'PUNJAB', 'CHANDIGARH': 'CHANDIGARH'
    };

    // All recognized Indian States & UTs
    const allIndianStates = {
        'TAMIL NADU': ['TAMILNADU', 'TN'],
        'ANDHRA PRADESH': ['ANDHRAPRADESH', 'AP'],
        'ARUNACHAL PRADESH': ['ARUNACHALPRADESH', 'AR'],
        'ASSAM': ['ASSAM', 'AS'],
        'BIHAR': ['BIHAR', 'BR'],
        'CHHATTISGARH': ['CHHATTISGARH', 'CG'],
        'GOA': ['GOA', 'GA'],
        'GUJARAT': ['GUJARAT', 'GJ'],
        'HARYANA': ['HARYANA', 'HR'],
        'HIMACHAL PRADESH': ['HIMACHALPRADESH', 'HP'],
        'JHARKHAND': ['JHARKHAND', 'JH'],
        'KARNATAKA': ['KARNATAKA', 'KA'],
        'KERALA': ['KERALA', 'KL'],
        'MADHYA PRADESH': ['MADHYAPRADESH', 'MP'],
        'MAHARASHTRA': ['MAHARASHTRA', 'MH'],
        'MANIPUR': ['MANIPUR', 'MN'],
        'MEGHALAYA': ['MEGHALAYA', 'ML'],
        'MIZORAM': ['MIZORAM', 'MZ'],
        'NAGALAND': ['NAGALAND', 'NL'],
        'ODISHA': ['ODISHA', 'ORISSA', 'OD', 'OR'],
        'PUNJAB': ['PUNJAB', 'PB'],
        'RAJASTHAN': ['RAJASTHAN', 'RJ'],
        'SIKKIM': ['SIKKIM', 'SK'],
        'TELANGANA': ['TELANGANA', 'TS', 'TG'],
        'TRIPURA': ['TRIPURA', 'TR'],
        'UTTAR PRADESH': ['UTTARPRADESH', 'UP'],
        'UTTARAKHAND': ['UTTARAKHAND', 'UK', 'UA'],
        'WEST BENGAL': ['WESTBENGAL', 'WB'],
        'DELHI': ['DELHI', 'DL', 'NEWDELHI'],
        'JAMMU AND KASHMIR': ['JAMMUANDKASHMIR', 'JAMMUKASHMIR', 'JK'],
        'LADAKH': ['LADAKH', 'LA'],
        'PUDUCHERRY': ['PUDUCHERRY', 'PONDICHERRY', 'PY'],
        'CHANDIGARH': ['CHANDIGARH', 'CH'],
        'DADRA AND NAGAR HAVELI AND DAMAN AND DIU': ['DADRAANDNAGARHAVELIANDDAMANANDDIU', 'DAMAN', 'DIU'],
        'ANDAMAN AND NICOBAR ISLANDS': ['ANDAMANANDNICOBARISLANDS', 'ANDAMAN', 'AN'],
        'LAKSHADWEEP': ['LAKSHADWEEP', 'LD']
    };

    function normalizeText(txt) {
        return (txt || '').toUpperCase().trim().replace(/[\s\-_.]+/g, '');
    }

    const addressInput = document.querySelector('textarea[name="address"]');
    function checkAddress() {
        if (!addressInput) return true;
        const val = addressInput.value.trim();
        if (val.length < 6 || !/[a-zA-Z]/.test(val)) {
            addressInput.classList.add('is-invalid');
            return false;
        }
        addressInput.classList.remove('is-invalid');
        return true;
    }
    if (addressInput) {
        addressInput.addEventListener('input', checkAddress);
        addressInput.addEventListener('blur', checkAddress);
    }

    function checkCityState() {
        const rawCity = (cityInput.value || '').trim();
        const rawState = (stateInput.value || '').trim();
        const normCity = normalizeText(rawCity);
        const normState = normalizeText(rawState);

        if (!normCity && !normState) {
            clearAlert();
            return true;
        }

        // 1. Check if City/District is entered as a State name
        for (const [stName, aliases] of Object.entries(allIndianStates)) {
            const stNorm = normalizeText(stName);
            if (normCity === stNorm || aliases.includes(normCity)) {
                showAlert(`<strong>Invalid City/District:</strong> "${rawCity.toUpperCase()}" is an Indian State name. Please enter a valid district or city (e.g. COIMBATORE, TIRUPPUR, CHENNAI).`, true, false);
                return false;
            }
        }

        // 2. Check if State is entered as a City/District
        for (const tn of tnCities) {
            if (normState === normalizeText(tn)) {
                showAlert(`<strong>Invalid State:</strong> "${rawState.toUpperCase()}" is a city or district in Tamil Nadu. Please enter <strong>TAMIL NADU</strong> as the state.`, false, true);
                return false;
            }
        }
        for (const [otherCity, expectedState] of Object.entries(otherStateCities)) {
            if (normState === normalizeText(otherCity)) {
                showAlert(`<strong>Invalid State:</strong> "${rawState.toUpperCase()}" is a city in ${expectedState}. Please enter <strong>${expectedState}</strong> as the state.`, false, true);
                return false;
            }
        }

        // 3. Check if State is a valid recognized Indian State
        let matchedStateName = null;
        for (const [stName, aliases] of Object.entries(allIndianStates)) {
            const stNorm = normalizeText(stName);
            if (normState === stNorm || aliases.includes(normState)) {
                matchedStateName = stName;
                break;
            }
        }
        if (normState && !matchedStateName) {
            showAlert(`<strong>Invalid State:</strong> "${rawState.toUpperCase()}" is not a recognized Indian State or UT. Please enter a valid state (e.g. TAMIL NADU, KARNATAKA, KERALA).`, false, true);
            return false;
        }

        // 4. Check City-State Location consistency
        let isTnCity = false;
        for (const tn of tnCities) {
            const normTn = normalizeText(tn);
            if (normCity === normTn || normCity.includes(normTn) || normTn.includes(normCity)) {
                isTnCity = true;
                break;
            }
        }

        if (isTnCity && matchedStateName && matchedStateName !== 'TAMIL NADU') {
            showAlert(`<strong>Location Mismatch:</strong> "${rawCity.toUpperCase()}" is located in Tamil Nadu, but the State is entered as "${rawState.toUpperCase()}". Please enter <strong>TAMIL NADU</strong> as the state.`, true, true);
            return false;
        }

        for (const [otherCity, otherState] of Object.entries(otherStateCities)) {
            const normOther = normalizeText(otherCity);
            if (normCity === normOther || normCity.includes(normOther) || normOther.includes(normCity)) {
                if (matchedStateName && matchedStateName !== otherState) {
                    showAlert(`<strong>Location Mismatch:</strong> "${rawCity.toUpperCase()}" is located in ${otherState}, but the State is entered as "${rawState.toUpperCase()}". Please set State to ${otherState}.`, true, true);
                    return false;
                }
                break;
            }
        }

        clearAlert();
        return true;
    }

    function showAlert(message, markCity = false, markState = false) {
        alertText.innerHTML = message;
        alertContainer.style.display = 'block';
        if (markCity) cityInput.classList.add('is-invalid');
        if (markState) stateInput.classList.add('is-invalid');
    }

    function clearAlert() {
        alertContainer.style.display = 'none';
        alertText.innerHTML = '';
        cityInput.classList.remove('is-invalid');
        stateInput.classList.remove('is-invalid');
    }

    cityInput.addEventListener('input', checkCityState);
    cityInput.addEventListener('change', checkCityState);
    stateInput.addEventListener('input', checkCityState);
    stateInput.addEventListener('change', checkCityState);

    form.addEventListener('submit', function(e) {
        // Allow form to submit smoothly without blocking
        return true;
    });
})();
</script>
