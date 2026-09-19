<?php
/**
 * Application translations and locale helpers.
 */

$supported_languages = ['en', 'sw'];
$current_language = $_SESSION['language'] ?? ($_COOKIE['rf_language'] ?? 'en');
if (!in_array($current_language, $supported_languages, true)) {
    $current_language = 'en';
}

$translations = [
    'en' => [
        'dashboard' => 'Dashboard', 'customers' => 'Customers', 'staff' => 'Staff',
        'deliveries' => 'Deliveries', 'reports' => 'Reports', 'settings' => 'Settings',
        'logout' => 'Logout', 'welcome_back' => 'Welcome back, :name!',
        'live_overview' => 'Live overview', 'refresh' => 'Refresh', 'updated_just_now' => 'Updated just now',
        'active_customers' => 'Active Customers', 'served_today' => 'Served Today',
        'due_today' => 'Due Today', 'overdue' => 'Overdue', 'gallons_today' => 'Gallons Today',
        'sales_tzs' => 'Sales (TZS)', 'account' => 'Account',
        'update_account_language' => 'Update your account details and language preference.',
        'full_name' => 'Full name', 'username' => 'Username', 'new_password' => 'New password',
        'leave_blank_password' => 'Leave blank to keep the current password.', 'language' => 'Language',
        'english' => 'English', 'swahili' => 'Kiswahili', 'cancel' => 'Cancel',
        'save_changes' => 'Save changes', 'search' => 'Search', 'add_customer' => 'Add Customer',
        'customer_management' => 'Customer Management', 'no_customers_found' => 'No customers found',
        'customer_code' => 'Customer Code', 'name' => 'Name', 'phone_1' => 'Phone 1',
        'phone_2' => 'Phone 2', 'email' => 'Email', 'address' => 'Address', 'status' => 'Status',
        'actions' => 'Actions', 'active' => 'Active', 'inactive' => 'Inactive',
        'forgot_password' => 'Forgot password?', 'show_password' => 'Show password',
        'sign_in' => 'Sign in', 'login_required' => 'Please enter both username and password.',
        'settings_updated' => 'Settings updated successfully.',
        'supported_language' => 'Please choose a supported language.',
        'required_account_fields' => 'Full name and username are required.',
        'password_minimum' => 'The new password must be at least 6 characters.',
        'username_in_use' => 'That username is already in use.',
        'update_failed' => 'Unable to update settings. Please try again.',
    ],
    'sw' => [
        'dashboard' => 'Dashibodi', 'customers' => 'Wateja', 'staff' => 'Wafanyakazi',
        'deliveries' => 'Uwasilishaji', 'reports' => 'Ripoti', 'settings' => 'Mipangilio',
        'logout' => 'Toka', 'welcome_back' => 'Karibu tena, :name!',
        'live_overview' => 'Muhtasari wa moja kwa moja', 'refresh' => 'Onyesha upya',
        'updated_just_now' => 'Imesasishwa sasa hivi', 'active_customers' => 'Wateja Hai',
        'served_today' => 'Waliohudumiwa Leo', 'due_today' => 'Walio hudumiwa Leo',
        'overdue' => 'Waliochelewa', 'gallons_today' => 'Galoni Leo', 'sales_tzs' => 'Mauzo (TZS)',
        'account' => 'Akaunti', 'update_account_language' => 'Sasisha taarifa za akaunti na mapendeleo ya lugha.',
        'full_name' => 'Jina kamili', 'username' => 'Jina la mtumiaji', 'new_password' => 'Nenosiri jipya',
        'leave_blank_password' => 'Acha wazi ili kuhifadhi nenosiri la sasa.', 'language' => 'Lugha',
        'english' => 'Kiingereza', 'swahili' => 'Kiswahili', 'cancel' => 'Ghairi',
        'save_changes' => 'Hifadhi mabadiliko', 'search' => 'Tafuta', 'add_customer' => 'Ongeza mteja',
        'customer_management' => 'Usimamizi wa Wateja', 'no_customers_found' => 'Hakuna wateja waliopatikana',
        'customer_code' => 'Namba ya mteja', 'name' => 'Jina', 'phone_1' => 'Simu 1',
        'phone_2' => 'Simu 2', 'email' => 'Barua pepe', 'address' => 'Anwani', 'status' => 'Hali',
        'actions' => 'Vitendo', 'active' => 'Hai', 'inactive' => 'Si hai',
        'forgot_password' => 'Umesahau nenosiri?', 'show_password' => 'Onyesha nenosiri',
        'sign_in' => 'Ingia', 'login_required' => 'Tafadhali jaza jina la mtumiaji na nenosiri.',
        'settings_updated' => 'Mipangilio imesasishwa kikamilifu.',
        'supported_language' => 'Tafadhali chagua lugha inayotumika.',
        'required_account_fields' => 'Jina kamili na jina la mtumiaji vinahitajika.',
        'password_minimum' => 'Nenosiri jipya lazima liwe na angalau herufi 6.',
        'username_in_use' => 'Jina hilo la mtumiaji linatumika tayari.',
        'update_failed' => 'Imeshindikana kusasisha mipangilio. Tafadhali jaribu tena.',
    ],
];

function t(string $key, array $replace = []): string
{
    global $translations, $current_language;
    $value = $translations[$current_language][$key] ?? $translations['en'][$key] ?? $key;
    foreach ($replace as $placeholder => $replacement) {
        $value = str_replace(':' . $placeholder, (string) $replacement, $value);
    }
    return $value;
}

function currentLanguage(): string
{
    global $current_language;
    return $current_language;
}

function setLanguage(string $language): void
{
    global $current_language;
    if (in_array($language, ['en', 'sw'], true)) {
        $current_language = $language;
        $_SESSION['language'] = $language;
        setcookie('rf_language', $language, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    }
}

function localizePageOutput(string $output): string
{
    if (currentLanguage() !== 'sw') {
        return $output;
    }

    $replacements = [
        'Dashboard' => 'Dashibodi', 'Customers' => 'Wateja', 'Staff' => 'Wafanyakazi',
        'Deliveries' => 'Uwasilishaji', 'Reports' => 'Ripoti', 'Settings' => 'Mipangilio',
        'Logout' => 'Toka', 'Refresh' => 'Onyesha upya', 'Updated just now' => 'Imesasishwa sasa hivi',
        'Live overview' => 'Muhtasari wa moja kwa moja', 'Active Customers' => 'Wateja Hai',
        'Served Today' => 'Waliohudumiwa Leo', 'Due Today' => 'Walio hudumiwa Leo',
        'Gallons Today' => 'Galoni Leo', 'Sales (TZS)' => 'Mauzo (TZS)', 'Overdue' => 'Zilizochelewa',
        'Customer Management' => 'Usimamizi wa Wateja', 'Add Customer' => 'Ongeza mteja',
        'Search' => 'Tafuta', 'No customers found' => 'Hakuna wateja aliyepatikana',
        'Customer Code' => 'Namba ya mteja', 'Full Name' => 'Jina kamili', 'Name' => 'Jina',
        'Phone 1' => 'Simu 1', 'Phone 2' => 'Simu 2', 'Email' => 'Barua pepe',
        'Address' => 'Anwani', 'Status' => 'Hali', 'Actions' => 'Vitendo',
        'Active' => 'Hai', 'Inactive' => 'Si hai', 'Cancel' => 'Ghairi',
        'Save changes' => 'Hifadhi mabadiliko', 'Language' => 'Lugha', 'English' => 'Kiingereza',
        'Kiswahili' => 'Kiswahili', 'Account' => 'Akaunti', 'New password' => 'Nenosiri jipya',
        'Username' => 'Jina la mtumiaji', 'Show password' => 'Onyesha nenosiri',
        'Forgot password?' => 'Umesahau nenosiri?', 'Sign In' => 'Ingia', 'Sign in' => 'Ingia',
        'Customer' => 'Mteja', 'Delivery' => 'Uwasilishaji', 'Report' => 'Ripoti',
        'Add New Customer' => 'Ongeza Mteja Mpya', 'Edit Customer' => 'Hariri Mteja',
        'Update Customer' => 'Sasisha Mteja', 'Delete Customer' => 'Futa Mteja',
        'Service Interval Date' => 'Tarehe ya huduma inayofuata', 'Last Service' => 'Huduma ya mwisho',
        'Next Due' => 'Inayofuata', 'Setup Database' => 'Sanidi hifadhidata',
        'Go to Login Page' => 'Nenda kwenye ukurasa wa kuingia',
        'Please enter both username and password.' => 'Tafadhali jaza jina la mtumiaji na nenosiri.',
        'Full name and username are required.' => 'Jina kamili na jina la mtumiaji vinahitajika.',
        'Please choose a supported language.' => 'Tafadhali chagua lugha inayotumika.',
        'Settings updated successfully.' => 'Mipangilio imesasishwa kikamilifu.',
        'That username is already in use.' => 'Jina hilo la mtumiaji linatumika tayari.',
        'Unable to update settings. Please try again.' => 'Imeshindikana kusasisha mipangilio. Tafadhali jaribu tena.',
        'Welcome back,' => 'Karibu tena,', 'Action required' => 'Hatua inahitajika',
        'Everything looks on track' => 'Kila kitu kiko kwenye ratiba',
        'No overdue deliveries today. You are operating within schedule.' => 'Hakuna uwasilishaji uliochelewa leo. Unaendelea kulingana na ratiba.',
        'No reminder queue' => 'Hakuna foleni ya vikumbusho', 'next in queue' => 'anayefuata kwenye foleni',
        'All' => 'Zote', 'Reminders' => 'Vikumbusho', 'Delivered Today' => 'Waliofikishiwa Leo',
        'Within One Day' => 'Ndani ya Siku Moja', 'No customers in reminder window' => 'Hakuna wateja kwenye muda wa kikumbusho',
        'Pending delivery' => 'Uwasilishaji unasubiri', 'Due:' => 'Muda:', 'Last Staff:' => 'Mfanyakazi wa mwisho:',
        'No customers due today' => 'Hakuna wateja wanaostahili leo', 'Due Time:' => 'Muda wa huduma:',
        'No overdue customers' => 'Hakuna wateja waliochelewa', 'Overdue - not delivered' => 'Imechelewa - haijawasilishwa',
        'Overdue time:' => 'Muda wa kuchelewa:', 'Was Due:' => 'Ilikuwa:', 'No deliveries completed today' => 'Hakuna uwasilishaji uliokamilika leo',
        'Delivered' => 'Imefikishwa', 'Late by' => 'Imechelewa kwa', 'Delivered at:' => 'Imefikishwa saa:',
        'Staff:' => 'Mfanyakazi:', 'Quick Actions' => 'Vitendo vya Haraka', 'Record Delivery' => 'Rekodi Uwasilishaji',
        'Add Staff' => 'Ongeza Mfanyakazi', 'Generate Report' => 'Tengeneza Ripoti',
        'Staff Management' => 'Usimamizi wa Wafanyakazi', 'No staff members found' => 'Hakuna wafanyakazi waliopatikana',
        'Total Deliveries' => 'Jumla ya Uwasilishaji', 'Total Gallons' => 'Jumla ya Galoni',
        'Add New Staff' => 'Ongeza Mfanyakazi Mpya', 'Staff Code' => 'Namba ya mfanyakazi',
        'Staff Member' => 'Mfanyakazi', 'Edit Staff' => 'Hariri Mfanyakazi', 'Update Staff' => 'Sasisha Mfanyakazi',
        'Delete Staff' => 'Futa Mfanyakazi', 'Confirm Delete' => 'Thibitisha Kufuta', 'Delete' => 'Futa',
        'Delivery Recording' => 'Kurekodi Uwasilishaji', 'Apply Filters' => 'Tumia Vichujio',
        'All Customers' => 'Wateja Wote', 'All Staff' => 'Wafanyakazi Wote', 'Date/Time' => 'Tarehe/Saa',
        'Gallons' => 'Galoni', 'Price/Gallon' => 'Bei/Galoni', 'Total Amount' => 'Jumla ya Kiasi',
        'Recorded By' => 'Imerekodiwa na', 'No delivery records found' => 'Hakuna kumbukumbu za uwasilishaji',
        'Record New Delivery' => 'Rekodi Uwasilishaji Mpya', 'Select Customer' => 'Chagua Mteja',
        'Select Staff' => 'Chagua Mfanyakazi', 'Service Date/Time' => 'Tarehe/Saa ya Huduma',
        'Notes' => 'Maelezo', 'Optional notes' => 'Maelezo ya hiari', 'Gallons Delivered' => 'Galoni Zilizowasilishwa',
        'Price Per Gallon' => 'Bei kwa Galoni', 'Next Due Date:' => 'Tarehe Inayofuata:',
        "Will be calculated automatically based on customer's service interval." => 'Itahesabiwa moja kwa moja kulingana na muda wa huduma wa mteja.',
        'Are you sure you want to delete this delivery record?' => 'Una uhakika unataka kufuta kumbukumbu hii ya uwasilishaji?',
        'This action cannot be undone.' => 'Hatua hii haiwezi kutenduliwa.', 'Amount:' => 'Kiasi:',
        'All Reports' => 'Ripoti Zote', 'Served Customers Only' => 'Wateja Waliohudumiwa Pekee',
        'Due Customers Only' => 'Wateja Wanaostahili Pekee', 'Overdue Customers Only' => 'Wateja Waliochelewa Pekee',
        'Report Results' => 'Matokeo ya Ripoti', 'Print' => 'Chapisha', 'Download PDF' => 'Pakua PDF',
        'Service and Sales Report' => 'Ripoti ya Huduma na Mauzo', 'Period:' => 'Kipindi:', 'Generated:' => 'Imetengenezwa:',
        'Sales Summary' => 'Muhtasari wa Mauzo', 'Customers Served' => 'Wateja Waliohudumiwa',
        'Staff Name' => 'Jina la Mfanyakazi', 'Sales (TZS)' => 'Mauzo (TZS)', 'Served Customers' => 'Wateja Waliohudumiwa',
        'Due Customers' => 'Wateja Wanaostahili', 'Overdue Customers' => 'Wateja Waliochelewa', 'Customer Name' => 'Jina la Mteja',
        'Next Due Date' => 'Tarehe Inayofuata', 'Last Staff' => 'Mfanyakazi wa Mwisho',
        'Overdue Duration' => 'Muda wa Kuchelewa', 'Was Due' => 'Ilipaswa', 'Phone' => 'Simu',
        'Search by name, code, or phone...' => 'Tafuta kwa jina, namba au simu...',
        'Optional - leave empty for no specific due date' => 'Hiari - acha wazi bila tarehe maalum ya huduma',
        'Please fill in all required fields.' => 'Tafadhali jaza sehemu zote zinazohitajika.',
        'Please fill in all required fields with valid values.' => 'Tafadhali jaza sehemu zote zinazohitajika kwa thamani sahihi.',
        'Customer code already exists. Please use a different code.' => 'Namba ya mteja ipo tayari. Tafadhali tumia namba nyingine.',
        'Customer added successfully!' => 'Mteja ameongezwa kikamilifu!', 'Customer updated successfully!' => 'Mteja amesasishwa kikamilifu!',
        'Customer deleted successfully!' => 'Mteja amefutwa kikamilifu!',
        'Cannot delete customer with existing service records. Please deactivate instead.' => 'Haiwezi kufuta mteja mwenye kumbukumbu za huduma. Mzime badala yake.',
        'Staff code already exists. Please use a different code.' => 'Namba ya mfanyakazi ipo tayari. Tafadhali tumia namba nyingine.',
        'Staff member added successfully!' => 'Mfanyakazi ameongezwa kikamilifu!', 'Staff member updated successfully!' => 'Mfanyakazi amesasishwa kikamilifu!',
        'Staff member deleted successfully!' => 'Mfanyakazi amefutwa kikamilifu!',
        'Cannot delete staff member with existing delivery records. Please deactivate instead.' => 'Haiwezi kufuta mfanyakazi mwenye kumbukumbu za uwasilishaji. Mzime badala yake.',
        'Delivery recorded successfully!' => 'Uwasilishaji umerekodiwa kikamilifu!', 'Delivery record deleted successfully!' => 'Kumbukumbu ya uwasilishaji imefutwa kikamilifu!',
        'Please select both start and end dates.' => 'Tafadhali chagua tarehe ya kuanza na ya mwisho.',
        'Search...' => 'Tafuta...', 'Date:' => 'Tarehe:', 'Total (TZS)' => 'Jumla (TZS)',
    ];

    $output = str_replace('<html lang="en">', '<html lang="sw">', $output);
    $output = str_replace(array_keys($replacements), array_values($replacements), $output);
    $output = str_replace([
        'Mteja Jina', 'Mfanyakazi Jina', 'Jina Mteja', 'Jina Mfanyakazi',
        'Namba ya Mteja', 'Mfanyakazi Code', 'Jumla Mauzo (TZS)'
    ], [
        'Jina la Mteja', 'Jina la Mfanyakazi', 'Jina la Mteja', 'Jina la Mfanyakazi',
        'Namba ya mteja', 'Namba ya mfanyakazi', 'Jumla ya Mauzo (TZS)'
    ], $output);
    $output = preg_replace('/(\d+) customer\(s\) need attention before the next route\./', '$1 wateja wanahitaji uangalizi kabla ya safari inayofuata.', $output);
    $output = preg_replace('/Welcome back,\s*([^<]+)!/', 'Karibu tena, $1!', $output);
    $output = preg_replace('/Error adding customer:/', 'Hitilafu ya kuongeza mteja:', $output);
    $output = preg_replace('/Error updating customer:/', 'Hitilafu ya kusasisha mteja:', $output);
    $output = preg_replace('/Error deleting customer:/', 'Hitilafu ya kufuta mteja:', $output);
    $output = preg_replace('/Error adding staff:/', 'Hitilafu ya kuongeza mfanyakazi:', $output);
    $output = preg_replace('/Error updating staff:/', 'Hitilafu ya kusasisha mfanyakazi:', $output);
    $output = preg_replace('/Error deleting staff:/', 'Hitilafu ya kufuta mfanyakazi:', $output);
    $output = preg_replace('/Error recording delivery:/', 'Hitilafu ya kurekodi uwasilishaji:', $output);
    $output = preg_replace('/Error deleting delivery:/', 'Hitilafu ya kufuta uwasilishaji:', $output);
    return $output;
}

ob_start('localizePageOutput');
