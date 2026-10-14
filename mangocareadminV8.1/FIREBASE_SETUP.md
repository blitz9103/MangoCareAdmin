# Firebase Setup Guide

This guide will help you set up Firebase for the MangoCare Admin System.

## Step 1: Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project" or select an existing project
3. Enter project name (e.g., "mangocare-admin")
4. Follow the setup wizard
5. Disable Google Analytics (optional) or enable if needed

## Step 2: Enable Realtime Database

1. In Firebase Console, go to "Realtime Database"
2. Click "Create Database"
3. Choose a location (select closest to your region)
4. Start in **test mode** for development (or production mode with rules)

## Step 3: Get Database URL

1. In Realtime Database, you'll see the database URL
2. It looks like: `https://your-project-id-default-rtdb.firebaseio.com/`
3. Copy this URL

## Step 4: Get API Key (Alternative Method)

Since we're using REST API, you have two options:

### Option A: Use Database Secret (Legacy - Not Recommended for Production)

1. Go to Project Settings (gear icon)
2. Go to "Service Accounts" tab
3. Click "Database secrets"
4. Copy the database secret

### Option B: Use Firebase Admin SDK (Recommended)

For production, you should use Firebase Admin SDK with service account:

1. Go to Project Settings > Service Accounts
2. Click "Generate new private key"
3. Download the JSON file
4. Use this with Firebase Admin SDK in PHP

## Step 5: Configure Database Rules

Go to Realtime Database > Rules and set:

### Development Rules (Open Access)
```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

### Production Rules (Recommended)
```json
{
  "rules": {
    "scans": {
      ".read": true,
      ".write": true
    },
    "trees": {
      ".read": true,
      ".write": true
    },
    "spray_schedules": {
      ".read": true,
      ".write": true
    },
    "spray_history": {
      ".read": true,
      ".write": true
    }
  }
}
```

## Step 6: Update config.php

Edit `config.php` with your Firebase credentials:

```php
// For REST API (using Database Secret)
define('FIREBASE_API_KEY', 'YOUR_DATABASE_SECRET');

// Or for Admin SDK (recommended)
// You'll need to install Firebase Admin SDK via Composer
```

## Step 7: Test Connection

1. Start your XAMPP server
2. Navigate to `http://localhost/mangocareadmin`
3. Login with admin credentials
4. Check if data loads (if you have test data)

## Alternative: Using Firebase Admin SDK

For better security and features, consider using Firebase Admin SDK:

### Installation

```bash
composer require kreait/firebase-php
```

### Updated config.php Example

```php
require_once __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

$serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/path/to/serviceAccountKey.json');
$firebase = (new Factory)
    ->withServiceAccount($serviceAccount)
    ->create();

$database = $firebase->getDatabase();
```

## Database Structure

The system expects this structure in Firebase:

```
mangocare-admin/
├── scans/
│   └── [auto-generated-id]/
│       ├── tree_id: "TREE-001"
│       ├── disease: "Anthracnose"
│       ├── location: "Farm A"
│       ├── timestamp: 1234567890000
│       ├── confidence: 95.5
│       └── image_url: "https://..."
├── trees/
│   └── TREE-001/
│       ├── tree_id: "TREE-001"
│       ├── location: "Farm A"
│       ├── has_qr: true
│       └── created_at: 1234567890000
├── spray_schedules/
│   └── [auto-generated-id]/
│       ├── tree_id: "TREE-001"
│       ├── location: "Farm A"
│       ├── spray_date: "2024-01-15"
│       ├── spray_time: "10:00"
│       ├── status: "pending"
│       └── created_at: 1234567890000
└── spray_history/
    └── [auto-generated-id]/
        ├── tree_id: "TREE-001"
        ├── timestamp: 1234567890000
        ├── status: "completed"
        └── notes: "Sprayed successfully"
```

## Testing Firebase Connection

Create a test file `test-firebase.php`:

```php
<?php
require_once 'config.php';

$firebase = new Firebase();

// Test write
$testData = [
    'test' => 'connection',
    'timestamp' => time() * 1000
];

$result = $firebase->push('test', $testData);
echo "Write test: " . ($result ? "Success" : "Failed") . "\n";

// Test read
$data = $firebase->get('test');
echo "Read test: " . ($data ? "Success" : "Failed") . "\n";
print_r($data);

// Cleanup
$firebase->delete('test');
?>
```

## Troubleshooting

### Error: "cURL error"
- Ensure cURL is enabled: `php -m | grep curl`
- Check internet connection
- Verify Firebase URL is correct

### Error: "Permission denied"
- Check Firebase database rules
- Verify API key/secret is correct
- Ensure database is in correct mode

### Error: "Database not found"
- Verify database URL in config.php
- Check if Realtime Database is enabled
- Ensure project ID is correct

## Security Best Practices

1. **Never commit** Firebase credentials to version control
2. Use environment variables for sensitive data
3. Implement proper Firebase Security Rules
4. Use Firebase Admin SDK for server-side operations
5. Enable Firebase Authentication for user access
6. Regularly rotate API keys and secrets

## Next Steps

1. Set up Firebase Authentication (optional)
2. Configure Firebase Storage for images
3. Set up Firebase Cloud Messaging for notifications
4. Implement proper security rules
5. Set up monitoring and alerts
