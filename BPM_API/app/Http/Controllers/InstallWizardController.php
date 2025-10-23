<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use App\Models\User; // Assuming a standard User model exists

class InstallWizardController extends Controller
{
    private const INSTALL_LOCK_FILE = '.installed';

    // Step 1: Check System Status & Show Config Form
    public function showSetupForm()
    {
        if (File::exists(base_path(self::INSTALL_LOCK_FILE))) {
            return response()->json(['message' => 'System is already installed.'], 403);
        }
        // In a real API wizard, this would return a view or a JSON endpoint
        // describing required fields. Here, we define the required data:
        return response()->json([
            'status' => 'pending',
            'required_steps' => ['db_config', 'admin_user'],
            'message' => 'Provide DB details and initial Admin credentials.'
        ]);
    }

    // Step 2: Process Configuration & Finalize
    public function runSetup(Request $request)
    {
        if (File::exists(base_path(self::INSTALL_LOCK_FILE))) {
            return response()->json(['message' => 'System is already installed.'], 403);
        }

        $request->validate([
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
            'admin_name' => 'required|string',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|min:8',
        ]);

        try {
            // A. Update .env with DB Credentials
            $this->updateEnvironmentFile($request);

            // B. Run Migrations
            Artisan::call('migrate', ['--force' => true]);

            // C. Create Admin User (using the standard Laravel User model)
            $user = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                // Optionally add a role/flag for admin status
            ]);

            // D. Create Lock File to Prevent Re-installation
            File::put(base_path(self::INSTALL_LOCK_FILE), now()->toDateTimeString());

            return response()->json([
                'message' => 'Installation complete.',
                'admin_user_id' => $user->id,
                'next_step' => '/api/documentation' // Redirect to Swagger UI
            ], 200);

        } catch (\Exception $e) {
            // Log the error and return a failure message
            return response()->json(['message' => 'Installation failed.', 'error' => $e->getMessage()], 500);
        }
    }

    private function updateEnvironmentFile(Request $request)
    {
        $env = File::get(base_path('.env'));
        $replacements = [
            'DB_DATABASE=' => 'DB_DATABASE=' . $request->db_database,
            'DB_USERNAME=' => 'DB_USERNAME=' . $request->db_username,
            'DB_PASSWORD=' => 'DB_PASSWORD=' . ($request->db_password ?? ''),
            'DB_HOST=' => 'DB_HOST=' . ($request->db_host ?? '127.0.0.1'),
        ];

        foreach ($replacements as $key => $value) {
            $env = preg_replace("/^{$key}.*$/m", $value, $env);
        }

        File::put(base_path('.env'), $env);
        // Reload environment variables (crucial for Artisan migrate to work with new config)
        Artisan::call('config:clear');
    }
}
