# Quick Start Guide - MangoCare Admin System

## 🚀 5-Minute Setup

### 1. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache**

### 2. Configure Firebase
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project
3. Enable **Realtime Database** (Test mode)
4. Copy the database URL

### 3. Update Config
Edit `config.php`:
```php
define('FIREBASE_DATABASE_URL', 'https://your-project-id-default-rtdb.firebaseio.com/');
```

### 4. Test Connection
Open browser: `http://localhost/mangocareadmin/test-firebase.php`

### 5. Login
- URL: `http://localhost/mangocareadmin/login.php`
- Username: `admin`
- Password: `admin123`

## 📋 Default Login

- **Username:** `admin`
- **Password:** `admin123`

⚠️ **Change this immediately after first login!**

## 🎯 Key Features

### Dashboard
- View statistics and charts
- Monitor recent scans
- Quick overview of system status

### Scan History
- Filter by disease, location, or tree ID
- View detailed scan information
- Track disease patterns

### Tree Management
- Register mango trees
- Generate QR codes
- Track spray history per tree

### Spray Schedules
- Create spray schedules
- Assign dates and times
- Monitor completion status

### QR Code Generator
- Generate unique QR codes per tree
- Download and print codes
- Prevent double spraying

## 📱 Android App Integration

Your Android app should write scan data to Firebase:

**Path:** `scans/`

**Format:**
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

## 🔧 Common Issues

### "cURL not enabled"
- Edit `php.ini`
- Remove `;` from `;extension=curl`
- Restart Apache

### "Firebase connection failed"
- Check database URL in `config.php`
- Verify Firebase database rules allow read/write
- Check internet connection

### "Page not found"
- Verify files are in `C:\xampp\htdocs\mangocareadmin\`
- Check Apache is running
- Try: `http://localhost/mangocareadmin/`

## 📚 Full Documentation

- **Installation:** See `INSTALLATION.md`
- **Firebase Setup:** See `FIREBASE_SETUP.md`
- **Full Guide:** See `README.md`

## ✅ Verification Checklist

- [ ] Apache is running
- [ ] Firebase project created
- [ ] Database URL configured
- [ ] Test page shows all green
- [ ] Can login successfully
- [ ] Dashboard loads
- [ ] Can generate QR code

## 🎉 You're Ready!

Start by:
1. Generating QR codes for your trees
2. Creating spray schedules
3. Monitoring scans from Android app

---

**Need Help?** Check the troubleshooting sections in `INSTALLATION.md`
