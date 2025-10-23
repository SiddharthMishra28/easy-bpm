# 🚀 BPM API - Business Process Management Rule Engine (Laravel/PHP)

A scalable, version-controlled Business Process Management (BPM) API backend built with Laravel. This API utilizes an immutable flow versioning system and a powerful nested JSON rule engine for dynamic ticket advancement.

## ✨ Features

* **Immutable Flow Versioning:** Changes to a flow create a new version, ensuring in-flight tickets are never affected.
* **Nested Rule Engine:** Evaluate complex conditions (AND/OR logic) stored as JSON to determine ticket routing.
* **Domain Agnostic:** Tickets (`ticket_data`) use a flexible JSON payload.
* **Installation Wizard:** Easy initial setup for database and administrator creation.
* **API Documentation:** Built-in Swagger/OpenAPI documentation.

---

## 🛠️ Prerequisites

* **PHP:** >= 8.2 (with JSON, Mbstring, PDO extensions)
* **Composer:** Latest version
* **Database:** MySQL >= 5.7 or MariaDB >= 10.2
* **Web Server:** Apache (with `mod_rewrite` enabled) or Nginx

---

## 💻 1. Local Development Setup (Recommended: Docker via Laravel Sail)

For the quickest and most consistent setup, use Docker and Laravel Sail.

### A. Docker / Laravel Sail Setup

1.  **Install/Configure Docker:** Ensure Docker Desktop is running.
2.  **Run Sail Setup Script:** Execute the following in your terminal. This command will set up the project and its containers (PHP, MySQL, etc.).

    ```bash
    # Navigate to the project directory
    cd BPM_API

    # Start the Docker containers (runs in background)
    ./vendor/bin/sail up -d

    # Install dependencies inside the container
    ./vendor/bin/sail composer install
    ```

3.  **Execute the Installation Wizard:**
    * **Access URL:** `http://localhost/` (or `http://localhost:80` if not using a virtual host).
    * **Action:** Follow the instructions in Section 4.

### B. Traditional VPS / Dedicated Server Setup (LEMP Stack)

1.  **Clone Repository & Install Dependencies:**

    ```bash
    # Install dependencies
    composer install --optimize-autoloader --no-dev

    # Copy the environment file
    cp .env.example .env

    # Generate the application key
    php artisan key:generate
    ```

2.  **Configure `.env`:** Manually edit the `.env` file to match your MySQL credentials and server environment settings.

    ```ini
    # Example .env settings
    APP_URL=[https://your-domain.com](https://your-domain.com)
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=bpm_db
    DB_USERNAME=your_db_user
    DB_PASSWORD=your_db_pass
    ```

3.  **Web Server Configuration (Nginx Example):** Configure your web server to point the root to the **`public`** directory and handle clean URLs.

    ```nginx
    server {
        listen 80;
        server_name your-domain.com;
        root /path/to/BPM_API/public;

        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";

        index index.php;

        charset utf-8;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # Adjust PHP version
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
        }

        # Deny access to .env and other hidden files
        location ~ /\.env {
            deny all;
        }
    }
    ```

4.  **Execute the Installation Wizard:**
    * **Access URL:** `https://your-domain.com/`
    * **Action:** Follow the instructions in Section 4.

### C. Shared Hosting Setup

1.  **Transfer Files:** Upload all files (including the hidden `.env` and `.htaccess`) to your hosting account's directory.
2.  **Public Folder Handling:**
    * **Option 1 (Preferred):** Point your domain's document root to the **`public`** folder.
    * **Option 2 (Common Shared Host Hack):** Move the contents of the `public` folder to the root (e.g., `public_html`), then update the paths in `index.php` and manually configure your `.env` file. (Not recommended, but necessary on some hosts).
3.  **Composer (If Available):** If your host supports SSH/Composer, follow the VPS steps.
4.  **Manual `.env` & Dependencies:** If Composer is unavailable, upload the entire project (including `vendor` directory) after running `composer install --no-dev` locally, and manually configure the `.env` file.
5.  **Execute the Installation Wizard:**
    * **Access URL:** Your domain root (e.g., `https://my-flow-engine.com/`)
    * **Action:** Follow the instructions in Section 4.

---

## ⚙️ 2. Running the Installation Wizard

**Do NOT** run `php artisan migrate` manually. The wizard handles this for you.

1.  **Initial Access:** Navigate to your application's root URL (`/`). The system will redirect you to the setup form.
2.  **Submit Setup Form (API Call):** Send a `POST` request to the `/install` endpoint with the following body to configure the database and create the first administrator.

    | Field | Description | Example |
    | :--- | :--- | :--- |
    | `db_database` | MySQL database name | `bpm_db` |
    | `db_username` | MySQL username | `root` |
    | `db_password` | MySQL password | `secret_password` |
    | `admin_name` | Initial Administrator Name | `Jane Doe` |
    | `admin_email` | Initial Administrator Email | `admin@company.com` |
    | `admin_password` | Administrator Password (min 8 chars) | `SuperSecurePwd123!` |

3.  **Result:** Upon success, the wizard will:
    * Update your **`.env`** file.
    * Run all database migrations.
    * Create the Admin user.
    * Create the **`.installed`** lock file.

---

## 🔑 3. API Usage and Documentation

All API routes are protected by Laravel Sanctum. You must authenticate first.

### A. Authentication (Sanctum Tokens)

1.  **Issue Token:** Send a `POST` request to an endpoint (you must implement a simple token generation route, e.g., `/api/tokens`) using the admin email/password.
2.  **Authorization Header:** Use the returned token for all subsequent API requests.

    ```
    Authorization: Bearer <YOUR_SANCTUM_TOKEN>
    Accept: application/json
    ```

### B. API Documentation (Swagger UI)

Access the live documentation to see all endpoints, request/response schemas, and the required JSON formats.

* **Documentation URL:** `http://your-domain.com/api/documentation`

### C. Core Usage Flow

#### 1. Define a Flow (`POST /api/flows`)

Create the master flow and its first immutable version. The `stages` array is nested inside the request body.

**Example Request Body:**

```json
{
    "name": "HR Onboarding Flow",
    "description": "Standard process for new hires.",
    "is_active": true,
    "stages": [
        {
            "name": "Initiation",
            "sequence": 1,
            "is_start_stage": true,
            "tasks": [{"name": "Verify Documents", "is_mandatory": true}],
            "rules": [
                {
                    "rule_type": "ROUTING",
                    "rule_definition": {
                        "rule_name": "Move to Contract Signing",
                        "groups": [
                            {
                                "operator": "AND",
                                "children": [
                                    {"type": "CONDITION", "field": "ticket_data.priority", "operator": "EQUALS", "value": "High"},
                                    {"type": "CHECKPOINT", "condition": "IS_COMPLETED", "task_id": 1}
                                ]
                            }
                        ],
                        "actions": [
                            {"action_type": "ROUTE_TO_STAGE", "target_stage_id": 2}
                        ]
                    }
                }
            ]
        },
        {"name": "Contract Signing", "sequence": 2},
        {"name": "Completion", "sequence": 3, "is_end_stage": true}
    ]
}
```

#### 2\. Create a Ticket (`POST /api/tickets`)

Start an execution instance of the latest flow version.

**Example Request Body:**

```json
{
    "flow_id": 1,
    "ticket_data": {
        "candidate_name": "Alice Smith",
        "priority": "High",
        "department": "Engineering"
    }
}
```

#### 3\. Advance the Ticket (`POST /api/tickets/{id}/advance`)

This is the core action. It performs mandatory task validation, executes the nested rules against the `ticket_data`, and routes the ticket accordingly.

  * **Before Advancement:** Ensure mandatory tasks (e.g., Task ID 1 in the example above) are marked complete via `PUT /api/tickets/{id}/tasks/{task_id}`.
  * **Response:** If successful (a rule matches), the ticket's `current_stage_id` is updated. If failed, you receive a `400` (missing checkpoints) or `409` (no routing rules matched).

-----

## 🐛 4. Troubleshooting

| Issue | Potential Cause | Solution |
| :--- | :--- | :--- |
| **Blank Page / 500 Error** | File permissions or Web Server configuration. | Ensure **storage** and **bootstrap/cache** folders are writable by the web server (e.g., `chmod -R 775 storage bootstrap/cache`). Check your web server (Nginx/Apache) config points to the `public` folder. |
| **404 Not Found (API)** | Clean URLs/URL Rewriting is not active. | Ensure `mod_rewrite` is enabled on Apache or Nginx config includes `try_files $uri $uri/ /index.php?$query_string;`. |
| **`migrate` fails in wizard** | Incorrect database credentials in `.env` or during setup. | Double-check the MySQL credentials provided in the setup form. Ensure the database user has sufficient privileges (CREATE, SELECT, INSERT, etc.). |
| **"System already installed"** | The lock file exists. | Delete the **`.installed`** file from the root directory to run the wizard again (use only in development). |
