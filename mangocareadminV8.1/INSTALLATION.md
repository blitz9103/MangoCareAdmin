# Installation Guide - MangoCare Admin System

## Quick Start Guide

Follow these steps to set up the MangoCare Admin System on your local machine.

## Prerequisites

- **XAMPP** installed and running (Apache + PHP)
- **Firebase account** (free tier is sufficient)
- **Modern web browser** (Chrome, Firefox, Safari, Edge)
- **Internet connection** (for Firebase and QR code generation)

## Step-by-Step Installation

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP to `C:\xampp\` (default location)
3. Start XAMPP Control Panel
4. Start **Apache** service

### Step 2: Place Project Files

1. Copy all project files to:
   ```
   C:\xampp\htdocs\mangocareadmin\
   ```

2. Your directory structure should look like:
   ```
   C:\xampp\htdocs\mangocareadmin\
   ├── assets/
   │   ├── css/
   │   │   └── style.css
   │   └── js/
   │       └── main.js
   ├── includes/
   │   ├── header.php
   │   └── sidebar.php
   ├── config.php
   ├── index.php
   ├── login.php
   ├── logout.php
   ├── scans.php
   ├── trees.php
   ├── schedules.php
   ├── qr-generator.php
   ├── reports.php
   ├── tree-details.php
   └── README.md
   ```

### Step 3: Set Up Firebase

1. **Create Firebase Project**
   - Go to [Firebase Console](https://console.firebase.google.com/)
   - Click "Add project"
   - Enter project name: `mangocare-admin` (or any name)
   - Follow the setup wizard
   - Disable Google Analytics (optional)

2. **Enable Realtime Database**
   - In Firebase Console, click "Realtime Database"
   - Click "Create Database"
   - Choose location (select closest to your region)
   - Start in **Test mode** (for development)

3. **Get Database URL**
   - In Realtime Database, you'll see the URL at the top
   - It looks like: `https://your-project-id-default-rtdb.firebaseio.com/`
   - Copy this URL

4. **Set Database Rules** (for development)
   - Go to Realtime Database > Rules
   - Set rules to:
     ```json
     {
       "rules": {
         ".read": true,
         ".write": true
       }
     }
     ```
   - Click "Publish"

5. **Get Database Secret** (Optional - for authentication)
   - Go to Project Settings (gear icon)
   - Click "Service Accounts" tab
   - Click "Database secrets"
   - Click "Show" and copy the secret

### Step 4: Configure the System

1. **Open `config.php`** in a text editor

2. **Update Firebase Configuration:**
   ```php
   // Replace with your Firebase Database URL
   define('FIREBASE_DATABASE_URL', 'https://your-project-id-default-rtdb.firebaseio.com/');
   
   // Optional: Add database secret for authentication
   // Leave empty if using public access (development only)
   define('FIREBASE_DATABASE_SECRET', '');
   ```

3. **Change Admin Password** (Important!):
   ```php
   define('ADMIN_USERNAME', 'admin');
   define('ADMIN_PASSWORD', 'your-secure-password'); // Change this!
   ```

### Step 5: Test the Installation

1. **Open your browser** and navigate to:
   ```
   http://localhost/mangocareadmin/test-firebase.php
   ```

2. **Check the test results:**
   - ✅ All tests should pass
   - If there are errors, check the error messages and fix them

3. **If tests pass**, proceed to login:
   ```
   http://localhost/mangocareadmin/login.php
   ```

4. **Login with default credentials:**
   - Username: `admin`
   - Password: `admin123` (or your custom password)

### Step 6: Verify Installation

1. **Login to the system**
2. **Check the dashboard** - it should load (may be empty if no data)
3. **Try creating a QR code:**
   - Go to "QR Code Generator"
   - Enter Tree ID: `TREE-001`
   - Enter Location: `Farm A, Block 1`
   - Click "Generate QR Code"
   - Verify QR code is generated

## Troubleshooting

### Problem: "cURL error" or "Connection failed"

**Solution:**
- Check if cURL is enabled in PHP
- Open `php.ini` (usually in `C:\xampp\php\php.ini`)
- Find `;extension=curl` and remove the semicolon: `extension=curl`
- Restart Apache in XAMPP

### Problem: "Firebase URL not configured"

**Solution:**
- Make sure you've updated `FIREBASE_DATABASE_URL` in `config.php`
- Verify the URL is correct (should end with `.firebaseio.com/`)
- Check for typos

### Problem: "Permission denied" in Firebase

**Solution:**
- Check Firebase Database Rules
- Make sure rules allow read/write:
  ```json
  {
    "rules": {
      ".read": true,
      ".write": true
    }
  }
  ```
- Click "Publish" after updating rules

### Problem: "Session not working"

**Solution:**
- Check if `session_start()` is called
- Verify PHP sessions are enabled
- Check `php.ini` for session settings

### Problem: "Page not found" or 404 errors

**Solution:**
- Verify files are in correct directory: `C:\xampp\htdocs\mangocareadmin\`
- Check Apache is running in XAMPP
- Try accessing: `http://localhost/mangocareadmin/`

### Problem: "QR code not generating"

**Solution:**
- Check internet connection (uses external API)
- Verify Tree ID and Location are filled
- Check browser console for errors

## Verification Checklist

- [ ] XAMPP Apache is running
- [ ] Files are in `C:\xampp\htdocs\mangocareadmin\`
- [ ] Firebase project is created
- [ ] Realtime Database is enabled
- [ ] Database URL is configured in `config.php`
- [ ] Database rules allow read/write
- [ ] `test-firebase.php` shows all tests passed
- [ ] Can login with admin credentials
- [ ] Dashboard loads without errors
- [ ] Can generate QR codes

## Next Steps After Installation

1. **Change default admin password** immediately
2. **Generate QR codes** for your mango trees
3. **Set up spray schedules** for your trees
4. **Configure Android app** to send scan data to Firebase
5. **Test the complete workflow:**
   - Android app scans leaf → Data appears in admin panel
   - Admin creates spray schedule
   - User scans QR code before spraying
   - Spray is recorded in system

## Android App Integration

To connect your Android app:

1. **Use the same Firebase project** for both Android app and web admin
2. **Android app should write scan data** to Firebase path: `scans/`
3. **Data format** should match:
   ```json
   {
     "tree_id": "TREE-001",
     "disease": "Anthracnose",
     "location": "Farm A, Block 1",
     "timestamp": 1234567890000,
     "confidence": 95.5,
     "image_url": "https://..."
   }
   ```

## Security Notes

⚠️ **Important for Production:**

1. **Change default admin password**
2. **Implement Firebase Security Rules** (don't use public read/write)
3. **Use Firebase Admin SDK** instead of REST API
4. **Enable HTTPS** for production
5. **Implement proper authentication**
6. **Regular backups** of Firebase data

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Verify all configuration steps are completed
3. Check PHP error logs in XAMPP
4. Check Firebase Console for database errors
5. Review browser console for JavaScript errors

## System Requirements

- **PHP:** 7.4 or higher
- **Apache:** 2.4 or higher (included in XAMPP)
- **Browser:** Chrome, Firefox, Safari, or Edge (latest versions)
- **Internet:** Required for Firebase and QR code generation

---

**Congratulations!** Your MangoCare Admin System should now be installed and ready to use! 🎉
