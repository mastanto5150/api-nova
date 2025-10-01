<?php
/**
 * ===============================================================
 * API ENTRY POINT & MAIN ROUTER
 * ===============================================================
 * Semua request ke API akan masuk melalui file ini.
 * File ini bertugas untuk:
 * 1. Memuat konfigurasi utama dan koneksi database.
 * 2. Mengarahkan request ke endpoint yang sesuai.
 */

// [IMPROVEMENT] Memuat satu file konfigurasi terpusat.
// Ini akan menyediakan koneksi $conn dan fungsi helper json_response().
require_once __DIR__ . '/config.php';

// Mendapatkan path URL untuk routing
$path = isset($_GET['path']) ? $_GET['path'] : '';
$path_parts = explode('/', rtrim($path, '/'));
$resource = $path_parts[0] ?? null;

// Routing sederhana berdasarkan bagian pertama dari URL
switch ($resource) {
    case 'auth':
        if (isset($path_parts[1]) && $path_parts[1] == 'login') {
            require __DIR__ . '/auth/login.php';
        } else {
            json_response(404, ['message' => 'Endpoint autentikasi tidak ditemukan']);
        }
        break;

    case 'users':
        require __DIR__ . '/users/index.php';
        break;

    case 'projects':
        require __DIR__ . '/projects/index.php';
        break;

    // Menangani semua request ke endpoint yang tidak terdaftar
    default:
        json_response(404, ['message' => 'Endpoint tidak ditemukan']);
        break;
}