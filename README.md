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

The entire lifecycle of a business process is managed through a series of API calls. Here’s a typical sequence:

#### 1. Define a Flow (`POST /api/flows`)

First, create a "master" flow, which acts as a container for its versions. This request creates the flow and its first immutable version.

**Example Request Body:**
```json
{
    "name": "HR Onboarding Flow",
    "description": "Standard process for new hires.",
    "stages": [
        {
            "name": "Initiation",
            "sequence": 1,
            "is_start_stage": true,
            "tasks": [{"name": "Verify Candidate Documents", "is_mandatory": true}],
            "rules": [
                {
                    "rule_type": "ROUTING",
                    "rule_definition_json": "{\"rule_name\":\"High Priority Routing\",\"groups\":[{\"operator\":\"AND\",\"children\":[{\"type\":\"CONDITION\",\"field\":\"ticket_data.priority\",\"operator\":\"EQUALS\",\"value\":\"High\"},{\"type\":\"CHECKPOINT\",\"condition\":\"IS_COMPLETED\",\"task_id\":1}]}],\"actions\":[{\"action_type\":\"ROUTE_TO_STAGE\",\"target_stage_id\":2}]}"
                }
            ]
        },
        {"name": "Contract Signing", "sequence": 2},
        {"name": "Completion", "sequence": 3, "is_end_stage": true}
    ]
}
```
**Success Response (`201 Created`):**
The API returns the created flow, including its `latest_version`. Note the `id` of the flow and the `task_id` for future requests.

#### 2. Create a Ticket (`POST /api/tickets`)
Next, start a new process instance (a "ticket") based on the `flow_id`. The ticket will automatically use the flow's `latest_version_id`.

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
**Success Response (`201 Created`):**
The response contains the new ticket, which is now at the defined "start stage" of the flow.

#### 3. Update Ticket Data (`PUT /api/tickets/{ticket}/data`)
As the process evolves, you may need to add or modify the ticket's data. This endpoint merges the new JSON data with the existing `ticket_data`.

**Example Request Body:**
```json
{
    "data": {
        "contract_type": "Full-Time",
        "start_date": "2024-01-15"
    }
}
```
**Success Response (`200 OK`):**
The ticket is returned with the updated `ticket_data`.

#### 4. Complete a Mandatory Task (`PUT /api/tickets/{ticket}/tasks/{taskId}`)
Before a ticket can be advanced, all mandatory tasks in its current stage must be completed.

**Example Request Body:**
```json
{
    "is_complete": true
}
```
**Success Response (`200 OK`):**
The task status is updated, and the ticket is now eligible for advancement.

#### 5. Advance the Ticket (`POST /api/tickets/{ticket}/advance`)
This is the core action. The API evaluates the routing rules of the ticket's current stage against its data.

**Request Body:** (No body required)

**Success Response (`200 OK`):**
If a rule's conditions are met, the ticket is moved to the next stage.
```json
{
    "message": "Ticket successfully advanced.",
    "current_stage_id": 2,
    "status": "IN_PROGRESS",
    "summary": {
        "rule_passed": "High Priority Routing"
    }
}
```

**Failure Response (`409 Conflict`):**
If no rules match, the ticket remains in the current stage.
```json
{
    "message": "Advancement failed. No routing rules qualified for the ticket data.",
    "routing_failures": [
        {
            "rule": "High Priority Routing",
            "errors": [
                "Group 'Priority Check' failed: Condition ticket_data.priority EQUALS 'High' was not met."
            ]
        }
    ]
}
```

-----

## 🚀 4. Advanced Usage

### A. The Rule Engine Explained

The power of this API lies in its flexible, nested rule engine. The `rule_definition_json` is a JSON string that defines the conditions for an action.

**Core Concepts:**

*   **Groups:** A group contains one or more `children` and has an `operator` (`AND` or `OR`). All conditions/checkpoints within an `AND` group must be true. At least one must be true for an `OR` group. Groups can be nested to create complex logic.
*   **Conditions:** A `CONDITION` evaluates a field from the `ticket_data` against a value.
    *   **Operators:** `EQUALS`, `NOT_EQUALS`, `GREATER_THAN`, `LESS_THAN`, `CONTAINS`.
*   **Checkpoints:** A `CHECKPOINT` verifies if a task is completed.
*   **Actions:** If a rule's top-level group evaluates to true, the defined `actions` are executed.
    *   `ROUTE_TO_STAGE`: Moves the ticket to a new `target_stage_id`.
    *   `SET_TICKET_STATUS`: Changes the ticket's status (e.g., to `COMPLETED`, `CANCELLED`).

**Complex Example: An IT Support Flow**

This rule routes a ticket based on its priority and whether a diagnostic task has been completed.

```json
{
    "rule_name": "Tier 2 Escalation Rule",
    "groups": [
        {
            "operator": "AND",
            "children": [
                {
                    "type": "CONDITION",
                    "field": "ticket_data.category",
                    "operator": "EQUALS",
                    "value": "Hardware"
                },
                {
                    "type": "CHECKPOINT",
                    "condition": "IS_COMPLETED",
                    "task_id": 5
                },
                {
                    "type": "GROUP",
                    "operator": "OR",
                    "children": [
                        {"type": "CONDITION", "field": "ticket_data.priority", "operator": "EQUALS", "value": "Urgent"},
                        {"type": "CONDITION", "field": "ticket_data.is_vip", "operator": "EQUALS", "value": true}
                    ]
                }
            ]
        }
    ],
    "actions": [
        {"action_type": "ROUTE_TO_STAGE", "target_stage_id": 4}
    ]
}
```
This rule will only pass if: The category is "Hardware" **AND** Task 5 is complete **AND** (the priority is "Urgent" **OR** the user is a VIP).

### B. Flow Versioning

You cannot directly edit a flow's stages, tasks, or rules after creation. Instead, you update the flow, which creates a *new, immutable version*.

*   **`PUT /api/flows/{id}`:** When you use this endpoint, the master flow is updated, and a new `flow_version` is created and set as the `latest_version_id`.
*   **In-flight tickets are unaffected:** Existing tickets will continue to follow the version they were created with, preventing disruptions.
*   **New tickets use the latest version:** All new tickets created via `POST /api/tickets` will automatically use the new version.
*   **View all versions:** You can retrieve a history of all versions for a flow using `GET /api/flows/{id}/versions`.

### C. Full Example: Employee Onboarding

Here is a more complete, multi-stage example demonstrating a real-world use case.

**1. Define the Flow (`POST /api/flows`)**
```json
{
    "name": "Employee Onboarding",
    "description": "Comprehensive onboarding process for new hires.",
    "stages": [
        // Stage 1: Document Collection
        {
            "name": "Document Collection",
            "sequence": 1,
            "is_start_stage": true,
            "tasks": [
                {"name": "Submit Signed Offer Letter", "is_mandatory": true}, // task_id: 1
                {"name": "Submit I-9 Form", "is_mandatory": true}              // task_id: 2
            ],
            "rules": [{
                "rule_type": "ROUTING",
                "rule_definition_json": "{\"rule_name\":\"Docs Submitted\",\"groups\":[{\"operator\":\"AND\",\"children\":[{\"type\":\"CHECKPOINT\",\"condition\":\"IS_COMPLETED\",\"task_id\":1},{\"type\":\"CHECKPOINT\",\"condition\":\"IS_COMPLETED\",\"task_id\":2}]}],\"actions\":[{\"action_type\":\"ROUTE_TO_STAGE\",\"target_stage_id\":2}]}"
            }]
        },
        // Stage 2: IT Provisioning
        {
            "name": "IT Provisioning",
            "sequence": 2,
            "tasks": [
                {"name": "Create Email Account", "is_mandatory": true}, // task_id: 3
                {"name": "Ship Laptop", "is_mandatory": false}         // task_id: 4
            ],
            "rules": [{
                "rule_type": "ROUTING",
                "rule_definition_json": "{\"rule_name\":\"Provisioning Complete\",\"groups\":[{\"operator\":\"AND\",\"children\":[{\"type\":\"CHECKPOINT\",\"condition\":\"IS_COMPLETED\",\"task_id\":3}]}],\"actions\":[{\"action_type\":\"ROUTE_TO_STAGE\",\"target_stage_id\":3}]}"
            }]
        },
        // Stage 3: Onboarding Complete
        {
            "name": "Onboarding Complete",
            "sequence": 3,
            "is_end_stage": true,
            "rules": [{
                "rule_type": "STATUS_UPDATE",
                "rule_definition_json": "{\"rule_name\":\"Finalize Ticket\",\"groups\":[{\"operator\":\"AND\",\"children\":[]}],\"actions\":[{\"action_type\":\"SET_TICKET_STATUS\",\"status_value\":\"COMPLETED\"}]}"
            }]
        }
    ]
}
```

**2. Run the Process**

*   A new hire ticket is created (`POST /api/tickets`).
*   HR uploads documents and marks tasks 1 and 2 as complete (`PUT /api/tickets/{id}/tasks/{taskId}`).
*   HR advances the ticket (`POST /api/tickets/{id}/advance`), which moves it to Stage 2.
*   The IT department creates the email and marks task 3 as complete.
*   IT advances the ticket, moving it to Stage 3.
*   A final advance call is made. The `STATUS_UPDATE` rule in Stage 3 has no conditions, so it always passes, changing the ticket status to `COMPLETED`.

## ⚠️ 5. Limitations and Considerations

*   **No Granular Stage/Task Editing:** The current version of the API treats a flow definition as a single unit. You cannot add, remove, or edit individual stages, tasks, or rules via dedicated endpoints. To change a flow, you must re-submit the entire flow structure using the `PUT /api/flows/{id}` endpoint, which will create a new version.
*   **Flow Deletion Constraint:** A master flow cannot be deleted if its latest version has any "IN_PROGRESS" tickets. This is a safety measure to prevent the orphaning of active processes.
*   **Authentication Scoped to a Single User:** The installation wizard creates a single administrator. The API does not include built-in functionality for creating additional users, roles, or permissions. All API actions are performed with the same level of authorization.
*   **Error Handling:** While the API provides validation and rule evaluation feedback, it is recommended to build robust error handling and logging on the client-side to manage scenarios like network failures or unexpected `500` server errors.

## 🐛 6. Troubleshooting

| Issue | Potential Cause | Solution |
| :--- | :--- | :--- |
| **Blank Page / 500 Error** | File permissions or Web Server configuration. | Ensure **storage** and **bootstrap/cache** folders are writable by the web server (e.g., `chmod -R 775 storage bootstrap/cache`). Check your web server (Nginx/Apache) config points to the `public` folder. |
| **404 Not Found (API)** | Clean URLs/URL Rewriting is not active. | Ensure `mod_rewrite` is enabled on Apache or Nginx config includes `try_files $uri $uri/ /index.php?$query_string;`. |
| **`migrate` fails in wizard** | Incorrect database credentials in `.env` or during setup. | Double-check the MySQL credentials provided in the setup form. Ensure the database user has sufficient privileges (CREATE, SELECT, INSERT, etc.). |
| **"System already installed"** | The lock file exists. | Delete the **`.installed`** file from the root directory to run the wizard again (use only in development). |
