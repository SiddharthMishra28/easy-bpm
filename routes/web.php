<?php

use App\Http\Controllers\InstallWizardController;
use Illuminate\Support\Facades\Route;

// This will be the first page users see on a fresh install
Route::get('/', [InstallWizardController::class, 'showSetupForm'])->name('install.start');
Route::post('/install', [InstallWizardController::class, 'runSetup'])->name('install.run');

// Redirect all API traffic to the wizard if the lock file doesn't exist
// A robust implementation would use a dedicated middleware on the API routes/group.
Route::fallback(function() {
    if (!File::exists(base_path('.installed'))) {
        return redirect()->route('install.start');
    }
    return response()->json(['message' => 'Not Found'], 404);
});
