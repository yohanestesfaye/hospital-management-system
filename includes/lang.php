<?php
/**
 * Language Translation System
 * Supports: English (en) and Amharic (am)
 */

// Get language from session or default to English
if (!isset($_SESSION)) {
    session_start();
}

$default_lang = 'en';
$current_lang = $_SESSION['lang'] ?? $default_lang;

// Validate language
$supported_langs = ['en', 'am'];
if (!in_array($current_lang, $supported_langs, true)) {
    $current_lang = $default_lang;
}

// Language translations
$translations = [
    'en' => [
        'dashboard' => 'Dashboard',
        'staff' => 'Staff',
        'department' => 'Department',
        'doctor' => 'Doctor',
        'patient' => 'Patient',
        'nurse' => 'Nurse',
        'pharmacy' => 'Pharmacy',
        'manage_pharmacy' => 'Manage Pharmacy',
        'manage_pharmacists' => 'Manage Pharmacists',
        'laboratorist' => 'Laboratorist',
        'accountant' => 'Accountant',
        'manage_staff' => 'Manage Staff',
        'manage_department' => 'Manage Department',
        'manage_doctor' => 'Manage Doctor',
        'manage_patient' => 'Manage Patient',
        'manage_nurse' => 'Manage Nurse',
        'manage_pharmacist' => 'Manage Pharmacist',
        'manage_accountant' => 'Manage Accountant',
        'manage_laboratorist' => 'Manage Laboratorist',
        'select_language' => 'Select Language',
        'account' => 'Account',
        'change_password' => 'Change Password',
        'logout' => 'Logout',
        'hospital_management_system' => 'Hospital Management System',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'search' => 'Search',
        'create' => 'Create',
        'update' => 'Update',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'name' => 'Name',
        'username' => 'Username',
        'password' => 'Password',
        'options' => 'Options',
        'list' => 'List',
        'created_successfully' => 'created successfully',
        'updated_successfully' => 'updated successfully',
        'deleted' => 'deleted',
        'no_items_found' => 'No items found',
        'monitor_hospital' => 'Monitor Hospital',
        'settings' => 'Settings',
        'profile' => 'Profile',
        'patients' => 'Patients',
        'manage_appointments' => 'Manage Appointments',
        'manage_prescription' => 'Manage Prescription',
        'medical_records' => 'Medical Records',
        'lab_results' => 'Lab Results',
        'order_lab_test' => 'Order Lab Test',
        'doctors' => 'Doctors',
        'pharmacy_vendors' => 'Pharmacy Vendors',
        'pharm_categories' => 'Pharm Categories',
        'pharmaceuticals' => 'Pharmaceuticals',
        'appointments' => 'Appointments',
        'book_appointment' => 'Book Appointment',
        'verify_appointments' => 'Verify Appointments',
        'billing' => 'Billing',
    ],
    'am' => [
        'dashboard' => 'ዳሽቦርድ',
        'staff' => 'ሰራተኞች',
        'department' => 'ክፍል',
        'doctor' => 'ዶክተር',
        'patient' => 'ታካሚ',
        'nurse' => 'ነርስ',
        'pharmacy' => 'ፋርማሲ',
        'manage_pharmacy' => 'ፋርማሲ ማስተዳደር',
        'manage_pharmacists' => 'ፋርማሲስቶችን ማስተዳደር',
        'laboratorist' => 'ላቦራቶሪስት',
        'accountant' => 'አካውንታንት',
        'manage_staff' => 'ሰራተኞችን ማስተዳደር',
        'manage_department' => 'ክፍል ማስተዳደር',
        'manage_doctor' => 'ዶክተር ማስተዳደር',
        'manage_patient' => 'ታካሚ ማስተዳደር',
        'manage_nurse' => 'ነርስ ማስተዳደር',
        'manage_pharmacist' => 'ፋርማሲስት ማስተዳደር',
        'manage_accountant' => 'አካውንታንት ማስተዳደር',
        'manage_laboratorist' => 'ላቦራቶሪስት ማስተዳደር',
        'select_language' => 'ቋንቋ ይምረጡ',
        'account' => 'መለያ',
        'change_password' => 'የይለፍ ቃል ለውጥ',
        'logout' => 'ውጣ',
        'hospital_management_system' => 'የሆስፒታል አስተዳደር ስርዓት',
        'add' => 'ጨምር',
        'edit' => 'አርም',
        'delete' => 'ሰርዝ',
        'search' => 'ፈልግ',
        'create' => 'ፍጠር',
        'update' => 'አዘምን',
        'cancel' => 'ተወው',
        'save' => 'አስቀምጥ',
        'name' => 'ስም',
        'username' => 'የተጠቃሚ ስም',
        'password' => 'የይለፍ ቃል',
        'options' => 'አማራጮች',
        'list' => 'ዝርዝር',
        'created_successfully' => 'በተሳካ ሁኔታ ተፈጥሯል',
        'updated_successfully' => 'በተሳካ ሁኔታ ተዘምኗል',
        'deleted' => 'ተሰርዟል',
        'no_items_found' => 'ምንም ንጥሎች አልተገኙም',
        'monitor_hospital' => 'ሆስፒታል እርምጃ አሳሽ',
        'settings' => 'ቅንብሮች',
        'profile' => 'መገለጫ',
        'patients' => 'ታካሚዎች',
        'manage_appointments' => 'ቀጠሮዎችን አስተዳድር',
        'manage_prescription' => 'መመሪያ አስተዳድር',
        'medical_records' => 'ሕክምና መዝገቦች',
        'lab_results' => 'የላብ ውጤቶች',
        'order_lab_test' => 'Order Lab Test',
        'doctors' => 'ዶክተሮች',
        'pharmacy_vendors' => 'የፋርማሲ ሻጮች',
        'pharm_categories' => 'የመድሃኒት ምድቦች',
        'pharmaceuticals' => 'መድሃኒቶች',
        'appointments' => 'ቀጠሮዎች',
        'book_appointment' => 'ቀጠሮ አቅዱ',
        'verify_appointments' => 'ቀጠሮዎችን አረጋግጥ',
        'billing' => 'ክፍያ',
    ]
];

/**
 * Get translation for a key
 * @param string $key Translation key
 * @param string|null $lang Language code (optional, uses current language if not provided)
 * @return string Translated text or key if translation not found
 */
function t($key, $lang = null) {
    global $translations, $current_lang;
    $lang = $lang ?? $current_lang;
    
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    
    // Fallback to English if translation not found
    if ($lang !== 'en' && isset($translations['en'][$key])) {
        return $translations['en'][$key];
    }
    
    // Return key if no translation found
    return $key;
}

/**
 * Get current language code
 * @return string Language code (en, am)
 */
function get_current_lang() {
    global $current_lang;
    return $current_lang;
}

/**
 * Set language preference
 * @param string $lang Language code
 */
function set_language($lang) {
    global $supported_langs;
    if (in_array($lang, $supported_langs, true)) {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['lang'] = $lang;
    }
}

