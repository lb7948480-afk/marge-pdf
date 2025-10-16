<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfMergeController;

// Endpoint de API: recebe uma lista de URLs de PDFs e retorna um único PDF mesclado
Route::post('/merge-pdfs', [PdfMergeController::class, 'mergeFromUrls']);